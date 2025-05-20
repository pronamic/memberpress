<?php

/**
 * Custom fields options view
 */

defined('ABSPATH') || exit;
?>
<select name="mepr-custom-fields[<?php echo $random_id; ?>][type]" class="mepr-custom-fields-select" data-value="<?php echo $random_id; ?>">
  <option value="text" <?php selected($line->field_type, 'text'); ?>><?php _e('Text', 'memberpress'); ?></option>
  <option value="email" <?php selected($line->field_type, 'email'); ?>><?php _e('Email', 'memberpress'); ?></option>
  <option value="url" <?php selected($line->field_type, 'url'); ?>><?php _e('URL', 'memberpress'); ?></option>
  <option value="tel" <?php selected($line->field_type, 'tel'); ?>><?php _e('Phone', 'memberpress'); ?></option>
  <option value="date" <?php selected($line->field_type, 'date'); ?>><?php _e('Date', 'memberpress'); ?></option>
  <option value="textarea" <?php selected($line->field_type, 'textarea'); ?>><?php _e('Textarea', 'memberpress'); ?></option>
  <option value="checkbox" <?php selected($line->field_type, 'checkbox'); ?>><?php _e('Checkbox', 'memberpress'); ?></option>
  <option value="dropdown" <?php selected($line->field_type, 'dropdown'); ?>><?php _e('Dropdown', 'memberpress'); ?></option>
  <option value="multiselect" <?php selected($line->field_type, 'multiselect'); ?>><?php _e('Multi-Select', 'memberpress'); ?></option>
  <option value="radios" <?php selected($line->field_type, 'radios'); ?>><?php _e('Radio Buttons', 'memberpress'); ?></option>
  <option value="checkboxes" <?php selected($line->field_type, 'checkboxes'); ?>><?php _e('Checkboxes', 'memberpress'); ?></option>
  <option value="file" <?php selected($line->field_type, 'file'); ?>><?php _e('File Upload', 'memberpress'); ?></option>
</select>
