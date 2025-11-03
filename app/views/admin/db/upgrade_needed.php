<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo esc_attr(get_option('blog_charset')); ?>" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?php esc_html_e('MemberPress needs to upgrade your database', 'memberpress'); ?></title>
  <?php
    // Enqueue Bootstrap CSS.
    wp_register_style('memberpress-bootstrap-css', MEPR_CSS_URL . '/vendor/bootstrap-3.3.6.min.css', [], '3.3.6');
    // Optional theme.
    wp_register_style('memberpress-bootstrap-theme', MEPR_CSS_URL . '/vendor/bootstrap-theme-3.3.6.min.css', ['memberpress-bootstrap-css'], '3.3.6');

    // Load compatible version of jQuery for Bootstrap 3.3.6.
    wp_register_script('memberpress-jquery', MEPR_JS_URL . '/vendor/jquery-1.12.4.min.js', [], '1.12.4', false);

    // Enqueue Bootstrap JS.
    wp_register_script('memberpress-bootstrap-js', MEPR_JS_URL . '/vendor/bootstrap-3.3.6.min.js', ['memberpress-jquery'], '3.3.6', false);

    wp_print_styles(['memberpress-bootstrap-css', 'memberpress-bootstrap-theme']);
    wp_print_scripts(['memberpress-jquery', 'memberpress-bootstrap-js']);
    ?>
  <style>
    body { background-color: #dedede; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    p { font-size: 120%; font-weight: 400; }
    h2 { font-size: 30px; font-weight: 500; }
    h3 { font-size: 24px; font-weight: 500; }
    a { text-decoration: none; }
  </style>
  <script>
    $(document).ready(function() {
      var ajaxurl = '<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>';

      var upgrade_db_success = function() {
        window.location.href = '<?php
          echo esc_js(
              esc_url_raw(
                  MeprUtils::admin_url(
                      'admin-ajax.php',
                      ['db_upgrade_success', 'mepr_db_upgrade_nonce'],
                      ['action' => 'mepr_db_upgrade_success']
                  )
              )
          ); ?>';
      };

      var upgrade_db_not_needed = function() {
        window.location.href = '<?php
          echo esc_js(
              esc_url_raw(
                  MeprUtils::admin_url(
                      'admin-ajax.php',
                      ['db_upgrade_not_needed', 'mepr_db_upgrade_nonce'],
                      ['action' => 'mepr_db_upgrade_not_needed']
                  )
              )
          ); ?>';
      };

      var error_count = 0;
      var upgrade_db_error = function(retries) {
        error_count = error_count + 1;

        // Let's only error out if we get at least 3 errors in a row
        if(error_count >= retries) {
          window.location.href = '<?php
            echo esc_js(
                esc_url_raw(
                    MeprUtils::admin_url(
                        'admin-ajax.php',
                        ['db_upgrade_error', 'mepr_db_upgrade_nonce'],
                        ['action' => 'mepr_db_upgrade_error']
                    )
                )
            ); ?>';
        }
        else {
          console.info('An error occurred (' + error_count + '), retrying')
        }
      };

      var last_progress = 0;
      var working_notice = function(message, progress, completed, total) {
        var wn = $('.progress-bar').text();

        if(wn=='' || wn==' ') {
          $('.progress-bar').text('<?php esc_html_e('Upgrading...', 'memberpress'); ?>');
        }
        else {
          $('.progress-bar').text(' ');
        }

        $('.progress-bar-status').html('<em>'+message+' ...</em> <strong>('+completed+'/'+total+')</strong>');

        var pg = parseFloat($('.progress-bar').attr('aria-valuenow'));
        progress = parseFloat(progress);

        // Let's at least throw a bone if they're checking
        if(progress==last_progress) {
          last_progress = progress;
          progress = (pg + 0.3);
        }

        $('.progress-bar').css('width', progress + "%").attr('aria-valuenow', progress);
      }

      var upgrade_db = function(already_upgrading) {
        $('#upgrade_db_trigger').attr('disabled',true);

        var args = {
          'action': 'mepr_db_upgrade',
          'mepr_db_upgrade_nonce': '<?php echo esc_js(wp_create_nonce('db_upgrade')); ?>'
        };

        $('#upgrade_db').modal({
          backdrop: 'static',
          keyboard: false
        });

        if(!already_upgrading) {
          $.post(ajaxurl, args, function(data) {
            // Do nothing until failure
            if(data['status']=='complete') {
              upgrade_db_success();
            }
            else if(data['status']=='already_migrated') {
              upgrade_db_not_needed();
            }
          },'json')
          .fail(function() {
            upgrade_db_error(0);
          });
        }

        setInterval(
          function() {
            $.get(ajaxurl, {
              'action': 'mepr_db_upgrade_in_progress',
              'mepr_db_upgrade_nonce': '<?php echo esc_js(wp_create_nonce('db_upgrade_in_progress')); ?>'
            },
            function(data) {
              // Do nothing until failure
              if(data['status']=='not_in_progress') {
                upgrade_db_success();
              }
              else if(data['status']=='in_progress') {
                error_count = 0; // reset error count to zero
                // Nothing ... notice & loop
                working_notice(data['message'],data['progress'],data['completed'],data['total']);
              }
            },'json')
            .fail(function() {
              upgrade_db_error(3);
            });
          },
          5000
        );
      };

      $.get(ajaxurl, {
        'action': 'mepr_db_upgrade_in_progress',
        'mepr_db_upgrade_nonce': '<?php echo esc_js(wp_create_nonce('db_upgrade_in_progress')); ?>'
      },
      function(data) {
        // Do nothing until failure
        if(data['status']=='not_in_progress') {
          //upgrade_db_success();
        }
        else if(data['status']=='in_progress') {
          working_notice(data['message'],data['progress'],data['completed'],data['total']);
          upgrade_db(true);
        }
      },'json')
      .fail(function() {
        upgrade_db_error(0);
      });

      $('#upgrade_db_trigger').on('click',function() {
        upgrade_db(false);
      });
    });
  </script>
</head>
<body>
  <div class="container">
    <div class="row">&nbsp;</div>
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="panel panel-primary">
          <div class="panel-heading"><h2><?php esc_html_e('MemberPress needs to upgrade your database', 'memberpress'); ?></h2></div>
          <div class="panel-body">
            <p>&nbsp;</p>
            <p><?php esc_html_e('Before starting the upgrade process <strong>make sure your <em>database is backed up</em></strong>.', 'memberpress'); ?></p>
            <p><?php esc_html_e('And please be patient, the upgrade process <em>may take a few minutes</em>.', 'memberpress'); ?></p>
            <p>&nbsp;</p>
            <?php
              $update_ctrl = new MeprUpdateCtrl();
            ?>
            <!-- <p><a class="btn btn-primary" href="<?php
              echo esc_url(MeprUtils::admin_url(
                  'admin-ajax.php',
                  ['db_upgrade', 'mepr_db_upgrade_nonce'],
                  ['action' => 'mepr_db_upgrade']
              ));
                ?>"><?php esc_html_e('Upgrade', 'memberpress'); ?></a></p> -->
            <!-- Button trigger modal -->
            <p>
              <button type="button" class="btn btn-primary btn-lg" id="upgrade_db_trigger"><?php esc_html_e('Upgrade', 'memberpress'); ?></button> or
              <a href="<?php echo esc_url($update_ctrl->rollback_url()); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure? This will cancel the upgrade and roll MemberPress back to the previous version.', 'memberpress')); ?>');" target="_blank"><?php esc_html_e('Cancel', 'memberpress'); ?></a>
            </p>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="upgrade_db" tabindex="-1" role="dialog" aria-labelledby="upgrade_db_label">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-body">
            <h2><?php esc_html_e('Your database is being upgraded', 'memberpress'); ?></h2>
            <p><?php esc_html_e('Please be patient this could take a few minutes.', 'memberpress'); ?></p>
            <p>&nbsp;</p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"> </div>
            </div>
            <p class="progress-bar-status"> </p>
            <br/><br/>
            <a href="<?php echo esc_url($update_ctrl->rollback_url()); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure? This will abort the upgrade and roll MemberPress back to the previous version.', 'memberpress')); ?>');" target="_blank"><?php esc_html_e('Cancel', 'memberpress'); ?></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
