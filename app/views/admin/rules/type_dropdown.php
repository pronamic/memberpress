<?php
/**
 * View admin/rules/type_dropdown.
 *
 * @var string $selected The selected rule type.
 */

defined('ABSPATH') || exit;

$current_post_id    = isset($_GET['post']) ? (int) $_GET['post'] : 0;
$selected           = $current_post_id > 0 ? $selected : ''; // Remove selection on new rule creation (default value is 'all').
$type_field_name    = MeprRule::$mepr_type_str;
$content_field_name = MeprRule::$mepr_content_str;
$onchange           = "mepr_show_content_dropdown('{$content_field_name}', this.value)";

$types = array_merge(
    ['' => __('- Please Select -', 'memberpress')],
    MeprRule::get_types(),
);
?>

<select
    name="<?php echo esc_attr($type_field_name); ?>"
    class="mepr-dropdown mepr-rule-types-dropdown"
    id="<?php echo esc_attr($type_field_name); ?>"
    onchange="<?php echo esc_attr($onchange); ?>"
    data-validation="required"
    data-validation-error-msg="<?php esc_attr_e('Rule Type cannot be blank', 'memberpress'); ?>"
>
    <?php foreach ($types as $rule_type => $label) : ?>
    <option
        value="<?php echo esc_attr($rule_type); ?>"
        <?php selected($selected, $rule_type); ?>
    >
        <?php echo esc_html($label); ?>
    </option>
    <?php endforeach; ?>
</select>

