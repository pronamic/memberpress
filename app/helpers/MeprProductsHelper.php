<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

use MemberPress\GroundLevel\Support\Time;

class MeprProductsHelper
{
    /**
     * Cache for auto-apply coupons queries.
     *
     * Key: MD5 hash of sorted product IDs
     * Value: Array of coupon data arrays
     *
     * @var array<string, array<array<string, mixed>>>
     */
    private static $auto_apply_coupons_cache = [];

    /**
     * Render a dropdown for selecting period type.
     *
     * @param string $id The ID for the dropdown element.
     *
     * @return void
     */
    public static function period_type_dropdown($id)
    {
        ?>
      <select id="<?php echo esc_attr($id); ?>-custom"
              class="mepr-dropdown mepr-period-type-dropdown"
              data-period-type-id="<?php echo esc_attr($id); ?>">
        <option value="months"><?php esc_html_e('months', 'memberpress'); ?>&nbsp;</option>
        <option value="weeks"><?php esc_html_e('weeks', 'memberpress'); ?>&nbsp;</option>
      </select>
        <?php
    }

    /**
     * Render a dropdown for selecting preset periods.
     *
     * @param string $period_str      The period string.
     * @param string $period_type_str The period type string.
     *
     * @return void
     */
    public static function preset_period_dropdown($period_str, $period_type_str)
    {
        ?>
    <select id="<?php echo esc_attr($period_type_str); ?>-presets"
            data-period-id="<?php echo esc_attr($period_str); ?>"
            data-period-type-id="<?php echo esc_attr($period_type_str); ?>">
      <option value="monthly"><?php esc_html_e('Monthly', 'memberpress'); ?>&nbsp;</option>
      <option value="yearly"><?php esc_html_e('Yearly', 'memberpress'); ?>&nbsp;</option>
      <option value="weekly"><?php esc_html_e('Weekly', 'memberpress'); ?>&nbsp;</option>
      <option value="quarterly"><?php esc_html_e('Every 3 Months', 'memberpress'); ?>&nbsp;</option>
      <option value="semi-annually"><?php esc_html_e('Every 6 Months', 'memberpress'); ?>&nbsp;</option>
      <option value="custom"><?php esc_html_e('Custom', 'memberpress'); ?>&nbsp;</option>
    </select>
        <?php
    }

    /**
     * Generate a list of pricing benefits.
     *
     * @param array $benefits The list of benefits.
     *
     * @return void
     */
    public static function generate_pricing_benefits_list($benefits)
    {
        if (!empty($benefits)) {
            foreach ($benefits as $b) {
                ?>
        <li class="benefit-item">
          <input type="text" name="<?php echo esc_attr(MeprProduct::$pricing_benefits_str); ?>[]" class="benefit-input" value="<?php echo esc_attr(wp_unslash($b)); ?>" />
          <span class="remove-span">
            <a href="" class="remove-benefit-item" title="<?php esc_attr_e('Remove Benefit', 'memberpress'); ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
          </span>
        </li>
                <?php
            }
        } else {
            ?>
        <li class="benefit-item">
          <input type="text" name="<?php echo esc_attr(MeprProduct::$pricing_benefits_str); ?>[]" class="benefit-input" value="" />
          <span class="remove-span">
            <a href="" class="remove-benefit-item" title="<?php esc_attr_e('Remove Benefit', 'memberpress'); ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
          </span>
        </li>
            <?php
        }
    }

    /**
     * Show the 'Add New' button for pricing benefits.
     *
     * @return void
     */
    public static function show_pricing_benefits_add_new()
    {
        ?>
    <a href="" class="add-new-benefit" title="<?php esc_attr_e('Add Benefit', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
        <?php
    }

    /**
     * Format a product's price as a currency string.
     *
     * @param MeprProduct $product       The product object.
     * @param boolean     $show_symbol   Whether to show the currency symbol.
     * @param string|null $coupon_code   The coupon code.
     * @param boolean     $show_prorated Whether to show prorated price.
     *
     * @return string The formatted currency string.
     */
    public static function format_currency($product, $show_symbol = true, $coupon_code = null, $show_prorated = true)
    {
        // Clone so we can apply trial overrides for display without mutating the original product.
        $display_product = clone $product;

        if (!empty($coupon_code)) {
            $coupon = MeprCoupon::get_one_from_code($coupon_code);

            if ($coupon !== false) {
                $coupon->maybe_apply_trial_override($display_product);
            }
        }

        return MeprAppHelper::format_price_string(
            $display_product,
            $display_product->adjusted_price($coupon_code, $show_prorated),
            $show_symbol,
            $coupon_code,
            $show_prorated
        );
    }

    /**
     * Get the discounted price for a product with a coupon applied.
     *
     * Returns the first/initial price the customer will pay. For first-payment
     * or trial-override coupons this is the trial amount; for standard coupons
     * it is the adjusted recurring price.
     *
     * @param MeprProduct $product     The product object.
     * @param string      $coupon_code The coupon code.
     *
     * @return float The discounted price.
     */
    public static function get_discounted_price($product, $coupon_code)
    {
        $display_product = clone $product;
        $coupon          = MeprCoupon::get_one_from_code($coupon_code);

        if ($coupon !== false) {
            $coupon->maybe_apply_trial_override($display_product);

            $discount_mode = $coupon->get_discount_mode($display_product);

            // For first-payment and trial-override coupons, the discounted
            // price is the trial amount set by maybe_apply_trial_override().
            if (in_array($discount_mode, ['first-payment', 'trial-override'], true)) {
                return (float) $display_product->trial_amount;
            }
        }

        return (float) $display_product->adjusted_price($coupon_code, false);
    }

    /**
     * Get the list of who can purchase items for a product.
     *
     * @param MeprProduct $product The product object.
     *
     * @return void
     */
    public static function get_who_can_purchase_items($product)
    {
        $id = 1;
        ?>
        <?php if (!empty($product->who_can_purchase)) : ?>
            <?php foreach ($product->who_can_purchase as $who) : ?>
                <?php if ($who->user_type === 'members') {
                    $class = '';
                } else {
                    $class = 'who_have_purchased';
                } ?>
          <li>
                <?php self::get_user_types_dropdown($who->user_type, $id); ?>
            <span id="who_have_purchased-<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($class); ?>">
                <?php self::get_purchase_type_dropdown(isset($who->purchase_type) ? $who->purchase_type : null); ?>
                <?php self::get_products_dropdown($who->product_id, $product->ID); ?>
            </span>
            <span class="remove-span">
              <a href="" class="remove-who-can-purchase-rule" title="Remove Rule"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
            </span>
          </li>
                <?php $id++;
            endforeach; ?>
        <?php else : ?>
            <?php self::get_blank_who_can_purchase_row($product); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Get a blank row for who can purchase items.
     *
     * @param MeprProduct $product The product object.
     *
     * @return void
     */
    public static function get_blank_who_can_purchase_row($product)
    {
        $id = 1;
        ?>
      <li>
        <?php self::get_user_types_dropdown(null, $id); ?>
        <span id="who_have_purchased-<?php echo esc_attr($id); ?>" class="who_have_purchased">
          <?php self::get_purchase_type_dropdown(null); ?>
          <?php self::get_products_dropdown(null, $product->ID); ?>
        </span>
        <span class="remove-span">
          <a href="" class="remove-who-can-purchase-rule" title="Remove Rule"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
        </span>
      </li>
        <?php
    }

    /**
     * Render a dropdown for selecting user types.
     *
     * @param string|null $chosen The chosen user type.
     * @param integer     $id     The ID for the dropdown element.
     *
     * @return void
     */
    public static function get_user_types_dropdown($chosen, $id)
    {
        ?>
      <select name="<?php echo esc_attr(MeprProduct::$who_can_purchase_str . '-user_type'); ?>[]" class="user_types_dropdown" data-value="<?php echo esc_attr($id); ?>">
        <option value="everyone" <?php selected('everyone', $chosen); ?>><?php esc_html_e('Everyone', 'memberpress'); ?></option>
        <option value="guests" <?php selected('guests', $chosen); ?>><?php esc_html_e('Guests', 'memberpress'); ?></option>
        <option value="members" <?php selected('members', $chosen); ?>><?php esc_html_e('Members', 'memberpress'); ?></option>
        <option value="disabled" <?php selected('disabled', $chosen); ?>><?php esc_html_e('No One (Disabled)', 'memberpress'); ?></option>
      </select>
        <?php
    }

    /**
     * Render a dropdown for selecting products.
     *
     * @param string|null  $chosen The chosen product ID.
     * @param integer|null $my_id  The current product ID.
     *
     * @return void
     */
    public static function get_products_dropdown($chosen = null, $my_id = null)
    {
        $products = MeprCptModel::all('MeprProduct');

        ?>
      <select name="<?php echo esc_attr(MeprProduct::$who_can_purchase_str . '-product_id'); ?>[]" id="<?php echo esc_attr(MeprProduct::$who_can_purchase_str . '-product_id'); ?>">
        <option value="nothing" <?php selected($chosen, 'nothing'); ?>><?php esc_html_e('no active memberships', 'memberpress'); ?></option>
        <option value="anything" <?php selected($chosen, 'anything'); ?>><?php esc_html_e('any membership', 'memberpress'); ?></option>
        <option value="subscribed-before" <?php selected($chosen, 'subscribed-before'); ?>><?php esc_html_e('subscribed to this membership before', 'memberpress'); ?></option>
        <option value="not-subscribed-before" <?php selected($chosen, 'not-subscribed-before'); ?>><?php esc_html_e('NOT subscribed to this membership before', 'memberpress'); ?></option>
        <option value="not-subscribed-any-before" <?php selected($chosen, 'not-subscribed-any-before'); ?>><?php esc_html_e('NOT subscribed to any membership before', 'memberpress'); ?></option>
        <?php foreach ($products as $p) : ?>
            <?php if ($p->ID !== $my_id) : ?>
            <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $chosen) ?>><?php echo esc_html($p->post_title); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php MeprHooks::do_action('mepr_get_products_dropdown_options', $chosen, $my_id, $products); ?>
      </select>
        <?php
    }

    /**
     * Render a dropdown for selecting purchase types.
     *
     * @param string|null $chosen The chosen purchase type.
     *
     * @return void
     */
    public static function get_purchase_type_dropdown($chosen = null)
    {
        ?>
      <select name="<?php echo esc_attr(MeprProduct::$have_or_had_str . '-type'); ?>[]" id="purchase_type_dropdown">
        <option value="have" <?php selected($chosen, 'have'); ?>><?php esc_html_e('who currently have', 'memberpress'); ?></option>
        <option value="had" <?php selected($chosen, 'had'); ?>><?php esc_html_e('who had', 'memberpress'); ?></option>
      </select>
        <?php
    }

    /**
     * Generate HTML for a product link.
     *
     * @param MeprProduct $product The product object.
     * @param string      $content The content for the link.
     *
     * @return string The generated HTML.
     */
    public static function generate_product_link_html($product, $content)
    {
        $permalink = MeprUtils::get_permalink($product->ID);
        $title     = ($content === '') ? $product->post_title : $content;

        ob_start();
        ?>
      <a href="<?php echo esc_url($permalink); ?>" class="mepr_product_link mepr-product-link-<?php echo esc_attr($product->ID); ?>"><?php echo esc_html($title); ?></a>
        <?php

        return ob_get_clean();
    }

    /**
     * Display an invoice for a product.
     *
     * @param MeprProduct    $product       The product object.
     * @param string|boolean $coupon_code   The coupon code.
     * @param boolean        $display_title Whether to display the title.
     *
     * @return void
     */
    public static function display_invoice($product, $coupon_code = false, $display_title = false)
    {
        $current_user = MeprUtils::get_currentuserinfo();
        MeprUtils::get_currentuserinfo();

        if ($product->is_one_time_payment() || !$product->is_payment_required($coupon_code)) {
            $tmp_txn          = new MeprTransaction();
            $tmp_txn->id      = 0;
            $tmp_txn->user_id = (isset($current_user->ID)) ? $current_user->ID : 0;
            $tmp_txn->load_product_vars($product, $coupon_code, true);
            $tmp_txn               = MeprHooks::apply_filters('mepr_display_invoice_txn', $tmp_txn);
            $tmp_txn->expires_at   = gmdate(get_option('date_format'), $product->get_expires_at(time()));
            $tmp_txn->expire_type  = $product->expire_type;
            $tmp_txn->expire_unit  = $product->expire_unit;
            $tmp_txn->expire_after = $product->expire_after;
            $tmp_txn->expire_fixed = $product->expire_fixed;
            $tmp_txn->period_type  = $product->period_type;

            if ($display_title) {
                echo esc_html($product->post_title) . ': ';
            }

            if (empty($coupon_code)) { // We've already validated the coupon before including signup_form.php.
                if ($product->register_price_action === 'custom') {
                    echo wp_kses(stripslashes($product->register_price), MeprAppHelper::kses_allowed_tags());
                } else {
                    echo esc_html(MeprAppHelper::format_price_string($tmp_txn, $tmp_txn->amount, true, $coupon_code));
                }
            } else {
                echo esc_html(MeprAppHelper::format_price_string($tmp_txn, $tmp_txn->amount, true, $coupon_code));
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo self::renewal_str($product); // Possibly print out the renewal string.
        } else {
            $current_user = MeprUtils::get_currentuserinfo();
            MeprUtils::get_currentuserinfo();

            // Setup to possibly do a proration without actually creating a subscription record.
            $tmp_sub          = new MeprSubscription();
            $tmp_sub->id      = 0;
            $tmp_sub->user_id = (isset($current_user->ID)) ? $current_user->ID : 0;
            $tmp_sub->load_product_vars($product, $coupon_code, true);
            $tmp_sub->maybe_prorate();
            $tmp_sub->expires_at = gmdate(get_option('date_format'), $product->get_expires_at(time()));

            $tmp_sub = MeprHooks::apply_filters('mepr_display_invoice_sub', $tmp_sub);

            if ($display_title) {
                echo esc_html($product->post_title) . ': ';
            }

            if ($product->register_price_action === 'custom' && empty($coupon_code) && !$tmp_sub->prorated_trial) {
                printf('<span class="mepr-custom-price">%s</span>', wp_kses(stripslashes($product->register_price), MeprAppHelper::kses_allowed_tags()));
            } else {
                echo esc_html(MeprAppHelper::format_price_string($tmp_sub, $tmp_sub->price, true, $coupon_code));
            }
        }
    }

    /**
     * Display an SPC invoice for a product.
     *
     * @param MeprProduct    $product             The product object.
     * @param string|boolean $coupon_code         The coupon code.
     * @param array          $order_bump_products The order bump products.
     *
     * @return void
     */
    public static function display_spc_invoice($product, $coupon_code = false, $order_bump_products = [])
    {
        $current_user = MeprUtils::get_currentuserinfo();
        MeprUtils::get_currentuserinfo();

        $tmp_txn          = new MeprTransaction();
        $tmp_txn->id      = 0;
        $tmp_txn->user_id = (isset($current_user->ID)) ? $current_user->ID : 0;
        $tmp_txn->load_product_vars($product, $coupon_code, true);
        $tmp_sub = '';

        if (!$product->is_one_time_payment() && $product->is_payment_required($coupon_code)) {
            // Setup to possibly do a proration without actually creating a subscription record.
            $tmp_sub          = new MeprSubscription();
            $tmp_sub->id      = 0;
            $tmp_sub->user_id = (isset($current_user->ID)) ? $current_user->ID : 0;
            $tmp_sub->load_product_vars($product, $coupon_code, true);
            $tmp_sub->maybe_prorate();
            $tmp_sub = MeprHooks::apply_filters('mepr_display_invoice_sub', $tmp_sub);
        }

        $order_bumps = [];

        try {
            foreach ($order_bump_products as $product) {
                list($transaction, $subscription) = MeprCheckoutCtrl::prepare_transaction(
                    $product,
                    0,
                    get_current_user_id(),
                    'manual',
                    false,
                    false
                );

                $order_bumps[] = [$product, $transaction, $subscription];
            }
        } catch (Exception $e) {
            // Ignore exception.
        }

        if (count($order_bumps)) {
            $invoice_html = MeprTransactionsHelper::get_invoice_order_bumps($tmp_txn, $tmp_sub, $order_bumps);
        } else {
            $invoice_html = MeprTransactionsHelper::get_invoice($tmp_txn, $tmp_sub);
        }

        echo $invoice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Get the terms for a product.
     *
     * @param MeprProduct      $product          The product object.
     * @param MeprUser|boolean $user             The user object.
     * @param string|null      $mepr_coupon_code The coupon code.
     *
     * @return string The product terms.
     */
    public static function product_terms($product, $user, $mepr_coupon_code = null)
    {
        $terms = '';

        if ($product->is_one_time_payment()) {
            if (empty($mepr_coupon_code) || !MeprCoupon::is_valid_coupon_code($mepr_coupon_code, $product->ID)) {
                $terms = MeprProductsHelper::format_currency($product);
            } else {
                $terms = MeprProductsHelper::format_currency($product, true, $mepr_coupon_code);
            }
        } else {
            // Setup to possibly do a proration without actually creating a subscription record.
            $tmp_sub          = new MeprSubscription();
            $tmp_sub->id      = 0;
            $tmp_sub->user_id = ($user === false) ? 0 : $user->ID;
            $tmp_sub->load_product_vars($product, $mepr_coupon_code, true);
            $tmp_sub->maybe_prorate();

            $terms = MeprAppHelper::format_price_string($tmp_sub, $tmp_sub->price, true, $mepr_coupon_code);
        }

        return $terms;
    }

    /**
     * Get the renewal string for a product.
     *
     * @param MeprProduct $product The product object.
     *
     * @return string The renewal string.
     */
    public static function renewal_str($product)
    {
        $renewal_str = '';
        $user        = MeprUtils::get_currentuserinfo();

        // Handle renewals.
        if ($product && $product->is_renewal()) {
            $last_txn = $product->get_last_active_txn($user->ID);
            if ($last_txn) {
                global $post;
                $is_thankyou_page = MeprAppHelper::is_thankyou_page($post);

                $new_created_at = $last_txn->expires_at;
                $new_expires_at = $product->get_expires_at();

                $new_created_at = MeprAppHelper::format_date($new_created_at);
                $new_expires_at = MeprAppHelper::format_date(gmdate('Y-m-d H:i:s', $new_expires_at));

                // RL Thank you special case.
                if ($is_thankyou_page) {
                    if (!empty($_GET['trans_num'])) {
                        $renewal_txn = MeprTransaction::get_one_by_trans_num(sanitize_text_field(wp_unslash($_GET['trans_num'])));
                    } elseif (!empty($_GET['transaction_id'])) {
                        $renewal_txn = MeprTransaction::get_one(sanitize_text_field(wp_unslash($_GET['transaction_id'])));
                    }
                    $active_txns = $user->transactions_for_product($product->ID);

                    // Ensure active txns larger than 1 and it's not an offline gateway renewal pending txn.
                    if (
                        !empty($active_txns) &&
                        isset($renewal_txn) &&
                        $renewal_txn->id > 0 &&
                        $renewal_txn->status !== MeprTransaction::$pending_str
                    ) {
                        if (count($active_txns) > 1) {
                            $new_created_at = MeprAppHelper::format_date($active_txns[1]->expires_at);
                            $new_expires_at = MeprAppHelper::format_date($renewal_txn->expires_at);
                        } else {
                            return ''; // No early renewal string.
                        }
                    }
                }

                $renewal_str .= sprintf(
                    // Translators: %1$s: renewal start date, %2$s: renewal end date.
                    __(' (renewal for %1$s to %2$s)', 'memberpress'),
                    $new_created_at,
                    $new_expires_at
                );
            }
        }

        return MeprHooks::apply_filters(
            'mepr_product_renewal_string',
            $renewal_str,
            $product
        );
    }

    /**
     * Get auto-apply coupons for given product IDs.
     *
     * Queries groups that contain the given products and returns coupon data
     * from groups where auto_apply_coupons is enabled.
     *
     * @param  array<int> $product_ids             Array of product IDs.
     * @param  boolean    $only_applicable_by_time Whether to filter only coupons applicable by time at current moment (default true).
     * @param  boolean    $use_cache               Whether to use cache for retrieval (cache is always updated).
     * @return array<int, array<array<string, mixed>>> Array keyed by product_id, containing arrays of coupon data arrays.
     */
    public static function get_auto_apply_coupons_for_products(
        array $product_ids,
        bool $only_applicable_by_time = true,
        bool $use_cache = true
    ): array {
        if (empty($product_ids)) {
            return [];
        }

        // Normalize product IDs to integers and filter out invalid IDs.
        $product_ids = array_filter(array_map('intval', $product_ids));
        if (empty($product_ids)) {
            return [];
        }

        // Sort product IDs for consistent cache key.
        $sorted_product_ids = $product_ids;
        sort($sorted_product_ids);

        // Include method name and only_applicable_by_time in cache key so filtered and unfiltered results are cached separately.
        $cache_key = md5(
            sprintf(
                '%s|%s|only_applicable_by_time:%d',
                __METHOD__,
                implode(',', $sorted_product_ids),
                $only_applicable_by_time ? 1 : 0
            )
        );

        // Check cache if enabled.
        if ($use_cache && isset(self::$auto_apply_coupons_cache[$cache_key])) {
            return self::$auto_apply_coupons_cache[$cache_key];
        }

        $results = self::query_auto_apply_coupons_for_products($product_ids);

        if (empty($results)) {
            self::$auto_apply_coupons_cache[$cache_key] = [];
            return [];
        }

        $coupon_data = self::extract_coupon_data_from_query_results($results);

        // Filter by time if requested.
        if ($only_applicable_by_time) {
            $coupon_data = self::filter_applicable_coupons_by_time($coupon_data);
        }

        // Update cache with the result (filtered or unfiltered based on only_applicable_by_time).
        self::$auto_apply_coupons_cache[$cache_key] = $coupon_data;
        return $coupon_data;
    }

    /**
     * Query groups with auto-apply coupons for given product IDs.
     *
     * @param  array<int> $product_ids Array of product IDs.
     * @return array<array<string, mixed>> Query results with group_id and group_coupons.
     */
    private static function query_auto_apply_coupons_for_products(array $product_ids): array
    {
        global $wpdb;

        // Prepare product IDs for IN clause using utility method.
        $prepared_product_ids = MeprUtils::prepare_ids_in_sql($product_ids);

        if (empty($prepared_product_ids)) {
            return [];
        }

        // Extract product IDs from prepared string to ensure FIELD() uses same IDs as IN clause.
        $field_product_ids = array_map('intval', explode(',', $prepared_product_ids));

        // Build ORDER BY clause with FIELD() to maintain IN clause order.
        $field_placeholders = implode(',', array_fill(0, count($field_product_ids), '%d'));
        $order_by_query     = sprintf(
            'ORDER BY FIELD(pm_product_group.post_id, %s) ASC',
            $field_placeholders
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $field_placeholders contains %d placeholders, $field_product_ids are sanitized integers.
        $order_by_part = $wpdb->prepare($order_by_query, ...$field_product_ids);

        // Build the query starting from product postmeta (we already have product_ids).
        $query_string = sprintf(
            "SELECT
                pm_product_group.post_id AS product_id,
                g.ID AS group_id,
                pm_coupons.meta_value AS group_coupons
            FROM {$wpdb->postmeta} AS pm_product_group
            INNER JOIN {$wpdb->posts} AS g
                ON g.ID = pm_product_group.meta_value
                AND g.post_type = %s
                AND g.post_status = 'publish'
            INNER JOIN {$wpdb->postmeta} AS pm_auto_apply
                ON g.ID = pm_auto_apply.post_id
                AND pm_auto_apply.meta_key = %s
                AND pm_auto_apply.meta_value = '1'
            LEFT JOIN {$wpdb->postmeta} AS pm_coupons
                ON g.ID = pm_coupons.post_id
                AND pm_coupons.meta_key = %s
            WHERE pm_product_group.meta_key = %s
                AND pm_product_group.post_id IN (%s)
            GROUP BY pm_product_group.post_id, pm_coupons.meta_value
            %s",
            '%s',
            '%s',
            '%s',
            '%s',
            $prepared_product_ids,
            $order_by_part
        );

        $prepare_args = [
            MeprGroup::$cpt,
            MeprGroup::$auto_apply_coupons_str,
            MeprGroup::$group_coupons_str,
            MeprProduct::$group_id_str,
        ];
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query_string contains placeholders for prepare(), $prepared_product_ids is sanitized by MeprUtils::prepare_ids_in_sql(), $order_by_part is prepared above.
        $query = $wpdb->prepare($query_string, ...$prepare_args);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Extract coupon data from query results containing group_coupons meta values.
     *
     * Groups coupon data by product_id as the first-level key.
     *
     * @param  array<array<string, mixed>> $results Query results with product_id, group_id, and group_coupons.
     * @return array<int, array<array<string, mixed>>> Array keyed by product_id, containing arrays of coupon data arrays.
     */
    private static function extract_coupon_data_from_query_results(array $results): array
    {
        $coupon_data_by_product = [];
        foreach ($results as $row) {
            $product_id    = (int) $row['product_id'];
            $group_id      = (int) $row['group_id'];
            $group_coupons = maybe_unserialize($row['group_coupons']);
            if (empty($group_coupons) || !is_array($group_coupons)) {
                continue;
            }

            $coupon_data_by_product[$product_id] = $coupon_data_by_product[$product_id] ?? [];

            foreach ($group_coupons as $coupon_data) {
                // Only include coupons with valid coupon_id.
                $coupon_id = (int) ($coupon_data[MeprGroup::$group_coupon_coupon_id_str] ?? 0);
                if ($coupon_id <= 0) {
                    continue;
                }

                // Add group_id to each coupon data entry.
                $coupon_data['group_id']               = $group_id;
                $coupon_data_by_product[$product_id][] = $coupon_data;
            }
        }

        return $coupon_data_by_product;
    }

    /**
     * Filter coupons by time applicability.
     *
     * Filters coupon data arrays to only include coupons that are currently applicable
     * based on their applies_on and unapplies_on timestamps. Only checks time constraints
     * if should_apply or should_unapply flags are enabled.
     *
     * @param  array<int, array<array<string, mixed>>> $coupon_data_by_product Array keyed by product_id, containing arrays of coupon data arrays.
     * @return array<int, array<array<string, mixed>>> Filtered array keyed by product_id, containing arrays of applicable coupon data arrays.
     */
    private static function filter_applicable_coupons_by_time(array $coupon_data_by_product): array
    {
        if (empty($coupon_data_by_product)) {
            return [];
        }

        $now                 = Time::now();
        $filtered_by_product = [];

        foreach ($coupon_data_by_product as $product_id => $coupon_data_array) {
            $applicable_coupons = [];
            foreach ($coupon_data_array as $coupon_data) {
                $should_apply   = (bool) ($coupon_data[MeprGroup::$group_coupon_should_apply_str] ?? false);
                $should_unapply = (bool) ($coupon_data[MeprGroup::$group_coupon_should_unapply_str] ?? false);
                $applies_on     = (int) ($coupon_data[MeprGroup::$group_coupon_applies_on_str] ?? 0);
                $unapplies_on   = (int) ($coupon_data[MeprGroup::$group_coupon_unapplies_on_str] ?? 0);

                // If should_apply is enabled, check if current time is after applies_on.
                if ($should_apply && $now < $applies_on) {
                    continue;
                }

                // If should_unapply is enabled, check if current time is before unapplies_on.
                if ($should_unapply && $now >= $unapplies_on) {
                    continue;
                }

                // Coupon is applicable.
                $applicable_coupons[] = $coupon_data;
            }
            if (!empty($applicable_coupons)) {
                $filtered_by_product[$product_id] = $applicable_coupons;
            }
        }

        return $filtered_by_product;
    }
}
