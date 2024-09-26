<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>

<div x-data="meprProTemplates" x-init="mounted(<?php echo esc_html(wp_json_encode($data)); ?>)" id="design" class="mepr-options-hidden-pane">
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

      <div class="mepr-flex-row" style="width: 50%;">
        <div>
          <a href="#" id="mepr-design-logo-btn" class="button"><?php esc_html_e('Select Image', 'memberpress'); ?></a>

          <button x-show="global.logoId" x-on:click="global.logoId=null" class="link" id="mepr-design-logo-remove-btn" style="color: #d63638" type="button">Remove</button>
        </div>
        <div>
          <img x-show="global.logoId" src="<?php echo esc_url(wp_get_attachment_url($mepr_options->design_logo_img)); ?>" id="mepr-design-logo" />
          <input x-model="global.logoId" type="hidden" name="<?php echo esc_attr($mepr_options->design_logo_img_str); ?>" id="mepr-design-logo-id" />
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
        <input type="text" name="<?php echo esc_attr($mepr_options->design_primary_color_str); ?>" value="<?php echo esc_html($mepr_options->design_primary_color); ?>" class="color-field" data-default-color="#06429E" />
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
        <select id="<?php echo esc_attr($mepr_options->rl_enable_wp_footer_str); ?>" name="<?php echo esc_attr($mepr_options->rl_enable_wp_footer_str); ?>">
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
        <tr>
          <td>
            <label class="switch">
              <input x-model="pricing.enableTemplate" type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_pricing_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_pricing_template_str); ?>" value="1" class="mepr-template-enablers">
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_pricing_template_str); ?>"><?php esc_html_e('Pricing Page', 'memberpress'); ?></label>
          </td>
          <td x-show="pricing.enableTemplate">
            <button x-on:click="pricing.openModal = true" class="link" type="button"><?php esc_html_e('Customize', 'memberpress'); ?></button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr>
          <td>
            <label class="switch">
              <input x-model="checkout.enableTemplate" type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_checkout_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_checkout_template_str); ?>" value="1" class="mepr-template-enablers">
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_checkout_template_str); ?>"><?php esc_html_e('Registration Page', 'memberpress'); ?></label>
          </td>
          <td x-show="checkout.enableTemplate">
            <button x-on:click="checkout.openModal = true" class="link" type="button">
              <?php esc_html_e('Customize', 'memberpress'); ?>
            </button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr>
          <td>
            <label class="switch">
              <input x-model="thankyou.enableTemplate" type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_thankyou_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_thankyou_template_str); ?>" value="1" class="mepr-template-enablers">
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_thankyou_template_str); ?>"><?php esc_html_e('Thank You Page', 'memberpress'); ?></label>
          </td>
          <td x-show="thankyou.enableTemplate">
            <button x-on:click="thankyou.openModal = true" class="link" type="button">
              <?php esc_html_e('Customize', 'memberpress'); ?>
            </button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr>
          <td>
            <label class="switch">
              <input x-model="login.enableTemplate" type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_login_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_login_template_str); ?>" value="1" class="mepr-template-enablers">
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_login_template_str); ?>"><?php esc_html_e('Login', 'memberpress'); ?></label>
          </td>
          <td x-show="login.enableTemplate">
            <button x-on:click="login.openModal = true" class="link" type="button"><?php esc_html_e('Customize', 'memberpress'); ?></button>
            <a href="#0"></a>
          </td>
        </tr>
        <tr>
          <td>
            <label class="switch">
              <input x-model="account.enableTemplate" type="checkbox" id="<?php echo esc_attr($mepr_options->design_enable_account_template_str); ?>" name="<?php echo esc_attr($mepr_options->design_enable_account_template_str); ?>" value="1" class="mepr-template-enablers">
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($mepr_options->design_enable_account_template_str); ?>"><?php esc_html_e('Account', 'memberpress'); ?></label>
          </td>
          <td x-show="account.enableTemplate">
            <button x-on:click="account.openModal = true" class="link" type="button">
              <?php esc_html_e('Customize', 'memberpress'); ?>
            </button>
            <a href="#0"></a>
          </td>
        </tr>
        <?php
        MeprHooks::do_action('mepr-after-readylaunch-options');
        ?>
      </tbody>
    </table>

    <?php MeprView::render('/admin/readylaunch/pricing', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/login', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/account', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/thankyou', get_defined_vars()); ?>
    <?php MeprView::render('/admin/readylaunch/checkout', get_defined_vars()); ?>
    <?php
    MeprHooks::do_action('mepr-after-readylaunch-options-table');
    ?>
  </div>
</div>
