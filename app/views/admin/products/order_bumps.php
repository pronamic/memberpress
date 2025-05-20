<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div class="product-options-panel">
  <h2><?php esc_html_e('Oops! Order Bumps is a Pro Perk', 'memberpress'); ?></h2>
  <h4><?php esc_html_e('Level up and unlock the power today!', 'memberpress'); ?></h4>
  <p>
    <?php
      printf(
        // Translators: %1$s: open italics tag, %2$s: close italics tag.
          esc_html__('%1$sTurn every sale into an upsell opportunity!%2$s Don\'t miss the chance to skyrocket your revenue with MemberPress Order Bumps – the game-changing tool that lets you automatically offer tempting add-ons to your customers right at checkout.', 'memberpress'),
          '<i>',
          '</i>'
      );
        ?>
  </p>
  <p>
    <?php esc_html_e('Amplify your average transaction value and maximize every customer interaction – all while elevating their shopping experience.', 'memberpress'); ?>
  </p>
  <p>
    <?php
      printf(
        // Translators: %1$s: open bold tag, %2$s: close bold tag.
          esc_html__('Level up your earning potential with the %1$sPro-exclusive Order Bumps%2$s feature today!', 'memberpress'),
          '<strong>',
          '</strong>'
      );
        ?>
  </p>
  <p>
    <a href="https://memberpress.com/plans/pricing/?utm_source=mp-plugin&utm_medium=in-plugin-link&utm_campaign=order-bumps&utm_content=membership-options" class="mepr-order-bumps-upgrade"><?php esc_html_e('Upgrade Now', 'memberpress'); ?><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/long-arrow-right.svg'); ?>" alt=""></a>
  </p>
</div>
