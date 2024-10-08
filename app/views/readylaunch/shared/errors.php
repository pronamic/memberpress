<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php if (isset($errors) && $errors != null && count($errors) > 0) : ?>
  <div class="mp_wrapper">
    <div class="mepr_pro_error" id="mepr_jump">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
        <line x1="12" y1="9" x2="12" y2="13" />
        <line x1="12" y1="17" x2="12.01" y2="17" />
      </svg>

      <ul>
        <?php foreach ($errors as $error) : ?>
          <li><?php print MeprAppHelper::wp_kses($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if (isset($message) and !empty($message)) : ?>
  <div class="mp_wrapper">
    <div class="mepr_updated"><?php echo MeprAppHelper::wp_kses($message); ?></div>
  </div>
<?php endif; ?>