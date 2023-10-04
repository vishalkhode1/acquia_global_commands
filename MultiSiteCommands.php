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

/**
 * A drush command file.
 *
 * @package Drupal\acquia_global_commands\Commands
 */
class MultiSiteCommands extends DrushCommands implements BuilderAwareInterface {

  use LoadAllTasks;

  /**
   * Execute code after site:install command.
   *
   * @param $result
   * @param CommandData $commandData
   *   The command data.
   *
   * @return void
   * @throws \Exception
   * @hook post-command site:install
   *
   */
  public function postSiteInstallCommand($result, CommandData $commandData): void {
    $options = $commandData->options();
    $rootDir = $options['root'] ?? DRUPAL_ROOT;
    $uri = $options['uri'] ?? "";
    $sitePath = $this->getSitePath();
    $siteName = explode('/', $sitePath)[1];

    $new_site_dir = $rootDir . '/' . $sitePath;
//    if (file_exists($new_site_dir)) {
//      throw new \Exception("Cannot generate new multisite, $new_site_dir already exists!");
//    }

    if ($siteName !='default') {
      $this->say("This will generate a new site in the docroot/$sitePath directory.");
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
  protected function createNewSiteDir(string $default_site_dir, string $new_site_dir) {
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
  protected function createNewSiteConfigDir(string $site_name, string $rootDir) {
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
   * @return array|bool|float|int|string|\UnitEnum|null
   * @throws \Exception
   */
  protected function getSitePath(): \UnitEnum|float|array|bool|int|string|null {
    Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
    return \Drupal::getContainer()->getParameter('site.path');
  }
}
