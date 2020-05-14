<?php

namespace Drupal\sentry_io\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;

/**
 * Implements a Sentry Config form.
 */
class SentryConfigForm {

  /**
   * Builds Sentry config form.
   */
  public static function buildForm(array &$form) {
    $config = \Drupal::config('sentry_io.settings');
    $form['sentry_io'] = [
      '#type'           => 'details',
      '#title'          => t('Sentry'),
      '#tree'           => TRUE,
      '#open'           => TRUE,
    ];
    $form['sentry_io']['client_key'] = [
      '#type'           => 'textfield',
      '#title'          => t('Sentry DSN'),
      '#default_value'  => $config->get('client_key'),
      '#description'    => t('Sentry client key for current site. This setting can be overridden with the SENTRY_DSN environment variable.'),
    ];
    $form['sentry_io']['environment'] = [
      '#type'           => 'textfield',
      '#title'          => t('Environment'),
      '#default_value'  => $config->get('environment'),
      '#description'    => t('The environment in which this site is running (leave blank to use kernel.environment parameter). This setting can be overridden with the SENTRY_ENVIRONMENT or ENVIRONMENT environment variable.'),
    ];
    $form['sentry_io']['release'] = [
      '#type'           => 'textfield',
      '#title'          => t('Release'),
      '#default_value'  => $config->get('release'),
      '#description'    => t('The release this site is running (could be a version or commit hash). This setting can be overridden with the SENTRY_RELEASE environment variable.'),
    ];
    $form['sentry_io']['js'] = [
      '#type'           => 'details',
      '#title'          => t('JavaScript'),
      '#open'           => TRUE,
    ];
    $form['sentry_io']['js']['javascript_error_handler'] = [
      '#type'           => 'checkbox',
      '#title'          => t('Enable JavaScript error handler'),
      '#description'    => t('Check to capture JavaScript errors (if user has the <a target="_blank" href=":url">send JavaScript errors to Sentry</a> permission).', [
        ':url' => Url::fromRoute('user.admin_permissions', [], ['fragment' => 'module-sentry_io'])->toString(),
      ]),
      '#default_value'  => $config->get('javascript_error_handler'),
    ];
    $form['sentry_io']['php'] = [
      '#type'           => 'details',
      '#title'          => t('PHP'),
      '#open'           => TRUE,
    ];
    // "0" is not a valid checkbox option.
    $log_levels = [];
    foreach (RfcLogLevel::getLevels() as $key => $value) {
      $log_levels[$key + 1] = $value;
    }
    $form['sentry_io']['php']['log_levels'] = [
      '#type'           => 'checkboxes',
      '#title'          => t('Log levels'),
      '#default_value'  => $config->get('log_levels'),
      '#description'    => t('Check the log levels that should be captured by Sentry.'),
      '#options'        => $log_levels,
    ];
    $form['sentry_io']['php']['fatal_error_handler'] = [
      '#type'           => 'checkbox',
      '#title'          => t('Enable fatal error handler'),
      '#description'    => t('Check to capture fatal PHP errors.'),
      '#default_value'  => $config->get('fatal_error_handler'),
    ];
    $form['#submit'][] = 'Drupal\sentry_io\Form\SentryConfigForm::submitForm';
  }

  /**
   * Submits Sentry config form.
   */
  public static function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('sentry_io.settings')
      ->set('client_key',
        $form_state->getValue(['sentry_io', 'client_key']))
      ->set('environment',
        $form_state->getValue(['sentry_io', 'environment']))
      ->set('release',
        $form_state->getValue(['sentry_io', 'release']))
      ->set('fatal_error_handler',
        $form_state->getValue(['sentry_io', 'php', 'fatal_error_handler']))
      ->set('log_levels',
        $form_state->getValue(['sentry_io', 'php', 'log_levels']))
      ->set('javascript_error_handler',
        $form_state->getValue(['sentry_io', 'js', 'javascript_error_handler']))
      ->save();
  }

}
