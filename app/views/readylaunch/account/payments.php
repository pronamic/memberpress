<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}
?>

<h1 class="mepr_page_header"><?php echo esc_html_x( 'Payments', 'ui', 'memberpress' ); ?></h1>

<?php
if ( ! empty( $payments ) ) {
  ?>
<div class="mp_wrapper mepr-payments-wrapper table-responsive">
  <table id="mepr-account-payments-table" class="mepr-pro-account-table" cellspacing="0">
  <thead>
    <tr>
    <th><?php _ex( 'Invoice', 'ui', 'memberpress' ); ?></th>
    <th><?php _ex( 'Date', 'ui', 'memberpress' ); ?></th>
    <th><?php _ex( 'Card', 'ui', 'memberpress' ); ?></th>
    <th><?php _ex( 'Status', 'ui', 'memberpress' ); ?></th>
    <th><?php _ex( 'Email', 'ui', 'memberpress' ); ?></th>
    <th><?php _ex( 'Total', 'ui', 'memberpress' ); ?></th>
    <th style="width: 30px;"></th>
    </tr>
  </thead>
  <tbody>
    <?php
    foreach ( $payments as $payment ) :
      $alt      = ( isset( $alt ) && ! $alt );
      $txn      = new MeprTransaction( $payment->id );
      $pm       = $txn->payment_method();
      $prd      = $txn->product();
      $cc_last4 = '';

      if ( $txn->subscription_id > 0 ) {
        $sub      = $txn->subscription();
        $cc_last4 = $sub->cc_last4 ? '**** ' . $sub->cc_last4 : '';
      }

      ob_start();
      MeprHooks::do_action( 'mepr_account_payments_table_row', $payment );
      $row_actions = ob_get_clean();
      ?>
    <tr class="mepr-payment-row <?php echo ( $alt ) ? 'mepr-alt-row' : ''; ?>" x-data="{open:false}">
    <td data-label="<?php _ex( 'Invoice', 'ui', 'memberpress' ); ?>">
      <p><?php echo $payment->trans_num; ?></p>
      <p class="sub"><?php echo MeprHooks::apply_filters( 'mepr-account-payment-product-name', $prd->post_title, $txn ); ?></p>
    </td>
    <td data-label="<?php _ex( 'Date', 'ui', 'memberpress' ); ?>">
      <div><strong><?php echo MeprAppHelper::format_date( $payment->created_at ); ?></strong></div>
      <!-- <div><span class="mepr-payment-row__desc"><?php // echo MeprAppHelper::format_currency( $payment->total <= 0.00 ? $payment->amount : $payment->total ); ?><span></div> -->
    </td>
    <td data-label="<?php _ex( 'Card', 'ui', 'memberpress' ); ?>">
      <p><?php echo esc_html( $pm->label ); ?></p>
      <p class="sub"><?php echo esc_html( $cc_last4 ); ?></p>
    </td>
    <td data-label="<?php _ex( 'Status', 'ui', 'memberpress' ); ?>"><?php

    echo '<button class="btn mepr-pro-account-table__badge --is-' . MeprAppHelper::pro_template_txn_status($txn) . '">' . MeprAppHelper::human_readable_status($txn->status, 'transaction') . '</button>';

    ?></td>
    <td data-label="<?php _ex( 'Email', 'ui', 'memberpress' ); ?>" class="mepr-payment-row__desc text-gray"><?php echo esc_html( $mepr_current_user->user_email ); ?></td>
    <td data-label="<?php _ex( 'Total', 'ui', 'memberpress' ); ?>">
      <?php echo MeprAppHelper::format_currency( $payment->total <= 0.00 ? $payment->amount : $payment->total ); ?>
    </td>
      <?php // MeprHooks::do_action('mepr_account_payments_table_row', $payment); ?>
      <?php if ( $row_actions ) { ?>
    <td class="mepr-pro-account-table__col-actions">
      <svg class="mepr-tooltip-trigger" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
      stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
      </svg>
      <div class="mepr-tooltip-content">
        <?php
          echo strip_tags( $row_actions, '<a>' );
        ?>
      </div>
    </td>
    <?php } ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
  </table>

  <div class="mepr-account-meta">
    <?php if ( $next_page ) : ?>
    <button class="mepr-button btn-outline" id="load-more-payments" data-count="<?php echo esc_attr( count( $payments ) ); ?>">Load More</button>
    <img class="mepr-account-meta__spinner" id="load-more-spinner" src="<?php echo esc_attr( MEPR_IMAGES_URL . '/spinner-loader.gif' ); ?>" />
    <?php endif; ?>
  </div>

  <div style="clear:both"></div>
</div>
  <?php
} else {
  ?>
<div class="mp-wrapper mp-no-subs">
  <?php
  _ex( 'You have no completed payments to display.', 'ui', 'memberpress' );
  ?>
</div>
  <?php
}

MeprHooks::do_action( 'mepr_account_payments', $mepr_current_user );
