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
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="profile" href="https://gmpg.org/xfn/11">

  <?php wp_head(); ?>
</head>

<body <?php body_class('mepr-pro-template mepr-guest-layout'); ?>>
<?php wp_body_open(); ?>
  <div id="page" class="site guest-layout">
    <header id="masthead" class="site-header">
      <div class="site-branding">
        <a href="<?php echo esc_url( home_url() ); ?>"><img class="site-logo" src="<?php echo $logo ?>" /></a>
      </div><!-- .site-branding -->
    </header><!-- #masthead -->
    <!-- ../assets/logo.svg -->
    <main id="primary" class="site-main">
      <?php // echo $content ?>
      <?php the_content() ?>
    </main>


    <?php wp_footer(); ?>
  </body>

</html>