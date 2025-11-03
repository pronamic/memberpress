<div class="notice is-dismissible" data-notice-id="<?php echo esc_attr($notice_id); ?>" id="mepr_ip_admin_notice" style="border-left-color: #00cee6;">
  <h3><?php echo esc_html($heading); ?></h3>
  <p><?php echo wp_kses_post($message); ?></p>
  <p>
    <a href="<?php echo esc_url($link); ?>" class="button button-primary" target="_blank"><?php echo esc_html($button_text); ?></a>
    <a href="#" class="button button-secondary" id="mepr_ip_admin_notice_dismiss"><?php esc_html_e('Dismiss', 'memberpress'); ?></a>
  </p>
</div>
<script>
  jQuery(document).ready(function($) {
    $('body').on('click', '#mepr_ip_admin_notice button.notice-dismiss, #mepr_ip_admin_notice_dismiss', function(event) {
      event.preventDefault();
      $notice = $('#mepr_ip_admin_notice');
      $.ajax({
        url: "<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>",
        type: 'POST',
        data: {
          action: 'mepr_dismiss_ip_admin_notice',
          nonce: "<?php echo esc_js(wp_create_nonce('mepr_dismiss_ip_admin_notice')); ?>",
          notice_id: $notice.data('notice-id')
        },
      })
      .done(function() {
        $('#mepr_ip_admin_notice').slideUp();
        $('.memberpress-menu-pulse.green').hide();
      });
    });
  });
</script>
