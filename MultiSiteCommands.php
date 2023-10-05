<?php

namespace Drush\Commands\acquia_global_commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Site\Settings;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\LoadAllTasks;
use Symfony\Component\Filesystem\Path;

/**
 * A drush command file.
 *
 * @package Drupal\acquia_global_commands\Commands
 */
class MultiSiteCommands extends DrushCommands implements BuilderAwareInterface {

  use LoadAllTasks;

  /**
   * Execute code before pre-validate site:install.
   *
   * @hook pre-validate site:install
   */
  public function validateSiteInstall(CommandData $commandData): void {
    $siteName = $this->getSitesSubdirFromUri(DRUPAL_ROOT, $commandData->input()->getOption('uri'));
    $this->say("This will generate a new site in the docroot/$siteName directory.");
    $commandData->input()->setOption('sites-subdir', $siteName);
  }

  /**
   * Execute code after site:install command.
   *
   * @hook post-command site:install
   * @param $result
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The drush command data.
   *
   * @throws \Exception
   */
  public function postSiteInstallCommand($result, CommandData $commandData): void {
    $options = $commandData->options();
    $rootDir = $options['root'] ?? DRUPAL_ROOT;
    $sitePath = $this->getSitePath();
    $siteName = explode('/', $sitePath)[1];

    $new_site_dir = $rootDir . '/' . $sitePath;

    if ($siteName != 'default') {
      $default_site_dir = $rootDir . '/sites/default';
      $this->createNewSiteDir($default_site_dir, $new_site_dir);
      $this->createNewSiteConfigDir($siteName, $rootDir);
    }

  }

  /**
   * Create new site dir.
   *
   * @param string $default_site_dir
   *   Default site dir.
   * @param string $new_site_dir
   *   New site dir.
   *
   * @throws \Exception
   */
  protected function createNewSiteDir(string $default_site_dir, string $new_site_dir): void {
    $result = $this->taskCopyDir([
      $default_site_dir => $new_site_dir,
    ])
      ->exclude(['local.settings.php', 'settings.php', 'files'])
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to create $new_site_dir.");
    }
  }

  /**
   * Create config directory for given multisite.
   *
   * @param string $site_name
   *   The site machine name.
   * @param string $rootDir
   *   The document root path.
   *
   * @throws \Exception
   */
  protected function createNewSiteConfigDir(string $site_name, string $rootDir): void {
    $config_dir = $rootDir . '/' . Settings::get('config_sync_directory', 'config') . '/' . $site_name;
    $result = $this->taskFilesystemStack()
      ->mkdir($config_dir)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to create $config_dir.");
    }
  }

  /**
   * Get site path string.
   *
   * @throws \Exception
   */
  protected function getSitePath(): \UnitEnum|float|array|bool|int|string|null {
    Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
    return \Drupal::getContainer()->getParameter('site.path');
  }

  /**
   * Determine an appropriate site subdir name to use for the
   * provided uri.
   *
   * This code copied from SiteInstallCommands.php file.
   */
  protected function getSitesSubdirFromUri($root, $uri) {
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
    // Find the dir from sites.php file
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
    return false;
  }

}
