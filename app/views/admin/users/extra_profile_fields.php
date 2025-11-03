<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<h3><?php esc_html_e('Membership Information', 'memberpress'); ?></h3>

<table class="form-table mepr-form">
  <tbody>
    <?php if ($mepr_options->require_privacy_policy) : ?>
      <tr>
        <th>
          <label><?php esc_html_e('Privacy Policy', 'memberpress') ?></label>
        </th>
        <td>
          <?php
            if (get_user_meta($user->ID, 'mepr_agree_to_privacy_policy', false)) {
                esc_html_e('User has consented to the Privacy Policy', 'memberpress');
            } else {
                esc_html_e('User has NOT consented to the Privacy Policy', 'memberpress');
            }
            ?>
        </td>
      </tr>
    <?php endif; ?>
    <?php if ($mepr_options->require_tos) : ?>
      <tr>
        <th>
          <label><?php esc_html_e('Terms of Service', 'memberpress') ?></label>
        </th>
        <td>
          <?php
            if (get_user_meta($user->ID, 'mepr_agree_to_tos', false)) {
                esc_html_e('User has consented to the Terms of Service', 'memberpress');
            } else {
                esc_html_e('User has NOT consented to the Terms of Service', 'memberpress');
            }
            ?>
        </td>
      </tr>
    <?php endif; ?>
    <tr>
      <th>
        <label for="mepr-geo-country"><?php esc_html_e('Signup Location', 'memberpress'); ?></label>
      </th>
      <td>
        <?php
        $geo_country = get_user_meta($user->ID, 'mepr-geo-country', true);
        if ($geo_country) {
            $countries = MeprUtils::countries(false);
            printf(esc_html($countries[$geo_country]));
        } else {
            esc_html_e('Unknown', 'memberpress');
        }
        ?>
        <p class="description"><?php esc_html_e('Detected on user\'s initial signup', 'memberpress'); ?></p>
      </td>
    </tr>
  <?php
    MeprUsersHelper::render_editable_custom_fields($user);

    if (MeprUtils::is_mepr_admin()) { // Allow admins to see.
        ?>
      <tr>
        <td colspan="2">
          <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-trans&search=' . urlencode($user->user_email)) . '&search-field=email'); ?>" class="button"><?php esc_html_e("View Member's Transactions", 'memberpress');?></a>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-subscriptions&search=' . urlencode($user->user_email)) . '&search-field=email'); ?>" class="button"><?php esc_html_e("View Member's Subscriptions", 'memberpress');?></a>
        </td>
        <?php MeprHooks::do_action('mepr_after_user_profile_subs_btn', $user); ?>
      </tr>
      <tr>
        <td colspan="2">
          <div class="mepr-resend-welcome-email-wrapper">
            <?php
            printf(
                '<a class="button mepr-resend-welcome-email" href="#" data-uid="%s" data-nonce="%s">%s</a>',
                esc_attr($user->ID),
                esc_attr(wp_create_nonce('mepr_resend_welcome_email')),
                esc_html__('Resend MemberPress Welcome Email', 'memberpress')
            );
            ?>
            <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" class="mepr-resend-welcome-email-loader">
            <span class="mepr-resend-welcome-email-message"></span>
          </div>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <h4><?php esc_html_e('Custom MemberPress Account Message', 'memberpress'); ?></h4>
          <?php wp_editor($user->user_message, MeprUser::$user_message_str); ?>
        </td>
      </tr>
        <?php
    }

    MeprHooks::do_action('mepr_extra_profile_fields', $user);
    ?>
  </tbody>
</table>
