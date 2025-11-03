<?php
  $mepr_current_user = MeprUtils::get_currentuserinfo();
  $delim             = MeprAppCtrl::get_param_delimiter_char($account_url);
  $logout_url        = MeprUtils::logout_url();
?>
<div class='mepr-account-container'>
  <nav id="mepr-account-nav" aria-label="<?php esc_attr_e('Account Navigation', 'memberpress'); ?>" class="mepr-nav">
    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('home'); ?>">
      <a class=""
        href="<?php echo esc_url(MeprHooks::apply_filters('mepr_account_nav_home_link', $account_url . $delim . 'action=home')); ?>"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_nav_home_label', _x('My Profile', 'ui', 'memberpress'))); ?></a>
    </span>

    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('payments'); ?>">
      <a class=""
        href="<?php echo esc_url(MeprHooks::apply_filters('mepr_account_nav_payments_link', $account_url . $delim . 'action=payments')); ?>"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_nav_payments_label', _x('Payments', 'ui', 'memberpress'))); ?></a>
    </span>

    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('subscriptions'); ?>">
      <a class=""
        href="<?php echo esc_url(MeprHooks::apply_filters('mepr_account_nav_subscriptions_link', $account_url . $delim . 'action=subscriptions')); ?>"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_nav_subscriptions_label', _x('Subscriptions', 'ui', 'memberpress'))); ?></a>
    </span>

    <?php MeprHooks::do_action('mepr_account_nav', $mepr_current_user); ?>

    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav('logout'); ?>">
      <a class=""
        href="<?php echo esc_url($logout_url); ?>"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_nav_logout_label', _x('Logout', 'ui', 'memberpress'))); ?></a>
    </span>

  </nav>
  <!-- This opening div is necessary for flex to work -->
  <div id="mepr-account-content" class="mp_wrapper">