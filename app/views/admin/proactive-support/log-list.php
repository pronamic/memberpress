<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>
<?php
$date_format     = get_option('date_format') . ' ' . get_option('time_format');
$status_counts   = $analytics['status_counts'] ?? [];
$send_totals     = $analytics['send_totals'] ?? [];
$trigger_totals  = $analytics['trigger_totals'] ?? [];
$admin_counts    = $analytics['admin_counts'] ?? [];
$total_records   = array_sum($status_counts);
$sent_total      = $send_totals['sent_total'] ?? 0;
$resend_total    = $send_totals['resend_total'] ?? 0;
$pending_total   = $status_counts['pending'] ?? 0;
$resolved_total  = $status_counts['resolved'] ?? 0;
$cancelled_total = $status_counts['cancelled'] ?? 0;
?>
<div class="wrap">
    <h1><?php esc_html_e('Proactive Support', 'memberpress'); ?></h1>

    <p><?php esc_html_e('Overview of proactive email sends and state changes for onboarding triggers.', 'memberpress'); ?></p>
    <?php
    $tab_labels = [
        'analytics'   => __('Analytics', 'memberpress'),
        'review'      => __('Manual Review Queue', 'memberpress'),
        'emails'      => __('Activity', 'memberpress'),
        'events'      => __('System Events', 'memberpress'),
        'preferences' => __('Preferences', 'memberpress'),
    ];
    ?>
    <h2 class="nav-tab-wrapper">
        <?php foreach ($tab_labels as $tab_key => $label) : ?>
            <?php
            $url     = add_query_arg('tab', $tab_key, admin_url('admin.php?page=memberpress-proactive-support'));
            $classes = 'nav-tab' . ($current_tab === $tab_key ? ' nav-tab-active' : '');
            ?>
            <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($classes); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </h2>

    <style>
    .mepr-analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin: 16px 0;
    }
    .mepr-analytics-card {
        background: #fff;
        border: 1px solid #dcdcde;
        padding: 12px;
        border-radius: 4px;
    }
    .mepr-analytics-card h3 {
        margin: 0 0 6px;
        font-size: 13px;
        text-transform: uppercase;
        color: #50575e;
    }
    .mepr-analytics-card strong {
        font-size: 20px;
        display: block;
    }
    </style>
    <?php settings_errors('mepr_proactive_support'); ?>
    <?php if (isset($_GET['mepr_proactive_notice'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <?php $notice = sanitize_key(wp_unslash($_GET['mepr_proactive_notice'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <?php if ($notice === 'opted_out') : ?>
            <div class="notice notice-success"><p><?php esc_html_e('You have been opted out of proactive support emails.', 'memberpress'); ?></p></div>
        <?php elseif ($notice === 'opted_in') : ?>
            <div class="notice notice-success"><p><?php esc_html_e('You have been opted in to proactive support emails.', 'memberpress'); ?></p></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($current_tab === 'analytics') : ?>
        <h2><?php esc_html_e('Analytics Overview', 'memberpress'); ?></h2>
        <div class="mepr-analytics-grid">
            <div class="mepr-analytics-card">
                <h3><?php esc_html_e('Total Records', 'memberpress'); ?></h3>
                <strong><?php echo esc_html($total_records); ?></strong>
            </div>
            <div class="mepr-analytics-card">
                <h3><?php esc_html_e('Emails Sent', 'memberpress'); ?></h3>
                <strong><?php echo esc_html($sent_total); ?></strong>
            </div>
            <div class="mepr-analytics-card">
                <h3><?php esc_html_e('Resends', 'memberpress'); ?></h3>
                <strong><?php echo esc_html($resend_total); ?></strong>
            </div>
            <div class="mepr-analytics-card">
                <h3><?php esc_html_e('Pending', 'memberpress'); ?></h3>
                <strong><?php echo esc_html($pending_total); ?></strong>
            </div>
            <div class="mepr-analytics-card">
                <h3><?php esc_html_e('Resolved', 'memberpress'); ?></h3>
                <strong><?php echo esc_html($resolved_total); ?></strong>
            </div>
            <div class="mepr-analytics-card">
                <h3><?php esc_html_e('Cancelled', 'memberpress'); ?></h3>
                <strong><?php echo esc_html($cancelled_total); ?></strong>
            </div>
            <div class="mepr-analytics-card">
                <h3><?php esc_html_e('Admins Reached', 'memberpress'); ?></h3>
                <strong><?php echo esc_html($admin_counts['admins_reached'] ?? 0); ?></strong>
                <span>
                    <?php
                    printf(
                        // Translators: %d: total admin count.
                        esc_html__('of %d admins', 'memberpress'),
                        esc_html($admin_counts['total_admins'] ?? 0)
                    );
                    ?>
                </span>
            </div>
        </div>

        <?php if (!empty($trigger_totals)) : ?>
            <table class="widefat striped" style="margin-bottom:20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Trigger', 'memberpress'); ?></th>
                        <th><?php esc_html_e('Total Records', 'memberpress'); ?></th>
                        <th><?php esc_html_e('Emails Sent', 'memberpress'); ?></th>
                        <th><?php esc_html_e('Resends', 'memberpress'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trigger_totals as $trigger_row) : ?>
                        <tr>
                            <td><?php echo esc_html(MeprProactiveSupportHelper::trigger_label($trigger_row['trigger'])); ?></td>
                            <td><?php echo esc_html($trigger_row['total']); ?></td>
                            <td><?php echo esc_html($trigger_row['sent_count']); ?></td>
                            <td><?php echo esc_html($trigger_row['resend_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($current_tab === 'preferences') : ?>
        <form method="post" class="mepr-proactive-preferences">
            <?php wp_nonce_field('mepr_proactive_settings'); ?>
            <input type="hidden" name="mepr_proactive_action" value="save_proactive_settings" />

            <h2><?php esc_html_e('Preferences', 'memberpress'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Opt-Out List', 'memberpress'); ?></th>
                    <td>
                        <textarea name="mepr_proactive_opt_out_emails" rows="6" cols="60"><?php echo esc_textarea(implode("\n", $opted_out_emails ?? [])); ?></textarea>
                        <p class="description"><?php esc_html_e('Enter email addresses, one per line, to opt specific recipients out of proactive emails.', 'memberpress'); ?></p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Preferences', 'memberpress'); ?></button>
            </p>
        </form>
    <?php endif; ?>

    <?php if ($current_tab === 'review') : ?>
        <?php if (isset($_GET['mepr_proactive_review'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <?php $review_notice = sanitize_key(wp_unslash($_GET['mepr_proactive_review'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <div class="notice notice-success">
                <p>
                    <?php
                    switch ($review_notice) {
                        case 'resolved':
                            esc_html_e('Notification marked as resolved.', 'memberpress');
                            break;
                        case 'cancelled':
                            esc_html_e('Notification cancelled.', 'memberpress');
                            break;
                        case 'resent':
                            esc_html_e('Notification queued to resend after the next cron run.', 'memberpress');
                            break;
                        case 'not_found':
                            esc_html_e('The requested record could not be found.', 'memberpress');
                            break;
                        case 'invalid':
                            esc_html_e('Invalid review action.', 'memberpress');
                            break;
                        default:
                            esc_html_e('Review queue updated.', 'memberpress');
                            break;
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
        <h2><?php esc_html_e('Manual Review Queue', 'memberpress'); ?></h2>
        <p><?php esc_html_e('Manage pending proactive notifications below. Mark them resolved, cancel, or queue for resend.', 'memberpress'); ?></p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Recipient', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Site', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Trigger', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Status', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Created', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Actions', 'memberpress'); ?></th>
                </tr>
        </thead>
        <tbody>
            <?php if (empty($queue)) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('Nothing in the manual review queue.', 'memberpress'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($queue as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row->id); ?></td>
                        <td><?php echo esc_html($row->admin_email ?: __('Unknown', 'memberpress')); ?></td>
                        <td>
                            <?php
                            $meta      = json_decode($row->meta ?? '', true);
                            $site_name = $meta['site_name'] ?? __('Unknown site', 'memberpress');
                            $site_url  = $meta['site_url'] ?? '';
                            if (!empty($site_url)) {
                                printf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($site_url), esc_html($site_name));
                            } else {
                                echo esc_html($site_name);
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(MeprProactiveSupportHelper::trigger_label($row->trigger_type)); ?></td>
                        <td><?php echo esc_html(ucfirst($row->status)); ?></td>
                        <td><?php echo esc_html(get_date_from_gmt($row->created_at, $date_format)); ?></td>
                        <td class="mepr-proactive-actions">
                            <?php
                            $review_actions = [
                                'resolve' => __('Resolve', 'memberpress'),
                                'cancel'  => __('Cancel', 'memberpress'),
                                'resend'  => __('Resend', 'memberpress'),
                            ];
                            ?>
                            <?php foreach ($review_actions as $action_key => $label) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:6px;">
                                    <?php wp_nonce_field('mepr_proactive_review_action'); ?>
                                    <input type="hidden" name="action" value="mepr_proactive_review" />
                                    <input type="hidden" name="record_id" value="<?php echo esc_attr($row->id); ?>" />
                                    <input type="hidden" name="review_action" value="<?php echo esc_attr($action_key); ?>" />
                                    <button type="submit" class="button button-secondary"><?php echo esc_html($label); ?></button>
                                </form>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        </table>
    <?php endif; ?>

    <?php if ($current_tab === 'emails') : ?>
        <h2><?php esc_html_e('Activity', 'memberpress'); ?></h2>
        <form method="get" class="mepr-proactive-filters" style="margin-bottom:16px;">
        <input type="hidden" name="page" value="memberpress-proactive-support" />
        <input type="hidden" name="tab" value="emails" />
        <label>
            <?php esc_html_e('Trigger:', 'memberpress'); ?>
            <select name="mepr_trigger">
                <option value=""><?php esc_html_e('All', 'memberpress'); ?></option>
                <?php foreach (MeprProactiveSupportHelper::get_triggers() as $trigger_key => $trigger_config) : ?>
                    <option value="<?php echo esc_attr($trigger_key); ?>" <?php selected($filters['trigger'], $trigger_key); ?>><?php echo esc_html($trigger_config['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <?php esc_html_e('Status:', 'memberpress'); ?>
            <select name="mepr_status">
                <option value=""><?php esc_html_e('All', 'memberpress'); ?></option>
                <?php foreach ($statuses as $status_option) : ?>
                    <option value="<?php echo esc_attr($status_option); ?>" <?php selected($filters['status'], $status_option); ?>><?php echo esc_html(ucfirst($status_option)); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <?php esc_html_e('Search Email:', 'memberpress'); ?>
            <input type="search" name="mepr_search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Email address', 'memberpress'); ?>" />
        </label>
        <button type="submit" class="button"><?php esc_html_e('Filter', 'memberpress'); ?></button>
        <a class="button" href="<?php echo esc_url(add_query_arg('tab', 'emails', admin_url('admin.php?page=memberpress-proactive-support'))); ?>"><?php esc_html_e('Reset', 'memberpress'); ?></a>
        <?php
        $export_url = wp_nonce_url(add_query_arg([
            'action'       => 'mepr_proactive_export',
            'mepr_trigger' => $filters['trigger'],
            'mepr_status'  => $filters['status'],
            'mepr_search'  => $filters['search'],
        ], admin_url('admin-post.php')), 'mepr_proactive_export');
        ?>
        <a class="button button-secondary" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'memberpress'); ?></a>
    </form>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Recipient', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Site', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Trigger', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Status', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Email Sent At', 'memberpress'); ?></th>
                    <th><?php esc_html_e('Created', 'memberpress'); ?></th>
                </tr>
        </thead>
        <tbody>
            <?php if (empty($records)) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No proactive emails have been recorded yet.', 'memberpress'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($records as $record) : ?>
                    <tr>
                        <td><?php echo esc_html($record->id); ?></td>
                        <td><?php echo esc_html($record->admin_email ?: __('Unknown', 'memberpress')); ?></td>
                        <td>
                            <?php
                            $meta      = json_decode($record->meta ?? '', true);
                            $site_name = $meta['site_name'] ?? __('Unknown site', 'memberpress');
                            $site_url  = $meta['site_url'] ?? '';
                            if (!empty($site_url)) {
                                printf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($site_url), esc_html($site_name));
                            } else {
                                echo esc_html($site_name);
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(MeprProactiveSupportHelper::trigger_label($record->trigger_type)); ?></td>
                        <td><?php echo esc_html(ucfirst($record->status)); ?></td>
                        <td>
                            <?php
                            if (!empty($record->email_sent_at)) {
                                echo esc_html(get_date_from_gmt($record->email_sent_at, $date_format));
                            } else {
                                echo '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(get_date_from_gmt($record->created_at, $date_format)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        </table>
    <?php endif; ?>

    <?php if ($current_tab === 'events') : ?>
        <h2><?php esc_html_e('System Events', 'memberpress'); ?></h2>
        <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Time', 'memberpress'); ?></th>
                <th><?php esc_html_e('Trigger', 'memberpress'); ?></th>
                <th><?php esc_html_e('Action', 'memberpress'); ?></th>
                <th><?php esc_html_e('Recipient', 'memberpress'); ?></th>
                <th><?php esc_html_e('Notes', 'memberpress'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e('No system events logged yet.', 'memberpress'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(get_date_from_gmt($log['timestamp'], $date_format)); ?></td>
                        <td><?php echo esc_html(MeprProactiveSupportHelper::trigger_label($log['trigger'])); ?></td>
                        <td><?php echo esc_html(ucfirst($log['action'])); ?></td>
                        <td><?php echo esc_html($log['admin_email'] ?? __('Unknown', 'memberpress')); ?></td>
                        <td><?php echo esc_html($log['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        </table>
    <?php endif; ?>
</div>
