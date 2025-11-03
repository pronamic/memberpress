<?php
/**
 * View admin/rules/access_operators_dropdown.
 *
 * @var string $type     The access condition type.
 * @var string $selected The selected operator.
 */

defined('ABSPATH') || exit;

$access_types     = MeprRule::mepr_access_types();
$access_operators = MeprRule::mepr_access_operators();
?>

<?php if (in_array($type, array_column($access_types, 'value'), true)) : ?>
    <?php if (count($access_operators) > 1) : ?>
        <select name="mepr_access_row[operator][]" class="mepr-rule-access-operator-input">
            <?php foreach ($access_operators as $operator) : ?>
                <option
                    value="<?php echo esc_attr($operator['value']); ?>"
                    <?php selected($selected, $operator['value']); ?>
                >
                    <?php echo esc_html($operator['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif (count($access_operators) === 1) : ?>
        <div class="mepr-rule-access-operator-input">
            <input
                type="hidden"
                name="mepr_access_row[operator][]"
                value="<?php echo esc_attr($access_operators[0]['value']); ?>"
            />
            <span><strong><?php echo esc_html(strtolower($access_operators[0]['label'])); ?></strong></span>
        </div>
    <?php endif; ?>
<?php else : ?>
    <span class="mepr-rule-access-operator-input">&nbsp;</span>
<?php endif; ?>
