<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="mepr-wizard-create-select-membership">
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Time to make your first membership', 'memberpress'); ?></h2>
  <p class="mepr-wizard-step-description"><?php esc_html_e("Now that you've got some content to protect, you'll want to show people how they can access it. That's what a \"membership\" is.", 'memberpress'); ?></p>
  <p class="mepr-wizard-step-description"><?php esc_html_e("MemberPress lets you create an unlimited number of memberships and name them any way you like (for example, Bronze, Silver, and Gold). Here, we'll set up your very FIRST membership.", 'memberpress'); ?></p>

  <div class="mepr-wizard-button-group">
      <button type="button" id="mepr-wizard-create-new-membership" class="mepr-wizard-button-blue"><?php esc_html_e('Create Membership', 'memberpress'); ?></button>
  </div>
</div>

<div id="mepr-wizard-selected-membership" class="mepr-hidden">
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Your membership', 'memberpress'); ?></h2>
  <div class="mepr-wizard-selected-content mepr-wizard-selected-content-full-scape">
    <div class="mepr-wizard-selected-content-column">
      <div class="mepr-wizard-selected-content-heading"><?php esc_html_e('Membership','memberpress'); ?></div>
      <div class="mepr-wizard-selected-content-name" id="mepr-selected-membership-name"></div>
    </div>
    <hr>
    <div class="mepr-wizard-selected-content-column">
      <div class="mepr-wizard-selected-content-heading"><?php esc_html_e('Billing','memberpress'); ?></div>
      <div class="mepr-wizard-selected-content-name"  id="mepr-selected-membership-billing"></div>
    </div>
    <hr>
    <div class="mepr-wizard-selected-content-column">
      <div class="mepr-wizard-selected-content-heading"><?php esc_html_e('Price','memberpress'); ?></div>
      <div class="mepr-wizard-selected-content-name"  id="mepr-selected-membership-price"></div>
    </div>
      <div class="mepr-wizard-selected-content-expand-menu" data-id="mepr-wizard-selected-membership-menu">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/expand-menu.svg'); ?>" alt="">
      </div>
      <div id="mepr-wizard-selected-membership-menu" class="mepr-wizard-selected-content-menu mepr-hidden">
        <div id="mepr-wizard-selected-membership-delete"><?php esc_html_e('Remove', 'memberpress'); ?></div>
      </div>
  </div>
</div>

<div id="mepr-wizard-create-new-membership-popup" class="mepr-wizard-popup mepr-wizard-popup-create-membership mfp-hide">
  <form id="mepr-wizard-create-new-membership-form">
    <h2><?php esc_html_e('Create Membership', 'memberpress'); ?></h2>

    <div class="mepr-wizard-popup-field">
      <label for="mepr-wizard-create-membership-name"><?php esc_html_e('Membership Name', 'memberpress'); ?></label>
      <input type="text" id="mepr-wizard-create-membership-name">
    </div>


    <div class="mepr-wizard-popup-field">
      <label for="mepr_wizard_create_membership_type"><?php esc_html_e('Billing', 'memberpress'); ?></label>
       <div class="mepr-wizard-create-content-type">
          <div>
            <input type="radio" id="mepr_wizard_create_membership_type-onetime" name="mepr_wizard_create_membership_type" value="onetime" checked>
            <label for="mepr_wizard_create_membership_type-onetime">
              <span><?php esc_html_e('One-time', 'memberpress'); ?></span>
            </label>
          </div>
          <div>
            <input type="radio" id="mepr_wizard_create_membership_type-months" name="mepr_wizard_create_membership_type" value="months">
            <label for="mepr_wizard_create_membership_type-months">
              <span><?php esc_html_e('Recurring (Monthly)', 'memberpress'); ?></span>
            </label>
          </div>
          <div>
            <input type="radio" id="mepr_wizard_create_membership_type-years" name="mepr_wizard_create_membership_type" value="years">
            <label for="mepr_wizard_create_membership_type-years">
              <span><?php esc_html_e('Recurring (Annually)', 'memberpress'); ?></span>
            </label>
          </div>
        </div>
    </div>

    <?php
    $mepr_options = MeprOptions::fetch();
    $currency_code = !empty($mepr_options->currency_code) ? $mepr_options->currency_code : 'USD';
    ?>
    <div class="mepr-wizard-popup-field">
      <label for="mepr-wizard-create-membership-price"><?php esc_html_e('Price', 'memberpress'); ?></label>
      <div class="mepr-create-membership-price-wrapper">
        <input type="text" id="mepr-wizard-create-membership-price" placeholder="<?php esc_attr_e('0', 'memberpress'); ?>">
        <span class="mepr-create-membership-price-currency"><span><?php echo esc_html($currency_code); ?></span></span>
      </div>
    </div>

    <div class="mepr-wizard-popup-button-row">
      <button type="button" id="mepr-wizard-create-new-membership-save" class="mepr-wizard-button-blue"><?php esc_html_e('Save', 'memberpress'); ?></button>
      <a target='_blank' class="mepr-wizard-popuphelp" href='<?php echo admin_url('edit.php?post_type=memberpressproduct'); ?>' id="mepr-wizard-create-content-course-help">
        <?php
          printf(
            /* translators: %1$s: open underline tag, %2$s: close underline tag */
            esc_html__('More advanced options are available in %1$sMemberPress > Memberships%2$s', 'memberpress'),
            '<u>',
            '</u>'
          );
        ?>
      </a>
    </div>
  </form>
</div>
