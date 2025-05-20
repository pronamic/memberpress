<?php if (! defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="mepr-signup-form mepr-form">
  <div class="mepr-checkout-container thankyou mp_wrapper alignwide">

  <?php if ($has_welcome_image && !empty($welcome_image)) : ?>
    <div class="form-wrapper">
      <figure>
      <img class="thankyou-image" src="<?php echo esc_url($welcome_image); ?>" alt="">
      </figure>
    </div>
  <?php endif; ?>

  <div class="invoice-wrapper thankyou">
    <h2 class=""><?php _ex('Thank you for your purchase', 'ui', 'memberpress'); ?></h2>


    <?php if ($hide_invoice) : ?>
        <?php echo $invoice_message ?>

    <?php else : ?>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
    class="w-6 h-6 thankyou">
    <path stroke-linecap="round" stroke-linejoin="round"
      d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>

    <div class="mepr-order-no">
    <p class=""><?php _ex('Payment Successful', 'ui', 'memberpress'); ?></p>
    <p class="">
        <?php
        _ex('Order: ', 'ui', 'memberpress');
          echo esc_html($trans_num);
        ?>
          </p>

    </div>

    <div class="mp-form-row mepr_bold mepr_price">
    <div class="mepr_price_cell invoice-amount">
        <?php echo $amount; ?>
    </div>
    </div>

        <?php
        echo $invoice_html;
        ?>

        <?php

        if (class_exists('MePdfInvoicesCtrl')) {
            ?>
    <a class="mepr-invoice-print mepr-button" href="
            <?php
            echo MeprUtils::admin_url(
                'admin-ajax.php',
                ['download_invoice', 'mepr_invoices_nonce'],
                [
                    'action' => 'mepr_download_invoice',
                    'txn'    => $txn->id,
                ]
            );
            ?>
        " target="_blank">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
      class="w-6 h-6">
      <path stroke-linecap="round" stroke-linejoin="round"
      d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
    </svg>

            <?php echo esc_html_x('Print', 'ui', 'memberpress'); ?>
    </a>
            <?php
        }

        ?>

    <?php endif ?>
    <?php do_action('mepr_readylaunch_thank_you_page_after_content'); ?>

    <p>
    <a href="<?php echo esc_url(home_url()); ?>"><?php _ex('Back to home', 'ui', 'memberpress'); ?></a>
    </p>

  </div>

  </div>
</div>
