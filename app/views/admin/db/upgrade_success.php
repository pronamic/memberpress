<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo esc_attr(get_option('blog_charset')); ?>" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?php esc_html_e('MemberPress database upgrade was successful', 'memberpress'); ?></title>
  <?php
    // Enqueue Bootstrap CSS.
    wp_register_style('memberpress-bootstrap-css', MEPR_CSS_URL . '/vendor/bootstrap-3.3.6.min.css', [], '3.3.6');
    // Optional theme.
    wp_register_style('memberpress-bootstrap-theme', MEPR_CSS_URL . '/vendor/bootstrap-theme-3.3.6.min.css', ['memberpress-bootstrap-css'], '3.3.6');

    wp_print_styles(['memberpress-bootstrap-css', 'memberpress-bootstrap-theme']);
    ?>
  <style>
    body { background-color: #dedede; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    p { font-size: 120%; font-weight: 400; }
    h2 { font-size: 30px; font-weight: 500; }
    h3 { font-size: 24px; font-weight: 500; }
    a { text-decoration: none; }
  </style>
</head>
<body>
  <div class="container">
    <div class="row">&nbsp;</div>
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="panel panel-primary">
          <div class="panel-heading"><h2><?php esc_html_e('MemberPress has successfully upgraded your database', 'memberpress'); ?></h2></div>
          <div class="panel-body">
            <p>&nbsp;</p>
            <p><?php esc_html_e('You just successfully upgraded your MemberPress database ... now time to get back at it.', 'memberpress'); ?></p>
            <p>&nbsp;</p>
            <p><a class="btn btn-primary" href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Back to Admin', 'memberpress'); ?></a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

