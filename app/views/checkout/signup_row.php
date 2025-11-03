<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="mp-form-row mepr_custom_field mepr_<?php echo esc_attr($line->field_key); ?><?php echo ($line->required) ? ' mepr-field-required' : ''; ?>">
  <?php if (in_array($line->field_type, ['radios', 'checkboxes'], true)) { ?>
    <fieldset>
      <legend class="screen-reader-text"><?php echo esc_html($line->field_name); ?></legend>
  <?php } ?>
  <?php if ($line->field_type !== 'checkbox') : ?>
    <div class="mp-form-label">
      <label class="placeholder-text" for="<?php echo esc_attr($line->field_key . $unique_suffix); ?>">
          <?php
            echo esc_html(
                sprintf(
                    // Translators: %1$s: custom field name, %2$s: required asterisk.
                    _x('%1$s:%2$s', 'ui', 'memberpress'),
                    stripslashes($line->field_name),
                    $required
                )
            );
            ?>
      </label>
        <?php // Here for email custom fields that are not required. ?>
      <span id="<?php echo esc_attr($line->field_key); ?>_error" class="cc-error">
          <?php
            if ($line->required) {
                echo esc_html(
                    sprintf(
                        // Translators: %s: custom field name.
                        _x('%s is Required', 'ui', 'memberpress'),
                        stripslashes($line->field_name)
                    )
                );
            } else {
                echo esc_html(
                    sprintf(
                        // Translators: %s: custom field name.
                        _x('%s is not valid', 'ui', 'memberpress'),
                        stripslashes($line->field_name)
                    )
                );
            }
            ?>
      </span>
    </div>
  <?php endif; ?>
  <?php echo MeprUsersHelper::render_custom_field($line, $value, [], $unique_suffix); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <?php if (in_array($line->field_type, ['radios', 'checkboxes'], true)) { ?>
    </fieldset>
  <?php } ?>
</div>
