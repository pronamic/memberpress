<?php
if (!defined('ABSPATH')) {
  die('You are not allowed to call this page directly.');
}
MeprHooks::do_action('mepr_before_account_subscriptions', $mepr_current_user);
?>

<h1 class="mepr_page_header"><?php echo esc_html_x('Subscriptions', 'ui', 'memberpress'); ?></h1>

<?php
if (!empty($subscriptions)) {
  $alt = false;
?>
  <div class="mp_wrapper mepr-subscriptions-wrapper table-responsive">

    <table class="mepr-pro-account-table">
      <thead>
        <tr>
          <th scope="col"><?php _ex('Subscription', 'ui', 'memberpress'); ?></th>
          <th scope="col"><?php _ex('Status', 'ui', 'memberpress'); ?></th>
          <th scope="col"><?php _ex('Dates', 'ui', 'memberpress'); ?></th>
          <th scope="col" style="width: 30px;"><span class="screen-reader-text"><?php _ex('Actions', 'ui', 'memberpress'); ?></span>
          </th>
          <?php MeprHooks::do_action('mepr-account-subscriptions-th', $mepr_current_user, $subscriptions); ?>
        </tr>
      </thead>
      <tbody>

        <?php
        foreach ($subscriptions as $s) :
          if (trim($s->sub_type) == 'transaction') {
            $is_sub  = false;
            $txn     = $sub = new MeprTransaction($s->id);
            $pm      = $txn->payment_method();
            $prd     = $txn->product();
            $group   = $prd->group();
            $default = _x('Never', 'ui', 'memberpress');
            if ($txn->txn_type == MeprTransaction::$fallback_str && $mepr_current_user->subscription_in_group($group)) {
              // Skip fallback transactions when user has an active sub in the fallback group
              continue;
            }
          } else {
            $is_sub = true;
            $sub    = new MeprSubscription($s->id);
            $txn    = $sub->latest_txn();
            $pm     = $sub->payment_method();
            $prd    = $sub->product();
            $group  = $prd->group();

            if ($txn == false || !($txn instanceof MeprTransaction) || $txn->id <= 0) {
              $default = _x('Unknown', 'ui', 'memberpress');
            } elseif (trim($txn->expires_at) == MeprUtils::db_lifetime() or empty($txn->expires_at)) {
              $default = _x('Never', 'ui', 'memberpress');
            } else {
              $default = _x('Unknown', 'ui', 'memberpress');
            }
          }

          $mepr_options = MeprOptions::fetch();
          $alt          = !$alt; // Facilitiates the alternating lines


          // Get row actions
          ob_start();

          if ($txn != false && $txn instanceof MeprTransaction && $txn->is_sub_account()) {
            echo '--';
          } else {
            if (
              $is_sub && $pm instanceof MeprBaseRealGateway &&
              ($s->status == MeprSubscription::$active_str ||
                $s->status == MeprSubscription::$suspended_str ||
                strpos($s->active, 'mepr-active') !== false)
            ) {
              $subscription = new MeprSubscription($s->id);

              if (!$subscription->in_grace_period()) { // Don't let people change shiz until a payment has come through yo
                $pm->print_user_account_subscription_row_actions($subscription);
              }
            } elseif (!$is_sub && !empty($prd->ID)) {
              if ($prd->is_renewable() && $prd->is_renewal()) {
              ?>
                <a href="<?php echo $prd->url(); ?>" class="mepr-account-row-action mepr-account-renew"><?php _ex('Renew', 'ui', 'memberpress'); ?></a>
              <?php
              }

              if ($txn != false && $txn instanceof MeprTransaction && $group !== false && strpos($s->active, 'mepr-inactive') === false) {
                MeprAccountHelper::group_link($txn);
              } elseif ( /*$group !== false &&*/strpos($s->active, 'mepr-inactive') !== false /*&& !$prd->is_renewable()*/) {
                if ($prd->can_you_buy_me()) {
                  MeprAccountHelper::purchase_link($prd);
                }
              }
            } else {
              if ($prd->can_you_buy_me()) {
                if ($group !== false && $txn !== false && $txn instanceof MeprTransaction) {
                  $sub_in_group  = $mepr_current_user->subscription_in_group($group);
                  $life_in_group = $mepr_current_user->lifetime_subscription_in_group($group);

                  if (!$sub_in_group && !$life_in_group) { // $prd is in group, but user has no other active subs in this group, so let's show the change plan option
                    MeprAccountHelper::purchase_link($prd, _x('Re-Subscribe', 'ui', 'memberpress'));
                    MeprAccountHelper::group_link($txn);
                  }
                } else {
                  MeprAccountHelper::purchase_link($prd);
                }
              }
            }

            MeprHooks::do_action('mepr-account-subscriptions-actions', $mepr_current_user, $s, $txn, $is_sub);
          }

          $row_actions = ob_get_clean();
        ?>

          <tr x-data="{open:false}">
            <td data-label="<?php _ex('Subscription', 'ui', 'memberpress'); ?>">
              <div class="mepr-pro-account-table__product">
                <?php if (isset($prd->access_url) && !empty($prd->access_url)) : ?>
                  <a href="<?php echo stripslashes($prd->access_url); ?>"><?php echo MeprHooks::apply_filters('mepr-account-subscr-product-name', $prd->post_title, $txn); ?></a>
                <?php else : ?>
                  <?php echo MeprHooks::apply_filters('mepr-account-subscr-product-name', $prd->post_title, $txn); ?>
                <?php endif; ?>
              </div>
              <div class="mepr-pro-account-table__subscr">
                <?php if( (isset($txn) && $txn instanceof MeprTransaction && !$txn->is_sub_account()) || false === $txn ): ?>
                  <?php echo $s->subscr_id; ?>
                <?php endif; ?>
              </div>
            </td>
            <td data-label="<?php _ex('Status', 'ui', 'memberpress'); ?>">
              <?php
              echo '<button class="btn mepr-pro-account-table__badge --is-' . MeprAppHelper::pro_template_sub_status($s) . '">' . ucfirst( MeprAppHelper::pro_template_sub_status($s) ) . '</button>';
              ?>
            </td>
            <td data-label="<?php _ex('Dates', 'ui', 'memberpress'); ?>">
              <div class="mepr-pro-account-table__created_at"><?php echo MeprAppHelper::format_date($s->created_at); ?></div>
              <div class="mepr-pro-account-table__rebill">

                <?php if ($txn != false && $txn instanceof MeprTransaction && !$txn->is_sub_account && $is_sub && ($nba = $sub->next_billing_at)) : ?>
                  <?php printf(_x('Next Billing: %s', 'ui', 'memberpress'), MeprAppHelper::format_date($nba)); ?>
                <?php elseif (!$sub->next_billing_at && ($nba = $sub->expires_at) && stripos($sub->expires_at, '0000-00') === false) : ?>
                  <?php
                    if( strtotime($nba) < time() ){
                      printf(_x('Expired: %s', 'ui', 'memberpress'), MeprAppHelper::format_date($nba));
                    } else {
                      printf(_x('Expires: %s', 'ui', 'memberpress'), MeprAppHelper::format_date($nba));
                    }
                  ?>
                <?php elseif (false === $txn && ($nba = $sub->created_at)) : ?>
                  <?php printf(_x('Expired: %s', 'ui', 'memberpress'), MeprAppHelper::format_date($nba)); ?>
                <?php endif; ?>
              </div>
            </td>
            <?php if ( $row_actions ) { ?>
              <td class="mepr-pro-account-table__col-actions" data-label="<?php _ex('Actions', 'ui', 'memberpress'); ?>">
                <svg class="mepr-tooltip-trigger" xmlns="http://www.w3.org/2000/svg" class="" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                </svg>

                <div class="mepr-tooltip-content">
                  <?php echo $row_actions; ?>
                </div>
              </td>
            <?php } ?>
          </tr>
        <?php endforeach; ?>
        <?php MeprHooks::do_action('mepr-account-subscriptions-table', $mepr_current_user, $subscriptions); ?>
      </tbody>
    </table>

    <div class="mepr-account-meta">
      <?php if ($next_page) : ?>
        <button class="mepr-button btn-outline" id="load-more-subscriptions" data-count="<?php echo esc_attr(count($subscriptions)) ?>">Load More</button>
        <img class="mepr-account-meta__spinner" id="load-more-spinner" src="<?php echo esc_attr(MEPR_IMAGES_URL . '/spinner-loader.gif'); ?>" />
      <?php endif; ?>
    </div>

  </div>

<?php
} else {
  echo '<div class="mepr-no-active-subscriptions">' . _x('You have no active subscriptions to display.', 'ui', 'memberpress') . '</div>';
}

MeprHooks::do_action('mepr_account_subscriptions', $mepr_current_user);
