<?php
/**
 * View admin/rules/access_types_dropdown.
 *
 * @var string $selected The selected access type.
 */

defined('ABSPATH') || exit;

$onchange     = 'mepr_show_access_options(this)';
$access_types = MeprRule::mepr_access_types();

array_unshift(
    $access_types,
    [
        'value' => '',
        'label' => __('- Select Type -', 'memberpress'),
    ]
);
?>

<select
    name="mepr_access_row[type][]"
    class="mepr-rule-access-type-input"
    onchange="<?php echo esc_attr($onchange); ?>"
    data-validation="required"
    data-validation-error-msg="<?php esc_attr_e('Rule Type cannot be blank', 'memberpress'); ?>"
>
    <?php foreach ($access_types as $access_type) : ?>
        <option
            value="<?php echo esc_attr($access_type['value']); ?>"
            <?php selected($selected, $access_type['value']); ?>
        >
            <?php echo esc_html($access_type['label']); ?>
        </option>
    <?php endforeach; ?>
</select>
