<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

if (!empty($records)) {
    $row_index = 0;
    foreach ($records as $rec) {
        $alternate = ( $row_index++ % 2 ? '' : 'alternate' );

        // Open the line.
        ?>
    <tr id="record_<?php echo esc_attr($rec->ID); ?>" class="<?php echo esc_attr($alternate); ?>">
        <?php
        foreach ($columns as $column_name => $column_display_name) {
            // Style attributes for each col.
            $class_value = $column_name . ' column-' . $column_name;
            $is_hidden = in_array($column_name, $hidden, true);

            // $editlink = admin_url('user-edit.php?user_id='.(int)$rec->ID);
            // $deletelink = admin_url('user-edit.php?user_id='.(int)$rec->ID);
            $deletelink = wp_nonce_url("users.php?action=delete&amp;user={$rec->ID}", 'bulk-users');
            $editlink   = esc_url(add_query_arg('wp_http_referer', urlencode(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? ''))), get_edit_user_link($rec->ID)));

            // Display the cell.
            switch ($column_name) {
                case 'col_id':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->ID); ?></td>
                    <?php
                    break;
                case 'col_photo':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo get_avatar($rec->email, 32); ?></td>
                    <?php
                    break;
                case 'col_name':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->name); ?></td>
                    <?php
                    break;
                case 'col_username':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
            <span class="mepr-member-avatar"><?php echo get_avatar($rec->email, 32); ?></span>
            <span class="mepr-member-username-and-actions">
              <div class="mepr-member-username">
                <a href="<?php echo esc_url($editlink); ?>" title="<?php esc_attr_e("View member's profile", 'memberpress'); ?>"><?php echo (int) $rec->ID ? esc_html($rec->username) : esc_html__('Deleted', 'memberpress'); ?></a>
              </div>
              <div class="mepr-member-actions mepr-hidden">
                <a href="<?php echo esc_url($editlink); ?>" title="<?php esc_attr_e("Edit member's profile", 'memberpress'); ?>"><?php esc_html_e('Edit', 'memberpress'); ?></a>
                |
                <a href="<?php echo esc_url($deletelink); ?>" title="<?php esc_attr_e('Delete member', 'memberpress'); ?>"><?php esc_html_e('Delete', 'memberpress'); ?></a>
              </div>
            </span>
          </td>
                    <?php
                    break;
                case 'col_email':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->email); ?></td>
                    <?php
                    break;
                case 'col_status':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php
                    $mepr_user = new MeprUser($rec->ID);
                    if ($mepr_user->is_active()) {
                        echo '<span class="mepr-active">' . esc_html__('Active', 'memberpress') . '</span>';
                    } elseif ($mepr_user->has_expired()) {
                        echo '<span class="mepr-inactive">' . esc_html__('Inactive', 'memberpress') . '</span>';
                    } else {
                        echo '<span>' . esc_html__('None', 'memberpress') . '</span>';
                    }
                    ?>
          </td>
                    <?php
                    break;
                case 'col_txn_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-trans&member=' . urlencode($rec->username))); ?>"><?php echo esc_html($rec->txn_count); ?></a></td>
                    <?php
                    break;
                case 'col_expired_txn_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->expired_txn_count); ?></td>
                    <?php
                    break;
                case 'col_active_txn_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->active_txn_count); ?></td>
                    <?php
                    break;
                case 'col_sub_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-subscriptions&member=' . urlencode($rec->username))); ?>"><?php echo esc_html($rec->sub_count); ?></a></td>
                    <?php
                    break;
                case 'col_sub_info':
                    $admin_sub_url = admin_url('admin.php?page=memberpress-subscriptions&member=' . urlencode($rec->username));
                    $sub_counts    = [
                        __('Enabled', 'memberpress') => 'active',
                        __('Stopped', 'memberpress') => 'cancelled',
                        __('Pending', 'memberpress') => 'pending',
                        __('Paused', 'memberpress')  => 'suspended',
                    ];
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php
                    foreach ($sub_counts as $label => $subscription_status) {
                        $status_count = "{$subscription_status}_sub_count";
                        if ($rec->$status_count > 0) {
                            ?>
                <div><a href="<?php echo esc_url($admin_sub_url . '&status=' . $subscription_status); ?>"><?php echo esc_html("{$rec->$status_count} {$label}"); ?></a></div>
                            <?php
                        }
                    }
                    ?>
          </td>
                    <?php
                    break;
                case 'col_txn_info':
                    $admin_txn_url = admin_url('admin.php?page=memberpress-trans&member=' . urlencode($rec->username));
                    $other_count   = $rec->txn_count;
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php
                    if ($rec->active_txn_count > 0) {
                        $other_count = $other_count - $rec->active_txn_count;
                        ?>
              <div><a href="<?php echo esc_url($admin_txn_url . '&status=complete'); ?>"><?php echo esc_html(sprintf('%d %s', $rec->active_txn_count, __('Complete', 'memberpress'))); ?></a></div>
                        <?php
                    }
                    if ($rec->expired_txn_count > 0) {
                        $other_count = $other_count - $rec->expired_txn_count;
                        ?>
              <div><a href="<?php echo esc_url($admin_txn_url); ?>"><?php echo esc_html(sprintf('%d %s', $rec->expired_txn_count, __('Expired', 'memberpress'))); ?></a></div>
                        <?php
                    }
                    if ($rec->trial_txn_count > 0) {
                        ?>
              <div><?php echo esc_html(sprintf('%d %s', $rec->trial_txn_count, __('Trial', 'memberpress'))); ?></div>
                        <?php
                    }
                    if ($other_count > 0) {
                        ?>
              <div><a href="<?php echo esc_url($admin_txn_url); ?>"><?php echo esc_html(sprintf('%d %s', $other_count, __('Other', 'memberpress'))); ?></a></div>
                        <?php
                    }
                    ?>
          </td>
                    <?php
                    break;
                case 'col_info':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
            <div><a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-subscriptions&member=' . urlencode($rec->username))); ?>"><?php echo esc_html(sprintf('%d Subscriptions', $rec->sub_count)); ?></a></div>
            <div><a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-trans&member=' . urlencode($rec->username))); ?>"><?php echo esc_html(sprintf('%d Transactions', $rec->txn_count)); ?></a></div>
          </td>
                    <?php
                    break;
                case 'col_pending_sub_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->pending_sub_count); ?></td>
                    <?php
                    break;
                case 'col_active_sub_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->active_sub_count); ?></td>
                    <?php
                    break;
                case 'col_suspended_sub_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->suspended_sub_count); ?></td>
                    <?php
                    break;
                case 'col_cancelled_sub_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->cancelled_sub_count); ?></td>
                    <?php
                    break;
                case 'col_memberships':
                    $titles = [];
                    if (!empty($rec->memberships)) {
                        $ids = explode(',', $rec->memberships);
                        foreach ($ids as $membership_id) {
                              $membership = new MeprProduct($membership_id);
                              $titles[]   = esc_html($membership->post_title);
                        }
                    }
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(implode(', ', $titles)); ?></td>
                    <?php
                    break;
                case 'col_inactive_memberships':
                    $inactive_titles = [];
                    if (!empty($rec->inactive_memberships)) {
                        $ids = explode(',', $rec->inactive_memberships);

                        foreach ($ids as $membership_id) {
                              $membership        = new MeprProduct($membership_id);
                              $inactive_titles[] = esc_html($membership->post_title);
                        }
                    }
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(implode(', ', $inactive_titles)); ?></td>
                    <?php
                    break;
                case 'col_total_spent':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_currency($rec->total_spent, true, false)); ?></td>
                    <?php
                    break;
                case 'col_last_login_date':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_date($rec->last_login_date, esc_html__('Never', 'memberpress'))); ?></td>
                    <?php
                    break;
                case 'col_login_count':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->login_count); ?></td>
                    <?php
                    break;
                case 'col_registered':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_date($rec->registered)); ?></td>
                    <?php
                    break;
                default:
                    $attributes = 'class="' . esc_attr($class_value) . '"' . ($is_hidden ? ' style="display:none;"' : '');
                    MeprHooks::do_action('mepr_members_list_table_row', $attributes, $rec, $column_name, $column_display_name);
            }
        }
        ?>
    </tr>
        <?php
    }//end foreach
}//end if
