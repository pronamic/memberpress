<div x-show="checkout.openModal" class="mepr_modal" aria-labelledby="mepr-checkout-modal" id="mepr-checkout-modal" role="dialog" aria-modal="true" x-cloak>
  <div class="mepr_modal__overlay"></div>
  <div class="mepr_modal__content_wrapper">
    <div class="mepr_modal__content">
      <div class="mepr_modal__box" @click.away="checkout.openModal = false">
        <button x-on:click="checkout.openModal=false" type="button" class="mepr_modal__close">&#x2715;</button>
        <div>
          <h3>
            <?php esc_html_e('Registration Page Settings', 'memberpress'); ?>
          </h3>
          <table class="mepr-modal-options-pane" style="width: 100%;">
            <tbody>
              <tr>
                <td>
                  <label class="switch">
                    <input x-model="checkout.showPriceTerms" id="<?php echo esc_attr($mepr_options->design_show_checkout_price_terms_str); ?>" name="<?php echo esc_attr($mepr_options->design_show_checkout_price_terms_str); ?>" class="mepr-template-enablers" type="checkbox" checked="<?php checked(1, (!isset($mepr_options->design_show_checkout_price_terms) || (isset($mepr_options->design_show_checkout_price_terms) && $mepr_options->design_show_checkout_price_terms) )) ?>">
                    <span class="slider round"></span>
                  </label>
                </td>
                <td>
                  <?php esc_html_e('Show Price Terms', 'memberpress'); ?>
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
