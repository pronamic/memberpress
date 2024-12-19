<?php

/**
 * The layout for authenticated or guest pages
 *
 * @package memberpress-pro-template
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="profile" href="https://gmpg.org/xfn/11">

  <?php wp_head(); ?>
</head>

<body <?php body_class('mepr-pro-template mepr-app-layout'); ?>>
  <?php wp_body_open(); ?>
  <div id="page" class="site app-layout">
    <header id="masthead" class="site-header <?php echo isset($is_account_page) ? 'account-header' : '' ?>">
      <div class="site-branding">
        <a href="<?php echo esc_url(home_url()); ?>"><img class="site-branding__logo"
            src="<?php echo esc_url_raw($logo); ?>" /></a>
      </div><!-- .site-branding -->

      <?php if ($user) : ?>
      <div x-data="{open: false}" class="ml-3 profile-menu">
        <div class="profile-menu__button-group">
          <button @click="open = !open" type="button" class="profile-menu__button --is-tablet" id="user-menu-button"
            @click="onButtonClick()" aria-expanded="false" aria-haspopup="true">
            <img class="profile-menu__avatar h-8 w-8 rounded-full"
              src="<?php echo esc_url_raw(get_avatar_url($user->ID, ['size' => '51'])) ?>"
              alt="">

            <div class="profile-menu__text">
              <span>
                <?php echo esc_html($user->full_name()); ?>
              </span>
              <span class="profile-menu__text--small"><?php echo esc_html($user->user_email); ?></span>
            </div>

            <svg class="profile-menu__arrow_down" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
              fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clip-rule="evenodd"></path>
            </svg>
          </button>

          <button x-data @click="$dispatch('toggle-menu')" class="profile-menu__button --is-mobile">
            <svg xmlns="http://www.w3.org/2000/svg" class="profile-menu__hamburger" fill="none" viewBox="0 0 24 24"
              stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <div x-show="open" @click.away="open=false" x-cloak @toggle-menu.window="open=!open" class="profile-menu__dropdown dropdown">
            <a class="profile-menu__dropdown-item dropdown__item"
              href="<?php echo esc_url($account_url); ?>"><?php _ex('Account', 'ui', 'memberpress'); ?></a>
            <a class="profile-menu__dropdown-item dropdown__item"
              href="<?php echo esc_url($change_password_url); ?>"><?php echo _ex('Change Password', 'ui', 'memberpress'); ?></a>
            <a class="profile-menu__dropdown-item dropdown__item"
              href="<?php echo esc_url($logout_url); ?>"><?php _ex('Logout', 'ui', 'memberpress'); ?></a>
          </div>

        </div>
      </div>
      <?php endif; ?>

    </header><!-- #masthead -->

    <main id="primary" class="site-main <?php echo $wrapper_classes ?>">
      <?php the_content() ?>

      <div class="mepr-rl-footer-widgets">
        <?php if (
          is_active_sidebar('mepr_rl_registration_footer') &&
          ( MeprReadyLaunchCtrl::template_enabled('checkout') || MeprAppHelper::has_block('memberpress/checkout') )
) : ?>
          <div id="mepr-rl-login-registration-widget" class="mepr-rl-login-registration-widget widget-area" role="complementary">
            <?php dynamic_sidebar('mepr_rl_registration_footer'); ?>
          </div>
        <?php endif; ?>

        <?php if (
          is_active_sidebar('mepr_rl_account_footer') &&
          ( MeprReadyLaunchCtrl::template_enabled('account') || MeprAppHelper::has_block('memberpress/pro-account-tabs') )
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

    <?php wp_footer(); ?>
</body>

</html>
