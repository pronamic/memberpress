<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<input type="hidden" name="action" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['action'] ?? ''))); ?>" />
<?php wp_nonce_field('mepr_create_or_update_subscription', 'mepr_subscriptions_nonce'); ?>

<tr valign="top">
  <th scope="row"><label for="subscr_id"><?php esc_html_e('Subscription Number*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="subscr_id" id="subscr_id" value="<?php echo esc_attr((empty($sub->subscr_id)) ? uniqid() : $sub->subscr_id); ?>" class="regular-text" />
    <p class="description"><?php esc_html_e('A unique subscription number for this subscription. Only edit this if you absolutely have to.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="user_login"><?php esc_html_e('User*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="user_login" id="user_login" class="mepr_suggest_user" value="<?php echo esc_attr($sub->user_login); ?>" autocomplete="off" />
    <p class="description"><?php esc_html_e('The user for this subscription.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="product_id"><?php esc_html_e('Membership*:', 'memberpress'); ?></label></th>
  <td>
    <?php $products = MeprCptModel::all('MeprProduct', false, [
    'orderby' => 'title',
    'order'   => 'ASC',
]); ?>
    <select name="product_id" id="product_id" class="mepr-membership-dropdown" data-expires_at_field_id="expires_at">
      <?php foreach ($products as $product) : ?>
        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($sub->product_id, $product->ID); ?>><?php echo esc_html($product->post_title); ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e('The membership that was purchased', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="price"><?php esc_html_e('Sub-Total*:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo esc_html($mepr_options->currency_symbol); ?></span>
    <input type="text" name="price" id="price" value="<?php echo esc_attr(MeprUtils::format_currency_float($sub->price)); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php esc_html_e('The sub-total (amount before tax) of this subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_amount"><?php esc_html_e('Tax Amount:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo esc_html($mepr_options->currency_symbol); ?></span>
    <input type="text" name="tax_amount" id="tax_amount" value="<?php echo esc_attr(MeprUtils::format_currency_float($sub->tax_amount)); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php esc_html_e('The amount of taxes for this subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_rate"><?php esc_html_e('Tax Rate:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="tax_rate" id="tax_rate" value="<?php echo esc_attr(MeprUtils::format_currency_float($sub->tax_rate, 3)); ?>" class="regular-text" style="width:95px !important;"/>
    <span>%</span>
    <p class="description">
        <?php
        echo esc_html(
            sprintf(
                // Translators: %s: tax rate.
                __('The tax rate in percentage. (Ex: %s for 10%%)', 'memberpress'),
                MeprUtils::format_currency_float(10.000)
            )
        );
        ?>
    </p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="status"><?php esc_html_e('Status*:', 'memberpress'); ?></label></th>
  <td>
    <select name="status" id="status">
      <option value="<?php echo esc_attr(MeprSubscription::$active_str); ?>" <?php echo selected($sub->status, MeprSubscription::$active_str); ?>><?php esc_html_e('Enabled', 'memberpress'); ?></option>
      <option value="<?php echo esc_attr(MeprSubscription::$pending_str); ?>" <?php echo selected($sub->status, MeprSubscription::$pending_str); ?>><?php esc_html_e('Pending', 'memberpress'); ?></option>
      <option value="<?php echo esc_attr(MeprSubscription::$suspended_str); ?>" <?php echo selected($sub->status, MeprSubscription::$suspended_str); ?>><?php esc_html_e('Paused', 'memberpress'); ?></option>
      <option value="<?php echo esc_attr(MeprSubscription::$cancelled_str); ?>" <?php echo selected($sub->status, MeprSubscription::$cancelled_str); ?>><?php esc_html_e('Stopped', 'memberpress'); ?></option>
    </select>
    <p class="description"><?php esc_html_e('The current status of the subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php esc_html_e('Gateway:', 'memberpress'); ?></label></th>
  <td>
    <?php MeprSubscriptionsHelper::payment_methods_dropdown('gateway', $sub->gateway); ?>
    <p class="description"><?php esc_html_e('The payment method associated with this subscription.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php esc_html_e('Created (UTC/GMT):', 'memberpress'); ?></label></th>
  <td>
    <?php MeprTransactionsHelper::transaction_created_at_field('created_at', $sub->created_at); ?>
    <p class="description"><?php esc_html_e('The date that this subscription was created on. This field is displayed in UTC/GMT.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="trial"><?php esc_html_e('Trial:', 'memberpress'); ?></label></th>
  <td>
    <input type="checkbox" name="trial" id="trial" <?php checked($sub->trial); ?> />
    <p class="description"><?php esc_html_e('The trial period for this subscription', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="trial_days"><?php esc_html_e('Trial Days:', 'memberpress'); ?></label></th>
  <td>
    <input name="trial_days" id="trial_days" type="text" size="2" value="<?php echo esc_attr($sub->trial_days); ?>" />
    <p class="description"><?php esc_html_e('The number of days for this trial period', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
    <th scope="row">
        <label for="trial_amount">
            <?php
            echo esc_html(
                sprintf(
                    // Translators: %s: currency symbol.
                    __('Trial Amount (%s):', 'memberpress'),
                    $mepr_options->currency_symbol
                )
            );
            ?>
        </label>
    </th>
    <td>
        <span><?php echo esc_html($mepr_options->currency_symbol); ?></span>
        <input type="text" name="trial_amount" id="trial_amount" value="<?php echo esc_attr(MeprUtils::format_currency_float($sub->trial_amount)); ?>" class="regular-text" style="width:95px !important;"/>
        <p class="description"><?php esc_html_e('The sub-total (amount before tax) of this subscription', 'memberpress'); ?></p>
    </td>
</tr>
