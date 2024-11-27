<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
$account_link = MeprDrmHelper::get_drm_link(MeprDrmHelper::DRM_LOCKED, 'general', 'account');
?>
<div class="mepr-notice-modal">
   <div class="mepr-notice-modal-wrapper">
    <a href='#' class='mepr-notice-modal-close'></a>
    <div class="mepr-notice-modal-content">
     <h3 class="mepr-notice-title"><?php _e('<span>ALERT!</span> MemberPress is running without a license', 'memberpress'); ?></h3>
     <div class="mepr-notice-desc">
      <p><?php printf(__('When using without a license, MemberPress will add an additional %s fee to each transaction.', 'memberpress'), MeprDrmHelper::get_application_fee_percentage() . '%'); ?></p>

      <p>
        <a target="_blank" href="<?php echo $account_link; ?>" class="button button-primary"><?php _e('Click here to purchase or renew your license key', 'memberpress'); ?></a>
      </p>

      <p><?php _e('If you already have a license key, you can find it on your <a href="' . $account_link . '" target="_blank">Account Page</a>, and enter it below:', 'memberpress'); ?></p>
      <form method="POST" id="mepr-drm-form">
         <div class="field"><input name="license_key" id="mepr-drm-license-key" type="text" placeholder="<?php esc_attr_e('License Key', 'memberpress'); ?>"></div>
         <div class="field"><input id="mepr-drm-activate-license-key" type="button" value="<?php esc_attr_e('Submit', 'memberpress'); ?>"></div>
      </form>
      <div class="mepr-key-error mepr-drm-messages" style="display: none;"><span class="drm-error"></span></div>
      <div class="mepr-key-success mepr-drm-messages" style="display: none;"><span class="drm-success"></span></div>

     </div>
    </div>
     <img src="<?php echo esc_url(MEPR_BRAND_URL . '/images/notice-modal-image.png'); ?>" class="mepr-notice-modal-banner" alt="" />
   </div>
</div>
<script>
   jQuery(document).ready(function($) {
     $('body').on('click', '.mepr-notice-modal-close', function (e) {
        e.preventDefault();
        $('body').removeClass('mepr-notice-modal-active');
        $('body').removeClass('mepr-locked');
        $('#mepr-drm-fee-notice-wrapper').show();
     });

    $('body').on('click', '#mepr-drm-activate-license-key', function (e) {
      e.preventDefault();
      var $button = $(this),
       key = $('#mepr-drm-license-key').val();
      if (!key) {
        $('#mepr-drm-license-key').focus();
        return;
       }

       if($button.hasClass( 'activating ')){
        return;
       }
      $button.addClass( 'activating ');
       $('.mepr-drm-messages').hide();
       var buttonText = $button.val();
       $button.val( '...' );
      var generic_message = '<?php __('ERROR! License Key not valid. Try Again.', 'memberpress'); ?>';

       $.ajax({
       url: ajaxurl,
       method: 'POST',
       dataType: 'json',
       data: {
         action: 'mepr_drm_activate_license',
         _ajax_nonce: '<?php echo wp_create_nonce('mepr_drm_activate_license'); ?>',
         key: key
       }
       })
       .done(function (response) {
       if (!response || typeof response != 'object' || typeof response.success != 'boolean') {
        $('span.drm-error').html( '<?php __('Invalid response.', 'memberpress'); ?>' );
        $('.mepr-key-error').show();
       } else if (!response.success) {
         $('span.drm-error').html( response.data );
         $('.mepr-key-error').show();
       } else {
         $('span.drm-success').html( response.data );
         $('.mepr-key-success').show();
         $('#mepr-drm-form').remove();
         setTimeout(function(){
           window.location.href="<?php echo admin_url('admin.php?page=memberpress-options'); ?>";
         }, 3000);
       }
       })
       .fail(function () {
       $('.mepr-drm-error span').html( '<?php echo __('Ajax error.', 'memberpress'); ?>' );
       $('.mepr-key-error').show();
       })
       .always(function () {
       $button.removeClass( 'activating ');
       $button.val( buttonText );
       });
     });
      $('body').on('keypress', '#mepr-drm-license-key', function (e) {
       if(e.which === 13) {
       e.preventDefault();
       $('#mepr-drm-activate-license-key').trigger('click');
       }
     });
});
</script>
