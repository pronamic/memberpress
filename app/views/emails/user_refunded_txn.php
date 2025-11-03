<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 0;"><?php echo esc_html_x('Your Transaction Was Refunded', 'ui', 'memberpress'); ?></h1>
  <h2 style="margin-top: 0; color: #999; font-weight: normal;"><?php echo esc_html_x('{$trans_num} &ndash; {$blog_name}', 'ui', 'memberpress'); ?></h2>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div id="receipt">
    <div class="section" style="display: block; margin-bottom: 24px;"><?php echo esc_html_x('Your transaction on {$blog_name} was refunded:', 'ui', 'memberpress'); ?></div>
    <table style="clear: both;" class="transaction">
      <tr><th style="text-align: left;"><?php echo esc_html_x('Website:', 'ui', 'memberpress'); ?></th><td>{$blog_name}</td></tr>
      <tr><th style="text-align: left;"><?php echo esc_html_x('Amount:', 'ui', 'memberpress'); ?></th><td>{$payment_amount}</td></tr>
      <tr><th style="text-align: left;"><?php echo esc_html_x('Transaction:', 'ui', 'memberpress'); ?></th><td>{$trans_num}</td></tr>
      <tr><th style="text-align: left;"><?php echo esc_html_x('Date:', 'ui', 'memberpress'); ?></th><td>{$trans_date}</td></tr>
      <tr><th style="text-align: left;"><?php echo esc_html_x('Status:', 'ui', 'memberpress'); ?></th><td><?php echo esc_html_x('Refunded', 'ui', 'memberpress'); ?></td></tr>
      <tr><th style="text-align: left;"><?php echo esc_html_x('Email:', 'ui', 'memberpress'); ?></th><td>{$user_email}</td></tr>
      <tr><th style="text-align: left;"><?php echo esc_html_x('Login:', 'ui', 'memberpress'); ?></th><td>{$user_login}</td></tr>
    </table>
  </div>
</div>

