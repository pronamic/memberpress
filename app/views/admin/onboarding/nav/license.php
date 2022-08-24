<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php $li = get_site_transient('mepr_license_info'); ?>
<?php if($li): ?>
<div>
  <button type="button" class="mepr-wizard-button-blue mepr-wizard-go-to-step" data-step="2" data-context="continue"><?php esc_html_e('Continue', 'memberpress'); ?></button>
</div>
<?php endif; ?>
