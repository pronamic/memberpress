<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="wrap">
  <h2><?php esc_html_e('Edit Transaction', 'memberpress'); ?></h2>

  <?php
    MeprView::render('/admin/errors', get_defined_vars());

    $pm = $mepr_options->payment_method($txn->gateway);
    if (!is_object($pm)) {
        $pm = (object)[
            'label' => __('Unknown', 'memberpress'),
            'name'  => __('Deleted Gateway', 'memberpress'),
        ];
    }
    ?>

  <div class="form-wrap">
    <form action="" method="post">
      <?php if (isset($txn) and $txn->id > 0) : ?>
        <input type="hidden" name="id" value="<?php echo esc_attr($txn->id); ?>" />
      <?php endif; ?>
      <table class="form-table">
        <tbody>
          <tr valign="top"><th scope="row"><label><?php esc_html_e('Transaction ID:', 'memberpress'); ?></label></th><td><?php echo esc_html($txn->id); ?></td></tr>
          <?php MeprHooks::do_action('mepr_edit_transaction_table_before', $txn); ?>
          <?php MeprView::render('/admin/transactions/trans_form', get_defined_vars()); ?>
          <?php MeprHooks::do_action('mepr_edit_transaction_table_after', $txn); ?>
        </tbody>
      </table>
      <p class="submit">
        <input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Update', 'memberpress'); ?>" />
      </p>
    </form>
  </div>
</div>
