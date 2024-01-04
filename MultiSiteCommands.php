<?php

namespace Drush\Commands\acquia_global_commands;

use Acquia\Drupal\RecommendedSettings\Settings;
use Acquia\Drupal\RecommendedSettings\Exceptions\SettingsException;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Robo\Contract\BuilderAwareInterface;
use Robo\LoadAllTasks;
use Symfony\Component\Filesystem\Path;

/**
 * A drush command file.
 *
 * @package Drupal\AcquiaGlobalCommands\Commands
 */
class MultiSiteCommands extends DrushCommands implements BuilderAwareInterface {

  use LoadAllTasks;

  /**
   * Execute code before pre-validate site:install.
   *
   * @hook pre-validate site:install
   */
   public function preValidateSiteInstall(CommandData $commandData): void {
     $uri = $commandData->input()->getOption('uri') ?? 'default';
     $sitesSubdir = $this->getSitesSubdirFromUri(DRUPAL_ROOT, $uri);
     $commandData->input()->setOption('sites-subdir', $sitesSubdir);
     $options = $commandData->options();
     if (!is_dir($sitesSubdir) || (is_dir($sitesSubdir) && !$database['default'])) {
       if ($sitesSubdir != 'default' && !$options['existing-config']) {
         $dbSpec = !($options['db-url']) ? $this->setLocalDbConfig($sitesSubdir, $commandData) : [];
         $settings = new Settings(DRUPAL_ROOT, $sitesSubdir);
         try {
           $settings->generate($dbSpec);
         } catch (SettingsException $e) {
           $this->io()->warning($e->getMessage());
         }
       }
     }
   }

  /**
   * Set local database credentials.
   *
   * @return array
   *   Returns databse information.
   */
  private function setLocalDbConfig($site_name, $commandData): array {
    $configDB = $this->io()->confirm(dt("Would you like to configure the local database credentials?"));
    $db = [];

    if ($configDB) {
      $dbName = $db['drupal']['db']['database'] = $this->io()->ask("Local database name", $site_name);
      $dbUser = $db['drupal']['db']['username'] = $this->io()->ask("Local database user", 'drupal');
      $dbPassword = $db['drupal']['db']['password'] = $this->io()->ask("Local database password", 'drupal');
      $dbHost = $db['drupal']['db']['host'] = $this->io()->ask("Local database host", "localhost");
      $dbPort = $db['drupal']['db']['port'] = $this->io()->ask("Local database port", "3306");

      $commandData->input()->setOption("db-url", "mysql://$dbUser:$dbPassword@$dbHost:$dbPort/$dbName");
    }

    return $db;
  }

  /**
   * Determine an appropriate site subdir name to use for the provided uri.
   *
   * This code copied from SiteInstallCommands.php file.
   *
   * @return array|false|mixed|string|string[]
   *   Returns the site uri.
   */
  private function getSitesSubdirFromUri($root, $uri): mixed {
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
