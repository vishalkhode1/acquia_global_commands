<?php

namespace Acquia\GlobalCommands\Utility;

/**
 * Acquia telemetery class to capture site install time.
 */
class AcquiaTelemetry {

  /**
   * Singleton instance.
   *
   * @var AcquiaTelemetry
   */
  private static $instance;

  /**
   * An array of telemetry data.
   *
   * @var array
   */
  private array $telemetryData = [];

  private function __construct() {
  // Private constructor to prevent direct instantiation
  }

  /**
   * Singleton method for this acquia telemetry class.
   *
   * @return AcquiaTelemetry
   */
  public static function getInstance(): AcquiaTelemetry {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Sets the time of installing the site.
   *
   * @param string $key
   *   Key to store the install time.
   * @param string $time
   *   Install time value.
   *
   * @return void
   */
  public function setTime(string $key, string $time): void {
    // Store the DateTime object in the telemetry data
    $this->telemetryData[$key] = $time;
  }

  /**
   * Gets the time of installing the site.
   *
   * @param string $key
   *   Key to store the install time.
   *
   * @return string|null
   *   Install time of site.
   */
  public function getTime(string $key): ?string {
    return $this->telemetryData[$key];
  }

  /**
   * Prepare time duration in seconds.
   *
   * @param string $start
   *   Start time.
   * @param string $end
   *   End time.
   *
   * @return string|null
   *   Time format in seconds.
   */
  public function getTimeDifferenceInSeconds(string $start, string $end): ?string {
    // Calculate the time difference in seconds
    $timeDifference = $this->telemetryData[$end] - $this->telemetryData[$start];

    return number_format($timeDifference, 2);
  }

}
