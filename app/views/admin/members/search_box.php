<span class="mepr-filter-by">
  <label><?php esc_html_e('Filter by', 'memberpress'); ?></label>

  <select class="mepr_filter_field" id="membership">
    <option value="all" <?php selected($membership, false); ?>><?php esc_html_e('All Memberships', 'memberpress'); ?></option>
    <?php foreach ($prds as $p) : ?>
      <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $membership); ?>><?php echo esc_html($p->post_title); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="mepr_filter_field" id="status">
    <option value="all" <?php selected($status, false); ?>><?php esc_html_e('All Members', 'memberpress'); ?></option>
    <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active Members', 'memberpress'); ?></option>
    <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive Members', 'memberpress'); ?></option>
    <option value="expired" <?php selected($status, 'expired'); ?>><?php esc_html_e('Expired Members', 'memberpress'); ?></option>
    <option value="none" <?php selected($status, 'none'); ?>><?php esc_html_e('Non-Members', 'memberpress'); ?></option>
    <?php MeprHooks::do_action('mepr_members_search_box_options', $status); ?>
  </select>

  <input type="submit" id="mepr_search_filter" class="button" value="<?php esc_attr_e('Go', 'memberpress'); ?>" />

  <?php
    if (isset($_REQUEST['status']) || isset($_REQUEST['membership'])) {
        $uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $uri = preg_replace('/[\?&]status=[^&]*/', '', $uri);
        $uri = preg_replace('/[\?&]membership=[^&]*/', '', $uri);
        ?>
      <a href="<?php echo esc_url($uri); ?>">[x]</a>
        <?php
    }
    ?>
</span>
