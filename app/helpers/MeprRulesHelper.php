<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprRulesHelper
{
    /**
     * Type dropdown.
     *
     * @param  string $field_name The field name.
     * @param  string $type       The type.
     * @param  string $onchange   The onchange.
     * @return void
     */
    public static function type_dropdown($field_name, $type, $onchange = '')
    {
        $field_value = (isset($_POST[$field_name])) ? $_POST[$field_name] : '';
        $types       = MeprRule::get_types();
        ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-rule-types-dropdown" onchange="<?php echo $onchange; ?>" data-validation="required" data-validation-error-msg="<?php _e('Rule Type cannot be blank', 'memberpress'); ?>">
        <option value="" <?php echo ! empty($_GET['post']) ? 'selected="selected"' : ''; ?>><?php _e('- Please Select -', 'memberpress'); ?></option>
        <?php
        foreach ($types as $curr_type => $curr_label) {
            ?>
          <option value="<?php echo $curr_type; ?>" <?php echo ! empty($_GET['post']) && $curr_type == get_post_meta((int) $_GET['post'], MeprRule::$mepr_type_str, true) ? 'selected="selected"' : ''; ?>><?php echo $curr_label; ?>&nbsp;&nbsp;</option>
            <?php
        }
        ?>
      </select>
        <?php
    }

    /**
     * Gets the access row.
     *
     * @param  mixed   $access_condition The access condition.
     * @param  integer $index            The index.
     * @return void
     */
    public static function access_row($access_condition = null, $index = 0)
    {
        echo self::access_row_string($access_condition, $index);
    }

    /**
     * Gets the access row string.
     *
     * @param  mixed   $access_condition The access condition.
     * @param  integer $index            The index.
     * @return string
     */
    public static function access_row_string($access_condition = null, $index = 0)
    {
        return MeprView::get_string('/admin/rules/access_row', compact('access_condition', 'index'));
    }

    /**
     * Gets the access types dropdown.
     *
     * @param  string $selected The selected.
     * @param  string $onchange The onchange.
     * @return void
     */
    public static function access_types_dropdown($selected = '', $onchange = 'mepr_show_access_options(this)')
    {
        $access_types = MeprRule::mepr_access_types();
        ?>
      <select name="mepr_access_row[type][]" class="mepr-rule-access-type-input" onchange="<?php echo $onchange; ?>" data-validation="required" data-validation-error-msg="<?php _e('Rule Type cannot be blank', 'memberpress'); ?>">
        <option value=""><?php _e('- Select Type -', 'memberpress'); ?></option>
        <?php foreach ($access_types as $type) : ?>
          <option value="<?php echo $type['value']; ?>" <?php echo $selected == $type['value'] ? 'selected' : ''; ?>>
            <?php echo $type['label']; ?>
          </option>
        <?php endforeach; ?>
      </select>
        <?php
    }

    /**
     * Gets the access types dropdown string.
     *
     * @param  string $selected The selected.
     * @param  string $onchange The onchange.
     * @return string
     */
    public static function access_types_dropdown_string($selected = '', $onchange = 'mepr_show_access_options(this)')
    {
        ob_start();
        self::access_types_dropdown($selected, $onchange);
        return ob_get_clean();
    }

    /**
     * Gets the access rules operators dropdown.
     *
     * @param  string $type     The type.
     * @param  string $selected The selected.
     * @return void
     */
    public static function access_operators_dropdown($type = '', $selected = '')
    {
        switch ($type) {
            case 'role':
            case 'capability':
            case 'membership':
            case 'member':
                $access_operators = MeprRule::mepr_access_operators();

                if (count($access_operators) > 1) {
                    ?>
            <select name="mepr_access_row[operator][]" class="mepr-rule-access-operator-input">
                    <?php foreach ($access_operators as $operator) : ?>
                <option value="<?php echo $operator['value']; ?>" <?php selected($selected, $operator['value']); ?>>
                        <?php echo $operator['label']; ?>
                </option>
                    <?php endforeach; ?>
            </select>
                    <?php
                } elseif (count($access_operators) == 1) {
                    $operator = $access_operators[0];

                    ?>
            <div class="mepr-rule-access-operator-input">
              <input type="hidden" name="mepr_access_row[operator][]" value="<?php echo $operator['value']; ?>" />
              <span><strong><?php echo strtolower($operator['label']); ?></strong></span>
            </div>
                    <?php
                }

                break;
            default:
                ?><span class="mepr-rule-access-operator-input">&nbsp;</span><?php
        }
    }

    /**
     * Gets the access operators dropdown string.
     *
     * @param  string $type     The type.
     * @param  string $selected The selected.
     * @return string
     */
    public static function access_operators_dropdown_string($type = '', $selected = '')
    {
        ob_start();
        self::access_operators_dropdown($type, $selected);
        return ob_get_clean();
    }

    /**
     * Returns Access Rule Conditions for selected type
     * Returns Membership dropdown or Autocomplete Member text field
     *
     * @param  string $type     The type.
     * @param  string $selected The selected.
     * @return void
     */
    public static function access_conditions_dropdown($type = '', $selected = '')
    {
        switch ($type) {
            case 'role':
                MeprAppHelper::roles_dropdown('mepr_access_row[condition][]', [$selected], 'mepr-rule-access-condition-input');
                break;
            case 'membership':
                MeprAppHelper::memberships_dropdown('mepr_access_row[condition][]', [$selected], 'mepr-rule-access-condition-input');
                break;
            case 'member':
                ?>
          <input
            type="text"
            name="mepr_access_row[condition][]"
            class="mepr_suggest_user mepr-rule-access-condition-input"
            value="<?php echo $selected; ?>"
            placeholder="<?php _e('Begin Typing Name', 'memberpress') ?>"
            data-validation="length"
            data-validation-error-msg="<?php _e('Member Name must be between 1-100 characters', 'memberpress'); ?>"
            data-validation-length="1-100"
          ></input>
                <?php
                break;
            case 'capability':
                ?>
          <input
            type="text"
            name="mepr_access_row[condition][]"
            class="mepr-rule-access-condition-input"
            value="<?php echo $selected; ?>"
            placeholder="<?php _e('Enter Capability', 'memberpress'); ?>"
          ></input>
                <?php
                break;
            default:
                ?><span class="mepr-rule-access-condition-input">&nbsp;</span><?php
        }
    }

    /**
     * Gets the access conditions dropdown string.
     *
     * @param  string $type     The type.
     * @param  string $selected The selected.
     * @return string
     */
    public static function access_conditions_dropdown_string($type = '', $selected = '')
    {
        ob_start();
        self::access_conditions_dropdown($type, $selected);
        return ob_get_clean();
    }

    /**
     * Gets the page title.
     *
     * @param  string $type    The type.
     * @param  string $content The content.
     * @return void
     */
    public static function get_page_title($type, $content)
    {
        $contents       = MeprRule::get_contents_array($type);
        $content_values = array_values((!$contents) ? [''] : $contents);
        $types          = MeprRule::get_types();

        $type_label = $types[(!empty($type) ? $type : 'all')];
        if ($type == 'custom') {
            $content_label = $content;
        } else {
            $content_label = (!empty($contents[$content]) ? $contents[$content] : $content_values[0]);
        }
    }

    /**
     * Gets the content dropdown.
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

        if ($type == 'custom') {
            $is_regexp = false;

            if (!isset($_POST["_is{$field_name}_regexp"]) and isset($options["_is{$field_name}_regexp"])) {
                $is_regexp = $options["_is{$field_name}_regexp"];
            }

            if (isset($_POST["_is{$field_name}_regexp"])) {
                $is_regexp = true;
            }

            ?>
      <span id="<?php echo $field_name; ?>-wrap">
            <?php self::get_page_title($type, $content); ?>
        <input type="text" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-input" value="<?php echo isset($_POST[$field_name]) ? $_POST[$field_name] : $content; ?>" onblur="mepr_update_rule_post_title( jQuery('#_mepr_rules_type').val(), this.value )" data-validation="required" data-validation-error-msg="<?php echo esc_html__('Content cannot be blank', 'memberpress'); ?>" />
        <input type="checkbox" name="_is<?php echo $field_name; ?>_regexp" id="_is<?php echo $field_name; ?>_regexp" <?php checked($is_regexp); ?> />&nbsp;<?php _e('Regular Expression', 'memberpress'); ?>
      </span>
            <?php
            return;
        }

        // Show a text field for collecting comma separated list of ID's to exclude
        if ($type == 'all' or (strstr($type, 'all_') !== false and !preg_match('#^all_tax_#', $type))) {
            ?>
      <span id="<?php echo $field_name; ?>-wrap">
            <?php self::get_page_title($type, $content); ?>
        <label for="<?php echo $field_name; ?>"><?php _e('Except', 'memberpress'); ?></label>
            <?php MeprAppHelper::info_tooltip(
                'mepr-rules-all-except',
                __('All Except IDs', 'memberpress'),
                __('If you want to exclude all except some Pages or Posts, list their IDs here in a comma separated list. Example: 102, 32, 546', 'memberpress')
            ); ?>
        <input type="text" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-input" value="<?php echo isset($_POST[$field_name]) ? $_POST[$field_name] : $content; ?>" onblur="mepr_update_rule_post_title( jQuery('#_mepr_rules_type').val(), this.value )" />
      </span>
            <?php
            return;
        }

        if (!MeprRule::type_has_contents($type)) {
            ?>
      <span id="<?php echo $field_name; ?>-wrap">
            <?php
            self::get_page_title($type, $content);
            if ($type != 'partial') {
                _e('There is not yet any content to select for this rule type.', 'memberpress');
            }
            ?>
      </span>
            <?php
            return;
        }

        $field_value = ( isset($_POST[$field_name]) ? $_POST[$field_name] : $content );
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
    <span id="<?php echo $field_name; ?>-wrap">
        <?php self::get_page_title($type, $content); ?>
      <input type="text" id="rule-content-text" class="mepr-rule-types-autocomplete" value="<?php echo esc_attr($obj->label); ?>" placeholder="<?php _e('Begin Typing Title', 'memberpress'); ?>" data-validation="required" data-validation-error-msg="<?php echo esc_html__('Content cannot be blank', 'memberpress'); ?>" />
      <input type="hidden" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-rule-types-autocomplete" value="<?php echo $obj->id; ?>" />
      <span id="rule-content-info"><?php echo $obj->desc; ?></span>
    </span>
        <?php
    }

    /**
     * Gets the access dropdown.
     *
     * @param  string $field_name The field name.
     * @param  array  $access     The access.
     * @return void
     */
    public static function access_dropdown($field_name, $access)
    {
        $contents = [];

        $post_contents = MeprCptModel::all('MeprProduct');

        foreach ($post_contents as $post) {
            $contents[$post->ID] = $post->post_title;
        }

        // Don't think this is used, blair feel free to kill this if I'm right
        $field_value = (isset($_POST[$field_name])) ? $_POST[$field_name] : '';

        if (!is_array($access)) {
            $access = [$access];
        }

        ?>
      <select name="<?php echo $field_name; ?>[]" id="<?php echo $field_name; ?>[]" class="mepr-multi-select mepr-rule-access-select" multiple="true">
        <?php
        foreach ($contents as $curr_type => $curr_label) {
            ?>
          <option value="<?php echo $curr_type; ?>" <?php echo (((isset($_POST[$field_name]) and in_array($curr_type, $_POST[$field_name])) or (!isset($_POST[$field_name]) and in_array($curr_type, $access))) ? ' selected="selected"' : ''); ?>><?php echo $curr_label; ?>&nbsp;</option>
            <?php
        }
        ?>
      </select>
        <?php
    }

    /**
     * Gets the time units dropdown.
     *
     * @param  object $rule The rule.
     * @param  string $type The type.
     * @return void
     */
    public static function time_units_dropdown($rule, $type)
    {
        $values = MeprRule::get_time_units();
        ?>
    <select name="<?php echo $type; ?>">
        <?php foreach ($values as $name => $value) : ?>
            <?php if ($type == MeprRule::$drip_unit_str) : ?>
        <option value="<?php echo $value; ?>" <?php selected($value, $rule->drip_unit); ?>><?php echo $name; ?></option>
            <?php endif; ?>

            <?php if ($type == MeprRule::$expires_unit_str) : ?>
        <option value="<?php echo $value; ?>" <?php selected($value, $rule->expires_unit); ?>><?php echo $name; ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
        <?php
    }

    /**
     * Gets the drip expires after dropdown.
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
    <select name="<?php echo $type; ?>" id="<?php echo $type; ?>">
      <option value="registers" <?php selected((($type == MeprRule::$drip_after_str && $rule->drip_after == 'registers') || ($type == MeprRule::$expires_after_str && $rule->expires_after == 'registers'))); ?>><?php _e('member registers', 'memberpress'); ?></option>
      <option value="fixed" <?php selected((($type == MeprRule::$drip_after_str && $rule->drip_after == 'fixed') || ($type == MeprRule::$expires_after_str && $rule->expires_after == 'fixed'))); ?>><?php _e('fixed date', 'memberpress'); ?></option>
      <option value="rule-products" <?php selected((($type == MeprRule::$drip_after_str && $rule->drip_after == 'rule-products') || ($type == MeprRule::$expires_after_str && $rule->expires_after == 'rule-products'))); ?>><?php _e('member purchases any membership for this rule', 'memberpress'); ?></option>
        <?php foreach ($products as $p) : ?>
            <?php if ($type == MeprRule::$drip_after_str) : ?>
        <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $rule->drip_after); ?>><?php echo __('member purchases', 'memberpress') . ' ' . $p->post_title; ?></option>
            <?php endif; ?>

            <?php if ($type == MeprRule::$expires_after_str) : ?>
        <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $rule->expires_after); ?>><?php echo __('member purchases', 'memberpress') . ' ' . $p->post_title; ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
        <?php
    }
}
