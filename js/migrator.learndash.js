var MeprMigratorLearnDash = (function ($) {
  var migrator;

  migrator = {
    init: function () {
      $('#mepr-migrator-learndash-start').on('click', function () {
        var $progress = $('#mepr-migrator-learndash-user-progress'),
            onSuccess = function () {
              $progress.prop('disabled', false);
              $('#mepr-wizard-choose-content-search').triggerHandler('keyup'); // refresh existing content
              $('#mepr-wizard-ld-migrator').remove();
              $('#mepr-wizard-ld-migrator-success, #mepr-wizard-create-select-content').show();
              $('#mepr-wizard-content-nav-skip button').prop('disabled', false);
              $('#mepr-migrator-learndash-please-wait').hide();
            },
            onError = function () {
              $progress.prop('disabled', false);
              $('#mepr-wizard-cancel-ld-migrator').show();
              $('#mepr-wizard-content-nav-skip button').prop('disabled', false);
              $('#mepr-migrator-learndash-please-wait').hide();
            },
            data = {
              migrator: 'learndash',
              step: 'start',
              options: {
                userProgress: $progress.is(':checked')
              }
            };

        $progress.prop('disabled', true);
        $('#mepr-wizard-cancel-ld-migrator').hide();
        $('#mepr-wizard-content-nav-skip button').prop('disabled', true);
        $('#mepr-migrator-learndash-please-wait').fadeIn();

        MeprMigrator.start($(this), data, onSuccess, onError);
      });

      $('#mepr-wizard-cancel-ld-migrator').on('click', function () {
        $('#mepr-wizard-ld-migrator').remove();
        $('#mepr-wizard-create-select-content').show();
      });
    },
  };

  $(migrator.init);

  return migrator;
})(jQuery);
