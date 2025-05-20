<?php

/**
 * The layout for unauthenticated or guest pages
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

<body <?php body_class('mepr-pro-template mepr-guest-layout'); ?>>
<?php wp_body_open(); ?>
  <div id="page" class="site guest-layout">
    <header id="masthead" class="site-header">
      <div class="site-branding">
        <a href="<?php echo esc_url(home_url()); ?>"><img class="site-logo" src="<?php echo $logo ?>" /></a>
      </div><!-- .site-branding -->
    </header><!-- #masthead -->
    <!-- ../assets/logo.svg -->
    <main id="primary" class="site-main">
      <?php the_content() ?>

      <?php
        if (
            $mepr_options->login_page_id &&
            ( is_active_sidebar('mepr_rl_login_footer') || is_active_sidebar('mepr_rl_global_footer') )
        ) { ?>
        <div class="mepro-login-widget">
          <div class="mepro-login-widget-box mepro-boxed">

            <?php if (is_active_sidebar('mepr_rl_login_footer')) : ?>
              <div id="mepr-rl-login-footer-widget" class="mepr-rl-login-footer-widget widget-area" role="complementary">
                <?php dynamic_sidebar('mepr_rl_login_footer'); ?>
              </div>
            <?php endif; ?>

            <?php if (is_active_sidebar('mepr_rl_global_footer')) : ?>
              <div id="mepr-rl-global-footer-widget" class="mepr-rl-global-footer-widget widget-area" role="complementary">
                <?php dynamic_sidebar('mepr_rl_global_footer'); ?>
              </div>
            <?php endif; ?>

          </div>
        </div>
        <?php } ?>
    </main>


    <?php wp_footer(); ?>
  </body>

</html>
