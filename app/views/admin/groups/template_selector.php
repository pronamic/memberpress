<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php
$is_new_group     = empty($group->ID) || get_post_status($group->ID) === 'auto-draft';
$current_template = !empty($group->modern_template) ? $group->modern_template : ($is_new_group ? 'bold' : '');
$is_legacy_group  = !$is_new_group && empty($group->modern_template);
$template_type    = $is_legacy_group ? 'legacy' : 'modern';
$template_options = is_array($group->modern_template_options) ? $group->modern_template_options : [];
$border_radius    = !empty($template_options['border_radius']) ? $template_options['border_radius'] : 'rounded';
$shadow           = !empty($template_options['shadow']) ? $template_options['shadow'] : 'subtle';

$preview_style = MeprGroupsHelper::build_modern_style_string($group);

$preview_template_class = !empty($current_template)
    ? 'mepr-tpl-' . esc_attr($current_template)
    : 'mepr-tpl-modern-cards';
?>

<div id="mepr-template-section">
  <h4><strong><?php esc_html_e('Pricing Table Style:', 'memberpress'); ?></strong></h4>

  <div id="mepr-template-type-toggle" class="mepr-template-toggle">
    <label class="mepr-toggle-label <?php echo ($template_type === 'modern') ? 'active' : ''; ?>">
      <input type="radio" name="_mepr_group_template_type" value="modern" <?php checked($template_type, 'modern'); ?> />
      <?php esc_html_e('Modern Templates', 'memberpress'); ?>
    </label>
    <label class="mepr-toggle-label <?php echo ($template_type === 'legacy') ? 'active' : ''; ?>">
      <input type="radio" name="_mepr_group_template_type" value="legacy" <?php checked($template_type, 'legacy'); ?> />
      <?php esc_html_e('Legacy Templates', 'memberpress'); ?>
    </label>
  </div>

  <!-- Modern Templates -->
  <div id="mepr-modern-templates-section" <?php echo ($template_type !== 'modern') ? 'style="display:none;"' : ''; ?>>
    <div class="mepr-template-grid">
      <?php
        $templates = MeprGroup::modern_templates();
        foreach ($templates as $slug => $label) :
            $is_selected = ($current_template === $slug);
            ?>
        <div class="mepr-template-card <?php echo $is_selected ? 'selected' : ''; ?>" data-template="<?php echo esc_attr($slug); ?>">
          <div class="mepr-template-preview mepr-preview-<?php echo esc_attr($slug); ?>">
            <div class="mepr-tp-box"></div>
            <div class="mepr-tp-box mepr-tp-highlighted"></div>
            <div class="mepr-tp-box"></div>
          </div>
          <span class="mepr-template-label"><?php echo esc_html($label); ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <input type="hidden" name="<?php echo esc_attr(MeprGroup::$modern_template_str); ?>" id="mepr-modern-template-value" value="<?php echo esc_attr($current_template); ?>" />

    <!-- Live Preview -->
    <div id="mepr-live-preview" <?php echo empty($current_template) ? 'style="display:none;"' : ''; ?>>
      <h4><strong><?php esc_html_e('Preview:', 'memberpress'); ?></strong></h4>
      <div id="mepr-live-preview-container">
        <div id="mepr-live-preview-inner" class="mepr-price-menu mepr-modern <?php echo esc_attr($preview_template_class); ?> mepr-no-carousel" style="<?php echo esc_attr($preview_style); ?>">
          <div class="mepr-price-boxes">

            <div class="mepr-price-box">
              <div class="mepr-price-box-head">
                <div class="mepr-price-box-title"><?php esc_html_e('Basic', 'memberpress'); ?></div>
                <div class="mepr-price-box-price">
                  <span class="mepr-price-box-price-currency">$</span>9<span class="mepr-price-box-price-term">/ <?php esc_html_e('month', 'memberpress'); ?></span>
                </div>
                <div class="mepr-price-box-heading"><?php esc_html_e('For individuals', 'memberpress'); ?></div>
              </div>
              <div class="mepr-price-box-benefits">
                <div class="mepr-price-box-benefits-list">
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('5 Projects', 'memberpress'); ?></div>
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('Basic support', 'memberpress'); ?></div>
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('1 GB storage', 'memberpress'); ?></div>
                </div>
              </div>
              <div class="mepr-price-box-foot">
                <div class="mepr-price-box-button">
                  <a href="#"><?php esc_html_e('Sign Up', 'memberpress'); ?></a>
                </div>
              </div>
            </div>

            <div class="mepr-price-box highlighted">
              <div class="mepr-most-popular" id="mepr-preview-badge"><?php esc_html_e('Most Popular', 'memberpress'); ?></div>
              <div class="mepr-price-box-head">
                <div class="mepr-price-box-title"><?php esc_html_e('Pro', 'memberpress'); ?></div>
                <div class="mepr-price-box-price">
                  <span class="mepr-price-box-price-currency">$</span>29<span class="mepr-price-box-price-term">/ <?php esc_html_e('month', 'memberpress'); ?></span>
                </div>
                <div class="mepr-price-box-heading"><?php esc_html_e('For growing teams', 'memberpress'); ?></div>
              </div>
              <div class="mepr-price-box-benefits">
                <div class="mepr-price-box-benefits-list">
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('Unlimited projects', 'memberpress'); ?></div>
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('Priority support', 'memberpress'); ?></div>
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('50 GB storage', 'memberpress'); ?></div>
                </div>
              </div>
              <div class="mepr-price-box-foot">
                <div class="mepr-price-box-button">
                  <a href="#"><?php esc_html_e('Sign Up', 'memberpress'); ?></a>
                </div>
              </div>
            </div>

            <div class="mepr-price-box">
              <div class="mepr-price-box-head">
                <div class="mepr-price-box-title"><?php esc_html_e('Enterprise', 'memberpress'); ?></div>
                <div class="mepr-price-box-price">
                  <span class="mepr-price-box-price-currency">$</span>99<span class="mepr-price-box-price-term">/ <?php esc_html_e('month', 'memberpress'); ?></span>
                </div>
                <div class="mepr-price-box-heading"><?php esc_html_e('For large organizations', 'memberpress'); ?></div>
              </div>
              <div class="mepr-price-box-benefits">
                <div class="mepr-price-box-benefits-list">
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('Everything in Pro', 'memberpress'); ?></div>
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('Dedicated support', 'memberpress'); ?></div>
                  <div class="mepr-price-box-benefits-item"><?php esc_html_e('Unlimited storage', 'memberpress'); ?></div>
                </div>
              </div>
              <div class="mepr-price-box-foot">
                <div class="mepr-price-box-button">
                  <a href="#"><?php esc_html_e('Sign Up', 'memberpress'); ?></a>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- Customization Options -->
    <div id="mepr-template-options" <?php echo empty($current_template) ? 'style="display:none;"' : ''; ?>>
      <h4><strong><?php esc_html_e('Customize Template:', 'memberpress'); ?></strong></h4>

      <div class="mepr-color-grid">
        <div class="mepr-color-field">
          <label for="mepr-opt-text-color"><?php esc_html_e('Text', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-text-color" name="_mepr_group_modern_template_options[text_color]" value="<?php echo esc_attr(!empty($template_options['text_color']) ? $template_options['text_color'] : '#1e293b'); ?>" />
          <span class="description"><?php esc_html_e('Title, benefits, body', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-price-color"><?php esc_html_e('Price', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-price-color" name="_mepr_group_modern_template_options[price_color]" value="<?php echo esc_attr(!empty($template_options['price_color']) ? $template_options['price_color'] : '#0f172a'); ?>" />
          <span class="description"><?php esc_html_e('Price display', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-primary-color"><?php esc_html_e('Primary', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-primary-color" name="_mepr_group_modern_template_options[primary_color]" value="<?php echo esc_attr(!empty($template_options['primary_color']) ? $template_options['primary_color'] : '#2563eb'); ?>" />
          <span class="description"><?php esc_html_e('Highlights, borders', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-accent-color"><?php esc_html_e('Accent', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-accent-color" name="_mepr_group_modern_template_options[accent_color]" value="<?php echo esc_attr(!empty($template_options['accent_color']) ? $template_options['accent_color'] : '#16a34a'); ?>" />
          <span class="description"><?php esc_html_e('Badge, checkmarks', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-button-color"><?php esc_html_e('Button', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-button-color" name="_mepr_group_modern_template_options[button_color]" value="<?php echo esc_attr(!empty($template_options['button_color']) ? $template_options['button_color'] : '#2563eb'); ?>" />
          <span class="description"><?php esc_html_e('Button background', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-btn-text-color"><?php esc_html_e('Button Text', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-btn-text-color" name="_mepr_group_modern_template_options[btn_text_color]" value="<?php echo esc_attr(!empty($template_options['btn_text_color']) ? $template_options['btn_text_color'] : '#ffffff'); ?>" />
          <span class="description"><?php esc_html_e('Button label', 'memberpress'); ?></span>
        </div>
      </div>

      <h4 style="margin-bottom:0;"><strong><?php esc_html_e('Highlighted Card Colors:', 'memberpress'); ?></strong></h4>
      <div class="mepr-color-grid">
        <div class="mepr-color-field">
          <label for="mepr-opt-hl-text-color"><?php esc_html_e('Text', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-hl-text-color" name="_mepr_group_modern_template_options[hl_text_color]" value="<?php echo esc_attr(!empty($template_options['hl_text_color']) ? $template_options['hl_text_color'] : '#ffffff'); ?>" />
          <span class="description"><?php esc_html_e('Title, heading', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-hl-price-color"><?php esc_html_e('Price', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-hl-price-color" name="_mepr_group_modern_template_options[hl_price_color]" value="<?php echo esc_attr(!empty($template_options['hl_price_color']) ? $template_options['hl_price_color'] : '#ffffff'); ?>" />
          <span class="description"><?php esc_html_e('Price display', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-hl-btn-color"><?php esc_html_e('Button', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-hl-btn-color" name="_mepr_group_modern_template_options[hl_btn_color]" value="<?php echo esc_attr(!empty($template_options['hl_btn_color']) ? $template_options['hl_btn_color'] : '#16a34a'); ?>" />
          <span class="description"><?php esc_html_e('Button background', 'memberpress'); ?></span>
        </div>
        <div class="mepr-color-field">
          <label for="mepr-opt-hl-btn-text-color"><?php esc_html_e('Button Text', 'memberpress'); ?></label>
          <input type="color" id="mepr-opt-hl-btn-text-color" name="_mepr_group_modern_template_options[hl_btn_text_color]" value="<?php echo esc_attr(!empty($template_options['hl_btn_text_color']) ? $template_options['hl_btn_text_color'] : '#ffffff'); ?>" />
          <span class="description"><?php esc_html_e('Button label', 'memberpress'); ?></span>
        </div>
      </div>

      <table class="form-table mepr-template-options-table">
        <tbody>
          <tr>
            <th scope="row"><label><?php esc_html_e('Border Radius', 'memberpress'); ?></label></th>
            <td>
              <select id="mepr-opt-border-radius" name="_mepr_group_modern_template_options[border_radius]">
                <option value="none" <?php selected($border_radius, 'none'); ?>><?php esc_html_e('None', 'memberpress'); ?></option>
                <option value="subtle" <?php selected($border_radius, 'subtle'); ?>><?php esc_html_e('Subtle', 'memberpress'); ?></option>
                <option value="rounded" <?php selected($border_radius, 'rounded'); ?>><?php esc_html_e('Rounded', 'memberpress'); ?></option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label><?php esc_html_e('Card Shadow', 'memberpress'); ?></label></th>
            <td>
              <select id="mepr-opt-shadow" name="_mepr_group_modern_template_options[shadow]">
                <option value="none" <?php selected($shadow, 'none'); ?>><?php esc_html_e('None', 'memberpress'); ?></option>
                <option value="subtle" <?php selected($shadow, 'subtle'); ?>><?php esc_html_e('Subtle', 'memberpress'); ?></option>
                <option value="prominent" <?php selected($shadow, 'prominent'); ?>><?php esc_html_e('Prominent', 'memberpress'); ?></option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label><?php esc_html_e('Carousel', 'memberpress'); ?></label></th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(MeprGroup::$carousel_enabled_str); ?>" id="mepr-carousel-enabled" <?php checked($group->carousel_enabled); ?> />
                <?php esc_html_e('Enable carousel for responsive sliding (when disabled, boxes wrap to next line)', 'memberpress'); ?>
              </label>
            </td>
          </tr>
          <tr id="mepr-arrows-outside-row" <?php echo !$group->carousel_enabled ? 'style="display:none;"' : ''; ?>>
            <th scope="row"><label><?php esc_html_e('Arrow Position', 'memberpress'); ?></label></th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(MeprGroup::$carousel_arrows_outside_str); ?>" <?php checked($group->carousel_arrows_outside); ?> />
                <?php esc_html_e('Place arrows outside the cards (cards will be slightly narrower)', 'memberpress'); ?>
              </label>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Legacy Themes (only shown for existing groups that were using legacy themes) -->
  <div id="mepr-legacy-themes-section" <?php echo ($template_type !== 'legacy') ? 'style="display:none;"' : ''; ?>>
