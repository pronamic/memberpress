<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php $li = MeprUpdateCtrl::get_license_info(); ?>
<?php if ($li) : ?>
<div id="mepr-wizard-license-wrapper" class="mepr-hidden">
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Your License', 'memberpress'); ?></h2>
    <?php MeprView::render('/admin/onboarding/active_license', get_defined_vars()); ?>
</div>
<?php else : ?>
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Activate License', 'memberpress'); ?></h2>
    <?php if (!MeprUtils::is_oem_edition(MEPR_EDITION)) : ?>
    <p class="mepr-wizard-step-description"><?php esc_html_e("First thing's first. Let's get your license activated.", 'memberpress'); ?></p>
    <p><a href="<?php echo esc_url(MeprAuthenticatorCtrl::get_auth_connect_url(false, false, ['onboarding' => 'true'], admin_url('admin.php?page=memberpress-onboarding&step=1'))); ?>" class="mepr-wizard-button-blue"><?php esc_html_e('Activate', 'memberpress'); ?></a></p>
        <?php if (isset($_GET['license_error'])) : ?>
      <div class="notice notice-error inline">
        <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['license_error']))); ?></p>
      </div>
        <?php endif; ?>
    <?php else : ?>
    <p class="mepr-wizard-step-description"><?php esc_html_e("First thing's first. Let's get your license activated. Contact your developer agency if you need your license key.", 'memberpress'); ?></p>
    <div id="mepr-wizard-activate-license-container">
      <input type="text" id="mepr-wizard-license-key" placeholder="<?php esc_attr_e('Enter license key', 'memberpress'); ?>">
      <button type="button" id="mepr-wizard-activate-license-key" class="mepr-wizard-button-blue"><?php esc_html_e('Activate', 'memberpress'); ?></button>
    </div>
    <?php endif; ?>
<?php endif; ?>
