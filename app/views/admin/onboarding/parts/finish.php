<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php
MeprUpdateCtrl::manually_queue_update();
$license                         = get_site_transient('mepr_license_info');
$current_license                 = $license && isset($license['product_slug']) ? $license['product_slug'] : '';
$features_list                   = MeprOnboardingHelper::features_list();
$features_data                   = MeprOnboardingHelper::get_selected_features_data(get_current_user_id());
$addons_not_installed            = isset($features_data['addons_not_installed']) ? $features_data['addons_not_installed'] : [];
$addons_installed                = isset($features_data['addons_installed']) ? $features_data['addons_installed'] : [];
$addons_upgrade_failed           = isset($features_data['addons_upgrade_failed']) ? $features_data['addons_upgrade_failed'] : [];
$mepr_onboarding_payment_gateway = get_option('mepr_onboarding_payment_gateway');
$upgraded_edition                = isset($data['edition']) ? sanitize_text_field($data['edition']) : '';

$upgrade_type = MeprOnboardingHelper::is_upgrade_required(
    [
        'addons_installed'     => $addons_installed,
        'addons_not_installed' => $addons_not_installed,
        'payment_gateway'      => $mepr_onboarding_payment_gateway,
    ]
);

if ($upgrade_type === 'memberpress-elite') {
    $upgrade_type = 'memberpress-pro-5';
}

if (!empty($upgraded_edition) && !empty($current_license) && $upgraded_edition != $current_license) {
    // The user upgraded to another edition, but it has not processed yet.
    ?>
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Processing upgrade', 'memberpress'); ?></h2>
  <p class="mepr-wizard-step-description">
    <?php esc_html_e('Please wait while the upgrade is processed, this may take a minute.', 'memberpress'); ?>
    <i class="mp-icon mp-icon-spinner animate-spin"></i>
  </p>
  <input type="hidden" id="mepr-upgrade-wait-edition" value="1" />
<?php } elseif (false !== $upgrade_type || $mepr_onboarding_payment_gateway == 'MeprAuthorizeGateway') {
    $cta_data    = MeprOnboardingHelper::get_upgrade_cta_data($upgrade_type);
    $pricing_url = $cta_data['url'];

    $finish_description = $cta_data['heading'];
    $pricing_url_args   = [
        'onboarding' => 1,
    ];

    if (in_array('easy-affiliate', $features_data['addons_not_installed'], true)) {
        $finish_description       = esc_html__('To unlock selected features, upgrade with Easy Affiliate.', 'memberpress');
        $pricing_url_args['doea'] = 1;
    }

    if (in_array('memberpress-coachkit', $features_data['addons_not_installed'], true)) {
        $finish_description       = esc_html__('To unlock selected features, upgrade with CoachKit™.', 'memberpress');
        $pricing_url_args['dock'] = 1;
    }

    if (isset($pricing_url_args['doea']) && isset($pricing_url_args['dock'])) {
        $finish_description = esc_html__('To unlock selected features, upgrade with CoachKit™ and Easy Affiliate.', 'memberpress');
    }

    $pricing_url_args['return_url'] = urlencode(admin_url('admin.php?page=memberpress-onboarding&step=7&onboarding=1'));

    $pricing_url = add_query_arg(
        $pricing_url_args,
        $pricing_url
    );

    if (! empty($addons_installed)) {
        $finish_description = '';
        $pricing_url        = '';
    }

    $target = '';
    if (
        1 == count($features_data['addons_not_installed'])
        && in_array('easy-affiliate', $features_data['addons_not_installed'], true)
        && ( MeprUtils::is_pro_edition($current_license) || MeprUtils::is_elite_edition($current_license) )
    ) {
        $finish_description = '';
        $pricing_url        = 'https://easyaffiliate.com/ipob/pricing/';
        $cta_data['label']  = esc_html__('Purchase Easy Affiliate', 'memberpress');
        $target             = ' target="_blank"';
    }

    if (
        1 == count($features_data['addons_not_installed'])
        && in_array('memberpress-coachkit', $features_data['addons_not_installed'], true)
        && ( MeprUtils::is_pro_edition($current_license) || MeprUtils::is_elite_edition($current_license) )
    ) {
        $finish_description = '';
        $pricing_url        = 'https://memberpress.com/sign-in/?redirect_to=/register/coachkit-add-on/';
        $cta_data['label']  = esc_html__('Purchase CoachKit™', 'memberpress');
        $target             = ' target="_blank"';
    }

    $is_plan_upgrade_required = true;
    if (
        MeprUtils::is_elite_edition(MEPR_EDITION) || MeprUtils::is_pro_edition(MEPR_EDITION) ||
        MeprUtils::is_elite_edition($current_license) || MeprUtils::is_pro_edition($current_license)
    ) {
        $is_plan_upgrade_required = false;
        $finish_description       = '';
    }

    $multiple_ctas = [];
    if (false === $is_plan_upgrade_required && ! empty($addons_not_installed)) {
        $finish_description = sprintf(esc_html__('You have selected following features:', 'memberpress'), $cta_data['token']);
        if (in_array('easy-affiliate', $addons_not_installed, true)) {
            $multiple_ctas['ea']          = [];
            $multiple_ctas['ea']['url']   = 'https://memberpress.com/sign-in/?redirect_to=/register/easy-affiliate-pro/';
            $multiple_ctas['ea']['label'] = esc_html__('Purchase Easy Affiliate', 'memberpress');
        }

        if (in_array('memberpress-coachkit', $addons_not_installed, true)) {
            $multiple_ctas['ck']          = [];
            $multiple_ctas['ck']['url']   = 'https://memberpress.com/sign-in/?redirect_to=/register/coachkit-add-on/';
            $multiple_ctas['ck']['label'] = esc_html__('Purchase CoachKit™', 'memberpress');
        }
    }

    // If the license mismatched and upgrade still required, show them the upgrade CTA interface.
    if ($pricing_url != '' && $current_license != $upgrade_type) : ?>
    <h2 class="mepr-wizard-step-title"><?php esc_html_e('Finish setup', 'memberpress'); ?></h2>
    <p class="mepr-wizard-step-description"><?php echo esc_html($finish_description); ?></p>

    <div class="mepr-wizard-features">
        <?php
        foreach ($addons_not_installed as $i => $addon_slug) :
            $mepr_active_class = 'mepr-wizard-feature-activatedx';
            if (in_array($addon_slug, $addons_installed, true)) {
                $mepr_active_class = 'mepr-wizard-feature-activated';
            }

            $addons_installation_message       = '';
            $addons_installation_message_class = '';

            $purchase_links = MeprOnboardingHelper::features_addons_purchase_links();
            if (in_array($addon_slug, $addons_upgrade_failed, true)) {
                $addons_installation_message       =  MeprOnboardingHelper::prepare_purchase_message($purchase_links[$addon_slug]);
                $addons_installation_message_class = 'error';
            } else {
                $addons_installation_message       = esc_html__('Unable to install. Please download and install manually.', 'memberpress');
                $addons_installation_message_class = 'error';
            }
            ?>
        <div class="mepr-wizard-feature no-border no-padding">
          <div class="<?php echo esc_attr($mepr_active_class); ?>">
            <h3><span class="step-complete"></span> <?php echo esc_html($features_list[ $addon_slug ]); ?></h3>
            <p class="<?php echo esc_attr($addons_installation_message_class); ?>"><?php echo wp_kses_post($addons_installation_message); ?></p>
          </div>
          <div class="mepr-wizard-feature-right"></div>
        </div>
        <?php endforeach; ?>

        <?php if ($mepr_onboarding_payment_gateway == 'MeprAuthorizeGateway') : ?>
        <div class="mepr-wizard-feature no-border no-padding MeprAuthorizeGateway" data-slug="MeprAuthorizeGateway">
          <div class="mepr-wizard-feature-activatedx">
            <h3><span class="step-complete"></span> <?php esc_html_e('Authorize.net', 'memberpress'); ?></h3>
            <p></p>
          </div>
          <div class="mepr-wizard-feature-right"></div>
        </div>
        <?php endif; ?>
    </div>


        <?php if ($is_plan_upgrade_required) : ?>
    <div class="mepr-wizard-button-group">
      <a <?php echo $target; ?> href="<?php echo $pricing_url; ?>" id="mepr-wizard-create-new-content" class="mepr-wizard-button-orange"><?php echo esc_html($cta_data['label']); ?></a>
    </div>
        <?php endif; ?>

        <?php if (! empty($multiple_ctas)) : ?>
      <div class="mepr-wizard-button-group">
            <?php foreach ($multiple_ctas as $multiple_cta) : ?>
        <a target="_blank" href="<?php echo $multiple_cta['url']; ?>"class="mepr-wizard-button-orange"><?php echo esc_html($multiple_cta['label']); ?></a>
            <?php endforeach; ?>
      </div>
        <?php endif; ?>

        <?php
        // Lets run the upgrade.
    else :
        echo '<input type="hidden" id="mepr_wizard_finalize_setup" value="1" />';
        $editions = MeprUtils::is_incorrect_edition_installed();
        if ($editions) {
            echo '<input type="hidden" id="mepr_wizard_install_correct_edition" value="1" />';
        }
        ?>
    <h2 class="mepr-wizard-step-title"><?php esc_html_e('Finishing setup', 'memberpress'); ?></h2>
    <p class="mepr-wizard-step-description"><?php echo esc_html__("Please don't close the browser.", 'memberpress'); ?> <i class="mp-icon mp-icon-spinner animate-spin mepr-wizard-finish-step-processing"></i></p>
    <div class="mepr-wizard-features">
        <?php foreach ($features_data['addons_not_installed'] as $i => $addon_slug) : ?>
        <div class="mepr-wizard-feature no-border no-padding" id="mepr-finish-step-addon-<?php echo esc_attr($addon_slug); ?>">
          <div class="mepr-wizard-feature-activatedx">
            <h3><span class="step-complete"></span> <?php echo esc_html($features_list[ $addon_slug ]); ?></h3>
            <p class="mepr-wizard-addon-text"></p>
          </div>
          <div class="mepr-wizard-feature-right"><i id="mepr-wizard-finish-step-<?php echo esc_attr($addon_slug); ?>" class="mp-icon mp-icon-spinner animate-spin"></i></div>
        </div>
            <?php if ($i == 0) : ?>
          <input type="hidden" id="start_addon_slug_installable" value="<?php echo esc_attr($addon_slug); ?>" />
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($mepr_onboarding_payment_gateway == 'MeprAuthorizeGateway') : ?>
        <input type="hidden" id="mepr-wizard-finish-add-authorize-gateway" value="1" />
        <div class="mepr-wizard-feature no-border no-padding" id="mepr-wizard-finish-step-addon-MeprAuthorizeGateway" data-slug="MeprAuthorizeGateway">
          <div class="mepr-wizard-feature-activatedx">
            <h3><span class="step-complete"></span> <?php esc_html_e('Authorize.net', 'memberpress'); ?></h3>
            <p class="mepr-wizard-addon-text">
              <button type="button" id="mepr-wizard-finish-configure-authorize" class="mepr-wizard-button-secondary"><?php esc_html_e('Configure Gateway', 'memberpress'); ?></button>
            </p>
          </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
} else {
    ?>
  <h2 class="mepr-wizard-step-title"><?php esc_html_e('Finishing setup', 'memberpress'); ?></h2>
  <p class="mepr-wizard-step-description">
    <?php esc_html_e('Please wait..', 'memberpress'); ?>
    <i class="mp-icon mp-icon-spinner animate-spin"></i>
  </p>
  <input type="hidden" id="mepr-finishing-setup-redirect" value="1" />
<?php } ?>
