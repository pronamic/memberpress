<div class="mepr_modal" aria-labelledby="mepr-pricing-modal" id="mepr-pricing-modal"
    role="dialog" aria-modal="true" style="display: none;">
    <div class="mepr_modal__overlay"></div>
    <div class="mepr_modal__content_wrapper">
    <div class="mepr_modal__content">
      <div class="mepr_modal__box">
      <button type="button" class="mepr_modal__close">&#x2715;</button>
      <div>
        <h3>
        <?php esc_html_e('Pricing Settings', 'memberpress'); ?>
        </h3>

        <?php if ($pricing_columns_limit) { ?>
          <p class="notice  notice-warning is-dismissible" style="padding: 10px;">
            <b>Please note, </b> only the first 5 listed memberships will be included in the pricing page.
        </p>
        <?php } ?>

        <table class="mepr-modal-options-pane" style="width: 100%;">
        <tbody>
        <tr valign="top">
          <td colspan="2">
          <label class="mepr-modal-options-pane-label" for="<?php echo esc_attr($mepr_options->design_pricing_title_str); ?>">
            <span>
            <?php esc_html_e('Page Title', 'memberpress'); ?>
            </span>
          </label>
          <input id="<?php echo esc_attr($mepr_options->design_pricing_title_str); ?>" name="<?php echo esc_attr($mepr_options->design_pricing_title_str); ?>" class="" type="text"
            placeholder="<?php esc_attr_e('Page Title', 'memberpress'); ?>"
            value="<?php echo esc_attr($mepr_options->design_pricing_title); ?>" />
          </td>
        </tr>

        <tr valign="top">
          <td colspan="2">
          <label class="mepr-modal-options-pane-label" for="<?php echo esc_attr($mepr_options->design_pricing_cta_color_str); ?>">
            <span>
            <?php esc_html_e('CTA Button Color', 'memberpress'); ?>
            </span>
          </label>
          <input type="text" name="<?php echo esc_attr($mepr_options->design_pricing_cta_color_str); ?>"
            value="<?php echo esc_attr($mepr_options->design_pricing_cta_color); ?>" class="color-field"
            data-default-color="#06429E" />
          </td>
        </tr>

        <tr valign="top">
          <td colspan="2">
          <label class="mepr-modal-options-pane-label" for="<?php echo esc_attr($mepr_options->design_pricing_subheadline_str); ?>">
            <span>
            <?php esc_html_e('Subheadline', 'memberpress'); ?>
            </span>
          </label>
          <?php
            $editor_config = [
                'teeny'         => true,
                'quicktags'     => false,
                'textarea_rows' => 20,
            ];
            wp_editor($mepr_options->design_pricing_subheadline, $mepr_options->design_pricing_subheadline_str, $editor_config);
            ?>
          </td>
        </tr>

        </tbody>
        </table>

      </div>
      <button class="mepr_modal__button button button-primary"><?php echo esc_html_x('Update', 'ui', 'memberpress'); ?></button>
      </div>
    </div>
    </div>
  </div>
