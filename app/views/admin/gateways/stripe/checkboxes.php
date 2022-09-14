<?php

$classes = '';

if ( ! isset( $_GET['display-keys'] ) && ! isset( $_COOKIE['mepr_stripe_display_keys'] ) && ! defined( 'MEPR_DISABLE_STRIPE_CONNECT' ) ) {
  $classes = 'class="mepr-hidden"';
}

?>

<table class="form-table">
  <tbody>
    <?php if ( MeprStripeGateway::is_stripe_connect( $id ) || MeprStripeGateway::keys_are_set( $id ) ) : ?>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $test_mode_str; ?>"><?php _e('Test Mode', 'memberpress'); ?></label></th>
        <td><input class="mepr-stripe-testmode" data-integration="<?php echo $id; ?>" type="checkbox" name="<?php echo $test_mode_str; ?>"<?php echo checked($test_mode); ?> <?php disabled((defined('MEMBERPRESS_STRIPE_TESTING') && MEMBERPRESS_STRIPE_TESTING == true));?> /></td>
      </tr>
      <?php if($currency_supports_link) : ?>
        <tr valign="top">
          <th scope="row" class="stripe-checkbox-column-left">
            <label for="<?php echo $stripe_link_enabled_str ?>"><?php _e('Enable <a href="https://link.co/" target="_blank">Link</a> (recommended)', 'memberpress'); ?>
              <?php
                MeprAppHelper::info_tooltip(
                  'mepr-stripe-link-info',
                  __('Stripe Link Considerations', 'memberpress'),
                  __('If the buyer is not already using Link, they will see a message to save their data for future use. This information is stored by Stripe and not MemberPress.<br /><br/>Buyers can un-enroll from link at anytime on the Link.co website.<br /><br/>For more information, see our help documentation or Stripe\'s Link FAQ.', 'memberpress')
                );
              ?>
             </label>
          </th>
          <td class="stripe-checkbox-column-right">
              <input class="mepr-stripe-no-link" data-integration="<?php echo $id; ?>" type="checkbox" name="<?php echo $stripe_link_enabled_str; ?>"<?php echo checked($stripe_link_enabled); ?> />
          </td>
        </tr>
      <?php endif; ?>
    <?php endif; ?>
    <tr valign="top" <?php echo MeprStripeGateway::is_stripe_connect( $id ) || empty( $live_public_key ) ? 'style="display:none;"' : ''; ?>>
      <th scope="row"><label for="<?php echo $force_ssl_str; ?>"><?php _e('Force SSL', 'memberpress'); ?></label></th>
      <td><input type="checkbox" name="<?php echo $force_ssl_str; ?>"<?php echo checked($force_ssl); ?> /></td>
    </tr>
    <tr valign="top" <?php echo MeprStripeGateway::is_stripe_connect( $id ) || empty( $live_public_key ) ? 'style="display:none;"' : ''; ?>>
      <th scope="row"><label for="<?php echo $debug_str; ?>"><?php _e('Send Debug Emails', 'memberpress'); ?></label></th>
      <td><input type="checkbox" name="<?php echo $debug_str; ?>"<?php echo checked($debug); ?> /></td>
    </tr>
    <tr valign="top" <?php echo $classes; ?>>
      <th scope="row"><label><?php _e('Stripe Webhook URL:', 'memberpress'); ?></label></th>
      <td>
        <?php MeprAppHelper::clipboard_input($whk_url); ?>
      </td>
    </tr>
  </tbody>
</table>
