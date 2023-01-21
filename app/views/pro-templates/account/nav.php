<?php
  $mepr_current_user = MeprUtils::get_currentuserinfo();
  $delim = MeprAppCtrl::get_param_delimiter_char($account_url);
  $logout_url   = MeprUtils::logout_url();
?>
  <nav id="mepr-account-nav" x-data="{ open: false }" class="mepr-nav" :class="open ? 'open' : ''" @toggle-menu.window="open=!open" >
    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav( 'home' ); ?>">
      <a class=""
        href="<?php echo MeprHooks::apply_filters( 'mepr-account-nav-home-link', $account_url . $delim . 'action=home' ); ?>"><?php echo MeprHooks::apply_filters( 'mepr-account-nav-home-label', _x( 'My Profile', 'ui', 'memberpress' ) ); ?></a>
    </span>

    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav( 'payments' ); ?>">
      <a class=""
        href="<?php echo MeprHooks::apply_filters( 'mepr-account-nav-payments-link', $account_url . $delim . 'action=payments' ); ?>"><?php echo MeprHooks::apply_filters( 'mepr-account-nav-payments-label', _x( 'Payments', 'ui', 'memberpress' ) ); ?></a>
    </span>

    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav( 'subscriptions' ); ?>">
      <a class=""
        href="<?php echo MeprHooks::apply_filters( 'mepr-account-nav-subscriptions-link', $account_url . $delim . 'action=subscriptions' ); ?>"><?php echo MeprHooks::apply_filters( 'mepr-account-nav-subscriptions-label', _x( 'Subscriptions', 'ui', 'memberpress' ) ); ?></a>
    </span>

    <?php MeprHooks::do_action( 'mepr_account_nav', $mepr_current_user ); ?>

    <span class="mepr-nav-item <?php MeprAccountHelper::active_nav( 'logout' ); ?>">
      <a class=""
        href="<?php echo esc_url( $logout_url ); ?>"><?php echo MeprHooks::apply_filters( 'mepr-account-nav-logout-label', _x( 'Logout', 'ui', 'memberpress' ) ); ?></a>
    </span>

  </nav>
  <!-- This opening div is necessary for flex to work -->
  <div id="mepr-account-content" class="mp_wrapper">