<?php
/**
 * View admin/options/custom-fields/row.
 *
 * @var string $row_id
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

<li class="mepr-custom-field postbox">
    <span class="mp-icon mp-icon-drag-target"></span>
    <label><?php esc_html_e('Name:', 'memberpress'); ?></label>
    <input type="text" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][name]" value="<?php echo esc_attr(stripslashes($line->field_name)); ?>" />

    <label><?php esc_html_e('Type:', 'memberpress'); ?></label>
    <?php MeprView::render('/admin/options/custom-fields/types', get_defined_vars()); ?>

    <label for="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][default]"><?php esc_html_e('Default Value(s):', 'memberpress'); ?></label>
    <input type="text" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][default]" value="<?php echo esc_attr(stripslashes($line->default_value)); ?>" />

    <a href="" class="mepr-custom-field-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>

    <p class="mepr-custom-fields-p">
        <input type="checkbox" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][signup]" id="mepr-custom-fields-signup-<?php echo esc_attr($row_id); ?>" <?php checked($line->show_on_signup); ?> />
        <label for="mepr-custom-fields-signup-<?php echo esc_attr($row_id); ?>"><?php esc_html_e('Show at Signup', 'memberpress'); ?></label>

        &nbsp;&nbsp;&nbsp;<input type="checkbox" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][show_in_account]" id="mepr-custom-fields-account-<?php echo esc_attr($row_id); ?>" <?php checked(isset($line->show_in_account) ? $line->show_in_account : $blank_line[0]->show_in_account); ?> />
        <label for="mepr-custom-fields-account-<?php echo esc_attr($row_id); ?>"><?php esc_html_e('Show in Account', 'memberpress'); ?></label>

        &nbsp;&nbsp;&nbsp;<input type="checkbox" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][required]" id="mepr-custom-fields-required-<?php echo esc_attr($row_id); ?>" <?php checked($line->required); ?> />
        <label for="mepr-custom-fields-required-<?php echo esc_attr($row_id); ?>"><?php esc_html_e('Required', 'memberpress'); ?></label>
    </p>

    <input type="hidden" name="mepr-custom-fields-index[]" value="<?php echo esc_attr($row_id); ?>" />

    <div id="dropdown-hidden-options-<?php echo esc_attr($row_id); ?>">
        <?php if (!empty($line->options)) : ?>
        <ul class="custom_options_list">
            <?php foreach ($line->options as $option) : ?>
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
        <?php endif; ?>
    </div>

    <input type="hidden" name="mepr-custom-fields[<?php echo esc_attr($row_id); ?>][slug]" value="<?php echo esc_attr((!empty($line->field_key)) ? $line->field_key : 'mepr_none'); ?>" />

    <?php if (!empty($line->field_key)) : ?>
    <p class="mepr-custom-fields-p"><b><?php esc_html_e('Slug:', 'memberpress'); ?></b> <?php echo esc_html($line->field_key); ?></p>
    <?php endif; ?>
</li>
