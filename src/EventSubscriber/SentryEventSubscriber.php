<?php

namespace Drupal\sentry_io\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sentry_io\Service\SentryService;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class SentryEventSubscriber.
 */
class SentryEventSubscriber implements EventSubscriberInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * Sentry service.
   *
   * @var \Drupal\sentry_io\Service\SentryService
   */
  protected $sentry;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new Sentry RequestSubscriber.
   *
   * @param \Drupal\sentry_io\Service\SentryService $sentry
   *   The configuration factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    SentryService $sentry,
    ConfigFactoryInterface $config_factory
  ) {
    $this->sentry = $sentry;
    $this->configFactory = $config_factory;
  }

  /**
   * Log all exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event) {
    $this->sentry->log($event);
  }

  /**
   * Initializes Sentry logger if fatal error logging is enabled.
   */
  public function onRequest() {
    if ($this->configFactory->get('sentry_io.settings')->get('fatal_error_handler') && $this->container) {
      $this->container->get('sentry_io.error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException', 50];
    $events[KernelEvents::REQUEST][] = ['onRequest', 222];

    return $events;
  }

}
