<?php
/**
 * View admin/options/custom-fields/options.
 *
 * @var string        $row_id
 * @var array<object> $options
 */

defined('ABSPATH') || exit;
?>

<ul class="custom_options_list">
    <?php foreach ($options as $option) : ?>
    <li>
        <label><?php esc_html_e('Option Name:', 'memberpress'); ?></label>
        <input type="text" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][option][]" value="<?php echo esc_attr(stripslashes($option->option_name)); ?>" />

        <label><?php esc_html_e('Option Value:', 'memberpress'); ?></label>
        <input type="text" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][value][]" value="<?php echo esc_attr(stripslashes($option->option_value)); ?>" />

        <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
    </li>
    <?php endforeach; ?>

    <a href="" id="mepr-add-new-option" title="Add Option" data-value="<?php echo esc_attr($row_id); ?>"><i class="mp-icon mp-icon-plus-circled mp-16"></i></a>
</ul>
