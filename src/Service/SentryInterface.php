<?php

namespace Drupal\sentry_io\Service;

/**
 * Interface Sentry.
 */
interface SentryInterface {

  /**
   * Logs with an arbitrary level.
   *
   * @param object $event
   *   Catch error.
   */
  public function log($event);

  /**
   * Return Severity level.
   *
   * @param int $level
   *   Error level.
   */
  public function levels($level);

  /**
   * Return Sentry client.
   */
  public function setClient();

  /**
   * Set Sentry client.
   */
  public function catchSentry();

}
