<?php

namespace Drupal\sentry_io\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Utility\Error;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\RequestStack;
use function Sentry\captureException as captureExceptionAlias;
use function Sentry\captureLastError as captureLastErrorAlias;
use function Sentry\configureScope as configureScopeAlias;

/**
 * Class SentryService.
 */
class SentryService implements SentryInterface {

  /**
   * Sentry client.
   *
   * @var \ClientBuilder|null
   */
  public $client;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\Language\LanguageManagerInterface definition.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Get Severity error.
   *
   * @var \Sentry\Severity|null
   */
  private $severity;

  /**
   * Get selected levels.
   *
   * @var array|mixed|null
   */
  private $loglevels;

  /**
   * Set DSN Sentry.
   *
   * @var string|null
   */
  public $dsn;

  /**
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Constructs a new SentryService object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    LanguageManagerInterface $language_manager,
    RequestStack $request_stack
  ) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->requestStack = $request_stack;
    $this->config = $this->configFactory->get('sentry_io.settings');
    $this->loglevels = $this->config->get('log_levels') ? array_filter($this->config->get('log_levels')) : '';
    $this->dsn = empty($_SERVER['SENTRY_DSN']) ? $this->config->get('client_key') : $_SERVER['SENTRY_DSN'];
    $this->severity = NULL;
    $this->setClient();
    if (!$this->client) {
      return;
    }
    $this->catchSentry();
    if ($this->client && $this->config->get('fatal_error_handler')) {
      captureLastErrorAlias();
    }
  }

  /**
   * Logs with an arbitrary level.
   *
   * @param object $event
   *   Catch error.
   */
  public function log($event) {
    $exception = $event->getException();
    $error = Error::decodeException($exception);
    if (!$this->client && !array_key_exists($error['severity_level'], $this->loglevels)) {
      return;
    }
    $this->levels($error['severity_level']);
    configureScopeAlias(function (Scope $scope): void {
      $scope->setLevel($this->severity);
      $scope->setUser([
        'email' => $this->currentUser ? $this->currentUser->getEmail() : '',
        'id' => $this->currentUser ? $this->currentUser->id() : 0,
        'ip_address' => $this->requestStack && ($request = $this->requestStack->getCurrentRequest()) ? $request->getClientIp() : '',
      ], TRUE);
      $scope->setTag('page_locale', $this->languageManager->getCurrentLanguage()->getId());
    });
    captureExceptionAlias($exception);
  }

  /**
   * Return Severity level.
   *
   * @param int $level
   *   Error level.
   */
  public function levels($level) {
    switch ($level) {
      case RfcLogLevel::EMERGENCY:
      case RfcLogLevel::ALERT:
      case RfcLogLevel::CRITICAL:
        $this->severity = Severity::fatal();
        break;

      case RfcLogLevel::ERROR:
        $this->severity = Severity::error();
        break;

      case RfcLogLevel::WARNING:
        $this->severity = Severity::warning();
        break;

      case RfcLogLevel::NOTICE:
      case RfcLogLevel::INFO:
        $this->severity = Severity::info();
        break;

      case RfcLogLevel::DEBUG:
        $this->severity = Severity::debug();
        break;
    }
  }

  /**
   * Return Sentry client.
   */
  public function setClient() {
    $options = [
      'dsn' => $this->dsn,
      'environment' => empty($_SERVER['SENTRY_ENVIRONMENT']) ? $this->config->get('environment') : $_SERVER['SENTRY_ENVIRONMENT'],
    ];

    if (!empty($_SERVER['SENTRY_RELEASE'])) {
      $options['release'] = $_SERVER['SENTRY_RELEASE'];
    }
    elseif (!empty($this->config->get('release'))) {
      $options['release'] = $this->config->get('release');
    }
    try {
      $this->client = ClientBuilder::create($options);
    }
    catch (InvalidArgumentException $e) {
      // Sentry is incorrectly configured.
      return;
    }
  }

  /**
   * Set Sentry client.
   */
  public function catchSentry() {
    SentrySdk::getCurrentHub()->bindClient($this->client->getClient());
  }

}
