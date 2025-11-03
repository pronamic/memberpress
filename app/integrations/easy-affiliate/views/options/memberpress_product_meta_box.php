<?php
defined('ABSPATH') || exit;

use EasyAffiliate\Helpers\AppHelper;
?>
<div class="wafp-memberpress-meta-box">
    <input type="checkbox" name="wafp_enable_commission_group" id="wafp_enable_commission_group" class="esaf-show-commission-override"<?php checked($commission_groups_enabled); ?> />
    <label for="wafp_enable_commission_group"><?php esc_html_e('Enable Commission Group', 'memberpress'); ?></label>
    <?php
    AppHelper::info_tooltip(
        'esaf-product-enable-commission-group',
        sprintf(
            // Translators: %1$s: br tag.
            esc_html__('If enabled, purchasers of this product will be added to your Easy Affiliate affiliate program and will be enrolled in a special commission group set here.%1$s%1$sCommissions for these affiliates will be calculated based on this commission structure.', 'memberpress'),
            '<br />'
        )
    );
    ?>
    <?php AppHelper::display_commission_override($commission_type, $commission_levels, $subscription_commissions); ?>
</div>
