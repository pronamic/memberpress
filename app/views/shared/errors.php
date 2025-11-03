<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php if (isset($errors) && is_array($errors) && count($errors) > 0) : ?>
<div class="mp_wrapper">
  <div class="mepr_error" id="mepr_jump">
    <ul>
      <?php foreach ($errors as $single_error) : ?>
        <li><strong><?php echo esc_html_x('ERROR', 'ui', 'memberpress'); ?></strong>: <?php echo wp_kses($single_error, MeprAppHelper::kses_allowed_tags()); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<?php if (isset($message) and !empty($message)) : ?>
<div class="mp_wrapper">
  <div class="mepr_updated"><?php echo wp_kses($message, MeprAppHelper::kses_allowed_tags()); ?></div>
</div>
<?php endif; ?>
