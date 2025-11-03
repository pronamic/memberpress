<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="mepr-drm-fee-notice-wrapper" class="notice notice-error is-dismissible mepr-notice-dismiss-24hour mepr-notice-dismiss-fee-notice" data-notice="" data-secret="">
  <div class="mepr-notice-wrapper">
     <h3 class="mepr-notice-title"><?php esc_html_e('MemberPress is running without a license', 'memberpress'); ?></h3>
     <p  class="mepr-notice-desc"><?php esc_html_e('When using without a license, MemberPress will add an additional fee to each transaction.', 'memberpress'); ?></p>

     <p class="mepr-notice-actions">
        <a href="#" class="button button-primary mepr-btn-fee-learnmore"><?php esc_html_e('Learn More', 'memberpress'); ?></a>
     </p>
  </div>
</div>