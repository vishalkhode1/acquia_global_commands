<?php

namespace Drush\Commands\acquia_global_commands;

use Acquia\Drupal\RecommendedSettings\Config\ConfigInitializer;
use Acquia\Drupal\RecommendedSettings\Config\SettingsConfig;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Sql\SqlBase;
use Robo\Contract\BuilderAwareInterface;
use Robo\LoadAllTasks;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * A drush command file.
 *
 * @package Drupal\acquia_global_commands\Commands
 */
class MultiSiteCommands extends DrushCommands implements BuilderAwareInterface {

  use LoadAllTasks;

  /**
   * Settings warning.
   *
   * @var string
   * Warning text added to the end of settings.php to point people
   * to the Acquia Drupal Recommended Settings
   * docs on how to include settings.
   *
   * @todo use these variable from DRS's Settings.php
   */
  private string $settingsWarning = <<<WARNING
/**
 * IMPORTANT.
 *
 * Do not include additional settings here. Instead, add them to settings
 * included by `acquia-recommended.settings.php`. See Acquia's documentation for more detail.
 *
 * @link https://docs.acquia.com/
 */
WARNING;

  /**
   * Execute code before pre-validate site:install.
   *
   * @hook pre-validate site:install
   */
  public function preValidateSiteInstall(CommandData $commandData): void {
    $sitesSubdir = $this->getSitesSubdirFromUri(DRUPAL_ROOT, $commandData->input()->getOption('uri'));
    $commandData->input()->setOption('sites-subdir', $sitesSubdir);
  }

  /**
   * Execute code before pre-validate site:install.
   *
   * @hook validate site:install
   *
   * @throws \Exception
   */
  public function validate(CommandData $commandData): void {
    $sitesSubDir = $commandData->input()->getOption('sites-subdir');
    $this->createSettingFiles($commandData, $sitesSubDir);
    // @todo currently site:install command adds db credentials
    // in settings.php instead of local.settings.php
    // we have to figure out that issue.
  }

  /**
   * Create settings related files.
   *
   * Before pre command hook of site:install.
   *
   * @throws \Exception
   */
  protected function createSettingFiles(CommandData $commandData, string $sitesSubDir): void {
    $sitesSubDir = Path::join('sites', $sitesSubDir);
    $settingsFile = Path::join($sitesSubDir, 'settings.php');
    $localSettingsFile = Path::join($sitesSubDir . '/settings', 'local.settings.php');
    $localSettingsPath = $sitesSubDir . '/settings';
    $fileSystem = new Filesystem();

    if (!drush_file_not_empty($settingsFile)) {
      // Create site sub directory.
      if (!file_exists($sitesSubDir)) {
        $fileSystem->mkdir($sitesSubDir);
      }
      // Create settings subdirectory.
      if (!file_exists($localSettingsPath)) {
        $this->say("This will generate a new site in the docroot/$sitesSubDir directory.");
        $fileSystem->mkdir($localSettingsPath);
      }
      // Create local.settings.php out of default.local.settings.php.
      if (!drush_op('copy', 'sites/default/settings/default.local.settings.php', $localSettingsFile)) {
        throw new \Exception(dt('Failed to copy sites/default/settings/default.local.settings.php to @settingsFile', ['@settingsFile' => $localSettingsFile]));
      }
      // Create settings.php out of default.settings.php.
      if (!drush_op('copy', 'sites/default/default.settings.php', $settingsFile)) {
        throw new \Exception(dt('Failed to copy sites/default/default.settings.php to @settingsfile', ['@settingsfile' => $settingsFile]));
      }
      $this->updateDbSpec($commandData, $sitesSubDir, $localSettingsFile);
      // @todo use these function from DRS's Settings.php
      $this->appendIfMatchesCollect($settingsFile, '#vendor/acquia/drupal-recommended-settings/settings/acquia-recommended.settings.php#', 'require DRUPAL_ROOT . "/../vendor/acquia/drupal-recommended-settings/settings/acquia-recommended.settings.php";' . "\n");
      $this->appendIfMatchesCollect($settingsFile, '#Do not include additional settings here#', $this->settingsWarning . "\n");
    }
  }

  /**
   * Update database specification.
   *
   * @throws \Exception
   */
  protected function updateDbSpec(CommandData $commandData, string $sitesSubDir, string $localSettingsFile): void {
    $root = $commandData->input()->getOption('root');
    $configInitializer = new ConfigInitializer($root, $root, '');
    $configInitializer->setSite($sitesSubDir);
    if ($sql = SqlBase::create($commandData->input()->getOptions())) {
      $db_spec = $sql->getDbSpec();
      // @todo create addDbConfig function in DRS's ConfigInitializer.php
      $config = $configInitializer->addDbConfig([
        'drupal' => [
          'db' => [
            'database' => $db_spec['database'],
            'username' => $db_spec['username'],
            'password' => $db_spec['password'],
            'host' => $db_spec['host'],
            'port' => $db_spec['port'],
          ],
        ],
      ]);
      $settings = new SettingsConfig($config->export());
      $settings->expandFileProperties($localSettingsFile);
    }
  }

  /**
   * Append the string to file, if matches.
   *
   * @param string $file
   *   The path to file.
   * @param string $pattern
   *   The regex patten.
   * @param string $text
   *   Text to append.
   * @param bool $shouldMatch
   *   Decides when to append if match found.
   */
  protected function appendIfMatchesCollect(string $file, string $pattern, string $text, bool $shouldMatch = FALSE): void {
    // @todo use these function from DRS's Settings.php
    $contents = file_get_contents($file);
    if (preg_match($pattern, $contents) == $shouldMatch) {
      $contents .= $text;
    }
    (new Filesystem())->dumpFile($file, $contents);
  }

  /**
   * Determine an appropriate site subdir name to use for the
   * provided uri.
   *
   * This code copied from SiteInstallCommands.php file.
   *
   * @return array|false|mixed|string|string[]
   */
  private function getSitesSubdirFromUri($root, $uri) {
    $dir = strtolower($uri);
    // Always accept simple uris (e.g. 'dev', 'stage', etc.)
    if (preg_match('#^[a-z0-9_-]*$#', $dir)) {
      return $dir;
    }
    // Strip off the protocol from the provided uri -- however,
    // now we will require that the sites subdir already exist.
    $dir = preg_replace('#[^/]*/*#', '', $dir);
    if ($dir && file_exists(Path::join($root, $dir))) {
      return $dir;
    }
    // Find the dir from sites.php file.
    $sites_file = $root . '/sites/sites.php';
    if (file_exists($sites_file)) {
      $sites = [];
      include $sites_file;
      if (!empty($sites) && array_key_exists($uri, $sites)) {
        return $sites[$uri];
      }
    }
    // Fall back to default directory if it exists.
    if (file_exists(Path::join($root, 'sites', 'default'))) {
      return 'default';
    }
    return FALSE;
  }

}
