<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php
// Determine if this product's group uses a modern template for preview styling.
$preview_group        = null;
$preview_modern_class = '';
$preview_modern_style = '';

if (!empty($product->group_id)) {
    $preview_group = new MeprGroup($product->group_id);

    if (!empty($preview_group->modern_template) && !MeprReadyLaunchCtrl::template_active('pricing')) {
        $preview_modern_class = 'mepr-modern mepr-tpl-'
            . esc_attr($preview_group->modern_template)
            . ' mepr-no-carousel';
        $preview_modern_style = MeprGroupsHelper::build_modern_style_string($preview_group);
    }
}
?>
<div id="mepr-price-box-configuration" data-value="<?php echo esc_attr($product->ID); ?>">
  <div id="preview-pane">
    <span id="pricing-preview-head" class="pricing-preview"><?php esc_html_e('Preview', 'memberpress'); ?></span>
    <div class="mepr-price-menu <?php echo esc_attr($preview_modern_class); ?>"
         style="<?php echo esc_attr($preview_modern_style); ?>">
      <?php MeprGroupsHelper::group_page_item($product, $preview_group, true); ?>
    </div>
  </div>

  <div class="pricing-options-pane">
    <div>
      <input type="checkbox"
             name="<?php echo esc_attr(MeprProduct::$is_highlighted_str); ?>"
             id="<?php echo esc_attr(MeprProduct::$is_highlighted_str); ?>"
             <?php checked($product->is_highlighted); ?> />
      <label for="<?php echo esc_attr(MeprProduct::$is_highlighted_str); ?>">
        <?php esc_html_e('Highlighted', 'memberpress'); ?>
      </label>
      <?php
        MeprAppHelper::info_tooltip(
            'mepr-pricing-page-highlight',
            __('Highlight', 'memberpress'),
            __(
                '<strong>Highlighted:</strong> Make this a Highlighted option on the Group Pricing Page. This makes it stand-out from the other listed memberships.', // phpcs:ignore Generic.Files.LineLength.TooLong
                'memberpress'
            )
        );
        ?>
    </div>
    <div id="mepr-badge-text-wrap" <?php echo !$product->is_highlighted ? 'style="display:none;"' : ''; ?>>
      <label><?php esc_html_e('Badge Text:', 'memberpress'); ?></label><br/>
      <input type="text"
             name="<?php echo esc_attr(MeprProduct::$pricing_badge_txt_str); ?>"
             id="<?php echo esc_attr(MeprProduct::$pricing_badge_txt_str); ?>"
             value="<?php echo esc_attr(!empty($product->pricing_badge_txt) ? $product->pricing_badge_txt : ''); ?>"
             placeholder="<?php esc_attr_e('Most Popular', 'memberpress'); ?>" />
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Title:', 'memberpress'); ?></label><br/>
      <input type="text"
             name="<?php echo esc_attr(MeprProduct::$pricing_title_str); ?>"
             id="<?php echo esc_attr(MeprProduct::$pricing_title_str); ?>"
             value="<?php echo esc_attr($product->pricing_title); ?>">
    </div>
    <br/>
    <div>
      <label for="mepr-pricing-display"><?php esc_html_e('Pricing Display', 'memberpress'); ?></label>
      <?php
        MeprAppHelper::info_tooltip(
            'mepr-pricing-display',
            __('Pricing Display', 'memberpress'),
            __(
                'This determines how the price will be displayed on the pricing table. If \'Auto\' is selected then MemberPress will automatically generate the price for you, if \'Custom\' is selected then you\'ll be able to enter your own custom pricing terms and if you select \'None\' then no price will be visible.', // phpcs:ignore Generic.Files.LineLength.TooLong
                'memberpress'
            )
        );
        ?>
      <select id="mepr-pricing-display" name="<?php echo esc_attr(MeprProduct::$pricing_display_str); ?>">
        <option value="auto" <?php selected($product->pricing_display, 'auto'); ?>>
          <?php esc_html_e('Auto', 'memberpress'); ?>
        </option>
        <option value="custom" <?php selected($product->pricing_display, 'custom'); ?>>
          <?php esc_html_e('Custom', 'memberpress'); ?>
        </option>
        <option value="none" <?php selected($product->pricing_display, 'none'); ?>>
          <?php esc_html_e('None', 'memberpress'); ?>
        </option>
      </select>
    </div>
    <div id="mepr-custom-pricing-display">
      <br/>
      <div class="mepr-sub-box mepr_custom_pricing_display_box">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <div>
          <label><?php esc_html_e('Price:', 'memberpress'); ?></label>
          <input type="text"
                 name="<?php echo esc_attr(MeprProduct::$custom_price_str); ?>"
                 id="mepr-custom-price"
                 value="<?php echo esc_attr($product->custom_price); ?>"
                 placeholder="<?php esc_attr_e('e.g. $49.99', 'memberpress'); ?>"
                 class="mepr-custom-price-input" />
        </div>
        <div class="mepr-custom-price-term-wrap">
          <label><?php esc_html_e('Term:', 'memberpress'); ?></label>
          <input type="text"
                 name="<?php echo esc_attr(MeprProduct::$custom_price_term_str); ?>"
                 id="mepr-custom-price-term"
                 value="<?php echo esc_attr($product->custom_price_term); ?>"
                 placeholder="<?php esc_attr_e('e.g. / month', 'memberpress'); ?>"
                 class="mepr-custom-price-input" />
        </div>
      </div>
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Heading Text:', 'memberpress'); ?></label><br/>
      <textarea
        name="<?php echo esc_attr(MeprProduct::$pricing_heading_txt_str); ?>"
        id="<?php echo esc_attr(MeprProduct::$pricing_heading_txt_str); ?>"
      ><?php echo esc_textarea($product->pricing_heading_txt); ?></textarea>
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
      <textarea
        name="<?php echo esc_attr(MeprProduct::$pricing_footer_txt_str); ?>"
        id="<?php echo esc_attr(MeprProduct::$pricing_footer_txt_str); ?>"
      ><?php echo esc_textarea($product->pricing_footer_txt); ?></textarea>
    </div>
    <br/>
    <div>
      <label><?php esc_html_e('Button Text:', 'memberpress'); ?></label><br/>
      <input type="text"
             name="<?php echo esc_attr(MeprProduct::$pricing_button_txt_str); ?>"
             id="<?php echo esc_attr(MeprProduct::$pricing_button_txt_str); ?>"
             value="<?php
                echo (!empty($product->pricing_button_txt))
                    ? esc_attr($product->pricing_button_txt)
                    : esc_attr__('Sign Up', 'memberpress');
                ?>" />
    </div>
    <br/>
    <div>
      <label for="mepr-pricing-button-position"><?php esc_html_e('Button Position', 'memberpress'); ?></label>
      <?php
        if (
            isset($mepr_options->design_enable_pricing_template)
            && $mepr_options->design_enable_pricing_template
        ) {
            ?>
        <select disabled
                id="mepr-pricing-button-position"
                name="<?php echo esc_attr(MeprProduct::$pricing_button_position_str); ?>">
          <option value="header" <?php selected($product->pricing_button_position, 'header'); ?>>
            <?php esc_html_e('Header', 'memberpress'); ?>
          </option>
        </select>

        <?php } else { ?>
        <select id="mepr-pricing-button-position"
                name="<?php echo esc_attr(MeprProduct::$pricing_button_position_str); ?>">
          <option value="footer" <?php selected($product->pricing_button_position, 'footer'); ?>>
            <?php esc_html_e('Footer', 'memberpress'); ?>
          </option>
          <option value="header" <?php selected($product->pricing_button_position, 'header'); ?>>
            <?php esc_html_e('Header', 'memberpress'); ?>
          </option>
          <option value="both" <?php selected($product->pricing_button_position, 'both'); ?>>
            <?php esc_html_e('Both', 'memberpress'); ?>
          </option>
        </select>
        <?php } ?>
    </div>
  </div>
</div>

