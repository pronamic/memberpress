<?php
defined('ABSPATH') || exit;
?>

<div class="wafp-mepr-coupon-meta-box">
    <input type="checkbox" name="mepr-associate-affiliate-enable" id="mepr-associate-affiliate-enable" style="vertical-align:bottom;"<?php checked($enabled); ?> />
    <label for="mepr-associate-affiliate-enable"><?php esc_html_e('Associate an Affiliate', 'memberpress'); ?></label>

    <div id="mepr-affiliate-search" class="wafp-hidden">
        <label for="mepr-associate-affiliate-username"><?php esc_html_e('Affiliate', 'memberpress'); ?></label><br/>
        <input type="text" name="mepr-associate-affiliate-username" id="mepr-associate-affiliate-username" class="mepr_suggest_user" value="<?php echo esc_attr($affiliate_login); ?>" />
    </div>
</div>
