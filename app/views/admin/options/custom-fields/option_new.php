<?php
/**
 * View admin/options/custom-fields/option_new.
 *
 * @var string $row_id
 */

defined('ABSPATH') || exit;
?>

<li>
    <label><?php esc_html_e('Option Name:', 'memberpress'); ?></label>
    <input type="text" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][option][]" value="" />

    <label><?php esc_html_e('Option Value:', 'memberpress'); ?></label>
    <input type="text" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][value][]" value="" />

    <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
</li>
