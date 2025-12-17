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
        <option value="complete" <?php selected($status, 'complete'); ?>><?php esc_html_e('Complete', 'memberpress'); ?></option>
        <option value="refunded" <?php selected($status, 'refunded'); ?>><?php esc_html_e('Refunded', 'memberpress'); ?></option>
        <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Failed', 'memberpress'); ?></option>
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
<?php if (!empty($date_range_filter_options)) : ?>
    <span class="mepr_date_filter_row">
        <label for="date_range_filter"><?php esc_html_e('Filter by date range', 'memberpress'); ?></label>
        <select class="mepr_filter_field" id="date_range_filter">
            <?php foreach ($date_range_filter_options as $option => $label) : ?>
                <option value="<?php echo esc_attr($option); ?>" <?php selected($date_range_filter, $option); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="mepr_date_range_filters_row <?php echo $date_range_filter === 'custom' ? '' : 'mepr-hidden'; ?>">
            <label for="date_start"><?php esc_html_e('From', 'memberpress'); ?></label>
            <input
                type="text"
                class="mepr_filter_field mepr-date-picker date-range-filter"
                id="date_start"
                data-show-time="false"
                data-time-format=""
                value="<?php echo esc_attr($date_start); ?>"
                placeholder="<?php esc_attr_e('YYYY-MM-DD', 'memberpress'); ?>"
            />
            <label for="date_end"><?php esc_html_e('To', 'memberpress'); ?></label>
            <input
                type="text"
                class="mepr_filter_field mepr-date-picker date-range-filter"
                id="date_end"
                data-show-time="false"
                data-time-format=""
                value="<?php echo esc_attr($date_end); ?>"
                placeholder="<?php esc_attr_e('YYYY-MM-DD', 'memberpress'); ?>"
            />
        </span>
        <?php if (! empty($date_fields)) : ?>
            <span class="mepr_filter_field_row <?php echo $date_range_filter === 'all' ? 'mepr-hidden' : ''; ?>">
                <label for="date_field"><?php esc_html_e('by', 'memberpress'); ?></label>
                <select class="mepr_filter_field" id="date_field" >
                    <?php foreach ($date_fields as $field => $label) : ?>
                        <option value="<?php echo esc_attr($field); ?>" <?php selected($field, $date_field); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
        <?php endif; ?>
        <input type="submit" id="mepr_date_filter" class="button" value="<?php esc_attr_e('Go', 'memberpress'); ?>" />
        <?php
        if (isset($_REQUEST['date_range_filter'])) {
            $uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
            $uri = preg_replace('/[\?&]date_start=[^&]*/', '', $uri);
            $uri = preg_replace('/[\?&]date_end=[^&]*/', '', $uri);
            $uri = preg_replace('/[\?&]date_field=[^&]*/', '', $uri);
            $uri = preg_replace('/[\?&]date_range_filter=[^&]*/', '', $uri);
            ?>
            <a href="<?php echo esc_url($uri); ?>">[x]</a>
            <?php
        }
        ?>
    </span>
<?php endif; ?>