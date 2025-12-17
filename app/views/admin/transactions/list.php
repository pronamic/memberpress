<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

$member          = (isset($_GET['member'])) ? sanitize_text_field(wp_unslash($_GET['member'])) : '';
$member          = (isset($_GET['search']) && isset($_GET['search-field']) && (sanitize_text_field(wp_unslash($_GET['search-field'])) === 'user' || sanitize_text_field(wp_unslash($_GET['search-field'])) === 'email')) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
$member_str      = !empty($member) ? __('for', 'memberpress') . ' ' . rawurldecode($member) : '';
$subscription_id = intval($_GET['subscription'] ?? 0);

$add_new_txn_url = admin_url('admin.php?page=memberpress-trans&action=new&user=' . $member);
if ($subscription_id > 0) {
    $add_new_txn_url .= '&subscription=' . $subscription_id;
}
?>
<div class="wrap">
  <h2><?php esc_html_e('Transactions', 'memberpress'); ?> <?php echo esc_html($member_str); ?> <a href="<?php echo esc_url($add_new_txn_url); ?>" class="add-new-h2"><?php esc_html_e('Add New', 'memberpress'); ?></a></h2>
  <input type="hidden" name="mepr-update-transactions" value="Y" />

    <!-- Display which coupon is being filtered on -->
    <?php
    if (isset($_GET['coupon_id']) && !empty($_GET['coupon_id'])) {
        $coupon = new MeprCoupon((int) $_GET['coupon_id']);
        if ($coupon->ID > 0) : ?>
        <h3>
            <?php esc_html_e('All Transactions for Coupon', 'memberpress'); ?>:
            <span id="txn-coupon-title"><?php echo esc_html($coupon->post_title); ?></span>
        </h3>
        <?php endif; ?>

    <?php } ?>

    <?php $list_table->display(); ?>
</div>
