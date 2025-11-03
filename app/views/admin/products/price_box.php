<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div id="mepr-price-box-configuration" data-value="<?php echo esc_attr($product->ID); ?>">
  <div id="preview-pane">
    <span id="pricing-preview-head" class="pricing-preview"><?php esc_html_e('Preview', 'memberpress'); ?></span>
    <?php MeprGroupsHelper::group_page_item($product, null, true); ?>
  </div>

  <div class="pricing-options-pane">
    <div>
      <input type="checkbox" name="<?php echo esc_attr(MeprProduct::$is_highlighted_str); ?>" id="<?php echo esc_attr(MeprProduct::$is_highlighted_str); ?>" <?php   checked($product->is_highlighted); ?> />
      <label for="<?php echo esc_attr(MeprProduct::$is_highlighted_str); ?>"><?php esc_html_e('Highlighted', 'memberpress'); ?></label>
      <?php
        MeprAppHelper::info_tooltip(
            'mepr-pricing-page-highlight',
            __('Highlight', 'memberpress'),
            __('<strong>Highlighted:</strong> Make this a Highlighted option on the Group Pricing Page. This makes it stand-out from the other listed memberships.', 'memberpress')
        );
        ?>
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Title:', 'memberpress'); ?></label><br/>
      <input type="text" name="<?php echo esc_attr(MeprProduct::$pricing_title_str); ?>" id="<?php echo esc_attr(MeprProduct::$pricing_title_str); ?>" value="<?php echo esc_attr($product->pricing_title); ?>">
    </div>
    <br/>
    <div>
      <label for="mepr-pricing-display"><?php esc_html_e('Pricing Display', 'memberpress'); ?></label>
      <?php
        MeprAppHelper::info_tooltip(
            'mepr-pricing-display',
            __('Pricing Display', 'memberpress'),
            __('This determines how the price will be displayed on the pricing table. If \'Auto\' is selected then MemberPress will automatically generate the price for you, if \'Custom\' is selected then you\'ll be able to enter your own custom pricing terms and if you select \'None\' then no price will be visible.', 'memberpress')
        );
        ?>
      <select id="mepr-pricing-display" name="<?php echo esc_attr(MeprProduct::$pricing_display_str); ?>">
        <option value="auto" <?php selected($product->pricing_display, 'auto'); ?>><?php esc_html_e('Auto', 'memberpress'); ?></option>
        <option value="custom" <?php selected($product->pricing_display, 'custom'); ?>><?php esc_html_e('Custom', 'memberpress'); ?></option>
        <option value="none" <?php selected($product->pricing_display, 'none'); ?>><?php esc_html_e('None', 'memberpress'); ?></option>
      </select>
    </div>
    <div id="mepr-custom-pricing-display">
      <br/>
      <div class="mepr-sub-box mepr_custom_pricing_display_box">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <div><?php esc_html_e('Custom Pricing', 'memberpress'); ?></div>
        <div><textarea name="<?php echo esc_attr(MeprProduct::$custom_price_str); ?>" id="mepr-custom-price" class="large-text"><?php echo esc_textarea($product->custom_price); ?></textarea></div>
      </div>
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Heading Text:', 'memberpress'); ?></label><br/>
      <textarea name="<?php echo esc_attr(MeprProduct::$pricing_heading_txt_str); ?>" id="<?php echo esc_attr(MeprProduct::$pricing_heading_txt_str); ?>"><?php echo esc_textarea($product->pricing_heading_txt); ?></textarea>
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Benefits:', 'memberpress'); ?></label>
      <ol id="sortable-benefits" class="mepr-sortable">
        <?php MeprProductsHelper::generate_pricing_benefits_list($product->pricing_benefits); ?>
      </ol>
      <?php MeprProductsHelper::show_pricing_benefits_add_new(); ?>
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Footer Text:', 'memberpress'); ?></label><br/>
      <textarea name="<?php echo esc_attr(MeprProduct::$pricing_footer_txt_str); ?>" id="<?php echo esc_attr(MeprProduct::$pricing_footer_txt_str); ?>"><?php echo esc_textarea($product->pricing_footer_txt); ?></textarea>
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Button Text:', 'memberpress'); ?></label><br/>
      <input type="text" name="<?php echo esc_attr(MeprProduct::$pricing_button_txt_str); ?>" id="<?php echo esc_attr(MeprProduct::$pricing_button_txt_str); ?>" value="<?php echo (!empty($product->pricing_button_txt)) ? esc_attr($product->pricing_button_txt) : esc_attr__('Sign Up', 'memberpress'); ?>" />
    </div>
    <br/>
    <div>
      <label for="mepr-pricing-button-position"><?php esc_html_e('Button Position', 'memberpress'); ?></label>
      <?php if (isset($mepr_options->design_enable_pricing_template) && $mepr_options->design_enable_pricing_template) { ?>
        <select disabled id="mepr-pricing-button-position" name="<?php echo esc_attr(MeprProduct::$pricing_button_position_str); ?>">
          <option value="header" <?php selected($product->pricing_button_position, 'header'); ?>><?php esc_html_e('Header', 'memberpress'); ?></option>
        </select>

      <?php } else { ?>
        <select id="mepr-pricing-button-position" name="<?php echo esc_attr(MeprProduct::$pricing_button_position_str); ?>">
          <option value="footer" <?php selected($product->pricing_button_position, 'footer'); ?>><?php esc_html_e('Footer', 'memberpress'); ?></option>
          <option value="header" <?php selected($product->pricing_button_position, 'header'); ?>><?php esc_html_e('Header', 'memberpress'); ?></option>
          <option value="both" <?php selected($product->pricing_button_position, 'both'); ?>><?php esc_html_e('Both', 'memberpress'); ?></option>
        </select>
      <?php } ?>
    </div>
  </div>
</div>

