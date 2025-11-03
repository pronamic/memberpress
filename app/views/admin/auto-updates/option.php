<?php
/**
 * View admin/auto-updates/option.
 *
 * @var MeprOptoins $mepr_options
 */

defined('ABSPATH') || exit;
?>
<section id="mepr-section-automatic-updates">
  <br/>
  <h3><?php esc_html_e('Automatic Updates', 'memberpress'); ?></h3>
  <table class="form-table">
    <tbody>
      <tr valign="top">
        <th scope="row">
          <?php esc_html_e('Enable automatic, background updates', 'memberpress'); ?>
          <?php MeprAppHelper::info_tooltip(
              'mepr-automatic-updates',
              __('Enable automatic, background updates', 'memberpress'),
              __('Enabling background updates will automatically update MemberPress to the latest version, or the latest minors version.', 'memberpress')
          ); ?>
        </th>
        <td>
          <p>
            <input type="radio" class="mepr-auto-update-option" name="<?php echo esc_attr($mepr_options->auto_updates_str); ?>" id="<?php echo esc_attr($mepr_options->auto_updates_str); ?>_all" value="all" <?php checked(empty($mepr_options->auto_updates) || 'all' === $mepr_options->auto_updates); ?>>
            <label for="<?php echo esc_attr($mepr_options->auto_updates_str); ?>_all"><?php printf(
                // Translators: %1$s: opening strong tag, %2$s: closing strong tag.
                esc_html__('%1$sAll Updates (recommended)%2$s - Get the latest features, bug fixes, and security updates as they are released.', 'memberpress'),
                '<strong>',
                '</strong>'
            ); ?></label>
          </p>
          <p>
            <input type="radio" class="mepr-auto-update-option" name="<?php echo esc_attr($mepr_options->auto_updates_str); ?>" id="<?php echo esc_attr($mepr_options->auto_updates_str); ?>_minor" value="minor" <?php checked($mepr_options->auto_updates, 'minor'); ?>>
            <label for="<?php echo esc_attr($mepr_options->auto_updates_str); ?>_minor"><?php printf(
                // Translators: %1$s: opening strong tag, %2$s: closing strong tag.
                esc_html__('%1$sMinor Updates Only%2$s - Get bug fixes and security updates, but not major features.', 'memberpress'),
                '<strong>',
                '</strong>'
            ); ?></label>
          </p>
          <p>
            <input type="radio" class="mepr-auto-update-option" name="<?php echo esc_attr($mepr_options->auto_updates_str); ?>" id="<?php echo esc_attr($mepr_options->auto_updates_str); ?>_none" value="none" <?php checked($mepr_options->auto_updates, 'none'); ?>>
            <label for="<?php echo esc_attr($mepr_options->auto_updates_str); ?>_none"><?php printf(
                // Translators: %1$s: opening strong tag, %2$s: closing strong tag.
                esc_html__('%1$sNone%2$s - Manually update everything.', 'memberpress'),
                '<strong>',
                '</strong>'
            ); ?></label>
          </p>
          <input type="hidden" id="<?php echo esc_attr($mepr_options->auto_updates_str); ?>_nonce" value="<?php echo esc_attr(wp_create_nonce('mp-auto-updates')); ?>">
        </td>
      </tr>
    </tbody>
  </table>
</section>
