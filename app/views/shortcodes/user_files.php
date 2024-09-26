<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<?php
?>
<div class="mp_wrapper mp_users_files">
  <a target="_blank" href="<?php echo esc_url($download) ?>"><?php echo !empty($content) ? esc_html($content) : esc_html($key)  ?></a>
</div>
