<?php if (!defined('ABSPATH')) {
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
            <h1><?php echo esc_html_x('Thank you for your purchase', 'ui', 'memberpress'); ?></h1>

            <?php if ($hide_invoice) : ?>
                <?php echo wp_kses_post($invoice_message); ?>
            <?php else : ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-6 h-6 thankyou">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>

                <div class="mepr-order-no">
                    <p><?php echo esc_html_x('Payment Successful', 'ui', 'memberpress'); ?></p>
                    <p>
                        <?php
                        echo esc_html_x('Order: ', 'ui', 'memberpress');
                        echo esc_html($trans_num);
                        ?>
                    </p>

                </div>

                <div class="mp-form-row mepr_bold mepr_price">
                    <div class="mepr_price_cell invoice-amount">
                        <?php echo esc_html($amount); ?>
                    </div>
                </div>

                <?php
                MeprHooks::do_action('mepr_readylaunch_thank_you_page_before_invoice', $txn);
                echo wp_kses_post($invoice_html);
                MeprHooks::do_action('mepr_readylaunch_thank_you_page_after_invoice', $txn);
                ?>
            <?php endif ?>
            <?php MeprHooks::do_action('mepr_readylaunch_thank_you_page_after_content'); ?>

            <p>
                <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html_x('Back to home', 'ui', 'memberpress'); ?></a>
            </p>

        </div>

    </div>
</div>
