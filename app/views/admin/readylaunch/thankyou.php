<div class="mepr_modal" aria-labelledby="mepr-thankyou-modal" id="mepr-thankyou-modal" role="dialog" aria-modal="true" style="display: none;">
  <div class="mepr_modal__overlay"></div>
  <div class="mepr_modal__content_wrapper">
    <div class="mepr_modal__content">
      <div class="mepr_modal__box">
        <button type="button" class="mepr_modal__close">&#x2715;</button>
        <div>
          <h3>
            <?php esc_html_e('Thank You Settings', 'memberpress'); ?>
          </h3>

          <table class="mepr-modal-options-pane" style="width: 100%;">
            <tbody>
              <tr>
                <td>
                  <label class="switch">
                    <input id="<?php echo esc_attr($mepr_options->design_show_thankyou_welcome_image_str); ?>" name="<?php echo esc_attr($mepr_options->design_show_thankyou_welcome_image_str); ?>" class="mepr-template-enablers" type="checkbox" value="1" <?php checked($mepr_options->design_show_thankyou_welcome_image, true); ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td>
                  <?php esc_html_e('Show Welcome Image', 'memberpress'); ?>
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  <div class="mepr-pluploader-wrapper mepr-conditional-section" id="mepr-design-thankyou-welcome-img" data-controlled-by="<?php echo esc_attr($mepr_options->design_show_thankyou_welcome_image_str); ?>" <?php echo !$mepr_options->design_show_thankyou_welcome_image ? 'style="display: none;"' : ''; ?>>

                    <!-- File Preview -->
                    <div class="mepr-pluploader-preview">
                      <div>
                        <img src="<?php echo esc_url(wp_get_attachment_url($mepr_options->design_thankyou_welcome_img)); ?>" alt="" class="src">
                      </div>
                      <div class="actions">
                        <div>
                          <button class="link" type="button">
                            <svg xmlns="http://www.w3.org/2000/svg" class="" style="width: 1rem; margin-right: 3px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span><?php esc_html_e('Remove', 'memberpress'); ?></span>
                          </button>
                          <!-- </a> -->
                        </div>
                      </div>
                    </div>

                    <!-- Input File -->
                    <input type="hidden" name="<?php echo esc_attr($mepr_options->design_thankyou_welcome_img_str); ?>" id="<?php echo esc_attr($mepr_options->design_thankyou_welcome_img_str); ?>" value="<?php echo esc_attr($mepr_options->design_thankyou_welcome_img); ?>">


                    <!-- uploader -->
                    <div class="upload-ui hide-if-no-js">
                      <div class="drag-drop-area">
                        <div class="drag-drop-inside">
                          <p class="drag-drop-info"><?php esc_html_e('Upload Welcome Image', 'memberpress'); ?></p>

                          <!-- Progress Indicator -->
                          <p class="drag-drop-loader">
                            <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/square-loader.gif'); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" class="mepr_loader" />
                          </p>

                          <div class="drag-drop-buttons">
                            <p>
                              <?php echo esc_html_x('or', 'Uploader: Upload Welcome Image - or - Select Image', 'memberpress'); ?>
                            </p>
                            <p>
                              <input type="button" value="<?php esc_attr_e('Select Image', 'memberpress'); ?>" class="button browse-button" />
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                </td>
              </tr>
              <tr>
                <td>
                  <label class="switch">
                    <input id="<?php echo esc_attr($mepr_options->design_thankyou_hide_invoice_str); ?>" name="<?php echo esc_attr($mepr_options->design_thankyou_hide_invoice_str); ?>" class="mepr-template-enablers" type="checkbox" value="1" <?php checked($mepr_options->design_thankyou_hide_invoice, true); ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td>
                  <?php esc_html_e('Custom Message', 'memberpress'); ?>
                </td>
              </tr>
              <tr class="mepr-conditional-section" data-controlled-by="<?php echo esc_attr($mepr_options->design_thankyou_hide_invoice_str); ?>" <?php echo !$mepr_options->design_thankyou_hide_invoice ? 'style="display: none;"' : ''; ?>>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="<?php echo esc_attr($mepr_options->design_thankyou_invoice_message_str); ?>">
                    <span>
                      <?php esc_html_e('Thank You Message', 'memberpress'); ?>
                    </span>
                    <?php
                    MeprAppHelper::info_tooltip(
                        'pricing-text-title',
                        __('Thank You Message', 'memberpress'),
                        __('Add custom message that will be displayed after a successful purchase.', 'memberpress')
                    );
                    ?>
                  </label>
                  <?php
                    $editor_config = [
                        'teeny'         => true,
                        'quicktags'     => false,
                        'textarea_rows' => 20,
                    ];
                    wp_editor($mepr_options->design_thankyou_invoice_message, $mepr_options->design_thankyou_invoice_message_str, $editor_config);
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