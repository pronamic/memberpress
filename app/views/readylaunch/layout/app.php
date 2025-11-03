<?php
do_action('mepr_rl_before_main', get_defined_vars()); ?>
<main id="primary" class="site-main <?php echo esc_attr($wrapper_classes) ?>">
  <?php the_content() ?>

  <div class="mepr-rl-footer-widgets">
    <?php if (
      is_active_sidebar('mepr_rl_registration_footer') &&
      (MeprReadyLaunchCtrl::template_enabled('checkout') || MeprAppHelper::has_block('memberpress/checkout'))
) : ?>
      <div id="mepr-rl-login-registration-widget" class="mepr-rl-login-registration-widget widget-area" role="complementary">
        <?php dynamic_sidebar('mepr_rl_registration_footer'); ?>
      </div>
    <?php endif; ?>

    <?php if (
      is_active_sidebar('mepr_rl_account_footer') &&
      (MeprReadyLaunchCtrl::template_enabled('account') || MeprAppHelper::has_block('memberpress/pro-account-tabs'))
) : ?>
      <div id="mepr-rl-registration-footer-widget" class="mepr-rl-registration-footer-widget widget-area" role="complementary">
        <?php dynamic_sidebar('mepr_rl_account_footer'); ?>
      </div>
    <?php endif; ?>

    <?php if (is_active_sidebar('mepr_rl_global_footer')) : ?>
      <div id="mepr-rl-global-footer-widget" class="mepr-rl-global-footer-widget widget-area" role="complementary">
        <?php dynamic_sidebar('mepr_rl_global_footer'); ?>
      </div>
    <?php endif; ?>
  </div>

</main>

<?php
do_action('mepr_rl_after_main', get_defined_vars()); ?>
