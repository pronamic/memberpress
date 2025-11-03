<?php

defined('ABSPATH') || exit;

class MeprRulesHelper
{
    /**
     * Renders the rule type dropdown.
     *
     * @param string $selected The selected rule type.
     */
    public static function type_dropdown(string $selected = ''): void
    {
        MeprView::render('/admin/rules/type_dropdown', compact('selected'));
    }

    /**
     * Renders the access row.
     *
     * @param MeprRuleAccessCondition|null $access_condition The access condition.
     * @param integer                      $index            The index.
     */
    public static function access_row($access_condition = null, int $index = 0): void
    {
        echo self::access_row_string($access_condition, $index); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Returns the access row string.
     *
     * @param MeprRuleAccessCondition|null $access_condition The access condition.
     * @param integer                      $index            The index.
     */
    public static function access_row_string($access_condition = null, int $index = 0): string
    {
        return MeprView::get_string('/admin/rules/access_row', compact('access_condition', 'index'));
    }

    /**
     * Renders the access types dropdown.
     *
     * @param string $selected The selected access type.
     */
    public static function access_types_dropdown(string $selected = ''): void
    {
        echo self::access_types_dropdown_string($selected); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Returns the access types dropdown string.
     *
     * @param string $selected The selected access type.
     */
    public static function access_types_dropdown_string(string $selected = ''): string
    {
        return MeprView::get_string('/admin/rules/access_types_dropdown', compact('selected'));
    }

    /**
     * Renders the access rules operators dropdown.
     *
     * @param string $type     The access condition type.
     * @param string $selected The selected operator.
     */
    public static function access_operators_dropdown(string $type = '', string $selected = ''): void
    {
        echo self::access_operators_dropdown_string($type, $selected); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Returns the access operators dropdown string.
     *
     * @param string $type     The rule type.
     * @param string $selected The selected operator.
     */
    public static function access_operators_dropdown_string(string $type = '', string $selected = ''): string
    {
        return MeprView::get_string('/admin/rules/access_operators_dropdown', compact('type', 'selected'));
    }

    /**
     * Renders the access condition field based on the selected type.
     *
     * Could be a dropdown or an input field (with search/autocompletion).
     *
     * @param string $type  The access condition type.
     * @param string $value The current condition value.
     */
    public static function access_conditions_field(string $type = '', string $value = ''): void
    {
        $field_name    = 'mepr_access_row[condition][]';
        $field_classes = 'mepr-rule-access-condition-input';

        if ('role' === $type) {
            MeprAppHelper::roles_dropdown($field_name, [$value], $field_classes);
        } elseif ('login_state' === $type) {
            MeprAppHelper::login_state_dropdown($field_name, $value, $field_classes);
        } elseif ('membership' === $type) {
            MeprAppHelper::memberships_dropdown($field_name, [$value], $field_classes);
        } elseif ('member' === $type) {
            MeprAppHelper::members_input($field_name, $value, $field_classes);
        } elseif ('capability' === $type) {
            MeprAppHelper::capabilities_input($field_name, $value, $field_classes);
        } else {
            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
            echo MeprHooks::apply_filters(
                'mepr_rule_access_condition_field_html',
                '<span class="mepr-rule-access-condition-input">&nbsp;</span>',
                $type,
                $value,
                $field_name,
                $field_classes
            );
            // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Returns the access conditions field string.
     *
     * @param string $type  The access condition type.
     * @param string $value The current condition value.
     */
    public static function access_conditions_field_string(string $type = '', string $value = ''): string
    {
        ob_start();
        self::access_conditions_field($type, $value);
        return ob_get_clean();
    }

    /**
     * Renders the content dropdown.
     *
     * @param  string $field_name The field name.
     * @param  string $content    The content.
     * @param  string $type       The type.
     * @param  array  $options    The options.
     * @return void
     */
    public static function content_dropdown($field_name, $content, $type = 'all', $options = [])
    {
        $types = MeprRule::get_types();

        if (!isset($type) or empty($type) or !array_key_exists($type, $types)) {
            $type = 'all';
        }

        if ($type === 'custom') {
            $is_regexp = false;

            if (!isset($_POST["_is{$field_name}_regexp"]) and isset($options["_is{$field_name}_regexp"])) {
                $is_regexp = $options["_is{$field_name}_regexp"];
            }

            if (isset($_POST["_is{$field_name}_regexp"])) {
                $is_regexp = true;
            }

            ?>
      <span id="<?php echo esc_attr($field_name); ?>-wrap">
        <input type="text" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_name); ?>" class="mepr-input" value="<?php echo esc_attr(isset($_POST[$field_name]) ? sanitize_text_field(wp_unslash($_POST[$field_name])) : $content); ?>" onblur="mepr_update_rule_post_title( jQuery('#_mepr_rules_type').val(), this.value )" data-validation="required" data-validation-error-msg="<?php esc_attr_e('Content cannot be blank', 'memberpress'); ?>" />
        <input type="checkbox" name="<?php echo esc_attr('_is' . $field_name . '_regexp'); ?>" id="<?php echo esc_attr('_is' . $field_name . '_regexp'); ?>" <?php checked($is_regexp); ?> />&nbsp;<?php esc_html_e('Regular Expression', 'memberpress'); ?>
      </span>
            <?php
            return;
        }

        // Show a text field for collecting comma separated list of ID's to exclude.
        if ($type === 'all' or (strstr($type, 'all_') !== false and !preg_match('#^all_tax_#', $type))) {
            ?>
      <span id="<?php echo esc_attr($field_name); ?>-wrap">
        <label for="<?php echo esc_attr($field_name); ?>"><?php esc_html_e('Except', 'memberpress'); ?></label>
            <?php MeprAppHelper::info_tooltip(
                'mepr-rules-all-except',
                __('All Except IDs', 'memberpress'),
                __('If you want to exclude all except some Pages or Posts, list their IDs here in a comma separated list. Example: 102, 32, 546', 'memberpress')
            ); ?>
        <input type="text" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_name); ?>" class="mepr-input" value="<?php echo esc_attr(isset($_POST[$field_name]) ? sanitize_text_field(wp_unslash($_POST[$field_name])) : $content); ?>" onblur="mepr_update_rule_post_title( jQuery('#_mepr_rules_type').val(), this.value )" />
      </span>
            <?php
            return;
        }

        if (!MeprRule::type_has_contents($type)) {
            ?>
      <span id="<?php echo esc_attr($field_name); ?>-wrap">
            <?php
            if ($type !== 'partial') {
                esc_html_e('There is not yet any content to select for this rule type.', 'memberpress');
            }
            ?>
      </span>
            <?php
            return;
        }

        $field_value = ( isset($_POST[$field_name]) ? sanitize_text_field(wp_unslash($_POST[$field_name])) : $content );
        if (!empty($field_value)) {
            $obj = MeprRule::get_content($type, $content);
        }
        if (!isset($obj) or empty($obj)) {
            $obj = (object)[
                'id'    => '',
                'label' => '',
                'slug'  => '',
                'desc'  => '',
            ];
        }

        ?>
    <span id="<?php echo esc_attr($field_name); ?>-wrap">
      <input type="text" id="rule-content-text" class="mepr-rule-types-autocomplete" value="<?php echo esc_attr($obj->label); ?>" placeholder="<?php esc_attr_e('Begin Typing Title', 'memberpress'); ?>" data-validation="required" data-validation-error-msg="<?php esc_attr_e('Content cannot be blank', 'memberpress'); ?>" />
      <input type="hidden" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_name); ?>" class="mepr-rule-types-autocomplete" value="<?php echo esc_attr($obj->id); ?>" />
      <span id="rule-content-info"><?php echo esc_html($obj->desc); ?></span>
    </span>
        <?php
    }

    /**
     * Renders the time units dropdown.
     *
     * @param  object $rule The rule.
     * @param  string $type The type.
     * @return void
     */
    public static function time_units_dropdown($rule, $type)
    {
        $values = MeprRule::get_time_units();
        ?>
    <select name="<?php echo esc_attr($type); ?>">
        <?php foreach ($values as $name => $value) : ?>
            <?php if ($type === MeprRule::$drip_unit_str) : ?>
        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $rule->drip_unit); ?>><?php echo esc_html($name); ?></option>
            <?php endif; ?>

            <?php if ($type === MeprRule::$expires_unit_str) : ?>
        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $rule->expires_unit); ?>><?php echo esc_html($name); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
        <?php
    }

    /**
     * Renders the drip expires after dropdown.
     *
     * @param  object $rule The rule.
     * @param  string $type The type.
     * @return void
     */
    public static function drip_expires_after_dropdown($rule, $type)
    {
        $products = MeprCptModel::all('MeprProduct', false, [
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);
        ?>
    <select name="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($type); ?>">
      <option value="registers" <?php selected((($type === MeprRule::$drip_after_str && $rule->drip_after === 'registers') || ($type === MeprRule::$expires_after_str && $rule->expires_after === 'registers'))); ?>><?php esc_html_e('member registers', 'memberpress'); ?></option>
      <option value="fixed" <?php selected((($type === MeprRule::$drip_after_str && $rule->drip_after === 'fixed') || ($type === MeprRule::$expires_after_str && $rule->expires_after === 'fixed'))); ?>><?php esc_html_e('fixed date', 'memberpress'); ?></option>
      <option value="rule-products" <?php selected((($type === MeprRule::$drip_after_str && $rule->drip_after === 'rule-products') || ($type === MeprRule::$expires_after_str && $rule->expires_after === 'rule-products'))); ?>><?php esc_html_e('member purchases any membership for this rule', 'memberpress'); ?></option>
        <?php foreach ($products as $p) : ?>
            <?php if ($type === MeprRule::$drip_after_str) : ?>
        <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $rule->drip_after); ?>><?php echo esc_html(__('member purchases', 'memberpress') . ' ' . $p->post_title); ?></option>
            <?php endif; ?>

            <?php if ($type === MeprRule::$expires_after_str) : ?>
        <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $rule->expires_after); ?>><?php echo esc_html(__('member purchases', 'memberpress') . ' ' . $p->post_title); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
        <?php
    }
}
