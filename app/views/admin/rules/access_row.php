<?php
/**
 * View admin/rules/access_row.
 *
 * @var MeprRuleAccessCondition|null $access_condition
 * @var integer                      $index
 */

defined('ABSPATH') || exit;
?>

<div class="mepr-access-row grid mepr-left-aligned-grid">
    <div class="col-1-12">
        <?php if ($index > 0) : ?>
            <span class="mepr-rule-access-and-or"><strong><?php esc_html_e('OR', 'memberpress'); ?></strong></span>
        <?php else : ?>
            &nbsp;
        <?php endif; ?>
    </div>

    <div class="col-2-12">
        <?php if ($access_condition) : ?>
            <input
                name="mepr_access_row[rule_access_condition_id][]"
                type="hidden"
                value="<?php echo esc_attr($access_condition->id); ?>"
            />
            <?php MeprRulesHelper::access_types_dropdown($access_condition->access_type); ?>
        <?php else : ?>
            <input
                name="mepr_access_row[rule_access_condition_id][]"
                type="hidden"
                value=""
            />
            <?php MeprRulesHelper::access_types_dropdown(); ?>
        <?php endif; ?>
    </div>

    <div class="col-1-12">
        <?php
        if ($access_condition) :
            MeprRulesHelper::access_operators_dropdown(
                $access_condition->access_type,
                $access_condition->access_operator
            );
        else :
            MeprRulesHelper::access_operators_dropdown();
        endif;
        ?>
    </div>

    <div class="col-7-12">
    <?php
    if ($access_condition) :
        MeprRulesHelper::access_conditions_field(
            $access_condition->access_type,
            $access_condition->access_condition
        );
    else :
            MeprRulesHelper::access_conditions_field();
    endif;
    ?>
    </div>

    <div class="col-1-12">
    <?php if ($index > 0) : ?>
        <a
            href=""
            class="remove-rule-condition"
            title="<?php esc_attr_e('Remove Access Rule', 'memberpress'); ?>"
        >
            <i class="mp-icon mp-icon-cancel-circled mp-16"></i>
        </a>
    <?php else : ?>
        &nbsp;
    <?php endif; ?>
    </div>
</div>
