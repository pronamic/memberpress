<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 0;"><?php echo esc_html_x('Your Subscription Payment Failed', 'ui', 'memberpress'); ?></h1>
  <h2 style="margin-top: 0; color: #999; font-weight: normal;"><?php echo esc_html_x('{$subscr_num} &ndash; {$blog_name}', 'ui', 'memberpress'); ?></h2>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div class="section" style="display: block; margin-bottom: 35px;"><?php echo esc_html_x('Sorry, your latest subscription payment on {$blog_name} failed. Please click the link below to pay the invoice to keep your subscription active.', 'ui', 'memberpress'); ?></div>
  <div class="section" style="display: block; margin-bottom: 35px; text-align: center;">
    <a style="background-color: #52a7e7; color: #fff; padding: 10px 30px; text-decoration: none; border-radius: 5px; display: inline-block;" href="{$stripe_invoice_url}"><?php echo esc_html_x('Pay Invoice', 'ui', 'memberpress'); ?></a>
  </div>
  <table style="clear: both;" class="transaction">
    <tr><th style="text-align: left;"><?php echo esc_html_x('Website:', 'ui', 'memberpress'); ?></th><td>{$blog_name}</td></tr>
    <tr><th style="text-align: left;"><?php echo esc_html_x('Terms:', 'ui', 'memberpress'); ?></th><td>{$subscr_terms}</td></tr>
    <tr><th style="text-align: left;"><?php echo esc_html_x('Subscription:', 'ui', 'memberpress'); ?></th><td>{$subscr_num}</td></tr>
    <tr><th style="text-align: left;"><?php echo esc_html_x('Started:', 'ui', 'memberpress'); ?></th><td>{$subscr_date}</td></tr>
    <tr><th style="text-align: left;"><?php echo esc_html_x('Email:', 'ui', 'memberpress'); ?></th><td>{$user_email}</td></tr>
    <tr><th style="text-align: left;"><?php echo esc_html_x('Login:', 'ui', 'memberpress'); ?></th><td>{$user_login}</td></tr>
  </table>
</div>
