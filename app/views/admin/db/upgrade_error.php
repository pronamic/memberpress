<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo esc_attr(get_option('blog_charset')); ?>" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?php esc_html_e('MemberPress database upgrade error', 'memberpress'); ?></title>
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
          <div class="panel-heading"><h2><?php esc_html_e('MemberPress database upgrade error', 'memberpress'); ?></h2></div>
          <div class="panel-body">
            <p>&nbsp;</p>
            <?php
              $update_ctrl = new MeprUpdateCtrl();
            ?>
            <h3><?php esc_html_e('Oops, your MemberPress database upgrade triggered an error...', 'memberpress'); ?></h3>
            <p><?php esc_html_e('If this is a production website rollback MemberPress to a previous version and contact our support team.', 'memberpress'); ?></p>
            <p>&nbsp;</p>
            <?php if (!empty($error)) : ?>
              <div class="alert alert-danger" role="alert"><?php echo esc_html($error); ?></div>
              <p>&nbsp;</p>
            <?php endif; ?>
            <p>
              <a class="btn btn-primary" href="<?php echo esc_url($update_ctrl->rollback_url()); ?>" target="_blank"><?php esc_html_e('Rollback MemberPress', 'memberpress'); ?></a>
              <a class="btn btn-primary" href="<?php echo esc_url(MeprUtils::get_link_url('support')); ?>" target="_blank"><?php esc_html_e('Contact Support', 'memberpress'); ?></a>
            </p>
            <p>&nbsp;</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
