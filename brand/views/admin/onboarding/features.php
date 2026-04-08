<?php

/**
 * View: admin/onboarding/features.
 */

defined('ABSPATH') || exit;

$selected_features = MeprOnboardingHelper::get_selected_features(get_current_user_id());
$selectable_addons = MeprOnboardingHelper::features_addons_selectable_list();
$features          = array_merge(
    [
        'advanced-content-protection' => [
            'title'       => __('Advanced Content Protection', 'memberpress'),
            'description' => __('Increase perceived value, and protect your bottom line from unpaying eyes. Paywall your valuable content a thousand ways!', 'memberpress'),
            'selectable'  => null,
        ],
        'customizable-checkout'       => [
            'title'       => __('Customizable Checkout', 'memberpress'),
            'description' => __('Sell more memberships by accepting multiple payment types – from PayPal and credit cards to digital wallets, bank checks, and even cash by mail. You decide.', 'memberpress'),
            'selectable'  => null,
        ],
    ],
    $selectable_addons
);
?>

<h2 class="mepr-wizard-step-title">
    <?php esc_html_e('What features do you want to enable?', 'memberpress'); ?>
</h2>

<p class="mepr-wizard-step-description">
    <?php esc_html_e('MemberPress is chock full of awesome features. Here are a few you can enable right off the bat.', 'memberpress'); ?>
</p>

<div class="mepr-wizard-features">
    <?php foreach ($features as $key => $feature) : ?>
    <div class="mepr-wizard-feature">
        <div>
            <h3><?php echo esc_html($feature['title']); ?></h3>
            <p><?php echo esc_html($feature['description']); ?></p>
        </div>

        <div class="mepr-wizard-feature-right">
            <?php if ($feature['selectable']) : ?>
            <input type="checkbox" class="mepr-wizard-feature-input" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_features, true)); ?> />
            <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="" />
            <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="" />
            <?php else : ?>
            <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="" />
            <?php endif; ?>

            <?php if (false === $feature['selectable']) : ?>
            <input type="hidden" class="mepr-wizard-feature-input-active" value="<?php echo esc_attr($key); ?>" />
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<p class="mepr-wizard-plugins-to-install">
    <?php
    printf(
        // Translators: %s: the list of plugins.
        esc_html__('If your subscription level allows, the following plugins will be installed automatically: %s', 'memberpress'),
        '<span></span> <br /><br /><strong>Want a feature your membership level doesn’t support? No worries! You’ll get the chance to upgrade later in the onboarding wizard.</strong>'
    );
    ?>
</p>
