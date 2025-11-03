<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

MeprHooks::do_action('mepr_before_account_subscriptions', $mepr_current_user);

if (!empty($subscriptions)) {
    $alt = false;
    ?>
  <div class="mp_wrapper">
    <table id="mepr-account-subscriptions-table" class="mepr-account-table">
      <caption class="screen-reader-text"><?php echo esc_html_x('Subscriptions', 'ui', 'memberpress'); ?></caption>
      <thead>
        <tr>
          <th scope="col"><?php echo esc_html_x('Membership', 'ui', 'memberpress'); ?></th>
          <th scope="col"><?php echo esc_html_x('Subscription', 'ui', 'memberpress'); ?></th>
          <th scope="col"><?php echo esc_html_x('Active', 'ui', 'memberpress'); ?></th>
          <th scope="col"><?php echo esc_html_x('Created', 'ui', 'memberpress'); ?></th>
          <th scope="col"><?php echo esc_html_x('Card Exp.', 'ui', 'memberpress'); ?></th>
          <th scope="col"><?php echo esc_html_x('Actions', 'ui', 'memberpress'); ?></th>
          <?php MeprHooks::do_action('mepr_account_subscriptions_th', $mepr_current_user, $subscriptions); ?>
        </tr>
      </thead>
      <tbody>
        <?php
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        foreach ($subscriptions as $s) :
            if (trim($s->sub_type) === 'transaction') {
                $is_sub  = false;
                $txn     = $sub = new MeprTransaction($s->id);
                $pm      = $txn->payment_method();
                $prd     = $txn->product();
                $group   = $prd->group();
                $default = esc_html_x('Never', 'ui', 'memberpress');
                if ($txn->txn_type === MeprTransaction::$fallback_str && $mepr_current_user->subscription_in_group($group)) {
                    // Skip fallback transactions when user has an active sub in the fallback group.
                    continue;
                }
            } else {
                $is_sub = true;
                $sub    = new MeprSubscription($s->id);
                $txn    = $sub->latest_txn();
                $pm     = $sub->payment_method();
                $prd    = $sub->product();
                $group  = $prd->group();

                if (!($txn instanceof MeprTransaction) || $txn->id <= 0) {
                    $default = esc_html_x('Unknown', 'ui', 'memberpress');
                } elseif (trim($txn->expires_at) === MeprUtils::db_lifetime() or empty($txn->expires_at)) {
                    $default = esc_html_x('Never', 'ui', 'memberpress');
                } else {
                    $default = esc_html_x('Unknown', 'ui', 'memberpress');
                }
            }

            $mepr_options = MeprOptions::fetch();
            $alt          = !$alt; // Facilitiates the alternating lines.
            ?>
          <tr id="mepr-subscription-row-<?php echo esc_attr($s->id); ?>" class="mepr-subscription-row <?php echo (isset($alt) && !$alt) ? 'mepr-alt-row' : ''; ?>">
            <td data-label="<?php echo esc_attr_x('Membership', 'ui', 'memberpress'); ?>">
              <!-- MEMBERSHIP ACCESS URL -->
              <?php if (isset($prd->access_url) && !empty($prd->access_url)) : ?>
                <div class="mepr-account-product"><a href="<?php echo esc_url(stripslashes($prd->access_url)); ?>"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_subscr_product_name', $prd->post_title, $txn)); ?></a></div>
              <?php else : ?>
                <div class="mepr-account-product"><?php echo esc_html(MeprHooks::apply_filters('mepr_account_subscr_product_name', $prd->post_title, $txn)); ?></div>
              <?php endif; ?>

              <?php if ($txn instanceof MeprTransaction && !$txn->is_sub_account()) : ?>
                <div class="mepr-account-subscr-id"><?php echo esc_html($s->subscr_id); ?></div>
              <?php endif; ?>
            </td>
            <td data-label="<?php echo esc_attr_x('Terms', 'ui', 'memberpress'); ?>">
              <div class="mepr-account-auto-rebill">
                <?php
                if ($txn instanceof MeprTransaction && $txn->is_sub_account()) {
                    ?>
                    <div class="mepr-account-sub-account-auto-rebill">
                    <?php echo esc_html_x('Sub Account', 'ui', 'memberpress'); ?>
                    <?php MeprHooks::do_action('mepr_account_subscriptions_sub_account_auto_rebill', $txn); ?>
                    </div>
                    <?php
                } else {
                    if ($is_sub) :
                        echo esc_html(($s->status === MeprSubscription::$active_str) ? esc_html_x('Enabled', 'ui', 'memberpress') : MeprAppHelper::human_readable_status($s->status, 'subscription'));
                    elseif (is_null($s->expires_at) or $s->expires_at === MeprUtils::db_lifetime()) :
                        echo esc_html_x('Lifetime', 'ui', 'memberpress');
                    else :
                        echo esc_html_x('None', 'ui', 'memberpress');
                    endif;
                }
                ?>
              </div>
              <?php if ($prd->register_price_action !== 'hidden') : ?>
                <div class="mepr-account-terms">
                    <?php
                    if ($txn instanceof MeprTransaction && $txn->is_sub_account()) {
                        MeprHooks::do_action('mepr_account_subscriptions_sub_account_terms', $txn);
                    } else {
                        if ($prd->register_price_action === 'custom' && !empty($prd->register_price)) {
                            // Add coupon in if one was used eh.
                            $coupon_str = '';
                            if ($is_sub) {
                                $subscr = new MeprSubscription($s->id);
                                if ($subscr->coupon_id) {
                                    $coupon = new MeprCoupon($subscr->coupon_id);
                                    if (isset($coupon->ID) && $coupon->ID) {
                                        $coupon_str = ' ' . esc_html_x('with coupon', 'ui', 'memberpress') . ' ' . $coupon->post_title;
                                    }
                                }
                            }

                            echo esc_html(stripslashes($prd->register_price) . $coupon_str);
                        } elseif ($txn instanceof MeprTransaction) {
                            echo esc_html(MeprTransactionsHelper::format_currency($txn));
                        }
                    }
                    ?>
                </div>
              <?php endif; ?>
              <?php
                $nba = $sub->next_billing_at;
                if ($txn instanceof MeprTransaction && !$txn->is_sub_account && $is_sub && $nba) : ?>
                  <div class="mepr-account-rebill"><?php echo esc_html(sprintf(
                      // Translators: %s: date.
                      _x('Next Billing: %s', 'ui', 'memberpress'),
                      MeprAppHelper::format_date($nba)
                  )); ?></div>
                <?php else :
                    $nba = $sub->expires_at;
                    if (!$sub->next_billing_at && $nba && stripos($sub->expires_at, '0000-00') === false) : ?>
                      <div class="mepr-account-rebill"><?php echo esc_html(sprintf(
                          // Translators: %s: date.
                          _x('Expires: %s', 'ui', 'memberpress'),
                          MeprAppHelper::format_date($nba)
                      )); ?></div>
                    <?php endif;
                endif; ?>

            </td>
            <td data-label="<?php echo esc_attr_x('Active', 'ui', 'memberpress'); ?>">
                <div class="mepr-account-active">
                    <?php echo wp_kses($s->active, ['span' => ['class' => []]]); ?>
                </div>
            </td>
            <td data-label="<?php echo esc_attr_x('Created', 'ui', 'memberpress'); ?>">
              <?php if ($txn instanceof MeprTransaction && $txn->is_sub_account()) : ?>
                <div>--</div>
              <?php else : ?>
                <div class="mepr-account-created-at"><?php echo esc_html(MeprAppHelper::format_date($s->created_at)); ?></div>
              <?php endif; ?>
            </td>
            <td data-label="<?php echo esc_attr_x('Card Expires', 'ui', 'memberpress'); ?>">
              <?php if ($txn instanceof MeprTransaction && $txn->is_sub_account()) : ?>
                <div>--</div>
              <?php else : ?>
                  <?php
                    $exp_mo = $sub->cc_exp_month;
                    $exp_yr = $sub->cc_exp_year;
                    if ($exp_mo && $exp_yr) : ?>
                          <?php $cc_class = (($sub->cc_expiring_before_next_payment()) ? ' mepr-inactive' : ''); ?>
                  <div class="mepr-account-cc-exp<?php echo esc_attr($cc_class); ?>"><?php echo esc_html(sprintf(
                      // Translators: %1$d: month, %2$d: year.
                      _x('%1$02d-%2$d', 'ui', 'memberpress'),
                      $exp_mo,
                      $exp_yr
                  )); ?></div>
                    <?php else : // Need a placeholder for responsive. ?>
                  <div>&zwnj;</div>
                    <?php endif; ?>
              <?php endif; ?>
            </td>
            <td data-label="<?php echo esc_attr_x('Actions', 'ui', 'memberpress'); ?>">
                <div class="mepr-account-actions">
                  <?php
                    if ($txn instanceof MeprTransaction && $txn->is_sub_account()) {
                        echo '--';
                    } else {
                        if (
                            $is_sub && $pm instanceof MeprBaseRealGateway &&
                            ( $s->status === MeprSubscription::$active_str ||
                            $s->status === MeprSubscription::$suspended_str ||
                            strpos($s->active, 'mepr-active') !== false )
                        ) {
                            $subscription = new MeprSubscription($s->id);

                            if (!$subscription->in_grace_period()) { // Don't let people change shiz until a payment has come through yo.
                                $pm->print_user_account_subscription_row_actions($subscription);
                            }
                        } elseif (!$is_sub && !empty($prd->ID)) {
                            if ($prd->is_one_time_payment() && $prd->is_renewable() && $prd->is_renewal()) {
                                ?>
                          <a href="<?php echo esc_url($prd->url()); ?>" class="mepr-account-row-action mepr-account-renew"><?php echo esc_html_x('Renew', 'ui', 'memberpress'); ?></a>
                                <?php
                            }

                            if ($txn instanceof MeprTransaction && $group !== false && strpos($s->active, 'mepr-inactive') === false) {
                                MeprAccountHelper::group_link($txn);
                            } elseif (
                                // $group !== false &&
                                strpos($s->active, 'mepr-inactive') !== false
                                // && !$prd->is_renewable()
                            ) {
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
                                        MeprAccountHelper::purchase_link($prd, esc_html_x('Re-Subscribe', 'ui', 'memberpress'));
                                        MeprAccountHelper::group_link($txn);
                                    }
                                } else {
                                    MeprAccountHelper::purchase_link($prd);
                                }
                            }
                        }

                        MeprHooks::do_action('mepr_account_subscriptions_actions', $mepr_current_user, $s, $txn, $is_sub);
                    }
                    ?>
                  &zwnj; <!-- Responsiveness when no actions present -->
                </div>
            </td>
            <?php MeprHooks::do_action('mepr_account_subscriptions_td', $mepr_current_user, $s, $txn, $is_sub); ?>
          </tr>
        <?php endforeach; ?>
        <?php MeprHooks::do_action('mepr_account_subscriptions_table', $mepr_current_user, $subscriptions); ?>
      </tbody>
    </table>
    <div id="mepr-subscriptions-paging">
      <?php if ($prev_page) : ?>
        <a href="<?php echo esc_url("{$account_url}{$delim}currpage={$prev_page}"); ?>">&lt;&lt; <?php echo esc_html_x('Previous Page', 'ui', 'memberpress'); ?></a>
      <?php endif; ?>
      <?php if ($next_page) : ?>
        <a href="<?php echo esc_url("{$account_url}{$delim}currpage={$next_page}"); ?>" style="float:right;"><?php echo esc_html_x('Next Page', 'ui', 'memberpress'); ?> &gt;&gt;</a>
      <?php endif; ?>
    </div>
    <div style="clear:both"></div>
  </div>
    <?php
} else {
    echo '<div class="mepr-no-active-subscriptions">' . esc_html_x('You have no active subscriptions to display.', 'ui', 'memberpress') . '</div>';
}

MeprHooks::do_action('mepr_account_subscriptions', $mepr_current_user);
