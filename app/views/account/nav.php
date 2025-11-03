<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="mp_wrapper">
  <nav id="mepr-account-nav" aria-label="<?php esc_attr_e('Account Navigation', 'memberpress'); ?>">
    <ul>
      <li class="mepr-nav-item <?php MeprAccountHelper::active_nav('home'); ?>">
        <a href="<?php echo esc_url(MeprHooks::apply_filters('mepr_account_nav_home_link', $account_url . $delim . 'action=home')); ?>" id="mepr-account-home"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_nav_home_label', _x('Home', 'ui', 'memberpress'))); ?></a>
      </li>
      <li class="mepr-nav-item <?php MeprAccountHelper::active_nav('subscriptions'); ?>">
        <a href="<?php echo esc_url(MeprHooks::apply_filters('mepr_account_nav_subscriptions_link', $account_url . $delim . 'action=subscriptions')); ?>" id="mepr-account-subscriptions"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_nav_subscriptions_label', _x('Subscriptions', 'ui', 'memberpress'))); ?></a>
      </li>
      <li class="mepr-nav-item <?php MeprAccountHelper::active_nav('payments'); ?>">
        <a href="<?php echo esc_url(MeprHooks::apply_filters('mepr_account_nav_payments_link', $account_url . $delim . 'action=payments')); ?>" id="mepr-account-payments"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_nav_payments_label', _x('Payments', 'ui', 'memberpress'))); ?></a>
      </li>
      <?php MeprHooks::do_action('mepr_account_nav', $mepr_current_user); ?>
      <li class="mepr-nav-item">
        <a href="<?php echo esc_url(MeprUtils::logout_url()); ?>" id="mepr-account-logout"><?php echo esc_html_x('Logout', 'ui', 'memberpress'); ?></a>
      </li>
    </ul>
  </nav>
</div>

<?php
if (isset($expired_subs) and !empty($expired_subs) && (empty($_GET['action']) || $_GET['action'] !== 'update')) {
    // $account_url = MeprUtils::get_permalink(); // $mepr_options->account_page_url();
    $sub_label = MeprHooks::apply_filters('mepr_account_nav_subscriptions_label', _x('Subscriptions', 'ui', 'memberpress'));
    $delim     = preg_match('#\?#', $account_url) ? '&' : '?';
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    $errors    = [
        sprintf(
          // Translators: %1$s: subscription label, %2$s: subscription label, %3$s: subscription label, %4$s: subscription label.
            _x('You have a problem with one or more of your %1$s. To prevent any lapses in your %1$s please visit your %2$s%3$s%4$s page to update them.', 'ui', 'memberpress'),
            strtolower($sub_label),
            '<a href="' . $account_url . $delim . 'action=subscriptions">',
            $sub_label,
            '</a>'
        ),
    ];
    MeprView::render('/shared/errors', get_defined_vars());
}

if (isset($_REQUEST['errors'])) {
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    $errors = [esc_html(sanitize_text_field(wp_unslash($_REQUEST['errors'])))];
    MeprView::render('/shared/errors', get_defined_vars());
}
