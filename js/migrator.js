var MeprMigrator = (function ($) {
  var migrator,
      migration,
      working = false;

  migrator = {
    /**
     * Start the migrator.
     *
     * @param {jQuery}   $button      The button that triggered the migrator to start
     * @param {object}   data         The data to pass to the migrator
     * @param {function} [onSuccess]  A callback function to call when the migrator finishes successfully
     * @param {function} [onError]    A callback function to call when the migrator finishes unsuccessfully
     */
    start: function ($button, data, onSuccess, onError) {
      if(working) {
        return;
      }

      working = true;

      migration = {
        $button: $button,
        $migrator: $button.closest('.mepr-migrator'),
        onSuccess: onSuccess,
        onError: onError
      };

      migration.$button.data('html', migration.$button.html())
                       .width(migration.$button.width())
                       .html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      migration.$migrator.find('.notice').remove();

      $(window).on('beforeunload.mepr-migrator', function () {
        return MeprMigratorL10n.leave_are_you_sure;
      });

      migrator.migrate(data);
    },

    /**
     * Sends a migration request.
     *
     * This function will continue to call itself with the response data from the server until all steps are complete.
     *
     * @param {object} data The data to pass to the migrator
     */
    migrate: function (data) {
      $.ajax({
        method: 'POST',
        url: MeprMigratorL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_migrator_migrate',
          _ajax_nonce: MeprMigratorL10n.migrate_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if (response && typeof response.success === 'boolean') {
          if (response.success) {
            if (response.data.logs) {
              $.each(response.data.logs, function (i, log) {
                migration.$migrator.append(
                  $('<div class="notice notice-error">').append(
                    $('<p>').html(log)
                  )
                );
              });
            }

            if (response.data.status === 'complete') {
              migration.$migrator.append(
                $('<div class="notice notice-success">').append(
                  $('<p>').html(MeprMigratorL10n.migration_complete)
                )
              );

              migrator.finish(true);
            } else {
              migrator.migrate(response.data);
            }
          } else {
            migrator.error(response.data);
          }
        } else {
          migrator.error('Request failed');
        }
      })
      .fail(function () {
        migrator.error('Request failed');
      });
    },

    /**
     * Handle a migrator error.
     *
     * @param {string} message
     */
    error: function (message) {
      migration.$migrator.append(
        $('<div class="notice notice-error">').append(
          $('<p>').html(message)
        )
      );

      migrator.finish(false);
    },

    /**
     * Handle the migrator finishing.
     *
     * @param {boolean} success Whether the migration was successful.
     */
    finish: function (success) {
      $(window).off('beforeunload.mepr-migrator');

      working = false;
      migration.$button.html(migration.$button.data('html')).width('auto');

      if (success && migration.onSuccess && typeof migration.onSuccess === 'function') {
        migration.onSuccess(migration);
      } else if (!success && migration.onError && typeof migration.onError === 'function') {
        migration.onError(migration);
      }

      migration = undefined;
    }
  };

  $(migrator.init);

  return migrator;
})(jQuery);
