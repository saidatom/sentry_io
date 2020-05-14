<?php

namespace Drupal\sentry_io\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Sentry\ClientBuilder;
use Sentry\ErrorHandler;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\RequestStack;
use function Sentry\captureLastError as captureLastErrorAlias;
use function Sentry\configureScope as configureScopeAlias;

/**
 * Logs events to Sentry.
 */
class Sentry implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * Sentry client.
   *
   * @var \ClientBuilder|null
   */
  public $client;

  /**
   * Set DSN Sentry.
   *
   * @var string|null
   */
  public $dsn;

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
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Constructs a new SentryService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
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
    $this->loglevels = array_filter($this->config->get('log_levels'));
    $this->dsn = empty($_SERVER['SENTRY_DSN']) ? $this->config->get('client_key') : $_SERVER['SENTRY_DSN'];
    $this->severity = NULL;
    $this->setClient();
    if (!$this->client) {
      // Sad sentry.
      return;
    }
    $this->catchSentry();
    if ($this->client && $this->config->get('fatal_error_handler')) {
      //ErrorHandler::registerOnceErrorHandler();
    }
  }

  /**
   * Logs with an arbitrary level.
   *
   * @param mixed $level
   *   Error level.
   * @param string $message
   *   Error message.
   * @param array $context
   *   Error object.
   */
  public function log($level, $message, array $context = []) {
    if (!$this->client && !array_key_exists($level, $this->loglevels)) {
      // Sad sentry.
      return;
    }
    $this->levels($level);
    configureScopeAlias(function (Scope $scope): void {
      $scope->setLevel($this->severity);
      $scope->setUser([
        'email' => $this->currentUser ? $this->currentUser->getEmail() : '',
        'id' => $this->currentUser ? $this->currentUser->id() : 0,
        'ip_address' => $this->requestStack && ($request = $this->requestStack->getCurrentRequest()) ? $request->getClientIp() : '',
      ]);
      $scope->setTag('page_locale', $this->languageManager->getCurrentLanguage()->getId());
    });
    captureLastErrorAlias();
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
    Hub::getCurrent()->bindClient($this->client->getClient());
  }

}
