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
          <a
            href="#"
            id="mepr-design-logo-btn"
            class="button"
            data-upload-target="mepr-design-logo-id"
          >
            <?php esc_html_e('Select Image', 'memberpress'); ?>
          </a>

          <?php if ($mepr_options->design_logo_img) : ?>
          <button
            class="link"
            id="mepr-design-logo-remove-btn"
            style="color: #d63638"
            type="button"
            data-remove-target="mepr-design-logo"
          >
            Remove
          </button>
          <?php endif; ?>
        </div>
        <div>
          <?php if ($mepr_options->design_logo_img) : ?>
          <img
            src="<?php echo esc_url(wp_get_attachment_url($mepr_options->design_logo_img)); ?>"
            id="mepr-design-logo"
            data-image-preview
          />
          <?php endif; ?>
          <input
            type="hidden"
            name="<?php echo esc_attr($mepr_options->design_logo_img_str); ?>"
            id="mepr-design-logo-id"
            value="<?php echo esc_attr($mepr_options->design_logo_img); ?>"
            data-image-input
          />
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
        <input
          type="text"
          name="<?php echo esc_attr($mepr_options->design_primary_color_str); ?>"
          value="<?php echo esc_html($mepr_options->design_primary_color); ?>"
          class="color-field"
          data-default-color="#06429E"
          data-color-type="primary"
        />
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
        <select
          id="<?php echo esc_attr($mepr_options->rl_enable_wp_footer_str); ?>"
          name="<?php echo esc_attr($mepr_options->rl_enable_wp_footer_str); ?>"
          data-setting-type="footer"
        >
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

    <?php
    $preserved_template_fields = [];
    $template_config           = [
        'pricing'  => [
            'label'            => __('Pricing Page', 'memberpress'),
            'enabled_prop'     => 'design_enable_pricing_template',
            'enabled_prop_str' => 'design_enable_pricing_template_str',
            'modal_target'     => 'mepr-pricing-modal',
            'preserved_fields' => [
                [
                    'type'           => 'text',
                    'value_prop'     => 'design_pricing_title',
                    'value_prop_str' => 'design_pricing_title_str',
                ],
                [
                    'type'           => 'text',
                    'value_prop'     => 'design_pricing_cta_color',
                    'value_prop_str' => 'design_pricing_cta_color_str',
                ],
                [
                    'type'           => 'html',
                    'value_prop'     => 'design_pricing_subheadline',
                    'value_prop_str' => 'design_pricing_subheadline_str',
                ],
            ],
        ],
        'checkout' => [
            'label'            => __('Registration Page', 'memberpress'),
            'enabled_prop'     => 'design_enable_checkout_template',
            'enabled_prop_str' => 'design_enable_checkout_template_str',
            'modal_target'     => 'mepr-checkout-modal',
            'preserved_fields' => [
                [
                    'type'           => 'bool',
                    'value_prop'     => 'design_show_checkout_price_terms',
                    'value_prop_str' => 'design_show_checkout_price_terms_str',
                ],
            ],
        ],
        'thankyou' => [
            'label'            => __('Thank You Page', 'memberpress'),
            'enabled_prop'     => 'design_enable_thankyou_template',
            'enabled_prop_str' => 'design_enable_thankyou_template_str',
            'modal_target'     => 'mepr-thankyou-modal',
            'preserved_fields' => [
                [
                    'type'           => 'bool',
                    'value_prop'     => 'design_show_thankyou_welcome_image',
                    'value_prop_str' => 'design_show_thankyou_welcome_image_str',
                ],
                [
                    'type'           => 'text',
                    'value_prop'     => 'design_thankyou_welcome_img',
                    'value_prop_str' => 'design_thankyou_welcome_img_str',
                ],
                [
                    'type'           => 'bool',
                    'value_prop'     => 'design_thankyou_hide_invoice',
                    'value_prop_str' => 'design_thankyou_hide_invoice_str',
                ],
                [
                    'type'           => 'html',
                    'value_prop'     => 'design_thankyou_invoice_message',
                    'value_prop_str' => 'design_thankyou_invoice_message_str',
                ],
            ],
        ],
        'login'    => [
            'label'            => __('Login', 'memberpress'),
            'enabled_prop'     => 'design_enable_login_template',
            'enabled_prop_str' => 'design_enable_login_template_str',
            'modal_target'     => 'mepr-login-modal',
            'preserved_fields' => [
                [
                    'type'           => 'bool',
                    'value_prop'     => 'design_show_login_welcome_image',
                    'value_prop_str' => 'design_show_login_welcome_image_str',
                ],
                [
                    'type'           => 'text',
                    'value_prop'     => 'design_login_welcome_img',
                    'value_prop_str' => 'design_login_welcome_img_str',
                ],
            ],
        ],
        'account'  => [
            'label'            => __('Account', 'memberpress'),
            'enabled_prop'     => 'design_enable_account_template',
            'enabled_prop_str' => 'design_enable_account_template_str',
            'modal_target'     => 'mepr-account-modal',
            'preserved_fields' => [
                [
                    'type'           => 'bool',
                    'value_prop'     => 'design_show_account_welcome_image',
                    'value_prop_str' => 'design_show_account_welcome_image_str',
                ],
                [
                    'type'           => 'text',
                    'value_prop'     => 'design_account_welcome_img',
                    'value_prop_str' => 'design_account_welcome_img_str',
                ],
            ],
        ],
    ];

    foreach ($template_config as $template_name => $config) {
        if (
            !MeprHooks::apply_filters(
                'mepr_readylaunch_show_template_customize',
                true,
                $template_name
            )
        ) {
            $preserved_template_fields[$template_name] = $config['preserved_fields'];
        }
    }
    ?>
    <?php foreach ($preserved_template_fields as $template_name => $fields) : ?>
        <?php foreach ($fields as $field) : ?>
            <?php
            $field_value      = $mepr_options->{$field['value_prop']};
            $field_value_name = $mepr_options->{$field['value_prop_str']};
            ?>
            <?php if ('bool' === $field['type']) : ?>
                <?php if (!empty($field_value)) : ?>
    <input type="hidden" name="<?php echo esc_attr($field_value_name); ?>" value="1" />
                <?php endif; ?>
            <?php elseif ('html' === $field['type']) : ?>
    <textarea name="<?php echo esc_attr($field_value_name); ?>" hidden>
                <?php echo esc_textarea($field_value); ?>
    </textarea>
            <?php else : ?>
    <input
      type="hidden"
      name="<?php echo esc_attr($field_value_name); ?>"
      value="<?php echo esc_attr($field_value); ?>"
    />
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <table class="mepr-options-pane">
      <tbody>
        <?php foreach ($template_config as $template_name => $config) : ?>
            <?php
            $enabled_prop         = $config['enabled_prop'];
            $enabled_prop_str     = $config['enabled_prop_str'];
            $is_template_enabled  = !empty($mepr_options->$enabled_prop);
            $template_option_name = $mepr_options->$enabled_prop_str;
            $show_customize       = MeprHooks::apply_filters(
                'mepr_readylaunch_show_template_customize',
                true,
                $template_name
            );
            ?>
        <tr data-template="<?php echo esc_attr($template_name); ?>">
          <td>
            <label class="switch">
              <input
                type="checkbox"
                id="<?php echo esc_attr($template_option_name); ?>"
                name="<?php echo esc_attr($template_option_name); ?>"
                value="1"
                class="mepr-template-enablers"
                data-modal-target="<?php echo esc_attr($config['modal_target']); ?>"
                <?php checked($is_template_enabled, true); ?>
              >
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <label for="<?php echo esc_attr($template_option_name); ?>">
                <?php echo esc_html($config['label']); ?>
            </label>
          </td>
          <td class="mepr-customize-cell <?php echo $is_template_enabled ? 'show' : ''; ?>">
                <?php if ($show_customize) : ?>
            <button
              class="link mepr-customize-btn"
              type="button"
              data-modal-target="<?php echo esc_attr($config['modal_target']); ?>"
            >
                    <?php esc_html_e('Customize', 'memberpress'); ?>
            </button>
            <a href="#0"></a>
                <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php
            MeprHooks::do_action('mepr_after_readylaunch_options');
        ?>
      </tbody>
    </table>

    <?php foreach ($template_config as $template_name => $config) : ?>
        <?php
        $should_render_template_modal = MeprHooks::apply_filters(
            'mepr_readylaunch_show_template_customize',
            true,
            $template_name
        );
        ?>
        <?php if ($should_render_template_modal) : ?>
            <?php MeprView::render('/admin/readylaunch/' . $template_name, get_defined_vars()); ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php
    MeprHooks::do_action('mepr_after_readylaunch_options_table');
    ?>
  </div>
</div>
