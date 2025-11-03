<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

if (!empty($records)) {
    $row_index = 0;
    foreach ($records as $rec) {
        $alternate = ($row_index++ % 2 ? '' : 'alternate');

        // Open the line.
        ?>
        <tr id="record_<?php echo esc_attr($rec->id); ?>" class="<?php echo esc_attr($alternate); ?>">
            <?php
            foreach ($columns as $column_name => $column_display_name) {
                // Style attributes for each col.
                $class_value = $column_name . ' column-' . $column_name;
                $is_hidden = in_array($column_name, $hidden, true);

                $editlink = admin_url('user-edit.php?user_id=' . (int) $rec->user_id);

                // Display the cell.
                switch ($column_name) {
                    case 'col_id':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->id); ?></td>
                        <?php
                        break;
                    case 'col_created_at':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_date($rec->created_at)); ?></td>
                        <?php
                        break;
                    case 'col_expires_at':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                            <?php echo esc_html(MeprAppHelper::format_date($rec->expires_at, __('Never', 'memberpress'))); ?>
                        </td>
                        <?php
                        break;
                    case 'col_user_login':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                            <?php if (!empty($rec->user_id)) : ?>
                                <a href="<?php echo esc_url($editlink); ?>"
                                    title="<?php esc_attr_e('View member\'s profile', 'memberpress'); ?>"><?php echo esc_html($rec->user_login); ?></a>
                            <?php else : ?>
                                <?php esc_html_e('Deleted', 'memberpress'); ?>
                            <?php endif; ?>
                        </td>
                        <?php
                        break;
                    case 'col_product':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                            <?php
                            if ($rec->product_id) {
                                $prd = new MeprProduct($rec->product_id);
                                echo '<a href="' . esc_url($prd->edit_url()) . '">' . esc_html($rec->product_name) . '</a>';
                            } else {
                                esc_html_e('Unknown', 'memberpress');
                            }
                            ?>
                        </td>
                        <?php
                        break;
                    case 'col_payment_system':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html($rec->gateway); ?></td>
                        <?php
                        break;
                    case 'col_trans_num':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-trans&action=edit&id=' . $rec->id)); ?>"
                                title="<?php esc_attr_e('Edit transaction', 'memberpress'); ?>" <?php echo MeprTransactionsHelper::get_tooltip_attributes($rec); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><b><?php echo esc_html($rec->trans_num); ?></b></a> <img
                                src="<?php echo esc_url(MEPR_IMAGES_URL . '/square-loader.gif'); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>"
                                class="mepr_loader" />
                            <div class="mepr-row-actions">
                                <?php echo MeprUtils::render_row_action_links(MeprTransactionsHelper::get_admin_action_links($rec)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </td>
                        <?php
                        break;
                    case 'col_subscr_id':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                            <?php
                            if (!empty($rec->sub_id)) :
                                ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-subscriptions&subscription=' . $rec->sub_id)); ?>"
                                    title="<?php esc_attr_e('View Subscription', 'memberpress'); ?>"><?php echo esc_html($rec->subscr_id); ?></a>
                                <?php
                            else :
                                esc_html_e('None', 'memberpress');
                            endif;
                            ?>
                        </td><?php
                        break;
                    case 'col_net':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_currency($rec->amount, true, false)); ?></td>
                        <?php
                        break;
                    case 'col_tax':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_currency($rec->tax_amount, true, false)); ?></td>
                        <?php
                        break;
                    case 'col_total':
                        if ((float) $rec->total === 0.00 && (float) $rec->amount > 0.00) : ?>
                            <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_currency($rec->amount, true, false)); ?></td>
                        <?php else : ?>
                            <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>><?php echo esc_html(MeprAppHelper::format_currency($rec->total, true, false)); ?></td>
                        <?php endif;
                        break;
                    case 'col_status':
                        ?>
                        <td class="<?php echo esc_attr($class_value); ?>"<?php echo $is_hidden ? ' style="display:none;"' : ''; ?>>
                            <div class="status_initial status_initial_<?php echo esc_attr($rec->id); ?>" data-value="<?php echo esc_attr($rec->id); ?>">
                                <a href=""
                                    title="<?php esc_attr_e('Change transaction\'s status', 'memberpress'); ?>"><?php echo esc_html(MeprAppHelper::human_readable_status($rec->status)); ?></a>
                            </div>
                            <div class="status_editable status_editable_<?php echo esc_attr($rec->id); ?>">
                                <?php
                                MeprAppHelper::info_tooltip(
                                    'mepr-transactions-status-' . $rec->id,
                                    __('Editing Transaction Status', 'memberpress'),
                                    __("Changing the status here will ONLY change the status on your site, not at the Gateway itself. To cancel a Transaction either you or the member should click the Cancel link next to the Subscription. You must use your Payment Gateway's web interface to refund a transaction.", 'memberpress')
                                );
                                ?>
                                <select class="status_edit status_edit_<?php echo esc_attr($rec->id); ?>" data-value="<?php echo esc_attr($rec->id); ?>">
                                    <option value="pending" <?php echo (stripslashes($rec->status) === 'pending') ? 'selected="selected"' : ''; ?>><?php esc_html_e('Pending', 'memberpress'); ?></option>
                                    <option value="failed" <?php echo (stripslashes($rec->status) === 'failed') ? 'selected="selected"' : ''; ?>><?php esc_html_e('Failed', 'memberpress'); ?></option>
                                    <option value="refunded" <?php echo (stripslashes($rec->status) === 'refunded') ? 'selected="selected"' : ''; ?>><?php esc_html_e('Refunded', 'memberpress'); ?></option>
                                    <option value="complete" <?php echo (stripslashes($rec->status) === 'complete') ? 'selected="selected"' : ''; ?>><?php esc_html_e('Complete', 'memberpress'); ?></option>
                                </select><br />
                                <a href="" class="button status_save"
                                    data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Save', 'memberpress'); ?></a>
                                <a href="" class="button cancel_change"
                                    data-value="<?php echo esc_attr($rec->id); ?>"><?php esc_html_e('Cancel', 'memberpress'); ?></a>
                            </div>
                            <div class="status_saving status_saving_<?php echo esc_attr($rec->id); ?>">
                                <?php esc_html_e('Saving ...', 'memberpress'); ?>
                            </div>
                        </td>
                        <?php
                        break;
                    case 'col_propername':
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
                        MeprHooks::do_action('mepr_admin_transactions_cell', $column_name, $rec, $attributes);
                        break;
                }
            }
            ?>
        </tr>
        <?php
    }
}
