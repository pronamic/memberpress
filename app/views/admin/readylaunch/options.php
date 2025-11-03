<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>

<div id="design" class="mepr-options-hidden-pane">
  <h3>
    <?php esc_html_e('Global Design Settings', 'memberpress'); ?>
  </h3>

  <div class="mepr-options-pane">
    <div class="mp-row">
      <div>
        <p>
          <strong>
            <?php esc_html_e('Your Logo (1000x300px recommended, svg or png)', 'memberpress'); ?>
          </strong>
        </p>
        <p>
          <?php esc_html_e('Logo (will be placed on top of brand color in all cases)', 'memberpress'); ?>
        </p>
      </div>

      <div class="mepr-flex-row" style="width: 50%;" data-upload-type="logo">
        <div>
          <a href="#" id="mepr-design-logo-btn" class="button" data-upload-target="mepr-design-logo-id"><?php esc_html_e('Select Image', 'memberpress'); ?></a>

          <?php if ($mepr_options->design_logo_img) : ?>
          <button class="link" id="mepr-design-logo-remove-btn" style="color: #d63638" type="button" data-remove-target="mepr-design-logo">Remove</button>
          <?php endif; ?>
        </div>
        <div>
          <?php if ($mepr_options->design_logo_img) : ?>
          <img src="<?php echo esc_url(wp_get_attachment_url($mepr_options->design_logo_img)); ?>" id="mepr-design-logo" data-image-preview />
          <?php endif; ?>
          <input type="hidden" name="<?php echo esc_attr($mepr_options->design_logo_img_str); ?>" id="mepr-design-logo-id" value="<?php echo esc_attr($mepr_options->design_logo_img); ?>" data-image-input />
        </div>
      </div>
    </div>

    <div class="mp-row">
      <div class="mepr-flex-row" style="width: 50%;">
        <p>
          <strong>
            <?php esc_html_e('Brand Colors', 'memberpress'); ?>
          </strong>
        </p>
      </div>
      <div class="mp-col-2">
        <?php esc_html_e('Primary Color', 'memberpress'); ?>
      </div>
      <div class="mp-col-3">
        <input type="text" name="<?php echo esc_attr($mepr_options->design_primary_color_str); ?>" value="<?php echo esc_html($mepr_options->design_primary_color); ?>" class="color-field" data-default-color="#06429E" data-color-type="primary" />
      </div>
    </div>
    <div class="mp-row">
      <div class="mepr-flex-row" style="width: 50%;">
        <p>
          <strong>
            <?php esc_html_e('Footer Settings', 'memberpress'); ?>
          </strong>
        </p>
      </div>
      <div class="mp-col-2">
        <?php esc_html_e('WP Footer Hook', 'memberpress'); ?>
      </div>
      <div class="mp-col-3">
        <select id="<?php echo esc_attr($mepr_options->rl_enable_wp_footer_str); ?>" name="<?php echo esc_attr($mepr_options->rl_enable_wp_footer_str); ?>" data-setting-type="footer">
          <option value="enabled" <?php selected($mepr_options->rl_enable_wp_footer, 'enabled'); ?>>
            <?php esc_html_e('Enabled', 'memberpress'); ?></option>
          <option value="disabled" <?php selected($mepr_options->rl_enable_wp_footer, 'disabled'); ?>>
            <?php esc_html_e('Disabled', 'memberpress'); ?></option>
        </select>
      </div>
    </div>
    <h3>
      <?php esc_html_e('ReadyLaunch™ Templates', 'memberpress'); ?>
    </h3>

    <table class="mepr-options-pane">
      <tbody>
        <tr data-template="pricing">
          <td>
            <label class="switch">
              <input type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_pricing_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_pricing_template_str); ?>" value="1" class="mepr-template-enablers" data-modal-target="mepr-pricing-modal" <?php checked($mepr_options->design_enable_pricing_template, true); ?>>
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_pricing_template_str); ?>"><?php esc_html_e('Pricing Page', 'memberpress'); ?></label>
          </td>
          <td class="mepr-customize-cell <?php echo $mepr_options->design_enable_pricing_template ? 'show' : ''; ?>">
            <button class="link mepr-customize-btn" type="button" data-modal-target="mepr-pricing-modal"><?php esc_html_e('Customize', 'memberpress'); ?></button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr data-template="checkout">
          <td>
            <label class="switch">
              <input type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_checkout_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_checkout_template_str); ?>" value="1" class="mepr-template-enablers" data-modal-target="mepr-checkout-modal" <?php checked($mepr_options->design_enable_checkout_template, true); ?>>
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_checkout_template_str); ?>"><?php esc_html_e('Registration Page', 'memberpress'); ?></label>
          </td>
          <td class="mepr-customize-cell <?php echo $mepr_options->design_enable_checkout_template ? 'show' : ''; ?>">
            <button class="link mepr-customize-btn" type="button" data-modal-target="mepr-checkout-modal">
              <?php esc_html_e('Customize', 'memberpress'); ?>
            </button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr data-template="thankyou">
          <td>
            <label class="switch">
              <input type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_thankyou_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_thankyou_template_str); ?>" value="1" class="mepr-template-enablers" data-modal-target="mepr-thankyou-modal" <?php checked($mepr_options->design_enable_thankyou_template, true); ?>>
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_thankyou_template_str); ?>"><?php esc_html_e('Thank You Page', 'memberpress'); ?></label>
          </td>
          <td class="mepr-customize-cell <?php echo $mepr_options->design_enable_thankyou_template ? 'show' : ''; ?>">
            <button class="link mepr-customize-btn" type="button" data-modal-target="mepr-thankyou-modal">
              <?php esc_html_e('Customize', 'memberpress'); ?>
            </button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr data-template="login">
          <td>
            <label class="switch">
              <input type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_login_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_login_template_str); ?>" value="1" class="mepr-template-enablers" data-modal-target="mepr-login-modal" <?php checked($mepr_options->design_enable_login_template, true); ?>>
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_login_template_str); ?>"><?php esc_html_e('Login', 'memberpress'); ?></label>
          </td>
          <td class="mepr-customize-cell <?php echo $mepr_options->design_enable_login_template ? 'show' : ''; ?>">
            <button class="link mepr-customize-btn" type="button" data-modal-target="mepr-login-modal"><?php esc_html_e('Customize', 'memberpress'); ?></button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr data-template="account">
          <td>
            <label class="switch">
              <input type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_account_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_account_template_str); ?>" value="1" class="mepr-template-enablers" data-modal-target="mepr-account-modal" <?php checked($mepr_options->design_enable_account_template, true); ?>>
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_account_template_str); ?>"><?php esc_html_e('Account', 'memberpress'); ?></label>
          </td>
          <td class="mepr-customize-cell <?php echo $mepr_options->design_enable_account_template ? 'show' : ''; ?>">
            <button class="link mepr-customize-btn" type="button" data-modal-target="mepr-account-modal">
              <?php esc_html_e('Customize', 'memberpress'); ?>
            </button>
            <a href="#0"></a>
          </td>
        </tr>
        <?php
        MeprHooks::do_action('mepr_after_readylaunch_options');
        ?>
      </tbody>
    </table>

    <?php MeprView::render('/admin/readylaunch/pricing', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/login', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/account', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/thankyou', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/checkout', get_defined_vars()); ?>
    <?php
    MeprHooks::do_action('mepr_after_readylaunch_options_table');
    ?>
  </div>
</div>
