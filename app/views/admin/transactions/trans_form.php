<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<tr valign="top">
  <th scope="row"><label for="trans_num"><?php esc_html_e('Transaction Number*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="trans_num" id="trans_num" value="<?php echo esc_attr((empty($txn->trans_num)) ? uniqid() : $txn->trans_num); ?>" class="regular-text" />
    <p class="description"><?php esc_html_e('A unique ID for this Transaction. Only edit this if you absolutely have to.', 'memberpress'); ?></p>
  </td>
</tr>

<?php MeprHooks::do_action('mepr_admin_txn_form_before_user', $txn); ?>

<tr valign="top">
  <th scope="row"><label for="user_login"><?php esc_html_e('User*:', 'memberpress'); ?></label></th>
  <td>
    <input type="hidden" name="action" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['action'] ?? ''))); ?>" />
    <?php wp_nonce_field('mepr_create_or_update_transaction', 'mepr_transactions_nonce'); ?>
    <input type="text" name="user_login" id="user_login" class="mepr_suggest_user" value="<?php echo esc_attr($user_login); ?>" autocomplete="off" />
    <p class="description"><?php esc_html_e('The user who made this transaction.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="product_id"><?php esc_html_e('Membership*:', 'memberpress'); ?></label></th>
  <td>
    <?php $prds = MeprCptModel::all('MeprProduct', false, [
    'orderby' => 'title',
    'order'   => 'ASC',
]); ?>
    <select name="product_id" id="product_id" class="mepr-membership-dropdown" data-expires_at_field_id="expires_at">
      <?php foreach ($prds as $product) : ?>
        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($txn->product_id, $product->ID); ?>><?php echo esc_html($product->post_title); ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e('The membership that was purchased', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="amount"><?php esc_html_e('Sub-Total*:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo esc_html($mepr_options->currency_symbol); ?></span>
    <input type="text" name="amount" id="amount" value="<?php echo esc_attr(MeprUtils::format_currency_float($txn->amount)); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php esc_html_e('The sub-total (amount before tax) of this transaction', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_amount"><?php esc_html_e('Tax Amount*:', 'memberpress'); ?></label></th>
  <td>
    <span><?php echo esc_html($mepr_options->currency_symbol); ?></span>
    <input type="text" name="tax_amount" id="tax_amount" value="<?php echo esc_attr(MeprUtils::format_currency_float($txn->tax_amount)); ?>" class="regular-text" style="width:95px !important;"/>
    <p class="description"><?php esc_html_e('The amount of taxes for this transaction', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="tax_rate"><?php esc_html_e('Tax Rate*:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="tax_rate" id="tax_rate" value="<?php echo esc_attr(MeprUtils::format_currency_float($txn->tax_rate, 3)); ?>" class="regular-text" style="width:95px !important;"/>
    <span><?php echo '%'; ?></span>
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
  <th scope="row"><label for="status"><?php esc_html_e('Status:', 'memberpress'); ?></label></th>
  <td>
    <select name="status" id="status">
      <option value="<?php echo esc_attr(MeprTransaction::$complete_str); ?>" <?php selected($txn->status, MeprTransaction::$complete_str); ?>><?php esc_html_e('Complete', 'memberpress'); ?></option>
      <option value="<?php echo esc_attr(MeprTransaction::$pending_str); ?>" <?php selected($txn->status, MeprTransaction::$pending_str); ?>><?php esc_html_e('Pending', 'memberpress'); ?></option>
      <option value="<?php echo esc_attr(MeprTransaction::$failed_str); ?>" <?php selected($txn->status, MeprTransaction::$failed_str); ?>><?php esc_html_e('Failed', 'memberpress'); ?></option>
      <option value="<?php echo esc_attr(MeprTransaction::$refunded_str); ?>" <?php selected($txn->status, MeprTransaction::$refunded_str); ?>><?php esc_html_e('Refunded', 'memberpress'); ?></option>
    </select>
    <p class="description"><?php esc_html_e('The current status of the transaction', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php esc_html_e('Gateway:', 'memberpress'); ?></label></th>
  <td>
    <?php MeprTransactionsHelper::payment_methods_dropdown('gateway', $txn->gateway); ?>
    <p class="description"><?php esc_html_e('The payment method associated with the transaction.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="subscr_num"><?php esc_html_e('Subscription:', 'memberpress'); ?></label></th>
  <td>
    <input type="text" name="subscr_num" id="subscr_num" class="mepr_suggest_subscr_num" value="<?php echo esc_attr($subscr_num); ?>" autocomplete="off" />
    <p class="description"><?php esc_html_e('The optional subscription to associate this transaction with.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
  <th scope="row"><label><?php esc_html_e('Created (UTC/GMT):', 'memberpress'); ?></label></th>
  <td>
    <?php MeprTransactionsHelper::transaction_created_at_field('created_at', $txn->created_at); ?>
    <p class="description"><?php esc_html_e('The date that the transaction was created on. This field is displayed in UTC/GMT.', 'memberpress'); ?></p>
  </td>
</tr>

<tr valign="top">
    <th scope="row"><label for="expires_at"><?php esc_html_e('Expiration Date (UTC/GMT):', 'memberpress'); ?></label></th>
    <td>
        <?php MeprTransactionsHelper::transaction_expires_at_field('expires_at', 'product_id', 'created_at', $txn->expires_at); ?>
        <p class="description"><?php esc_html_e('The date that the transaction will expire. This is used to determine how long the user will have access to the membership until another transaction needs to be made. This field is displayed in UTC/GMT', 'memberpress'); ?></p>
        <p class="description">
            <?php
            printf(
                // Translators: %1$s: opening span tag, %2$s: closing span tag, %3$s: opening span tag, %4$s: closing span tag.
                esc_html__('%1$sNote:%2$s Blank indicates a %3$slifetime%4$s expiration.', 'memberpress'),
                '<b>',
                '</b>',
                '<b>',
                '</b>'
            );
            ?>
        </p>
    </td>
</tr>

<tr valign="top">
  <th scope="row"><label for="refunded_at"><?php esc_html_e('Refunded Date (UTC/GMT):', 'memberpress'); ?></label></th>
  <td>
    <?php MeprTransactionsHelper::transaction_refunded_at_field('refunded_at', $txn->refunded_at); ?>
    <p class="description"><?php esc_html_e('The date that the transaction was refunded. This field is displayed in UTC/GMT', 'memberpress'); ?></p>
  </td>
</tr>
