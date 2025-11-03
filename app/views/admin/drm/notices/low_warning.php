<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div class="mepr-notice-wrapper">
   <h3 class="mepr-notice-title"><?php echo esc_html($drm_info['heading']); ?></h3>
   <p class="mepr-notice-desc"><?php echo wp_kses_post($drm_info['simple_message']); ?></p>
   <?php if ($drm_info['event_name'] === MeprDrmHelper::INVALID_LICENSE_EVENT) : ?>
     <ul class="mepr-drm-action-items">
       <li>
           <?php printf(
               // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
               esc_html__('Go to MemberPress.com and make your selection. %1$sPricing Page%2$s.', 'memberpress'),
               '<a target="_blank" href="' . esc_url($drm_info['pricing_link']) . '">',
               '</a>'
           ); ?>
       </li>
       <li>
           <?php printf(
               // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
               esc_html__('%1$sClick here%2$s to enter and activate your new license key.', 'memberpress'),
               '<a href="' . esc_url($drm_info['activation_link']) . '">',
               '</a>'
           ); ?>
       </li>
       <li><?php esc_html_e('That’s it!.', 'memberpress'); ?></li>
     </ul>
   <?php else : ?>
     <ul class="mepr-drm-action-items">
       <li>
           <?php printf(
               // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
               esc_html__('Grab your key from your %1$sAccount Page%2$s.', 'memberpress'),
               '<a target="_blank" href="' . esc_url($drm_info['account_link']) . '">',
               '</a>'
           ); ?>
       </li>
       <li>
           <?php printf(
               // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
               esc_html__('%1$sClick here%2$s to enter and activate it.', 'memberpress'),
               '<a href="' . esc_url($drm_info['activation_link']) . '">',
               '</a>'
           ); ?>
       </li>
       <li><?php esc_html_e('That’s it!', 'memberpress'); ?></li>
     </ul>
   <?php endif; ?>
</div>
