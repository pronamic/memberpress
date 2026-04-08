<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprGroupsHelper
{
    /**
     * Get existing products list.
     *
     * @param  MeprGroup $group The group.
     * @return void
     */
    public static function get_existing_products_list($group)
    {
        $products = $group->products();
        if (!empty($products)) {
            foreach ($products as $index => $prd) :
                ?>
        <li class="product-item">
                <?php MeprGroupsHelper::get_products_dropdown($prd->ID); ?>
          <span class="remove-span">
            <a href="" class="remove-product-item" title="Remove Membership">
              <i class="mp-icon mp-icon-cancel-circled mp-16"></i>
            </a>
          </span>
        </li>
                <?php
            endforeach;
        } else {
            ?>
      <li class="product-item">
            <?php MeprGroupsHelper::get_products_dropdown(); ?>
        <span class="remove-span">
          <a href="" class="remove-product-item" title="Remove Membership">
            <i class="mp-icon mp-icon-cancel-circled mp-16"></i>
          </a>
        </span>
      </li>
            <?php
        }
    }

    /**
     * Theme dropdown.
     *
     * @param  string $selected The selected.
     * @return void
     */
    public static function theme_dropdown($selected = null)
    {
        $themes = MeprGroup::group_themes();
        ?>
    <select name="<?php echo esc_attr(MeprGroup::$group_theme_str); ?>" class="group_theme_dropdown">
        <?php
        foreach ($themes as $theme) {
            $css  = basename($theme);
            $name = preg_replace('#\.css$#', '', $css);
            $name = ucwords(preg_replace('#_#', ' ', $name));
            ?>
            <option value="<?php echo esc_attr($css); ?>" <?php selected($css, $selected); ?>>
                <?php echo esc_html($name); ?>
            </option>
            <?php
        }
        ?>
      <option value="custom" <?php selected('custom', $selected); ?>>
        <?php esc_html_e('None / Custom', 'memberpress'); ?>
      </option>
    </select>
        <?php
    }

    /**
     * Get products dropdown.
     *
     * @param  string $chosen The chosen.
     * @return void
     */
    public static function get_products_dropdown($chosen = null)
    {
        $products = MeprCptModel::all('MeprProduct', false, [
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);
        ?>
      <select name="<?php echo esc_attr(MeprGroup::$products_str); ?>[product][]" class="group_products_dropdown">
        <?php foreach ($products as $p) : ?>
          <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $chosen); ?>>
            <?php echo esc_html($p->post_title); ?>
          </option>
        <?php endforeach; ?>
      </select>
        <?php
    }

    /**
     * Get product fallback dropdown.
     *
     * @param  MeprGroup $group The group.
     * @return void
     */
    public static function get_product_fallback_dropdown($group)
    {
        $products = $group->products();
        $selected = $group->fallback_membership;
        ?>
      <select name="<?php echo esc_attr(MeprGroup::$fallback_membership_str); ?>" class="group_theme_dropdown">
        <option value="" <?php selected('', $selected); ?>>
          <?php esc_html_e('Default', 'memberpress'); ?>
        </option>
        <?php foreach ($products as $p) : ?>
          <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $selected); ?>>
            <?php echo esc_html($p->post_title); ?>
          </option>
        <?php endforeach; ?>
      </select>
        <?php
    }

    /**
     * Group page item.
     *
     * @param  MeprProduct $product The product.
     * @param  MeprGroup   $group   The group.
     * @param  boolean     $preview The preview.
     * @return void
     */
    public static function group_page_item($product, $group = null, $preview = false)
    {
        ob_start();
        $benefits = '';

        if ($group === null) {
            $group = new MeprGroup();
        }

        if (!empty($product->pricing_benefits)) {
            $benefits = '<div class="mepr-price-box-benefits-list">';

            foreach ($product->pricing_benefits as $index => $b) {
                if ('' !== trim($b)) {
                    $benefits .= '<div class="mepr-price-box-benefits-item">'
                        . MeprHooks::apply_filters('mepr_price_box_benefit', $b, $index)
                        . '</div>';
                }
            }

            $benefits .= '</div>';
        }

        $user   = MeprUtils::get_currentuserinfo(); // If not logged in, $user will be false.
        $active = true; // Always true for now - that way users can click the button and see the custom "you don't have access" message now.

        $group_classes_str = ($product->is_highlighted) ? 'highlighted' : '';
        $group_classes_str = MeprHooks::apply_filters(
            'mepr_group_css_classes_string',
            $group_classes_str,
            $product,
            $group,
            $preview
        );

        ?>
    <div id="mepr-price-box-<?php echo esc_attr($product->ID); ?>"
      class="mepr-price-box <?php echo esc_attr($group_classes_str); ?>">
        <?php
        $badge_text = !empty($product->pricing_badge_txt)
            ? $product->pricing_badge_txt
            : __('Most Popular', 'memberpress');
        if ($preview) :
            // Always render in preview mode so JS can toggle it.
            ?>
        <div class="mepr-most-popular" <?php echo !$product->is_highlighted ? 'style="display:none;"' : ''; ?>><?php echo esc_html($badge_text); ?></div>
        <?php elseif ($product->is_highlighted) : ?>
        <div class="mepr-most-popular"><?php echo esc_html($badge_text); ?></div>
        <?php endif; ?>
      <div class="mepr-price-box-head">
        <div class="mepr-price-box-title"><?php echo wp_kses_post($product->pricing_title); ?></div>
        <?php if ($preview) : ?>
          <div class="mepr-price-box-price"></div>
          <span class="mepr-price-box-price-loading">
            <img src="<?php echo esc_url(admin_url('/images/wpspin_light.gif')); ?>"/>
          </span>
        <?php elseif ($product->pricing_display !== 'none') : ?>
          <div class="mepr-price-box-price">
            <?php
            $mepr_coupon_code = $product->get_applicable_coupon_code();
            $mepr_coupon_code = $mepr_coupon_code !== '' ? $mepr_coupon_code : null;

            if ($product->pricing_display === 'auto') {
                // When a coupon is applied, show the original price crossed out
                // with the discounted amount below. Full terms are on checkout.
                if (!empty($mepr_coupon_code)) {
                    $original_str   = MeprProductsHelper::format_currency($product, true, null, false);
                    $discount_price = MeprProductsHelper::get_discounted_price($product, $mepr_coupon_code);
                    $discount_str   = MeprAppHelper::format_currency($discount_price);

                    // For recurring memberships, append asterisk and show "first payment" note.
                    $is_recurring = !$product->is_one_time_payment();

                    // Wrap currency symbol in spans for modern template styling.
                    if ($group && !empty($group->modern_template)) {
                        $mepr_options  = MeprOptions::fetch();
                        $original_str  = preg_replace(
                            '/\/(.*)/',
                            "<span class='mepr-price-box-price-term'>$0</span>",
                            $original_str
                        );
                        $original_str  = str_replace(
                            $mepr_options->currency_symbol,
                            '<span class="mepr-price-box-price-currency">' . $mepr_options->currency_symbol . '</span>',
                            $original_str
                        );
                        $discount_str  = str_replace(
                            $mepr_options->currency_symbol,
                            '<span class="mepr-price-box-price-currency">' . $mepr_options->currency_symbol . '</span>',
                            $discount_str
                        );
                    }

                    echo '<span class="mepr-price-box-original-price">' . wp_kses_post($original_str) . '</span>';
                    echo '<span class="mepr-price-box-discount-price">' . wp_kses_post($discount_str) . '</span>';
                    $discount_note = $group ? $group->coupon_discount_note : __('first payment', 'memberpress');
                    if ($is_recurring && '' !== $discount_note) {
                        echo '<span class="mepr-price-box-discount-note">'
                            . esc_html($discount_note) . '</span>';
                    }
                } else {
                    $price_str = MeprProductsHelper::format_currency($product, true, null, false);

                    // Wrap currency symbol and billing term in spans for modern template styling.
                    if ($group && !empty($group->modern_template)) {
                        $mepr_options = MeprOptions::fetch();
                        $price_str    = preg_replace(
                            '/\/(.*)/',
                            "<span class='mepr-price-box-price-term'>$0</span>",
                            $price_str
                        );
                        $price_str    = str_replace(
                            $mepr_options->currency_symbol,
                            '<span class="mepr-price-box-price-currency">' . $mepr_options->currency_symbol . '</span>',
                            $price_str
                        );
                    }

                    echo wp_kses_post($price_str);
                }
            } else {
                $custom_str = $product->custom_price;

                // Wrap currency symbol in span for modern template styling.
                if ($group && !empty($group->modern_template)) {
                    $mepr_options   = MeprOptions::fetch();
                    $symbol         = $mepr_options->currency_symbol;
                    $escaped_symbol = esc_html($symbol);
                    $custom_str     = str_replace(
                        $escaped_symbol,
                        '<span class="mepr-price-box-price-currency">' . $escaped_symbol . '</span>',
                        esc_html($custom_str)
                    );
                } else {
                    $custom_str = esc_html($custom_str);
                }

                echo wp_kses_post($custom_str);
                if (!empty($product->custom_price_term)) {
                    echo ' <span class="mepr-price-box-price-term">'
                        . esc_html($product->custom_price_term) . '</span>';
                }
            }
            ?>
          </div>
        <?php endif; ?>
          <?php if ($preview || !empty($product->pricing_heading_txt)) : ?>
          <div class="mepr-price-box-heading"><?php echo wp_kses_post($product->pricing_heading_txt); ?></div>
          <?php endif; ?>
          <?php
            if ($preview || in_array($product->pricing_button_position, ['header','both'], true)) {
                echo wp_kses_post(self::price_box_button($user, $group, $product, $active));
            }
            ?>
      </div>
      <div class="mepr-price-box-benefits"><?php echo wp_kses_post($benefits); ?></div>
      <div class="mepr-price-box-foot">
        <div class="mepr-price-box-footer"><?php echo wp_kses_post($product->pricing_footer_txt); ?></div>
        <?php
        if ($preview || in_array($product->pricing_button_position, ['footer','both'], true)) {
            echo wp_kses_post(self::price_box_button($user, $group, $product, $active));
        }
        ?>
      </div>
    </div>
        <?php
        $output = ob_get_clean();
        echo wp_kses_post(
            MeprHooks::apply_filters('mepr_group_page_item_output', $output, $product, $group, $preview)
        );
    }

    /**
     * Price box button classes.
     *
     * @param  MeprGroup   $grp    The group.
     * @param  MeprProduct $prd    The product.
     * @param  boolean     $active The active.
     * @return string
     */
    public static function price_box_button_classes($grp, $prd, $active)
    {
        $bc = '';

        if ($prd->is_highlighted) {
            $bc .= $grp->page_button_highlighted_class;
        } else {
            $bc .= $grp->page_button_class;
        }

        if (!$active) {
            $bc .= " mepr-disabled {$grp->page_button_disabled_class}";
        }

        return trim($bc);
    }

    /**
     * Price box button.
     *
     * @param  MeprUser    $user    The user.
     * @param  MeprGroup   $group   The group.
     * @param  MeprProduct $product The product.
     * @param  boolean     $active  The active.
     * @return string
     */
    public static function price_box_button($user, $group, $product, $active)
    {
        ob_start();

        ?>
    <div class="mepr-price-box-button">
        <?php
        // All this logic is for showing a "VIEW" button instead of "Buy Now" if the member has already purchased it
        // and the membership access URL is set for that membership - and you can't buy the same membership more than once.
        if (
            $user && !$product->simultaneous_subscriptions &&
            $user->is_already_subscribed_to($product->ID) &&
            !empty($product->access_url)
        ) :
            ?>
          <a href="<?php echo esc_url($product->access_url); ?>"
            class="<?php echo esc_attr(self::price_box_button_classes($group, $product, true)); ?>">
            <?php esc_html_e('View', 'memberpress'); ?>
          </a>
        <?php else : ?>
            <a <?php echo $active ? 'href="' . esc_url($product->url()) . '"' : ''; ?>
              class="<?php echo esc_attr(self::price_box_button_classes($group, $product, $active)); ?>">
                <?php
                echo wp_kses_post(
                    !empty($product->pricing_button_txt) ? $product->pricing_button_txt : __('Sign Up', 'memberpress')
                );
                ?>
            </a>
        <?php endif; ?>
    </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Build the inline CSS custom properties string for a modern template group.
     *
     * @param  MeprGroup $group The group object.
     * @return string The inline style string with CSS custom properties.
     */
    public static function build_modern_style_string($group)
    {
        $opts = is_array($group->modern_template_options) ? $group->modern_template_options : [];

        $radius_map = [
            'none'    => '0',
            'subtle'  => '6px',
            'rounded' => '12px',
        ];
        $shadow_map = [
            'none'      => 'none',
            'subtle'    => '0 1px 3px rgba(0,0,0,0.1)',
            'prominent' => '0 4px 20px rgba(0,0,0,0.12)',
        ];

        $radius = $radius_map[$opts['border_radius'] ?? 'rounded'] ?? '12px';
        $shadow = $shadow_map[$opts['shadow'] ?? 'subtle'] ?? '0 1px 3px rgba(0,0,0,0.1)';

        return sprintf(
            '--mepr-primary:%s;--mepr-accent:%s;--mepr-btn-color:%s;'
            . '--mepr-btn-text-color:%s;--mepr-text-color:%s;--mepr-price-color:%s;'
            . '--mepr-hl-text-color:%s;--mepr-hl-price-color:%s;--mepr-hl-btn-color:%s;'
            . '--mepr-hl-btn-text-color:%s;--mepr-radius:%s;--mepr-shadow:%s;',
            esc_attr($opts['primary_color'] ?? '#2563eb'),
            esc_attr($opts['accent_color'] ?? '#16a34a'),
            esc_attr($opts['button_color'] ?? '#2563eb'),
            esc_attr($opts['btn_text_color'] ?? '#ffffff'),
            esc_attr($opts['text_color'] ?? '#1e293b'),
            esc_attr($opts['price_color'] ?? '#0f172a'),
            esc_attr($opts['hl_text_color'] ?? '#ffffff'),
            esc_attr($opts['hl_price_color'] ?? '#ffffff'),
            esc_attr($opts['hl_btn_color'] ?? '#16a34a'),
            esc_attr($opts['hl_btn_text_color'] ?? '#ffffff'),
            esc_attr($radius),
            esc_attr($shadow)
        );
    }

    /**
     * Filter eligible coupon IDs.
     *
     * Filters coupon data arrays to only include those whose coupon IDs
     * have publish status.
     *
     * @param  array<array<string, mixed>> $coupons The coupons.
     * @return array<array<string, mixed>> The eligible coupons.
     */
    private static function filter_eligible_coupons(array $coupons): array
    {
        if (empty($coupons)) {
            return [];
        }

        // Extract coupon IDs.
        $ids = array_column($coupons, MeprGroup::$group_coupon_coupon_id_str);
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return [];
        }

        // Query for eligible coupon IDs in a single query.
        $query = new WP_Query([
            'post_type'   => MeprCoupon::$cpt,
            'post__in'    => array_unique($ids),
            'fields'      => 'ids',
            'post_status' => 'publish',
        ]);

        $eligible_ids = $query->posts;

        // Filter original coupon data arrays to only include eligible IDs.
        $filtered = [];
        foreach ($coupons as $coupon_data) {
            $coupon_id = intval($coupon_data[MeprGroup::$group_coupon_coupon_id_str] ?? 0);
            if (in_array($coupon_id, $eligible_ids, true)) {
                $filtered[] = array_merge($coupon_data, [MeprGroup::$group_coupon_coupon_id_str => $coupon_id]);
            }
        }

        return $filtered;
    }

    /**
     * Get existing coupons list for the group.
     *
     * @param  MeprGroup $group The group.
     * @return void
     */
    public static function get_existing_coupons_list($group)
    {
        $coupons = $group->group_coupons;
        if (!empty($coupons) && is_array($coupons)) {
            // Collect all coupon IDs first for batch status fetching.
            foreach ($coupons as &$coupon_data) {
                $coupon_data[MeprGroup::$group_coupon_coupon_id_str] = intval(
                    $coupon_data[MeprGroup::$group_coupon_coupon_id_str] ?? 0
                );
            }
        }
        $coupons = self::filter_eligible_coupons($coupons);

        if (empty($coupons)) {
            $unique_id        = wp_unique_id('coupon_');
            $apply_box_id     = 'apply_schedule_box_' . $unique_id;
            $unapply_box_id   = 'unapply_schedule_box_' . $unique_id;
            $index            = 0;
            $default_timezone = MeprCouponsHelper::get_wp_selected_timezone_setting();

            self::render_coupon_item([
                'coupon_id'        => null,
                'index'            => $index,
                'apply_checked'    => false,
                'apply_date_ts'    => 0,
                'apply_timezone'   => $default_timezone,
                'unapply_checked'  => false,
                'unapply_date_ts'  => 0,
                'unapply_timezone' => $default_timezone,
                'apply_box_id'     => $apply_box_id,
                'unapply_box_id'   => $unapply_box_id,
            ]);
            return;
        }

        foreach ($coupons as $index => $coupon_data) {
            $should_apply     = (bool) ($coupon_data[MeprGroup::$group_coupon_should_apply_str] ?? false);
            $should_unapply   = (bool) ($coupon_data[MeprGroup::$group_coupon_should_unapply_str] ?? false);
            $apply_date_ts    = intval($coupon_data[MeprGroup::$group_coupon_applies_on_str] ?? 0);
            $unapply_date_ts  = intval($coupon_data[MeprGroup::$group_coupon_unapplies_on_str] ?? 0);
            $apply_timezone   = $coupon_data[MeprGroup::$group_coupon_apply_timezone_str]
                ?? MeprCouponsHelper::get_wp_selected_timezone_setting();
            $unapply_timezone = $coupon_data[MeprGroup::$group_coupon_unapply_timezone_str]
                ?? MeprCouponsHelper::get_wp_selected_timezone_setting();

            // Generate unique ID for DOM elements (generated fresh on each render).
            $unique_id      = wp_unique_id('coupon_');
            $apply_box_id   = 'apply_schedule_box_' . $unique_id;
            $unapply_box_id = 'unapply_schedule_box_' . $unique_id;

            self::render_coupon_item([
                'coupon_id'        => $coupon_data[MeprGroup::$group_coupon_coupon_id_str],
                'index'            => $index,
                'apply_checked'    => $should_apply,
                'apply_date_ts'    => $apply_date_ts,
                'apply_timezone'   => $apply_timezone,
                'unapply_checked'  => $should_unapply,
                'unapply_date_ts'  => $unapply_date_ts,
                'unapply_timezone' => $unapply_timezone,
                'apply_box_id'     => $apply_box_id,
                'unapply_box_id'   => $unapply_box_id,
            ]);
        }
    }

    /**
     * Render a single coupon item.
     *
     * @param  array<string, mixed> $args Array of arguments.
     * @return void
     */
    private static function render_coupon_item(array $args)
    {
        $coupon_id        = $args['coupon_id'] ?? null;
        $index            = $args['index'] ?? 0;
        $apply_checked    = $args['apply_checked'] ?? false;
        $apply_date_ts    = $args['apply_date_ts'] ?? 0;
        $apply_timezone   = empty($args['apply_timezone']) ?
            MeprCouponsHelper::get_wp_selected_timezone_setting() : $args['apply_timezone'];
        $unapply_checked  = $args['unapply_checked'] ?? false;
        $unapply_date_ts  = $args['unapply_date_ts'] ?? 0;
        $unapply_timezone = empty($args['unapply_timezone']) ?
            MeprCouponsHelper::get_wp_selected_timezone_setting() : $args['unapply_timezone'];
        $apply_box_id     = $args['apply_box_id'] ?? '';
        $unapply_box_id   = $args['unapply_box_id'] ?? '';
        $field_prefix     = $args['field_prefix'] ?? MeprGroup::$group_coupons_str;
        ?>
        <li class="coupon-item">
            <?php MeprGroupsHelper::get_coupons_dropdown($coupon_id, $index); ?>
          <div class="coupon-schedule-options">
            <?php
            $idx_prefix = "{$field_prefix}[{$index}]";
            MeprView::render(
                '/admin/groups/parts/coupon-schedule-option',
                [
                    'checkbox_field_name' => $idx_prefix . '[' . MeprGroup::$group_coupon_should_apply_str . ']',
                    'label'               => __('Apply from date', 'memberpress'),
                    'box_id'              => $apply_box_id,
                    'box_class'           => 'apply-box',
                    'checked'             => $apply_checked,
                    'month_field'         => $idx_prefix . '[' . MeprGroup::$group_coupon_apply_month_str . ']',
                    'day_field'           => $idx_prefix . '[' . MeprGroup::$group_coupon_apply_day_str . ']',
                    'year_field'          => $idx_prefix . '[' . MeprGroup::$group_coupon_apply_year_str . ']',
                    'hour_field'          => $idx_prefix . '[' . MeprGroup::$group_coupon_apply_hour_str . ']',
                    'minute_field'        => $idx_prefix . '[' . MeprGroup::$group_coupon_apply_minute_str . ']',
                    'timezone_field'      => $idx_prefix . '[' . MeprGroup::$group_coupon_apply_timezone_str . ']',
                    'date_ts'             => $apply_date_ts,
                    'timezone'            => $apply_timezone,
                    'begin'               => true,
                    'help'                => sprintf(
                        // Translators: %1$s: open strong tag, %2$s: close strong tag.
                        esc_html__(
                            'Coupon applies starting from %1$s00 seconds%2$s of the selected hour and minute.',
                            'memberpress'
                        ),
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
                    'checkbox_field_name' => $idx_prefix . '[' . MeprGroup::$group_coupon_should_unapply_str . ']',
                    'label'               => __('Unapply on date', 'memberpress'),
                    'box_id'              => $unapply_box_id,
                    'box_class'           => 'unapply-box',
                    'checked'             => $unapply_checked,
                    'month_field'         => $idx_prefix . '[' . MeprGroup::$group_coupon_unapply_month_str . ']',
                    'day_field'           => $idx_prefix . '[' . MeprGroup::$group_coupon_unapply_day_str . ']',
                    'year_field'          => $idx_prefix . '[' . MeprGroup::$group_coupon_unapply_year_str . ']',
                    'hour_field'          => $idx_prefix . '[' . MeprGroup::$group_coupon_unapply_hour_str . ']',
                    'minute_field'        => $idx_prefix . '[' . MeprGroup::$group_coupon_unapply_minute_str . ']',
                    'timezone_field'      => $idx_prefix . '[' . MeprGroup::$group_coupon_unapply_timezone_str . ']',
                    'date_ts'             => $unapply_date_ts,
                    'timezone'            => $unapply_timezone,
                    'begin'               => false,
                    'help'                => sprintf(
                        // Translators: %1$s: open strong tag, %2$s: close strong tag.
                        esc_html__(
                            'Coupon unapplies on %1$s59 seconds%2$s of the selected hour and minute.',
                            'memberpress'
                        ),
                        '<strong>',
                        '</strong>'
                    ),
                ]
            );
            ?>
          </div>
          <span class="remove-span">
            <a href="" class="remove-coupon-item" title="Remove Coupon">
              <i class="mp-icon mp-icon-cancel-circled mp-16"></i>
            </a>
          </span>
        </li>
        <?php
    }

    /**
     * Get coupons dropdown.
     *
     * @param  integer        $chosen The chosen coupon ID.
     * @param  integer|string $index  The index for the coupon (or 'TEMPLATE_INDEX' for template).
     * @return void
     */
    public static function get_coupons_dropdown($chosen = null, $index = 'TEMPLATE_INDEX')
    {
        $field_name = sprintf(
            '%s[%s][%s]',
            esc_attr(MeprGroup::$group_coupons_str),
            esc_attr($index),
            esc_attr(MeprGroup::$group_coupon_coupon_id_str)
        );
        MeprCouponsHelper::coupons_dropdown(
            $field_name,
            $chosen,
            [],
            false,
            'group_coupons_dropdown',
            __('-- Select Coupon --', 'memberpress')
        );
    }

    /**
     * Get group coupon field map.
     *
     * Maps internal keys (should_start, start_month, etc.) to group coupon meta keys.
     *
     * @return array<string, string>
     */
    public static function get_group_coupon_field_map(): array
    {
        return [
            'should_start'   => MeprGroup::$group_coupon_should_apply_str,
            'should_end'     => MeprGroup::$group_coupon_should_unapply_str,
            'start_month'    => MeprGroup::$group_coupon_apply_month_str,
            'start_day'      => MeprGroup::$group_coupon_apply_day_str,
            'start_year'     => MeprGroup::$group_coupon_apply_year_str,
            'start_hour'     => MeprGroup::$group_coupon_apply_hour_str,
            'start_minute'   => MeprGroup::$group_coupon_apply_minute_str,
            'start_timezone' => MeprGroup::$group_coupon_apply_timezone_str,
            'start_date_ts'  => MeprGroup::$group_coupon_applies_on_str,
            'end_month'      => MeprGroup::$group_coupon_unapply_month_str,
            'end_day'        => MeprGroup::$group_coupon_unapply_day_str,
            'end_year'       => MeprGroup::$group_coupon_unapply_year_str,
            'end_hour'       => MeprGroup::$group_coupon_unapply_hour_str,
            'end_minute'     => MeprGroup::$group_coupon_unapply_minute_str,
            'end_timezone'   => MeprGroup::$group_coupon_unapply_timezone_str,
            'end_date_ts'    => MeprGroup::$group_coupon_unapplies_on_str,
        ];
    }
}
