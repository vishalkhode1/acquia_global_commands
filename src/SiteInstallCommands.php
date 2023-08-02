<?php

namespace Drush\Commands\acquia_global_commands;

use Acquia\GlobalCommands\Utility\AcquiaTelemetry;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\acquia_global_commands\Commands
 */
class SiteInstallCommands extends DrushCommands {

  /**
   * Execute code before site:install command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The information about the current request.
   *
   * @hook pre-command site-install
   */
  public function preSiteInstallCommand(CommandData $commandData): void {
    $telemetry = AcquiaTelemetry::getInstance();
    $telemetry->setTime("start_time", microtime(true));
  }

  /**
   * Execute code before site:install command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The information about the current request.
   *
   * @hook post-command site-install
   */
  public function postSiteInstallCommand($result, CommandData $commandData): void {
    $telemetry = AcquiaTelemetry::getInstance();
    $telemetry->setTime("end_time", microtime(true));
    $installTime = $telemetry->getTimeDifferenceInSeconds("start_time", "end_time");
    \Drupal::state()->set('acquia_cms.site_install_time', $installTime);
    $this->io()->writeln(" <fg=white;bg=green>[success]</> Site installation time: <fg=green>" . $installTime . "</> s.");
  }

}
