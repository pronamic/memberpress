<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
$search_term  = (isset($_REQUEST['search']) && !empty($_REQUEST['search'])) ? sanitize_text_field(wp_unslash($_REQUEST['search'])) : '';
$perpage      = (isset($_REQUEST['perpage']) && !empty($_REQUEST['perpage'])) ? (int)$_REQUEST['perpage'] : 10;
$search_field = (isset($_REQUEST['search-field']) && !empty($_REQUEST['search-field'])) ? sanitize_text_field(wp_unslash($_REQUEST['search-field'])) : '';
?>

<p class="mepr-search-box">
  <?php MeprHooks::do_action('mepr_table_controls_search', $search_term, $perpage); ?>
  <span class="mepr-search-fields">
    <label for="cspf-table-search"><?php esc_html_e('Search', 'memberpress'); ?></label>
    <input type="search" id="cspf-table-search" value="<?php echo esc_attr($search_term); ?>" />
    <label for="cspf-table-search-field"><?php esc_html_e('by Field', 'memberpress'); ?></label>
    <select id="cspf-table-search-field">
      <?php foreach ($search_cols as $col => $name) : ?>
        <option value="<?php echo esc_attr($col); ?>" <?php selected($col, $search_field); ?>><?php echo esc_html($name); ?></option>
      <?php endforeach; ?>
      <option value="any" <?php selected($search_field, 'any'); ?>><?php esc_html_e('Any (Slow)', 'memberpress'); ?></option>
    </select>
    <input id="cspf-table-search-submit" class="button" type="submit" value="<?php esc_attr_e('Go', 'memberpress'); ?>" />
    <?php
    if (isset($_REQUEST['search']) || isset($_REQUEST['search-filter'])) {
        $uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $uri = preg_replace('/[\?&]search=[^&]*/', '', $uri);
        $uri = preg_replace('/[\?&]search-field=[^&]*/', '', $uri);
        ?>
        <a href="<?php echo esc_url($uri); ?>">[x]</a>
        <?php
    }
    ?>
  </span>
</p>

<br class="clear">
