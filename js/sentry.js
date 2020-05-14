/**
 * @file
 * Configures @sentry/browser with the Sentry DSN and extra options.
 */

(function (drupalSettings, Sentry) {

  'use strict';

  // Additional Sentry configuration can be applied by modifying
  // drupalSettings.sentry_io.options in custom PHP or JavaScript. Use the latter
  // for Sentry callback functions; library weight can be used to ensure your
  // custom settings are added before this file executes.
  Sentry.init(drupalSettings.sentry_io.options);

  Sentry.setUser({'id': drupalSettings.user.uid});

})(window.drupalSettings, window.Sentry);
