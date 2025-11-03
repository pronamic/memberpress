<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php if (isset($errors) && is_array($errors) && !empty($errors)) : ?>
  <div class="error notice is-dismissible below-h2">
    <ul>
      <?php foreach ($errors as $single_error) : ?>
        <li><strong><?php echo esc_html_x('ERROR', 'ui', 'memberpress'); ?></strong>: <?php echo wp_kses($single_error, MeprAppHelper::kses_allowed_tags()); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php if (isset($message) && !empty($message)) : ?>
  <div id="message" class="updated notice notice-success is-dismissible below-h2">
    <p><?php echo wp_kses_post($message); ?></p>
  </div>
<?php endif; ?>
