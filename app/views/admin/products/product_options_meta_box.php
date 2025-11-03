<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<h2 class="nav-tab-wrapper">
  <a class="nav-tab main-nav-tab nav-tab-active" href="#" id="registration"><?php esc_html_e('Registration', 'memberpress'); ?></a>
  <a class="nav-tab main-nav-tab" href="#" id="who-can-purchase"><?php esc_html_e('Permissions', 'memberpress'); ?></a>
  <a class="nav-tab main-nav-tab" href="#" id="group-layout"><?php esc_html_e('Price Box', 'memberpress'); ?></a>
  <a class="nav-tab main-nav-tab" href="#" id="advanced"><?php esc_html_e('Advanced', 'memberpress'); ?></a>
  <?php if (MeprHooks::apply_filters('mepr_display_order_bumps_upsell', false)) : ?>
    <a class="nav-tab main-nav-tab" href="#" id="order-bumps-upsell"><?php esc_html_e('Order Bumps', 'memberpress'); ?></a>
  <?php endif; ?>
  <?php MeprHooks::do_action('mepr_product_options_tabs', $product); ?>
</h2>

<div id="product_options_wrapper">
  <div class="product_options_page registration">
    <?php MeprView::render('/admin/products/registration', get_defined_vars()); ?>
  </div>
  <div class="product_options_page who-can-purchase">
    <?php MeprView::render('/admin/products/permissions', get_defined_vars()); ?>
  </div>
  <div class="product_options_page group-layout">
    <?php MeprView::render('/admin/products/price_box', get_defined_vars()); ?>
  </div>
  <div class="product_options_page advanced">
    <?php MeprView::render('/admin/products/advanced', get_defined_vars()); ?>
  </div>
  <?php if (MeprHooks::apply_filters('mepr_display_order_bumps_upsell', false)) : ?>
    <div class="product_options_page order-bumps-upsell">
        <?php MeprView::render('/admin/products/order_bumps', get_defined_vars()); ?>
    </div>
  <?php endif; ?>
  <?php MeprHooks::do_action('mepr_product_options_pages', $product); ?>
</div>
