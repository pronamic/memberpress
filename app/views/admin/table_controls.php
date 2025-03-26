<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
$search       = (isset($_REQUEST['search']) && !empty($_REQUEST['search'])) ? sanitize_text_field(stripslashes($_REQUEST['search'])) : '';
$perpage      = (isset($_REQUEST['perpage']) && !empty($_REQUEST['perpage'])) ? (int)$_REQUEST['perpage'] : 10;
$search_field = (isset($_REQUEST['search-field']) && !empty($_REQUEST['search-field'])) ? sanitize_text_field($_REQUEST['search-field']) : '';
?>

<p class="search-box">
  <?php MeprHooks::do_action('mepr_table_controls_search', $search, $perpage); ?>
  <span class="search-fields">
    <span><?php _e('Search', 'memberpress'); ?></span>
    <input id="cspf-table-search" value="<?php echo esc_attr($search); ?>" />
    <span><?php _e('by Field', 'memberpress'); ?></span>
    <select id="cspf-table-search-field">
      <?php foreach ($search_cols as $col => $name) : ?>
        <option value="<?php echo $col; ?>" <?php selected($col, $search_field); ?>><?php echo esc_html($name); ?></option>
      <?php endforeach; ?>
      <option value="any" <?php selected($search_field, 'any'); ?>><?php esc_html_e('Any (Slow)', 'memberpress'); ?></option>
    </select>
    <input id="cspf-table-search-submit" class="button" type="submit" value="<?php esc_html_e('Go', 'memberpress'); ?>" />
    <?php
    if (isset($_REQUEST['search']) || isset($_REQUEST['search-filter'])) {
        $uri = $_SERVER['REQUEST_URI'];
        $uri = preg_replace('/[\?&]search=[^&]*/', '', $uri);
        $uri = preg_replace('/[\?&]search-field=[^&]*/', '', $uri);
        ?>
        <a href="<?php echo esc_url($uri); ?>">[x]</a>
        <?php
    }
    ?>
  </span>
</p>

<div class="cspf-tablenav-spacer">&nbsp;</div>

