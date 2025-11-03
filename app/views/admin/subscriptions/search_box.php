<span class="mepr-filter-by">
    <label><?php esc_html_e('Filter by', 'memberpress'); ?></label>

    <select class="mepr_filter_field" id="membership">
        <option value="all" <?php selected($membership, false); ?>><?php esc_html_e('All Memberships', 'memberpress'); ?></option>
        <?php foreach ($prds as $p) : ?>
            <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $membership); ?>><?php echo esc_html($p->post_title); ?></option>
        <?php endforeach; ?>
    </select>

    <select class="mepr_filter_field" id="status">
        <option value="all" <?php selected($status, false); ?>><?php esc_html_e('All Statuses', 'memberpress'); ?></option>
        <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'memberpress'); ?></option>
        <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Enabled', 'memberpress'); ?></option>
        <option value="suspended" <?php selected($status, 'suspended'); ?>><?php esc_html_e('Paused', 'memberpress'); ?></option>
        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'memberpress'); ?></option>
    </select>

    <select class="mepr_filter_field" id="gateway">
        <option value="all" <?php selected($gateway, false); ?>><?php esc_html_e('All Gateways', 'memberpress'); ?></option>
        <?php foreach ($gateways as $gid => $g) : ?>
            <option value="<?php echo esc_attr($gid); ?>" <?php selected($gid, $gateway); ?>>
                <?php echo esc_html(sprintf('%1$s (%2$s)', $g->label, $g->name)); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="submit" id="mepr_search_filter" class="button" value="<?php esc_attr_e('Go', 'memberpress'); ?>" />

    <?php
    if (isset($_REQUEST['status']) || isset($_REQUEST['membership'])) {
        $uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $uri = preg_replace('/[\?&]status=[^&]*/', '', $uri);
        $uri = preg_replace('/[\?&]membership=[^&]*/', '', $uri);
        $uri = preg_replace('/[\?&]gateway=[^&]*/', '', $uri);
        ?>
        <a href="<?php echo esc_url($uri); ?>">[x]</a>
        <?php
    }
    ?>
</span>
