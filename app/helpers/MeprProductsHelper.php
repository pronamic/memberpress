<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprProductsHelper
{
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
      <select id="<?php echo $id; ?>-custom"
              class="mepr-dropdown mepr-period-type-dropdown"
              data-period-type-id="<?php echo $id; ?>">
        <option value="months"><?php _e('months', 'memberpress'); ?>&nbsp;</option>
        <option value="weeks"><?php _e('weeks', 'memberpress'); ?>&nbsp;</option>
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
    <select id="<?php echo $period_type_str; ?>-presets"
            data-period-id="<?php echo $period_str; ?>"
            data-period-type-id="<?php echo $period_type_str; ?>">
      <option value="monthly"><?php _e('Monthly', 'memberpress'); ?>&nbsp;</option>
      <option value="yearly"><?php _e('Yearly', 'memberpress'); ?>&nbsp;</option>
      <option value="weekly"><?php _e('Weekly', 'memberpress'); ?>&nbsp;</option>
      <option value="quarterly"><?php _e('Every 3 Months', 'memberpress'); ?>&nbsp;</option>
      <option value="semi-annually"><?php _e('Every 6 Months', 'memberpress'); ?>&nbsp;</option>
      <option value="custom"><?php _e('Custom', 'memberpress'); ?>&nbsp;</option>
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
          <input type="text" name="<?php echo MeprProduct::$pricing_benefits_str; ?>[]" class="benefit-input" value="<?php echo stripslashes(htmlspecialchars($b, ENT_QUOTES)); ?>" />
          <span class="remove-span">
            <a href="" class="remove-benefit-item" title="<?php _e('Remove Benefit', 'memberpress'); ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
          </span>
        </li>
                <?php
            }
        } else {
            ?>
        <li class="benefit-item">
          <input type="text" name="<?php echo MeprProduct::$pricing_benefits_str; ?>[]" class="benefit-input" value="" />
          <span class="remove-span">
            <a href="" class="remove-benefit-item" title="<?php _e('Remove Benefit', 'memberpress'); ?>"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
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
    <a href="" class="add-new-benefit" title="<?php _e('Add Benefit', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
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
        return MeprAppHelper::format_price_string(
            $product,
            $product->adjusted_price($coupon_code, $show_prorated),
            $show_symbol,
            $coupon_code,
            $show_prorated
        );
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
                <?php if ($who->user_type == 'members') {
                    $class = '';
                } else {
                    $class = 'who_have_purchased';
                } ?>
          <li>
                <?php self::get_user_types_dropdown($who->user_type, $id); ?>
            <span id="who_have_purchased-<?php echo $id; ?>" class="<?php echo $class; ?>">
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
        <span id="who_have_purchased-<?php echo $id; ?>" class="who_have_purchased">
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
      <select name="<?php echo MeprProduct::$who_can_purchase_str . '-user_type'; ?>[]" class="user_types_dropdown" data-value="<?php echo $id; ?>">
        <option value="everyone" <?php selected('everyone', $chosen); ?>><?php _e('Everyone', 'memberpress'); ?></option>
        <option value="guests" <?php selected('guests', $chosen); ?>><?php _e('Guests', 'memberpress'); ?></option>
        <option value="members" <?php selected('members', $chosen); ?>><?php _e('Members', 'memberpress'); ?></option>
        <option value="disabled" <?php selected('disabled', $chosen); ?>><?php _e('No One (Disabled)', 'memberpress'); ?></option>
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
      <select name="<?php echo MeprProduct::$who_can_purchase_str . '-product_id'; ?>[]" id="<?php echo MeprProduct::$who_can_purchase_str . '-product_id'; ?>">
        <option value="nothing" <?php selected($chosen, 'nothing'); ?>><?php _e('no active memberships', 'memberpress'); ?></option>
        <option value="anything" <?php selected($chosen, 'anything'); ?>><?php _e('any membership', 'memberpress'); ?></option>
        <option value="subscribed-before" <?php selected($chosen, 'subscribed-before'); ?>><?php _e('subscribed to this membership before', 'memberpress'); ?></option>
        <option value="not-subscribed-before" <?php selected($chosen, 'not-subscribed-before'); ?>><?php _e('NOT subscribed to this membership before', 'memberpress'); ?></option>
        <option value="not-subscribed-any-before" <?php selected($chosen, 'not-subscribed-any-before'); ?>><?php _e('NOT subscribed to any membership before', 'memberpress'); ?></option>
        <?php foreach ($products as $p) : ?>
            <?php if ($p->ID != $my_id) : ?>
            <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $chosen) ?>><?php echo $p->post_title; ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php MeprHooks::do_action('mepr-get-products-dropdown-options', $chosen, $my_id, $products); ?>
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
      <select name="<?php echo MeprProduct::$have_or_had_str . '-type'; ?>[]" id="purchase_type_dropdown">
        <option value="have" <?php selected($chosen, 'have'); ?>><?php _e('who currently have', 'memberpress'); ?></option>
        <option value="had" <?php selected($chosen, 'had'); ?>><?php _e('who had', 'memberpress'); ?></option>
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
        $title     = ($content == '') ? $product->post_title : $content;

        ob_start();
        ?>
      <a href="<?php echo $permalink; ?>" class="mepr_product_link mepr-product-link-<?php echo $product->ID; ?>"><?php echo $title; ?></a>
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
            $tmp_txn->expires_at   = date(get_option('date_format'), $product->get_expires_at(time()));
            $tmp_txn->expire_type  = $product->expire_type;
            $tmp_txn->expire_unit  = $product->expire_unit;
            $tmp_txn->expire_after = $product->expire_after;
            $tmp_txn->expire_fixed = $product->expire_fixed;

            if ($display_title) {
                echo esc_html($product->post_title) . ': ';
            }

            if (empty($coupon_code)) { // We've already validated the coupon before including signup_form.php.
                if ($product->register_price_action == 'custom') {
                    echo stripslashes($product->register_price);
                } else {
                    echo MeprAppHelper::format_price_string($tmp_txn, $tmp_txn->amount, true, $coupon_code);
                }
            } else {
                echo MeprAppHelper::format_price_string($tmp_txn, $tmp_txn->amount, true, $coupon_code);
            }

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
            $tmp_sub->expires_at = date(get_option('date_format'), $product->get_expires_at(time()));

            $tmp_sub = MeprHooks::apply_filters('mepr_display_invoice_sub', $tmp_sub);

            if ($display_title) {
                echo esc_html($product->post_title) . ': ';
            }

            if ($product->register_price_action == 'custom' && empty($coupon_code) && !$tmp_sub->prorated_trial) {
                printf('<span class="mepr-custom-price">%s</span>', stripslashes($product->register_price));
            } else {
                echo MeprAppHelper::format_price_string($tmp_sub, $tmp_sub->price, true, $coupon_code);
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

        echo $invoice_html;
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
                if ($is_thankyou_page && !empty($_GET['transaction_id'])) {
                    $txn_id = sanitize_text_field($_GET['transaction_id']);
                    $renewal_txn = new MeprTransaction($txn_id);
                    $active_txns = $user->transactions_for_product($product->ID);

                    // Ensure active txns larger than 1 and it's not an offline gateway renewal pending txn.
                    if (
                        !empty($active_txns) &&
                        $renewal_txn->id > 0 &&
                        $renewal_txn->status != MeprTransaction::$pending_str
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
            'mepr-product-renewal-string',
            $renewal_str,
            $product
        );
    }
}
