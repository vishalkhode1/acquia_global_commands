<?php

namespace Drush\Commands\acquia_global_commands;

use Acquia\Drupal\RecommendedSettings\Exceptions\SettingsException;
use Acquia\Drupal\RecommendedSettings\Helpers\EnvironmentDetector;
use Acquia\Drupal\RecommendedSettings\Settings;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Database;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface as DrushContainer;
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
   * Construct an object of Multisite commands.
   */
  public function __construct(private readonly BootstrapManager $bootstrapManager) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  public static function createEarly(DrushContainer $drush_container): self {
    return new static(
      $drush_container->get('bootstrap.manager')
    );
  }

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
    $this->bootstrapManager->setUri('http://' . $sitesSubdir);

    // Try to get any already configured database information.
    $this->bootstrapManager->bootstrapMax(DrupalBootLevels::CONFIGURATION, $commandData->annotationData());

    // By default, bootstrap manager boot site from default/setting.php
    // hence remove the database connection if site is other than default.
    if (($sitesSubdir && "sites/$sitesSubdir" !== $this->bootstrapManager->bootstrap()->confpath())) {
      Database::removeConnection('default');
      $dbSpec = !($options['db-url']) ? $this->setLocalDbConfig($sitesSubdir, $commandData) : [];
      $settings = new Settings(DRUPAL_ROOT, $sitesSubdir);
      try {
        $settings->generate($dbSpec);
      }
      catch (SettingsException $e) {
        $this->io()->warning($e->getMessage());
      }
    }
  }

  /**
   * Get local database specs.
   *
   * @param string $site_name
   *   The site name.
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data object.
   *
   * @return array
   *   The database specs.
   * @throws \ReflectionException
   */
  private function setLocalDbConfig(string $site_name, CommandData $commandData): array {

    // Initialise with default db specs.
    $dbSpec['drupal']['db'] = [
      'database' => 'drupal',
      'username' => 'drupal',
      'password' => 'drupal',
      'host' => 'localhost',
      'port' => '3306',
    ];

    $configDB = NULL;
    if (EnvironmentDetector::isLocalEnv()) {
      $configDB = $this->io()->confirm(dt("Would you like to configure the local database credentials?"));
    }

    if ($configDB) {
      $dbSpec['drupal']['db']['database'] = $this->io()->ask("Local database name", $site_name);
      $dbSpec['drupal']['db']['username'] = $this->io()->ask("Local database user", 'drupal');
      $dbSpec['drupal']['db']['password'] = $this->io()->ask("Local database password", 'drupal');
      $dbSpec['drupal']['db']['host'] = $this->io()->ask("Local database host", "localhost");
      $dbSpec['drupal']['db']['port'] = $this->io()->ask("Local database port", "3306");
    }
    $dbString = "mysql://" . $dbSpec['drupal']['db']['username'] . ":" .
      $dbSpec['drupal']['db']['password'] . '@' .
      $dbSpec['drupal']['db']['host'] . ':' .
      $dbSpec['drupal']['db']['port'] . '/' .
      $dbSpec['drupal']['db']['database'];
    $commandData->input()->setOption("db-url", $dbString);

    return $dbSpec;
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
