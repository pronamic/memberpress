<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo esc_html($company); ?> | Update Payment Information</title>
    <?php
    wp_enqueue_style('memberpress-churnbuster-styles', 'https://proxy-assets.churnbuster.io/v2/styles.css', [], null);
    wp_print_styles('memberpress-churnbuster-styles');

    wp_enqueue_script('memberpress-churnbuster-scripts', 'https://proxy-assets.churnbuster.io/v2/scripts.js', [], null, false);
    wp_add_inline_script('memberpress-churnbuster-scripts', 'ChurnBuster.load("' . esc_js($uuid) . '");');
    wp_print_scripts('memberpress-churnbuster-scripts');
    ?>
  </head>
  <body>
    <noscript><h1>This page requires JavaScript</h1><p>Please enable JavaScript in your browser settings, or use a different web browser like Google Chrome or Safari.</p></noscript>
  </body>
</html>
