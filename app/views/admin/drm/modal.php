<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
$account_link       = MeprDrmHelper::get_drm_link(MeprDrmHelper::DRM_LOCKED, 'general', 'account');
$pm_id              = MeprStripeGateway::has_method_with_connect_status('connected', true);
$country            = MeprStripeGateway::get_account_country($pm_id);
$has_stripe_connect = MeprDrmHelper::is_country_unlockable_by_fee($country);

?>
<div class="mepr-notice-modal">
   <div class="mepr-notice-modal-wrapper">
    <a href='<?php echo esc_url(admin_url()); ?>' class='mepr-notice-modal-close'></a>
    <div class="mepr-notice-modal-content">
     <h3 class="mepr-notice-title"><?php printf(
         // Translators: %1$s: opening span tag, %2$s: closing span tag.
         esc_html__('%1$sALERT!%2$s MemberPress Backend is Deactivated', 'memberpress'),
         '<span>',
         '</span>'
     ); ?></h3>
     <div class="mepr-notice-desc">
      <p><?php esc_html_e('Your MemberPress license key is not found or is invalid. Without an active license key, your frontend is unaffected. However, you can no longer:', 'memberpress'); ?></p>
      <ul>
         <li><?php esc_html_e('Issue customer refunds', 'memberpress'); ?></li>
         <li><?php esc_html_e('Add new members', 'memberpress'); ?></li>
         <li><?php esc_html_e('Manage memberships', 'memberpress'); ?></li>
      </ul>
      <div class="mepr-drm-unlock-steps">
        <div class="mepr-drm-unlock-step1">
          <p><?php esc_html_e('This problem is easy to fix!', 'memberpress'); ?></p>
          <p>
          <?php if ($has_stripe_connect) : ?>
            <a href="#" id="mepr-drm-btn-without-license-confirm" class="button button-secondary button-reactivate-fee mepr-drm-cta"><?php esc_html_e('Reactivate Backend Instantly*', 'memberpress'); ?></a>
            <a target="_blank" href="<?php echo esc_url($account_link); ?>" class="button button-primary mepr-drm-cta"><?php esc_html_e('Buy or renew your license', 'memberpress'); ?></a>
          <?php else : ?>
            <a target="_blank" href="<?php echo esc_url($account_link); ?>" class="button button-primary"><?php esc_html_e('Click Here to purchase or renew your license key', 'memberpress'); ?></a>
          <?php endif; ?>
          </p>
        </div>
        <div class="mepr-drm-unlock-step2" style="display: none;">
          <?php $application_fee_percentage = MeprDrmHelper::get_application_fee_percentage() . '%'; ?>
          <p><?php
              printf(
                  // Translators: %s: application fee percentage.
                  esc_html__('When re-activating without an active license, MP will add an additional %s fee to each transaction. Are you sure that you want to reactivate the backend without a license?', 'memberpress'),
                  esc_html($application_fee_percentage)
              );
                ?></p>
          <p><?php
              printf(
                  // Translators: %s: application fee percentage.
                  esc_html__('IMPORTANT: If this is a staging site, and is connected to your Stripe account, the %s fee WILL be added to your production subscriptions. If you have a valid license, click "Cancel" below and please contact MemberPress support to inquire about a key for your staging site instead.', 'memberpress'),
                  esc_html($application_fee_percentage)
              );
                ?></p>
          <a href="#" id="mepr-drm-btn-without-license" class="button button-secondary"><?php esc_html_e('Yes, I Understand. Reactivate Backend Now.', 'memberpress'); ?></a>
          <p>
            <a href="#" id="mepr-drm-btn-without-license-cancel" class="button button-text"><?php esc_html_e('Cancel', 'memberpress'); ?></a>
          </p>
        </div>
      </div>
      <p><?php printf(
          // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
          esc_html__('If you already have a license key, you can find it on your %1$sAccount Page%2$s, and enter it below:', 'memberpress'),
          '<a href="' . esc_url($account_link) . '" target="_blank">',
          '</a>'
      ); ?></p>
      <form method="POST" id="mepr-drm-form">
         <div class="field"><input name="license_key" id="mepr-drm-license-key" type="text" placeholder="<?php esc_attr_e('License Key', 'memberpress'); ?>"></div>
         <div class="field"><input id="mepr-drm-activate-license-key" type="button" value="<?php esc_attr_e('Submit', 'memberpress'); ?>"></div>
      </form>
      <div class="mepr-key-error mepr-drm-messages" style="display: none;"><span class="drm-error"></span></div>
      <div class="mepr-key-success mepr-drm-messages" style="display: none;"><span class="drm-success"></span></div>

      <?php if ($has_stripe_connect) : ?>
      <p class="mepr-drm-modal-footnote">
        <small>
            <?php
                echo esc_html(
                    sprintf(
                        // Translators: %s: application fee percentage.
                        __('* When re-activating without an active license, MP will add an additional %s fee to each transaction.', 'memberpress'),
                        MeprDrmHelper::get_application_fee_percentage() . '%'
                    )
                );
            ?>
        </small>
      </p>
      <?php endif; ?>

     </div>
    </div>
    <img src="<?php echo esc_url(MEPR_BRAND_URL . '/images/notice-modal-image.png'); ?>" class="mepr-notice-modal-banner" alt="" />
   </div>
</div>
<script>
   jQuery(document).ready(function($) {
     $('body').on('click', '.mepr-notice-modal-close', function (e) {
        $('body').removeClass('mepr-notice-modal-active');
        $('body').removeClass('mepr-locked');
        $('.mepr-notice-modal').remove();
        $('#wpbody').remove();
     });
    <?php if ($has_stripe_connect) : ?>
    $('body').on('click', '#mepr-drm-btn-without-license-confirm,#mepr-drm-btn-without-license-cancel', function (e) {
      e.preventDefault();
      $('.mepr-drm-unlock-step1').toggle();
      $('.mepr-drm-unlock-step2').toggle();
    });
    $('body').on('click', '#mepr-drm-btn-without-license', function (e) {
      e.preventDefault();
      var $button = $(this);
      $.ajax({
       url: ajaxurl,
       method: 'POST',
       dataType: 'json',
       data: {
         action: 'mepr_drm_use_without_license',
         _ajax_nonce: '<?php echo esc_js(wp_create_nonce('mepr_drm_use_without_license')); ?>'
       }
       })
       .done(function (response) {

          if( response.success == false ) {
            alert(response.data);
            return;
          }

          if( response.data.redirect_to ) {
            window.location = response.data.redirect_to;
          }
       })
       .fail(function () {

       })
       .always(function () {

       });
    });
    <?php endif; ?>

   $('body').on('click', '#mepr-drm-activate-license-key', function (e) {
    e.preventDefault();
    var $button = $(this),
     key = $('#mepr-drm-license-key').val();
    if (!key) {
      $('#mepr-drm-license-key').focus();
     return;
     }

     if ($button.hasClass( 'activating ')) {
     return;
     }
    $button.addClass( 'activating ');
     $('.mepr-drm-messages').hide();
     var buttonText = $button.val();
     $button.val( '...' );
    var generic_message = '<?php echo esc_js(__('ERROR! License Key not valid. Try Again.', 'memberpress')); ?>';

     $.ajax({
     url: ajaxurl,
     method: 'POST',
     dataType: 'json',
     data: {
       action: 'mepr_drm_activate_license',
       _ajax_nonce: '<?php echo esc_js(wp_create_nonce('mepr_drm_activate_license')); ?>',
       key: key
     }
     })
     .done(function (response) {
     if (!response || typeof response != 'object' || typeof response.success != 'boolean') {
      $('span.drm-error').html( '<?php echo esc_js(__('Invalid response.', 'memberpress')); ?>' );
      $('.mepr-key-error').show();
     } else if (!response.success) {
       $('span.drm-error').html( response.data );
       $('.mepr-key-error').show();
     } else {
       $('span.drm-success').html( response.data );
       $('.mepr-key-success').show();
       $('#mepr-drm-form').remove();
       setTimeout(function(){
         window.location.href="<?php echo esc_js(esc_url_raw(admin_url('admin.php?page=memberpress-options'))); ?>";
       }, 3000);
     }
     })
     .fail(function () {
     $('.mepr-drm-error span').html( '<?php echo esc_js(__('Ajax error.', 'memberpress')); ?>' );
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
