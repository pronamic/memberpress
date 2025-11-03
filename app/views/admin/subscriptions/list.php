<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
$member_login = (isset($_GET['member'])) ? __('for', 'memberpress') . ' ' . sanitize_text_field(wp_unslash($_GET['member'])) : '';
?>

<div class="wrap">
  <h2><?php esc_html_e('Subscriptions', 'memberpress'); ?> <?php echo esc_html($member_login); ?><a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-' . ($sub_table->lifetime ? 'lifetimes' : 'subscriptions') . '&action=new')); ?>" class="add-new-h2"><?php esc_html_e('Add New', 'memberpress'); ?></a></h2>
  <?php $sub_table->display(); ?>
</div>
