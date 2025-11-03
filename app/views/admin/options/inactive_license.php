<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<p class="description">
  <?php
    printf(
      // Translators: %1$s: opening link tag for website, %2$s: closing link tag, %3$s: opening link tag for login, %4$s: closing link tag.
        esc_html__('A License Key is required to enable automatic updates for MemberPress, access admin areas, and receive support. If you don\'t have a license, please %1$svisit our website%2$s to get one. If you already have a license, you can %3$slog in to your account%4$s to manage your licenses and site activations.', 'memberpress'),
        '<a href="' . esc_url(MeprUtils::get_link_url('home')) . '">',
        '</a>',
        '<a href="' . esc_url(MeprUtils::get_link_url('login')) . '">',
        '</a>'
    );
    ?>
</p>

<table class="form-table">
  <tr class="form-field">
    <th valign="top"><?php esc_html_e('License Key:', 'memberpress'); ?></th>
    <td>
      <input type="text" id="mepr-license-key" value="<?php echo esc_attr($mepr_options->mothership_license); ?>" />
      <button type="button" id="mepr-activate-license-key" class="button button-primary"><?php esc_html_e('Activate License Key', 'memberpress'); ?></button>
    </td>
  </tr>
</table>
