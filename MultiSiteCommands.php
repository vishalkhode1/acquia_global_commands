<?php

namespace Drush\Commands\acquia_global_commands;

use Acquia\Drupal\RecommendedSettings\Config\ConfigInitializer;
use Acquia\Drupal\RecommendedSettings\Config\SettingsConfig;
use Acquia\Drupal\RecommendedSettings\Settings;
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
   * Execute code before pre-validate site:install.
   *
   * @hook pre-validate site:install
   */
  public function preValidateSiteInstall(CommandData $commandData): void {
    $sitesSubdir = $this->getSitesSubdirFromUri(DRUPAL_ROOT, $commandData->input()->getOption('uri'));
    $commandData->input()->setOption('sites-subdir', $sitesSubdir);

    $options = $commandData->options();
    $dbUrl = $options['db-url'] ?? "";
    $existingConfig = $options['existing-config'] ?? FALSE;

    if ($sitesSubdir != 'default' && !$existingConfig) {

      if(!$dbUrl) {
        $dbSpec = $this->setLocalDbConfig($sitesSubdir, $commandData);
      }

      $Settings = new Settings(DRUPAL_ROOT, $sitesSubdir);
      if (!empty($dbSpec)) {
        $Settings->generate($dbSpec);
      }
      else
      $Settings->generate();
    }
  }

  /**
   * Set local database credentials.
   */
  private function setLocalDbConfig($site_name, $commandData) {
    $configDB = $this->confirm("Would you like to configure the local database credentials?");
    $db = [];

    if ($configDB) {
      $dbName = $db['drupal']['db']['database'] = $this->askDefault("Local database name", $site_name);
      $dbUser = $db['drupal']['db']['username'] = $this->askDefault("Local database user", $site_name);
      $dbPassword = $db['drupal']['db']['password'] = $this->askDefault("Local database password", $site_name);
      $dbHost = $db['drupal']['db']['host'] = $this->askDefault("Local database host", "localhost");
      $dbPort = $db['drupal']['db']['port'] = $this->askDefault("Local database port", "3306");

      $commandData->input()->setOption("db-url", "mysql://$dbUser:$dbPassword@$dbHost:$dbPort/$dbName");
    }
    return $db;
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
