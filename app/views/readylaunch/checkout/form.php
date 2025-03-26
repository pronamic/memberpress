<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="mepr-before-signup-form">
  <?php do_action('mepr-above-checkout-form', $product->ID); ?>
</div>

<form name="mepr_signup_form" id="mepr_signup_form" class="mepr-signup-form mepr-form alignwide" method="post"
      action="<?php echo esc_url(wp_unslash($_SERVER['REQUEST_URI']) . '#mepr_jump'); ?>" enctype="multipart/form-data" novalidate>
  <div class="mepr-checkout-container mp_wrapper <?php echo $is_rl_widget ? 'mepr-is-footer-widget' : ''; ?>">


  <div class="form-wrapper">
    <?php

    if (isset($errors) and ! empty($errors)) {
        // MeprView::render( '/shared/errors', get_defined_vars() );
    }


    ?>
    <input type="hidden" name="mepr_process_signup_form"
    value="<?php echo isset($_GET['mepr_process_signup_form']) ? esc_attr($_GET['mepr_process_signup_form']) : 1; ?>" />
    <input type="hidden" name="mepr_product_id" value="<?php echo esc_attr($product->ID); ?>" />
    <input type="hidden" name="mepr_transaction_id"
    value="<?php echo isset($_GET['mepr_transaction_id']) ? esc_attr($_GET['mepr_transaction_id']) : ''; ?>" />

    <?php if (MeprUtils::is_user_logged_in()) : ?>
    <input type="hidden" name="logged_in_purchase" value="1" />
    <input type="hidden" name="mepr_checkout_nonce"
    value="<?php echo esc_attr(wp_create_nonce('logged_in_purchase')); ?>">
        <?php wp_referer_field(); ?>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr-checkout-before-name', $product->ID); ?>

    <?php
    if (
        ( ! MeprUtils::is_user_logged_in() ||
        ( MeprUtils::is_user_logged_in() && $mepr_options->show_fields_logged_in_purchases ) ) &&
        $mepr_options->show_fname_lname
    ) :
        ?>

    <div class="mp-form-row ">

    <label for="user_first_name<?php echo $unique_suffix; ?>">
        <?php
                      _ex('Name:', 'ui', 'memberpress');
                      echo ( $mepr_options->require_fname_lname ) ? '*' : '';
        ?>
    </label>
    <div class="mp-form-row-group">
      <input type="text" name="user_first_name" id="user_first_name<?php echo $unique_suffix; ?>"
      class="mepr-form-input" value="<?php echo esc_attr($first_name_value); ?>" placeholder="<?php
                            _ex('First Name', 'ui', 'memberpress');
                            echo ( $mepr_options->require_fname_lname ) ? '*' : '';
        ?>
      " <?php echo ( $mepr_options->require_fname_lname ) ? 'required' : ''; ?> />
      <input type="text" name="user_last_name" id="user_last_name<?php echo $unique_suffix; ?>"
      class="mepr-form-input" value="<?php echo esc_attr($last_name_value); ?>" placeholder="<?php
                            _ex('Last Name', 'ui', 'memberpress');
                            echo ( $mepr_options->require_fname_lname ) ? '*' : '';
        ?>
      " <?php echo ( $mepr_options->require_fname_lname ) ? 'required' : ''; ?> />
    </div>

    <!-- <div class="mp-form-row mepr_first_name<?php // echo ($mepr_options->require_fname_lname) ? ' mepr-field-required' : ''; ?>"> -->
    <!-- <div class="mp-form-label"> -->
    <!-- <label for="user_first_name<?php // echo $unique_suffix; ?>">
        <?php
        _ex('First Name:', 'ui', 'memberpress');
        echo ( $mepr_options->require_fname_lname ) ? '*' : '';
        ?>
    </label> -->
    <span class="cc-error"><?php _ex('First Name Required', 'ui', 'memberpress'); ?></span>
    <!-- </div> -->

    <!-- </div> -->
    <!-- <div class="mp-form-row mepr_last_name<?php echo ( $mepr_options->require_fname_lname ) ? ' mepr-field-required' : ''; ?>"> -->
    <!-- <div class="mp-form-label"> -->
    <!-- <label for="user_last_name<?php echo $unique_suffix; ?>">
                          <?php
                            _ex('Last Name:', 'ui', 'memberpress');
                            echo ( $mepr_options->require_fname_lname ) ? '*' : '';
                            ?>
    </label> -->
    <span class="cc-error"><?php _ex('Last Name Required', 'ui', 'memberpress'); ?></span>
    <!-- </div> -->

    <!-- </div> -->
    </div>
    <?php else : // this is here to avoid validation issues ?>
    <input type="hidden" name="user_first_name" value="<?php echo esc_attr($first_name_value); ?>" />
    <input type="hidden" name="user_last_name" value="<?php echo esc_attr($last_name_value); ?>" />
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr-checkout-before-custom-fields', $product->ID); ?>

    <?php
    if (! MeprUtils::is_user_logged_in() || ( MeprUtils::is_user_logged_in() && $mepr_options->show_fields_logged_in_purchases )) {
        ?>
    <div class="mp-form-row mp-address-group">
        <?php
        if ($mepr_options->show_address_fields && ! $product->disable_address_fields) {
            MeprUsersHelper::render_address_fields();
        }
        ?>
    </div>
        <?php
        MeprUsersHelper::render_custom_fields($product, 'signup', $unique_suffix, false);
    }
    ?>

    <?php MeprHooks::do_action('mepr-checkout-after-custom-fields', $product->ID); ?>

    <?php if (MeprUtils::is_user_logged_in()) : ?>
    <input type="hidden" name="user_email"
    value="<?php echo esc_attr(stripslashes($mepr_current_user->user_email)); ?>" />
    <?php else : ?>
    <input type="hidden" class="mepr-geo-country" name="mepr-geo-country" value="" />

        <?php if (! $mepr_options->username_is_email) : ?>
    <div class="mp-form-row mepr_username mepr-field-required">
    <div class="mp-form-label">
      <label
      for="user_login<?php echo $unique_suffix; ?>"><?php _ex('Username:*', 'ui', 'memberpress'); ?></label>
      <span class="cc-error"><?php _ex('Invalid Username', 'ui', 'memberpress'); ?></span>
    </div>
    <input type="text" name="user_login" id="user_login<?php echo $unique_suffix; ?>" class="mepr-form-input"
      value="<?php echo ( isset($user_login) ) ? esc_attr(stripslashes($user_login)) : ''; ?>" required />
    </div>
        <?php endif; ?>
    <div class="mp-form-row mepr_email mepr-field-required">
    <div class="mp-form-label">
      <label for="user_email<?php echo $unique_suffix; ?>"><?php _ex('Email:*', 'ui', 'memberpress'); ?></label>
      <span class="cc-error"><?php _ex('Invalid Email', 'ui', 'memberpress'); ?></span>
    </div>
    <input type="email" name="user_email" id="user_email<?php echo $unique_suffix; ?>" class="mepr-form-input"
      value="<?php echo ( isset($user_email) ) ? esc_attr(stripslashes($user_email)) : ''; ?>" required />
    </div>
    <div class="mp-form-row mepr_email_stripe mepr-field-required mepr-hidden">
    </div>
        <?php MeprHooks::do_action('mepr-after-email-field'); // Deprecated ?>
        <?php MeprHooks::do_action('mepr-checkout-after-email-field', $product->ID); ?>
        <?php if ($mepr_options->disable_checkout_password_fields === false) : ?>
    <div class="mp-form-row mepr_password mepr-field-required">
    <div class="mp-form-label">
      <label
      for="mepr_user_password<?php echo $unique_suffix; ?>"><?php _ex('Password:*', 'ui', 'memberpress'); ?></label>
      <span class="cc-error"><?php _ex('Invalid Password', 'ui', 'memberpress'); ?></span>
    </div>
    <div class="mp-hide-pw">
      <input type="password" name="mepr_user_password" id="mepr_user_password<?php echo $unique_suffix; ?>"
      class="mepr-form-input mepr-password"
      value="<?php echo ( isset($mepr_user_password) ) ? esc_attr(stripslashes($mepr_user_password)) : ''; ?>"
      required />
      <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0"
      aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
      <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
      </button>
    </div>
    </div>
    <div class="mp-form-row mepr_password_confirm mepr-field-required">
    <div class="mp-form-label">
      <label
      for="mepr_user_password_confirm<?php echo $unique_suffix; ?>"><?php _ex('Password Confirmation:*', 'ui', 'memberpress'); ?></label>
      <span class="cc-error"><?php _ex('Password Confirmation Doesn\'t Match', 'ui', 'memberpress'); ?></span>
    </div>
    <div class="mp-hide-pw">
      <input type="password" name="mepr_user_password_confirm"
      id="mepr_user_password_confirm<?php echo $unique_suffix; ?>" class="mepr-form-input mepr-password-confirm"
      value="<?php echo ( isset($mepr_user_password_confirm) ) ? esc_attr(stripslashes($mepr_user_password_confirm)) : ''; ?>"
      required />
      <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0"
      aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
      <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
      </button>
    </div>
    </div>
            <?php MeprHooks::do_action('mepr-after-password-fields'); // Deprecated ?>
            <?php MeprHooks::do_action('mepr-checkout-after-password-fields', $product->ID); ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr-before-coupon-field'); // Deprecated ?>
    <?php MeprHooks::do_action('mepr-checkout-before-coupon-field', $product->ID); ?>

    <?php MeprHooks::do_action('mepr_render_order_bumps', $product); ?>

    <?php if ($payment_required || ! empty($product->plan_code)) : ?>
    <div class="mepr-payment-methods-wrapper">
        <?php if (sizeof($payment_methods) > 1) : ?>
    <h3><?php _ex('Select Payment Method', 'ui', 'memberpress'); ?></h3>
        <?php endif; ?>
    <div class="mepr-payment-methods-icons">
        <?php echo MeprOptionsHelper::payment_methods_icons($payment_methods); ?>
    </div>
    <div class="mepr-payment-methods-radios<?php echo sizeof($payment_methods) === 1 ? ' mepr-hidden' : ''; ?>">
        <?php echo MeprOptionsHelper::payment_methods_radios($payment_methods); ?>
    </div>
        <?php if (sizeof($payment_methods) > 1) : ?>
    <hr />
        <?php endif; ?>
        <?php echo MeprOptionsHelper::payment_methods_descriptions($payment_methods, $product); ?>
    </div>
    <?php else : ?>
    <input type="hidden" id="mepr_coupon_code-<?php echo $product->ID; ?>" name="mepr_coupon_code"
    value="<?php echo ( isset($mepr_coupon_code) ) ? esc_attr(stripslashes($mepr_coupon_code)) : ''; ?>" />
    <?php endif; ?>

    <?php if ($mepr_options->enable_spc_invoice && $product->adjusted_price($mepr_coupon_code) <= 0.00 && false == ( isset($_GET['ca']) && class_exists('MPCA_Corporate_Account') )) { ?>
    <div class="mepr-transaction-invoice-wrapper" style="padding-top:10px">
    <span class="mepr-invoice-loader mepr-hidden">
      <img src="<?php echo includes_url('js/thickbox/loadingAnimation.gif'); ?>"
      alt="<?php _e('Loading...', 'memberpress'); ?>"
      title="<?php _ex('Loading icon', 'ui', 'memberpress'); ?>" width="100" height="10" />
    </span>
    <div>
      <!-- Transaction Invoice shows up here  -->
    </div>
    </div>
    <?php } ?>


    <?php if ($mepr_options->require_tos) : ?>
    <div class="mp-form-row mepr_tos">
    <label for="mepr_agree_to_tos<?php echo $unique_suffix; ?>" class="mepr-checkbox-field mepr-form-input"
      required>
      <input type="checkbox" name="mepr_agree_to_tos" id="mepr_agree_to_tos<?php echo $unique_suffix; ?>"
        <?php checked(isset($mepr_agree_to_tos)); ?> />
      <a href="<?php echo stripslashes($mepr_options->tos_url); ?>" target="_blank"
      rel="noopener noreferrer"><?php echo wp_kses_post(stripslashes($mepr_options->tos_title)); ?></a>*
    </label>
    </div>
    <?php endif; ?>

    <?php
    $privacy_page_link = MeprAppHelper::privacy_policy_page_link();
    if ($mepr_options->require_privacy_policy && $privacy_page_link) : ?>
        <div class="mp-form-row">
        <label for="mepr_agree_to_privacy_policy<?php echo $unique_suffix; ?>"
        class="mepr-checkbox-field mepr-form-input" required>
        <input type="checkbox" name="mepr_agree_to_privacy_policy"
        id="mepr_agree_to_privacy_policy<?php echo $unique_suffix; ?>" />
            <?php echo preg_replace('/%(.*)%/', '<a href="' . $privacy_page_link . '" target="_blank">$1</a>', wp_kses_post(__($mepr_options->privacy_policy_title, 'memberpress'))); ?>
        </label>
        </div>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr-user-signup-fields'); // Deprecated ?>
    <?php MeprHooks::do_action('mepr-checkout-before-submit', $product->ID); ?>

    <div class="mepr_spacer">&nbsp;</div>

    <div class="mp-form-submit">
    <?php // This mepr_no_val needs to be hidden in order for this to work so we do it explicitly as a style ?>
    <label for="mepr_no_val" class="mepr-visuallyhidden"><?php _ex('No val', 'ui', 'memberpress'); ?></label>
    <input type="text" id="mepr_no_val" name="mepr_no_val"
      class="mepr-form-input mepr-visuallyhidden mepr_no_val mepr-hidden" autocomplete="off" />

    <input type="submit" class="mepr-submit" value="<?php echo stripslashes($product->signup_button_text); ?>" />
    <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>"
      style="display: none;" class="mepr-loading-gif"
      title="<?php _ex('Loading icon', 'ui', 'memberpress'); ?>" />
    <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
    </div>
  </div>


  <div class="invoice-wrapper">
    <h3 class="invoice-heading"><?php printf(esc_html_x('Pay %s', 'ui', 'memberpress'), get_bloginfo('name')); ?></h3>

    <div class="mp-form-row mepr_bold mepr_price">
      <div class="mepr_price_cell invoice-amount">
        <?php MeprProductsHelper::display_invoice($product, $mepr_coupon_code); ?>
      </div>
    </div>


    <div class="mepr-product-rows">
    <?php if ($payment_required || ! empty($product->plan_code)) : ?>
        <?php if ($mepr_options->coupon_field_enabled) : ?>
    <a class="have-coupon-link" data-prdid="<?php echo $product->ID; ?>" href="">
            <?php echo MeprCouponsHelper::show_coupon_field_link_content($mepr_coupon_code); ?>
    </a>
    <div class="mp-form-row mepr_coupon mepr_coupon_<?php echo $product->ID; ?> mepr-hidden">
      <div class="mp-form-label">
      <!-- <label for="mepr_coupon_code<?php // echo $unique_suffix; ?>"><?php // _ex( 'Coupon Code:', 'ui', 'memberpress' ); ?></label> -->
      <span class="mepr-coupon-loader mepr-hidden">
        <img src="<?php echo includes_url('js/thickbox/loadingAnimation.gif'); ?>"
        alt="<?php _e('Loading...', 'memberpress'); ?>"
        title="<?php _ex('Loading icon', 'ui', 'memberpress'); ?>" width="100" height="10" />
      </span>
      <span class="cc-error"><?php _ex('Invalid Coupon', 'ui', 'memberpress'); ?></span>
      <span class="cc-success"><?php _ex('Coupon applied successfully', 'ui', 'memberpress'); ?></span>
      </div>
      <input type="text" id="mepr_coupon_code<?php echo $unique_suffix; ?>" class="mepr-form-input mepr-coupon-code"
      placeholder="<?php _ex('Coupon Code:', 'ui', 'memberpress'); ?>"
      name="mepr_coupon_code"
      value="<?php echo ( isset($mepr_coupon_code) ) ? esc_attr(stripslashes($mepr_coupon_code)) : ''; ?>"
      data-prdid="<?php echo $product->ID; ?>" />
    </div>
        <?php else : ?>
    <input type="hidden" name="mepr_coupon_code"
      value="<?php echo ( isset($mepr_coupon_code) ) ? esc_attr(stripslashes($mepr_coupon_code)) : ''; ?>" />
        <?php endif; ?>
    <?php endif ?>


    <div class="mepr-transaction-invoice-wrapper" style="padding-top:10px">
      <span class="mepr-invoice-loader mepr-hidden">
      <img src="<?php echo includes_url('js/thickbox/loadingAnimation.gif'); ?>"
        alt="<?php _e('Loading...', 'memberpress'); ?>"
        title="<?php _ex('Loading icon', 'ui', 'memberpress'); ?>" width="100" height="10" />
      </span>
      <div><?php MeprProductsHelper::display_spc_invoice($product, $mepr_coupon_code); ?></div>
    </div>


    </div>

  </div>

  </div>
</form>
