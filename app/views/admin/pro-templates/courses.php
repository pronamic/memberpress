<?php

use memberpress\courses\helpers as helpers; ?>
<div x-show="courses.openModal" class="mepr_modal" aria-labelledby="mepr-courses-modal" id="mepr-courses-modal" role="dialog" aria-modal="true" x-cloak>
  <div class="mepr_modal__overlay"></div>
  <div class="mepr_modal__content_wrapper">
    <div class="mepr_modal__content">
      <div class="mepr_modal__box" @click.away="closeModal($event, courses)">
        <button x-on:click="courses.openModal=false" type="button" class="mepr_modal__close">&#x2715;</button>
        <div>
          <h3>
            <?php esc_html_e('Courses Settings', 'memberpress'); ?>
          </h3>

          <table class="mepr-modal-options-pane" style="width: 100%;">
            <tbody>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label">
                    <span class="mepr-modal-options-pane-label"><?php esc_html_e('Brand Color', 'memberpress'); ?></span>
                    <?php
                    MeprAppHelper::info_tooltip(
                      'mpcs-complete-link-css',
                      esc_html__('Brand Color', 'memberpress-courses', 'memberpress'),
                      esc_html__('Use this field to change Navbar background color', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>

                  <input name="mpcs-options[brand-color]" class="mpcs-color-field" type="text" value="<?php echo helpers\Options::val($courses_options, 'brand-color', '#2c3637'); ?>" data-default-color="#2c3637" />
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label">
                    <span class="mepr-modal-options-pane-label"><?php esc_html_e('Accent Color', 'memberpress'); ?></span>
                    <?php
                    MeprAppHelper::info_tooltip(
                      'mpcs-complete-link-css',
                      __('Accent Color', 'memberpress-courses', 'memberpress'),
                      __('Use this field to change accent color', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>

                  <input name="mpcs-options[accent-color]" class="mpcs-color-field" type="text" value="<?php echo helpers\Options::val($courses_options, 'accent-color', '#2c3637'); ?>" data-default-color="#2c3637" />
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[progress-color]">
                    <span>
                      <?php _e('Progress Color', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-progress-color',
                      __('Progress Color', 'memberpress-courses', 'memberpress'),
                      __('Use this field to change progress color.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>
                  <input name="mpcs-options[progress-color]" class="mpcs-color-field" type="text" value="<?php echo helpers\Options::val($courses_options, 'progress-color', '#1da69a'); ?>" data-default-color="#1da69a" />
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[menu-text-color]">
                    <span>
                      <?php _e('Menu Text Color', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-menu-text-color',
                      __('Menu Text Color', 'memberpress-courses', 'memberpress'),
                      __('Use this field to change text color of menu items.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>
                  <input name="mpcs-options[menu-text-color]" class="mpcs-color-field" type="text" value="<?php echo helpers\Options::val($courses_options, 'menu-text-color', '#ffffff'); ?>" data-default-color="#ffffff" />
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[classroom-logo]">
                    <span>
                      <?php _e('Logo', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-complete-link-css',
                      __('Logo', 'memberpress-courses', 'memberpress'),
                      __('Use this field to add custom logo to your classroom header.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>



                  <div class="mepr-pluploader-wrapper" id="mepr-design-courses-logo">

                    <!-- File Preview -->
                    <div x-show="courses.logoId" class="mepr-pluploader-preview">
                      <div>
                        <img src="<?php echo wp_get_attachment_url(helpers\Options::val($courses_options, 'classroom-logo')) ?>" alt="" class="src">
                      </div>
                      <div class="actions">
                        <div>
                          <button x-on:click="courses.logoId = null" class="link" type="button">
                            <svg xmlns="http://www.w3.org/2000/svg" class="" style="width: 1rem; margin-right: 3px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span><?php esc_html_e('Remove', 'memberpress'); ?></span>
                          </button>
                        </div>
                      </div>
                    </div>



                    <!-- Input File -->
                    <input type="hidden" name="mpcs-options[classroom-logo]" id="mepr-design-courses-logo" x-model="courses.logoId">


                    <!-- uploader -->
                    <div x-show="!courses.logoId" class="upload-ui hide-if-no-js">
                      <div class="drag-drop-area">
                        <div class="drag-drop-inside">
                          <p class="drag-drop-info"><?php _e('Upload Welcome Image', 'memberpress-courses', 'memberpress'); ?></p>

                          <!-- Progress Indicator -->
                          <p class="drag-drop-loader">
                            <img src="<?php echo MEPR_IMAGES_URL . '/square-loader.gif'; ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" class="mepr_loader" />
                          </p>

                          <div class="drag-drop-buttons">
                            <p>
                              <?php echo esc_html_x('or', 'Uploader: Upload Welcome Image - or - Select Image', 'memberpress-courses', 'memberpress'); ?>
                            </p>
                            <p>
                              <input type="button" value="<?php esc_attr_e('Select Image', 'memberpress-courses', 'memberpress'); ?>" class="button browse-button" />
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>

                  </div>

                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[menu-text-color]">
                    <span>
                      <?php _e('Lesson Button Location', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-lesson-button-location',
                      __('Lesson Button Location', 'memberpress-courses', 'memberpress'),
                      __('Choose where to position the continue/back buttons when viewing a lesson.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>

                  <select id="mpcs-options[lesson-button-location]" name="mpcs-options[lesson-button-location]">
                    <option value="top" <?php selected(helpers\Options::val($courses_options, 'lesson-button-location'), 'top'); ?>>
                      <?php esc_html_e('Top', 'memberpress-courses', 'memberpress'); ?></option>
                    <option value="bottom" <?php selected(helpers\Options::val($courses_options, 'lesson-button-location'), 'bottom'); ?>>
                      <?php esc_html_e('Bottom', 'memberpress-courses', 'memberpress'); ?></option>
                    <option value="both" <?php selected(helpers\Options::val($courses_options, 'lesson-button-location'), 'both'); ?>>
                      <?php esc_html_e('Both', 'memberpress-courses', 'memberpress'); ?></option>
                  </select>

                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[complete-link-css]">
                    <span>
                      <?php _e('Complete Link CSS', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-complete-link-css',
                      __('Complete Link CSS Classes', 'memberpress-courses', 'memberpress'),
                      __('Use this field to add custom CSS classes to the "Complete Lesson/Section/Course" link in each of your Lessons.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>

                  <input type="text" name="mpcs-options[complete-link-css]" class="regular-text" value="<?php echo helpers\Options::val($courses_options, 'complete-link-css'); ?>" />
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[previous-link-css]">
                    <span>
                      <?php _e('Previous Link CSS', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-previous-link-css',
                      __('Previous Lesson/Section Link CSS Classes', 'memberpress-courses', 'memberpress'),
                      __('Use this field to add custom CSS classes to the "Previous Lesson/Section" link in each of your Lessons.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>
                  <input type="text" name="mpcs-options[previous-link-css]" class="regular-text" value="<?php echo helpers\Options::val($courses_options, 'previous-link-css'); ?>" />
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[breadcrumb-link-css]">
                    <span>
                      <?php _e('Breadcrumb Link CSS', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-breadcrumb-link-css',
                      __('Breadcrumb Link CSS Classes', 'memberpress-courses', 'memberpress'),
                      __('Use this field to add custom CSS classes to the breadcrumb links in each of your Lessons.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>
                  <input type="text" name="mpcs-options[breadcrumb-link-css]" class="regular-text" value="<?php echo helpers\Options::val($courses_options, 'breadcrumb-link-css'); ?>" />
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <label class="mepr-modal-options-pane-label" for="mpcs-options[classroom-wp-footer]">
                    <span>
                      <?php _e('WP Footer Hook', 'memberpress-courses', 'memberpress'); ?>
                    </span>
                    <?php MeprAppHelper::info_tooltip(
                      'mpcs-complete-link-css',
                      __('WP Footer Hook', 'memberpress-courses', 'memberpress'),
                      __('Use this field to enable/disable the WP Footer hook. In classroom mode, \'wp_footer\' hook is disabled by default.', 'memberpress-courses', 'memberpress')
                    );
                    ?>
                  </label>
                  <select id="mpcs-options[classroom-wp-footer]" name="mpcs-options[classroom-wp-footer]">
                    <option value="disabled" <?php selected(helpers\Options::val($courses_options, 'classroom-wp-footer'), 'disabled'); ?>>
                      <?php esc_html_e('Disabled', 'memberpress-courses', 'memberpress'); ?></option>
                    <option value="enabled" <?php selected(helpers\Options::val($courses_options, 'classroom-wp-footer'), 'enabled'); ?>>
                      <?php esc_html_e('Enabled', 'memberpress-courses', 'memberpress'); ?></option>
                  </select>
                </td>
              </tr>

              <?php do_action('mpc_admin_general_options'); ?>

            </tbody>
          </table>

        </div>
        <button class="mepr_modal__button button button-primary"><?php echo esc_html_x( 'Update', 'ui', 'memberpress' ); ?></button>
      </div>
    </div>
  </div>
</div>