<?php
/**
 * View admin/usage/option.
 *
 * @var mixed $disable_senddata
 * @var mixed $hide_announcements
 */

defined('ABSPATH') || exit;
?>
<section id="mepr-section-usage">
    <br/>
    <h3><?php esc_html_e('Usage', 'memberpress'); ?></h3>
    <table id="mepr-option-enable-senddata" class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="mepr_enable_senddata">
                        <?php esc_html_e('Help Improve MemberPress by Sending Usage Data', 'memberpress'); ?>
                    </label>
                    <?php
                    MeprAppHelper::info_tooltip(
                        'mepr-enable-senddata',
                        esc_html__('Help Improve MemberPress by Sending Usage Data', 'memberpress'),
                        esc_html__('In order to help us improve MemberPress you can allow MemberPress to send usage data back to our developers. Any data that is sent to us will help us to fix issues, identify new features and generally improve MemberPress.', 'memberpress') // phpcs:ignore Generic.Files.LineLength.TooLong
                    );
                    ?>
                </th>
                <td>
                    <input type="checkbox" name="mepr_enable_senddata" id="mepr_enable_senddata"
                           <?php checked(!$disable_senddata); ?>>
                </td>
            </tr>
        </tbody>
    </table>
    <table id="mepr-option-hide-announcements" class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="mepr_hide_announcements"><?php esc_html_e('Hide Announcements', 'memberpress'); ?></label>
                    <?php
                    MeprAppHelper::info_tooltip(
                        'mepr-announcements',
                        __('Hide Announcements', 'memberpress'),
                        __('Enabling this option will hide announcements/notifications from MemberPress.', 'memberpress')
                    );
                    ?>
                </th>
                <td>
                    <input type="checkbox" name="mepr_hide_announcements" id="mepr_hide_announcements"
                           <?php checked($hide_announcements); ?>>
                </td>
            </tr>
        </tbody>
    </table>
    <table id="mepr-option-proactive-support" class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="mepr_proactive_notifications"><?php esc_html_e('Proactive Support Notifications', 'memberpress'); ?></label>
                    <?php
                    MeprAppHelper::info_tooltip(
                        'mepr-proactive-support',
                        esc_html__('Proactive Support Notifications', 'memberpress'),
                        esc_html__('Allow MemberPress to send proactive onboarding emails to the addresses listed under Admin Emails & Notices.', 'memberpress')
                    );
                    ?>
                </th>
                <td>
                    <input type="checkbox" name="mepr_proactive_notifications" id="mepr_proactive_notifications"
                        <?php checked($proactive_support_notifications); ?>>
                </td>
            </tr>
        </tbody>
    </table>
</section>
