<?php

namespace Acquia\GlobalCommands\Utility;

class AcquiaTelemetry {

  private static $instance;

  private $telemetryData = [];

  private function __construct() {
  // Private constructor to prevent direct instantiation
  }

  public static function getInstance(): AcquiaTelemetry {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function setTime(string $key, string $time): void {
    // Store the DateTime object in the telemetry data
    $this->telemetryData[$key] = $time;
  }

  public function getTime(string $key): ?string {
    return $this->telemetryData[$key];
  }

  public function getTimeDifferenceInSeconds(string $key1, string $key2): ?string {
    // Get DateTime objects for the specified keys
    $dateTime1 = $this->telemetryData[$key1];
    $dateTime2 = $this->telemetryData[$key2];

    // Calculate the time difference in seconds
    $timeDifference = $dateTime2 - $dateTime1;

    return number_format($timeDifference, 2);;
  }

}
