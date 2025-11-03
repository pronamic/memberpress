<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

// Loop for each record.
if (!empty($records)) {
    $row_index = 0;
    foreach ($records as $rec) {
        $alternate = ( $row_index++ % 2 ? '' : 'alternate' );

        ?>
    <tr id="record_<?php echo esc_attr($rec->id); ?>" class="<?php echo esc_attr($alternate); ?>">
        <?php
        foreach ($columns as $column_name => $column_display_name) {
            // Style attributes for each col.
            $class_value = $column_name . ' column-' . $column_name;
            $is_hidden = in_array($column_name, $hidden, true);

            $editlink = admin_url('user-edit.php?user_id=' . (int)$rec->user_id);

            // Display the cell.
            switch ($column_name) {
                case 'col_id':
                case 'col_txn_id':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->id); ?></td>
                    <?php
                    break;
                case 'col_created_at':
                case 'col_txn_created_at':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_date($rec->created_at)); ?></td>
                    <?php
                    break;
                case 'col_subscr_id':
                    $view_url = admin_url("admin.php?page=memberpress-trans&subscription={$rec->id}");
                    $add_url  = admin_url("admin.php?page=memberpress-trans&action=new&subscription={$rec->id}");
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><b><?php echo esc_html($rec->subscr_id); ?></b> <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/square-loader.gif'); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" class="mepr_loader" />
            <div class="mepr-row-actions">
              <a href="<?php echo esc_url($add_url); ?>" title="<?php esc_attr_e('Manually add a transaction to this subscription', 'memberpress'); ?>"><?php esc_html_e('Add Txn', 'memberpress'); ?></a> |
              <a href="<?php echo esc_url($view_url); ?>" title="<?php esc_attr_e('View related transactions', 'memberpress'); ?>"><?php esc_html_e('View Txns', 'memberpress'); ?></a> |
                    <?php
                    $sub = new MeprSubscription($rec->id);
                    if ($sub->can('suspend-subscriptions')) :
                        if ($sub->status === MeprSubscription::$active_str) {
                            $hide_suspend = '';
                            $hide_resume  = ' mepr-hidden';
                        } elseif ($sub->status === MeprSubscription::$suspended_str) {
                            $hide_suspend = ' mepr-hidden';
                            $hide_resume  = '';
                        } else {
                            $hide_suspend = $hide_resume = ' mepr-hidden';
                        }
                        ?>
                <span class="mepr-suspend-sub-action<?php echo esc_attr($hide_suspend); ?>">
                  <a href="" class="mepr-suspend-sub" title="<?php esc_attr_e('Pause Subscription', 'memberpress'); ?>" data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Pause', 'memberpress'); ?></a> |
                </span>
                <span class="mepr-resume-sub-action<?php echo esc_attr($hide_resume); ?>">
                  <a href="" class="mepr-resume-sub" title="<?php esc_attr_e('Resume Subscription', 'memberpress'); ?>" data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Resume', 'memberpress'); ?></a> |
                </span>
                        <?php
                    endif;

                    ?>
              <span class="mepr-edit-sub-action">
                <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-subscriptions&action=edit&id=' . $rec->id)); ?>" title="<?php esc_attr_e('Edit Subscription', 'memberpress'); ?>"><?php esc_html_e('Edit', 'memberpress'); ?></a> |
              </span>
                    <?php
                    if ($sub->status === MeprSubscription::$active_str and $sub->can('cancel-subscriptions')) :
                        ?>
                <span class="mepr-cancel-sub-action">
                  <a href="" class="mepr-cancel-sub" title="<?php esc_attr_e('Cancel Subscription', 'memberpress'); ?>" data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Cancel', 'memberpress'); ?></a> |
                </span>
                        <?php
                    endif;
                    ?>
              <a href="" class="remove-sub-row" title="<?php esc_attr_e('Delete Subscription', 'memberpress'); ?>" data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Delete', 'memberpress'); ?></a>
            </div>
          </td>
                    <?php
                    break;
                case 'col_txn_subscr_id':
                    $include_confirmations = '';
                    if ($rec->sub_type === 'transaction' && $rec->id > 0) {
                        $txn = new MeprTransaction($rec->id);
                        if ($txn->txn_type === 'sub_account') {
                                $include_confirmations = '&include-confirmations';
                        }
                    }
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
            <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-trans&transaction=') . $rec->id . $include_confirmations); ?>" title="<?php esc_attr_e('Show related transaction', 'memberpress'); ?>"><b><?php echo esc_html($rec->subscr_id); ?></b></a>
          </td>
                    <?php
                    break;
                case 'col_txn_count':
                case 'col_txn_txn_count':
                    $view_url = admin_url("admin.php?page=memberpress-trans&subscription={$rec->id}");
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
            <a href="<?php echo esc_url($view_url); ?>" title="<?php esc_attr_e('Show related transactions', 'memberpress'); ?>"><?php echo esc_html($rec->txn_count); ?></a>
          </td>
                    <?php
                    break;
                case 'col_member':
                case 'col_txn_member':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php if (!empty($rec->user_id)) : ?>
              <a href="<?php echo esc_url($editlink); ?>" title="<?php esc_attr_e("View member's profile", 'memberpress'); ?>"><?php echo esc_html(stripslashes($rec->member)); ?></a>
                    <?php else : ?>
                        <?php esc_html_e('Deleted', 'memberpress'); ?>
                    <?php endif; ?>
          </td>
                    <?php
                    break;
                case 'col_gateway':
                case 'col_txn_gateway':
                    $pm = $mepr_options->payment_method($rec->gateway);
                    if ($pm) {
                        $pm_str = "{$pm->label} ({$pm->name})";
                    } else {
                        $pm_str = ucwords($rec->gateway);
                    }
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($pm_str); ?></td>
                    <?php
                    break;
                case 'col_product':
                case 'col_txn_product':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php
                    if ($rec->product_id) {
                        $prd          = new MeprProduct($rec->product_id);
                        echo '<a href="' . esc_url($prd->edit_url()) . '">' . esc_html($rec->product_name) . '</a>';
                    } else {
                        esc_html_e('Unknown', 'memberpress');
                    }
                    ?>
          </td>
                    <?php
                    break;
                case 'col_product_meta':
                case 'col_txn_product_meta':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php
                    if ($table->lifetime) {
                          $txn = new MeprTransaction($rec->id);
                          echo esc_html(MeprTransactionsHelper::format_currency($txn));
                    } elseif ($rec->status === MeprSubscription::$pending_str) {
                        $prd = new MeprProduct($rec->product_id);
                        $sub = new MeprSubscription();
                        $sub->load_product_vars($prd);
                        $sub->coupon_id = $rec->coupon_id;
                        echo esc_html(MeprSubscriptionsHelper::format_currency($sub, true, false));
                    } else {
                        $sub = new MeprSubscription($rec->id);
                        $txn = $sub->latest_txn();

                        if ($txn instanceof MeprTransaction) {
                            echo esc_html(MeprTransactionsHelper::format_currency($txn));
                        } else {
                            echo esc_html(MeprSubscriptionsHelper::format_currency($sub));
                        }
                    }
                    ?>
          </td>
                    <?php
                    break;
                case 'col_active':
                case 'col_txn_active':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo wp_kses($rec->active, ['span' => ['class' => []]]); ?></td>
                    <?php
                    break;
                case 'col_expires_at':
                case 'col_txn_expires_at':
                    $expire_ts = is_null($rec->expires_at) ? 0 : strtotime($rec->expires_at);
                    $lifetime  = (MeprAppHelper::format_date($rec->expires_at, 0) === 0);
                    $expired   = !$lifetime && $expire_ts < current_time('timestamp');

                    if ($table->lifetime) {
                        $default = __('Never', 'memberpress');
                    } else {
                        $sub = new MeprSubscription($rec->id);
                        $txn = $sub->latest_txn();

                        if (!($txn instanceof MeprTransaction) || $txn->id <= 0) {
                            $default = __('Unknown', 'memberpress');
                        } elseif (trim($txn->expires_at) === MeprUtils::db_lifetime() || empty($txn->expires_at)) {
                            $default = __('Never', 'memberpress');
                        } else {
                            $default = __('Unknown', 'memberpress');
                        }
                    }

                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><span <?php echo $expired ? 'class="mepr-inactive"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_date($rec->expires_at, $default)); ?></span></td>
                    <?php
                    break;
                case 'col_status':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
            <a href="" id="status-row-<?php echo esc_attr($rec->id); ?>" class="status_editable" data-value="<?php echo esc_attr($rec->id); ?>"><?php echo esc_html(MeprAppHelper::human_readable_status($rec->status, 'subscription')); ?></a>
            <div id="status-hidden-<?php echo esc_attr($rec->id); ?>" class="status_hidden">
                    <?php
                    MeprAppHelper::info_tooltip(
                        'mepr-subscriptions-status-' . $rec->id,
                        __('Editing Subscription Status', 'memberpress'),
                        __('Modifying the Auto Rebill status here will change the status of the Subscription ONLY on your site, not at the Gateway itself. To cancel a Subscription, either you or the member must click on Cancel.', 'memberpress')
                    );
                    ?>
              <select id="status-select-<?php echo esc_attr($rec->id); ?>" class="status_select" data-value="<?php echo esc_attr($rec->id); ?>">
                <option value="<?php echo esc_attr(MeprSubscription::$pending_str); ?>"><?php esc_html_e('Pending', 'memberpress'); ?></option>
                <option value="<?php echo esc_attr(MeprSubscription::$active_str); ?>"><?php esc_html_e('Enabled', 'memberpress'); ?></option>
                <option value="<?php echo esc_attr(MeprSubscription::$suspended_str); ?>"><?php esc_html_e('Paused', 'memberpress'); ?></option>
                <option value="<?php echo esc_attr(MeprSubscription::$cancelled_str); ?>"><?php esc_html_e('Stopped', 'memberpress'); ?></option>
              </select><br/>
              <a href="" class="button status_save" data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Save', 'memberpress'); ?></a>
              <a href="" class="button cancel_change" data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Cancel', 'memberpress'); ?></a>
            </div>

            <div id="status-saving-<?php echo esc_attr($rec->id); ?>" class="status_saving">
                    <?php esc_html_e('Saving...', 'memberpress'); ?>
            </div>
          </td>
                    <?php
                    break;
                case 'col_txn_status':
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php echo esc_html($rec->status); ?>
          </td>
                    <?php
                    break;
                case 'col_propername':
                case 'col_txn_propername':
                    if (empty($rec->first_name) && empty($rec->last_name)) {
                        $full_name = __('Unknown', 'memberpress');
                    } elseif (empty($rec->first_name) && !empty($rec->last_name)) {
                        $full_name = stripslashes($rec->last_name);
                    } elseif (!empty($rec->first_name) && empty($rec->last_name)) {
                        $full_name = stripslashes($rec->first_name);
                    } else {
                        $full_name = stripslashes($rec->last_name) . ', ' . stripslashes($rec->first_name);
                    }
                    ?>
          <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                    <?php echo esc_html($full_name); ?>
          </td>
                    <?php
                    break;
                default:
                    $attributes = 'class="' . esc_attr($class_value) . '"' . ($is_hidden ? ' style="display:none;"' : '');
                    MeprHooks::do_action('mepr_admin_subscriptions_cell', $column_name, $rec, $table, $attributes);
                    break;
            }
        }

        ?>
    </tr>
        <?php
    }//end foreach
}//end if
