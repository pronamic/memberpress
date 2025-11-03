<?php
/**
 * View admin/options/custom-fields/types.
 *
 * @var string $row_id
 * @var string $hide
 * @var object{
 *     field_key: string,
 *     field_name: string,
 *     field_type: string,
 *     default_value: string,
 *     show_on_signup: bool,
 *     show_in_account: bool,
 *     required: bool,
 *     options: array
 * } $line
 */

defined('ABSPATH') || exit;
?>

<select name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][type]" class="mepr-custom-fields-select" data-value="<?php echo esc_attr($row_id); ?>">
    <option value="text" <?php selected($line->field_type, 'text'); ?>><?php esc_html_e('Text', 'memberpress'); ?></option>
    <option value="email" <?php selected($line->field_type, 'email'); ?>><?php esc_html_e('Email', 'memberpress'); ?></option>
    <option value="url" <?php selected($line->field_type, 'url'); ?>><?php esc_html_e('URL', 'memberpress'); ?></option>
    <option value="tel" <?php selected($line->field_type, 'tel'); ?>><?php esc_html_e('Phone', 'memberpress'); ?></option>
    <option value="date" <?php selected($line->field_type, 'date'); ?>><?php esc_html_e('Date', 'memberpress'); ?></option>
    <option value="textarea" <?php selected($line->field_type, 'textarea'); ?>><?php esc_html_e('Textarea', 'memberpress'); ?></option>
    <option value="checkbox" <?php selected($line->field_type, 'checkbox'); ?>><?php esc_html_e('Checkbox', 'memberpress'); ?></option>
    <option value="dropdown" <?php selected($line->field_type, 'dropdown'); ?>><?php esc_html_e('Dropdown', 'memberpress'); ?></option>
    <option value="multiselect" <?php selected($line->field_type, 'multiselect'); ?>><?php esc_html_e('Multi-Select', 'memberpress'); ?></option>
    <option value="radios" <?php selected($line->field_type, 'radios'); ?>><?php esc_html_e('Radio Buttons', 'memberpress'); ?></option>
    <option value="checkboxes" <?php selected($line->field_type, 'checkboxes'); ?>><?php esc_html_e('Checkboxes', 'memberpress'); ?></option>
    <option value="file" <?php selected($line->field_type, 'file'); ?>><?php esc_html_e('File Upload', 'memberpress'); ?></option>
</select>
