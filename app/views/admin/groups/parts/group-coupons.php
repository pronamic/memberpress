<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Group Coupons partial.
 *
 * Extracted from form.php so brands without coupons can exclude this file.
 *
 * @var MeprGroup   $group
 * @var MeprOptions $mepr_options
 */
?>
  <br/><br/>
  <h4><strong><?php esc_html_e('Group Coupons:', 'memberpress'); ?></strong>
  <?php
    MeprAppHelper::info_tooltip(
        'mepr-group-coupons',
        esc_html__('Group Coupons', 'memberpress'),
        esc_html__( // phpcs:ignore Generic.Files.LineLength.TooLong
            'Automatically apply coupons to memberships in this group. Coupons can be applied on schedule and unapplied on schedule.',
            'memberpress'
        )
    );
    ?>
  </h4>

  <input type="checkbox"
    id="<?php echo esc_attr(MeprGroup::$auto_apply_coupons_str); ?>"
    name="<?php echo esc_attr(MeprGroup::$auto_apply_coupons_str); ?>"
    class="mepr-toggle-checkbox"
    data-box="mepr_auto_apply_coupons_box"
    <?php checked($group->auto_apply_coupons); ?>
  />
  <label for="<?php echo esc_attr(MeprGroup::$auto_apply_coupons_str); ?>">
    <?php esc_html_e('Automatically apply coupons to group\'s memberships', 'memberpress'); ?>
  </label>

  <div id="mepr_auto_apply_coupons_box"
    class="mepr-sub-box mepr_auto_apply_coupons_box"
    <?php echo $group->auto_apply_coupons ? '' : 'style="display:none;"'; ?>
  >
    <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
    <ol id="sortable-coupons" class="mepr-sortable">
      <?php MeprGroupsHelper::get_existing_coupons_list($group); ?>
    </ol>
    <a href="" id="add-new-coupon"
      title="<?php esc_attr_e('Add Coupon', 'memberpress'); ?>">
      <i class="mp-icon mp-icon-plus-circled mp-24"></i>
    </a>
    <div id="hidden-coupon-line-item">
      <li class="coupon-item">
        <?php MeprGroupsHelper::get_coupons_dropdown(null, 'TEMPLATE_INDEX'); ?>
        <div class="coupon-schedule-options">
        <?php
        $field_prefix = MeprGroup::$group_coupons_str;
        $index        = 'TEMPLATE_INDEX';
        MeprView::render(
            '/admin/groups/parts/coupon-schedule-option',
            [
                'checkbox_field_name' => "{$field_prefix}[{$index}][should_apply]",
                'label'               => __('Apply on', 'memberpress'),
                'box_id'              => 'apply_schedule_box_template',
                'box_class'           => 'apply-box',
                'checked'             => false,
                'month_field'         => "{$field_prefix}[{$index}][apply_month]",
                'day_field'           => "{$field_prefix}[{$index}][apply_day]",
                'year_field'          => "{$field_prefix}[{$index}][apply_year]",
                'hour_field'          => "{$field_prefix}[{$index}][apply_hour]",
                'minute_field'        => "{$field_prefix}[{$index}][apply_minute]",
                'timezone_field'      => "{$field_prefix}[{$index}][apply_timezone]",
                'date_ts'             => 0,
                'timezone'            => MeprCouponsHelper::get_wp_selected_timezone_setting(),
                'begin'               => true,
                'help'                => sprintf(
                    // Translators: %1$s: open strong tag, %2$s: close strong tag.
                    esc_html__('Coupon applies at %1$s00 seconds%2$s of the selected hour and minute.', 'memberpress'),
                    '<strong>',
                    '</strong>'
                ),
            ]
        );
        ?>
        <?php
        MeprView::render(
            '/admin/groups/parts/coupon-schedule-option',
            [
                'checkbox_field_name' => "{$field_prefix}[{$index}][should_unapply]",
                'label'               => __('Unapply on', 'memberpress'),
                'box_id'              => 'unapply_schedule_box_template',
                'box_class'           => 'unapply-box',
                'checked'             => false,
                'month_field'         => "{$field_prefix}[{$index}][unapply_month]",
                'day_field'           => "{$field_prefix}[{$index}][unapply_day]",
                'year_field'          => "{$field_prefix}[{$index}][unapply_year]",
                'hour_field'          => "{$field_prefix}[{$index}][unapply_hour]",
                'minute_field'        => "{$field_prefix}[{$index}][unapply_minute]",
                'timezone_field'      => "{$field_prefix}[{$index}][unapply_timezone]",
                'date_ts'             => 0,
                'timezone'            => MeprCouponsHelper::get_wp_selected_timezone_setting(),
                'begin'               => false,
                'help'                => sprintf(
                    // Translators: %1$s: open strong tag, %2$s: close strong tag.
                    esc_html__('Coupon unapplies at %1$s59 seconds%2$s of the selected hour and minute.', 'memberpress'),
                    '<strong>',
                    '</strong>'
                ),
            ]
        );
        ?>
        </div>
        <span class="remove-span">
          <a href="" class="remove-coupon-item"
            title="Remove Coupon">
            <i class="mp-icon mp-icon-cancel-circled mp-16"></i>
          </a>
        </span>
      </li>
    </div>
  </div>

  <br/><br/>
  <label for="<?php echo esc_attr(MeprGroup::$coupon_discount_note_str); ?>">
    <?php esc_html_e('Pricing Table Discount Label:', 'memberpress'); ?>
  </label>
  <?php
    MeprAppHelper::info_tooltip(
        'mepr-coupon-discount-note',
        __('Pricing Table Discount Label', 'memberpress'),
        __( // phpcs:ignore Generic.Files.LineLength.TooLong
            'Text shown below the discounted price on the pricing table when a coupon is applied to a recurring membership. Leave blank to hide. Defaults to "first payment".',
            'memberpress'
        )
    );
    ?>
  <br/>
  <input type="text"
    id="<?php echo esc_attr(MeprGroup::$coupon_discount_note_str); ?>"
    name="<?php echo esc_attr(MeprGroup::$coupon_discount_note_str); ?>"
    value="<?php echo esc_attr($group->coupon_discount_note); ?>"
    placeholder="<?php esc_attr_e('first payment', 'memberpress'); ?>"
    style="width: 300px;"
  />

