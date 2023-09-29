<?php

namespace Drush\Commands\acquia_global_commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\LoadAllTasks;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * A drush command file.
 *
 * @package Drupal\acquia_global_commands\Commands
 */
class MultiSiteCommands extends DrushCommands implements BuilderAwareInterface {

  use LoadAllTasks;

  /**
   * Execute code before site:install command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The information about the current request.
   *
   * @hook pre-command site:install
   * @throws \Exception
   */
  public function preSiteInstallCommand(CommandData $commandData): void {
    $options = $commandData->options();
    $uri = $options['uri'] ?? "";
    $dbUrl = $options['db-url'] ?? "";
    $existingConfig = $options['existing-config'] ?? FALSE;

    if ($uri) {
      $site_name = $this->getNewSiteName($uri);
      $new_site_dir = $this->getConfigValue('docroot') . '/sites/' . $site_name;

      if (file_exists($new_site_dir)) {
        throw new \Exception("Cannot generate new multisite, $new_site_dir already exists!");
      }

      $this->say("This will generate a new site in the docroot/sites/$site_name directory.");
      $default_site_dir = $this->getConfigValue('docroot') . '/sites/default';
      $this->createNewSiteDir($default_site_dir, $new_site_dir);
      $this->createNewSiteConfigDir($site_name);
    }

    if ($uri && !$dbUrl && !$existingConfig) {
      $question = new ConfirmationQuestion("Would you like to configure the local database credentials?", TRUE);
      $answer = $this->io()->askQuestion($question);
      if ($answer) {
        $question = new Question("Local database name", $uri);
        $dbName = $this->io()->askQuestion($question);

        $question = new Question("Local database user", $uri);
        $dbUser = $this->io()->askQuestion($question);

        $question = new Question("Local database password", $uri);
        $dbPassword = $this->io()->askQuestion($question);

        $question = new Question("Local database host", "localhost");
        $dbHost = $this->io()->askQuestion($question);

        $question = new Question("Local database port", "3306");
        // @todo add validation for port number.
        $dbPort = $this->io()->askQuestion($question);
        // @todo generate settings.php code.
        $commandData->input()->setOption("db-url", "mysql://$dbUser:$dbPassword@$dbHost:$dbPort/$dbName");
      }
    }
  }
  /**
   * Get new site name.
   *
   * @param string $uri
   *   Options.
   *
   * @return string
   *   Site name.
   */
  private function getNewSiteName(string $uri) {
    $site_name = parse_url($uri);
    if (!empty($site_name)){
      if (isset($site_name['scheme'])) {
        $site_name = $site_name['host'];
      }
      else {
        $site_name = $site_name['path'];
      }
    }
    // @todo get site name from $sites value.
    return $site_name;
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
      ->exclude(['local.settings.php', 'files'])
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to create $new_site_dir.");
    }
  }

  /**
   * Create new config dir.
   *
   * @param string $site_name
   *   Site name.
   * @throws \Exception
   */
  protected function createNewSiteConfigDir(string $site_name) {
    $config_dir = $this->getConfigValue('docroot') . '/' . $this->getConfigValue('cm.core.path') . '/' . $site_name;
    $result = $this->taskFilesystemStack()
      ->mkdir($config_dir)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to create $config_dir.");
    }
  }
}
