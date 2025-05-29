<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php if (
    is_page($login_page_id) && isset($redirect_to) && !empty($redirect_to) &&
         (!isset($_GET['action']) || $_GET['action'] != 'mepr_unauthorized')
) : ?>
  <script type="text/javascript">
    window.location.href="<?php echo esc_url_raw($redirect_to); ?>";
  </script>
<?php endif; ?>

<div class="mepr-already-logged-in">
  <?php printf(
      // Translators: %1$s: opening anchor tag, %2$s: closing anchor tag.
      _x('You\'re already logged in. %1$sLogout.%2$s', 'ui', 'memberpress'),
      '<a href="' . wp_logout_url($redirect_to) . '">',
      '</a>'
  ); ?>
</div>

