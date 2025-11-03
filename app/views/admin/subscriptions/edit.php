<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="wrap">
  <h2><?php esc_html_e('Edit Subscription', 'memberpress'); ?></h2>

  <?php MeprView::render('/admin/errors', get_defined_vars()); ?>

  <div class="form-wrap">
    <form action="" method="post">
      <input type="hidden" name="id" value="<?php echo esc_attr($sub->id); ?>" />
      <input type="hidden" name="period" value="<?php echo esc_attr($sub->period); ?>" />
      <input type="hidden" name="period_type" value="<?php echo esc_attr($sub->period_type); ?>" />
      <input type="hidden" name="limit_cycles" value="<?php echo esc_attr($sub->limit_cycles); ?>" />
      <input type="hidden" name="limit_cycles_num" value="<?php echo esc_attr($sub->limit_cycles_num); ?>" />
      <input type="hidden" name="limit_cycles_action" value="<?php echo esc_attr($sub->limit_cycles_action); ?>" />
      <table class="form-table">
        <tbody>
          <tr valign="top"><th scope="row"><label><?php esc_html_e('Subscription ID:', 'memberpress'); ?></label></th><td><?php echo esc_html($sub->id); ?></td></tr>
          <?php MeprHooks::do_action('mepr_edit_subscription_table_before', $sub); ?>
          <?php MeprView::render('/admin/subscriptions/form', get_defined_vars()); ?>
          <?php MeprHooks::do_action('mepr_edit_subscription_table_after', $sub); ?>
        </tbody>
      </table>
      <p class="submit">
        <input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Update', 'memberpress'); ?>" />
      </p>
    </form>
  </div>
</div>
