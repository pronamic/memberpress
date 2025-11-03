<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php MeprHooks::do_action('mepr_above_checkout_form', $product->ID); ?>

<div class="mp_wrapper">
  <form name="mepr_signup_form" id="mepr_signup_form" class="mepr-signup-form mepr-form" method="post" action="<?php echo esc_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '')) . '#mepr_jump'); ?>" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="mepr_process_signup_form" value="<?php echo isset($_GET['mepr_process_signup_form']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['mepr_process_signup_form']))) : 1 ?>" />
    <input type="hidden" name="mepr_product_id" value="<?php echo esc_attr($product->ID); ?>" />
    <input type="hidden" name="mepr_transaction_id" value="<?php echo isset($_GET['mepr_transaction_id']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['mepr_transaction_id']))) : ''; ?>" />
    <input type="hidden" name="mepr_current_url" value="<?php echo esc_attr(MeprUtils::get_current_url()); ?>" />

    <?php if (MeprUtils::is_user_logged_in()) : ?>
      <input type="hidden" name="logged_in_purchase" value="1" />
      <input type="hidden" name="mepr_checkout_nonce" value="<?php echo esc_attr(wp_create_nonce('logged_in_purchase')); ?>">
        <?php wp_referer_field(); ?>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr_checkout_before_price', $product->ID); ?>

    <?php if (($product->register_price_action !== 'hidden') && MeprHooks::apply_filters('mepr_checkout_show_terms', true, $product)) : ?>
      <div class="mp-form-row mepr_bold mepr_price">
        <?php $price_label = ($product->is_one_time_payment() ? esc_html_x('Price:', 'ui', 'memberpress') : esc_html_x('Terms:', 'ui', 'memberpress')); ?>
        <div class="mepr_price_cell_label"><?php echo esc_html($price_label); ?></div>
        <div class="mepr_price_cell">
          <?php MeprProductsHelper::display_invoice($product, $mepr_coupon_code); ?>
        </div>
      </div>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr_checkout_before_name', $product->ID); ?>

    <?php if (sizeof($payment_methods) <= 0 && $payment_required) : ?>
      <div class="mepr_wrapper">
        <div class="mepr_error">
          <?php esc_html_e('There are no active payment methods. Please contact the site administrator.', 'memberpress'); ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (
    (!MeprUtils::is_user_logged_in() ||
              (MeprUtils::is_user_logged_in() && $mepr_options->show_fields_logged_in_purchases)) &&
             $mepr_options->show_fname_lname
) : ?>
      <div class="mp-form-row mepr_first_name<?php echo ($mepr_options->require_fname_lname) ? ' mepr-field-required' : ''; ?>">
        <div class="mp-form-label">
          <label for="user_first_name<?php echo esc_attr($unique_suffix); ?>"><?php echo esc_html_x('First Name:', 'ui', 'memberpress');
            echo ($mepr_options->require_fname_lname) ? '*' : ''; ?></label>
          <span class="cc-error"><?php echo esc_html_x('First Name Required', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="text" name="user_first_name" id="user_first_name<?php echo esc_attr($unique_suffix); ?>" class="mepr-form-input" value="<?php echo esc_attr($first_name_value); ?>" <?php echo ($mepr_options->require_fname_lname) ? 'required' : ''; ?> />
      </div>
      <div class="mp-form-row mepr_last_name<?php echo ($mepr_options->require_fname_lname) ? ' mepr-field-required' : ''; ?>">
        <div class="mp-form-label">
          <label for="user_last_name<?php echo esc_attr($unique_suffix); ?>"><?php echo esc_html_x('Last Name:', 'ui', 'memberpress');
            echo ($mepr_options->require_fname_lname) ? '*' : ''; ?></label>
          <span class="cc-error"><?php echo esc_html_x('Last Name Required', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="text" name="user_last_name" id="user_last_name<?php echo esc_attr($unique_suffix); ?>" class="mepr-form-input" value="<?php echo esc_attr($last_name_value); ?>" <?php echo ($mepr_options->require_fname_lname) ? 'required' : ''; ?> />
      </div>
    <?php else : // This is here to avoid validation issues. ?>
      <input type="hidden" name="user_first_name" value="<?php echo esc_attr($first_name_value); ?>" />
      <input type="hidden" name="user_last_name" value="<?php echo esc_attr($last_name_value); ?>" />
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr_checkout_before_custom_fields', $product->ID); ?>

    <?php
    if (!MeprUtils::is_user_logged_in() || (MeprUtils::is_user_logged_in() && $mepr_options->show_fields_logged_in_purchases)) {
        MeprUsersHelper::render_custom_fields($product, 'signup', $unique_suffix);
    }
    ?>

    <?php MeprHooks::do_action('mepr_checkout_after_custom_fields', $product->ID); ?>

    <?php if (MeprUtils::is_user_logged_in()) : ?>
      <input type="hidden" name="user_email" value="<?php echo esc_attr(stripslashes($mepr_current_user->user_email)); ?>" />
    <?php else : ?>
      <input type="hidden" class="mepr-geo-country" name="mepr-geo-country" value="" />

        <?php if (!$mepr_options->username_is_email) : ?>
        <div class="mp-form-row mepr_username mepr-field-required">
          <div class="mp-form-label">
            <label for="user_login<?php echo esc_attr($unique_suffix); ?>"><?php echo esc_html_x('Username:*', 'ui', 'memberpress'); ?></label>
            <span class="cc-error"><?php echo esc_html_x('Invalid Username', 'ui', 'memberpress'); ?></span>
          </div>
          <input type="text" name="user_login" id="user_login<?php echo esc_attr($unique_suffix); ?>" class="mepr-form-input" value="<?php echo (isset($user_login)) ? esc_attr(stripslashes($user_login)) : ''; ?>" required />
        </div>
        <?php endif; ?>
      <div class="mp-form-row mepr_email mepr-field-required">
        <div class="mp-form-label">
          <label for="user_email<?php echo esc_attr($unique_suffix); ?>"><?php echo esc_html_x('Email:*', 'ui', 'memberpress'); ?></label>
          <span class="cc-error"><?php echo esc_html_x('Invalid Email', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="email" name="user_email" id="user_email<?php echo esc_attr($unique_suffix); ?>" class="mepr-form-input" value="<?php echo (isset($user_email)) ? esc_attr(stripslashes($user_email)) : ''; ?>" required />
      </div>
      <div class="mp-form-row mepr_email_stripe mepr-field-required mepr-hidden">
      </div>
        <?php MeprHooks::do_action('mepr_after_email_field'); // Deprecated. ?>
        <?php MeprHooks::do_action('mepr_checkout_after_email_field', $product->ID); ?>
        <?php if ($mepr_options->disable_checkout_password_fields === false) : ?>
        <div class="mp-form-row mepr_password mepr-field-required">
          <div class="mp-form-label">
            <label for="mepr_user_password<?php echo esc_attr($unique_suffix); ?>"><?php echo esc_html_x('Password:*', 'ui', 'memberpress'); ?></label>
            <span class="cc-error"><?php echo esc_html_x('Invalid Password', 'ui', 'memberpress'); ?></span>
          </div>
          <div class="mp-hide-pw">
            <input type="password" name="mepr_user_password" id="mepr_user_password<?php echo esc_attr($unique_suffix); ?>" class="mepr-form-input mepr-password" value="<?php echo (isset($mepr_user_password)) ? esc_attr(stripslashes($mepr_user_password)) : ''; ?>" required />
            <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
              <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            </button>
          </div>
        </div>
        <div class="mp-form-row mepr_password_confirm mepr-field-required">
          <div class="mp-form-label">
            <label for="mepr_user_password_confirm<?php echo esc_attr($unique_suffix); ?>"><?php echo esc_html_x('Password Confirmation:*', 'ui', 'memberpress'); ?></label>
            <span class="cc-error"><?php echo esc_html_x('Password Confirmation Doesn\'t Match', 'ui', 'memberpress'); ?></span>
          </div>
          <div class="mp-hide-pw">
            <input type="password" name="mepr_user_password_confirm" id="mepr_user_password_confirm<?php echo esc_attr($unique_suffix); ?>" class="mepr-form-input mepr-password-confirm" value="<?php echo (isset($mepr_user_password_confirm)) ? esc_attr(stripslashes($mepr_user_password_confirm)) : ''; ?>" required />
            <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Show password', 'memberpress'); ?>">
              <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            </button>
          </div>
        </div>
            <?php MeprHooks::do_action('mepr_after_password_fields'); // Deprecated. ?>
            <?php MeprHooks::do_action('mepr_checkout_after_password_fields', $product->ID); ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr_before_coupon_field'); // Deprecated. ?>
    <?php MeprHooks::do_action('mepr_checkout_before_coupon_field', $product->ID); ?>

    <?php MeprHooks::do_action('mepr_render_order_bumps', $product); ?>

    <?php if ($payment_required || !empty($product->plan_code)) : ?>
        <?php if ($mepr_options->coupon_field_enabled) : ?>
        <a class="have-coupon-link" data-prdid="<?php echo esc_attr($product->ID); ?>" href="">
            <?php echo esc_html(MeprCouponsHelper::show_coupon_field_link_content($mepr_coupon_code)); ?>
        </a>
        <div class="mp-form-row mepr_coupon mepr_coupon_<?php echo esc_attr($product->ID); ?> mepr-hidden">
          <div class="mp-form-label">
            <label for="mepr_coupon_code<?php echo esc_attr($unique_suffix); ?>"><?php echo esc_html_x('Coupon Code:', 'ui', 'memberpress'); ?></label>
            <span class="mepr-coupon-loader mepr-hidden">
              <img src="<?php echo esc_url(includes_url('js/thickbox/loadingAnimation.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" title="<?php echo esc_attr_x('Loading icon', 'ui', 'memberpress'); ?>" width="100" height="10" />
            </span>
            <span class="cc-error"><?php echo esc_html_x('Invalid Coupon', 'ui', 'memberpress'); ?></span>
            <span class="cc-success"><?php echo esc_html_x('Coupon applied successfully', 'ui', 'memberpress'); ?></span>
          </div>
          <input type="text" id="mepr_coupon_code<?php echo esc_attr($unique_suffix); ?>" class="mepr-form-input mepr-coupon-code" name="mepr_coupon_code" value="<?php echo (isset($mepr_coupon_code)) ? esc_attr(stripslashes($mepr_coupon_code)) : ''; ?>" data-prdid="<?php echo esc_attr($product->ID); ?>" />
        </div>
        <?php else : ?>
        <input type="hidden" name="mepr_coupon_code" value="<?php echo (isset($mepr_coupon_code)) ? esc_attr(stripslashes($mepr_coupon_code)) : ''; ?>" />
        <?php endif; ?>

        <?php MeprHooks::do_action('mepr_checkout_before_invoice', $product->ID); ?>

        <?php if ($mepr_options->enable_spc_invoice) : ?>
        <div class="mepr-transaction-invoice-wrapper" style="padding-top:10px">
          <span class="mepr-invoice-loader mepr-hidden">
            <img src="<?php echo esc_url(includes_url('js/thickbox/loadingAnimation.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" title="<?php echo esc_attr_x('Loading icon', 'ui', 'memberpress'); ?>" width="100" height="10" />
          </span>
          <div><?php MeprProductsHelper::display_spc_invoice($product, $mepr_coupon_code); ?></div>
        </div>
        <?php endif; ?>

        <?php MeprHooks::do_action('mepr_checkout_before_payment_methods', $product->ID); ?>

      <fieldset class="mepr-payment-methods-wrapper">
        <?php if (sizeof($payment_methods) > 1) : ?>
          <legend><?php echo esc_html_x('Select Payment Method', 'ui', 'memberpress'); ?></legend>
        <?php endif; ?>
        <div class="mepr-payment-methods-icons">
          <?php echo MeprOptionsHelper::payment_methods_icons($payment_methods); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <div class="mepr-payment-methods-radios<?php echo sizeof($payment_methods) === 1 ? ' mepr-hidden' : ''; ?>">
          <?php echo MeprOptionsHelper::payment_methods_radios($payment_methods); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php if (sizeof($payment_methods) > 1) : ?>
          <hr />
        <?php endif; ?>
        <?php echo MeprOptionsHelper::payment_methods_descriptions($payment_methods, $product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </fieldset>
    <?php else : ?>
      <input type="hidden" id="mepr_coupon_code-<?php echo esc_attr($product->ID); ?>" name="mepr_coupon_code" value="<?php echo (isset($mepr_coupon_code)) ? esc_attr(stripslashes($mepr_coupon_code)) : ''; ?>" />
    <?php endif; ?>

    <?php if ($mepr_options->enable_spc_invoice && $product->adjusted_price($mepr_coupon_code) <= 0.00 && false === ( isset($_GET['ca']) && class_exists('MPCA_Corporate_Account') )) { ?>
      <div class="mepr-transaction-invoice-wrapper" style="padding-top:10px">
        <span class="mepr-invoice-loader mepr-hidden">
                      <img src="<?php echo esc_url(includes_url('js/thickbox/loadingAnimation.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" title="<?php echo esc_attr_x('Loading icon', 'ui', 'memberpress'); ?>" width="100" height="10" />
        </span>
        <div>  <!-- Transaction Invoice shows up here  --> </div>
      </div>
    <?php } ?>

    <?php if ($mepr_options->require_tos) : ?>
      <div class="mp-form-row mepr_tos">
        <label for="mepr_agree_to_tos<?php echo esc_attr($unique_suffix); ?>" class="mepr-checkbox-field mepr-form-input" required>
          <input type="checkbox" name="mepr_agree_to_tos" id="mepr_agree_to_tos<?php echo esc_attr($unique_suffix); ?>" <?php checked(isset($mepr_agree_to_tos)); ?> />
          <a href="<?php echo esc_url(stripslashes($mepr_options->tos_url)); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses_post(stripslashes($mepr_options->tos_title)); ?></a>*
        </label>
      </div>
    <?php endif; ?>

    <?php
        $privacy_page_link = MeprAppHelper::privacy_policy_page_link();
    if ($mepr_options->require_privacy_policy && $privacy_page_link) :?>
      <div class="mp-form-row">
        <label for="mepr_agree_to_privacy_policy<?php echo esc_attr($unique_suffix); ?>" class="mepr-checkbox-field mepr-form-input" required>
          <input type="checkbox" name="mepr_agree_to_privacy_policy" id="mepr_agree_to_privacy_policy<?php echo esc_attr($unique_suffix); ?>" />
          <?php echo wp_kses_post(preg_replace('/%(.*)%/', '<a href="' . esc_url($privacy_page_link) . '" target="_blank">$1</a>', $mepr_options->privacy_policy_title)); ?>
        </label>
      </div>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr_user_signup_fields'); // Deprecated. ?>
    <?php MeprHooks::do_action('mepr_checkout_before_submit', $product->ID); ?>

    <div class="mepr_spacer">&nbsp;</div>

    <div class="mp-form-submit">
      <?php // This mepr_no_val needs to be hidden in order for this to work so we do it explicitly as a style. ?>
      <label for="mepr_no_val" class="mepr-visuallyhidden"><?php echo esc_html_x('No val', 'ui', 'memberpress'); ?></label>
      <input type="text" id="mepr_no_val" name="mepr_no_val" class="mepr-form-input mepr-visuallyhidden mepr_no_val mepr-hidden" autocomplete="off" />

      <?php if (sizeof($payment_methods) > 0 || !$payment_required) : ?>
        <input type="submit" class="mepr-submit" value="<?php echo esc_attr(stripslashes($product->signup_button_text)); ?>" />
      <?php endif; ?>
      <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" title="<?php echo esc_attr_x('Loading icon', 'ui', 'memberpress'); ?>" />
      <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
    </div>
  </form>
</div>
