<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php if ($user !== false && !empty($top_desc)) { ?>
  <div class="mepr-subscriptions-widget-top-wrapper"><span class="mepr-subscriptions-widget-top-desc"><?php echo $top_desc; ?></span></div>
<?php } ?>

<?php if ($user !== false) {
    $subs = $user->active_product_subscriptions('products');

    if (empty($subs)) { ?>
    <div class="mepr-subscriptions-widget-no-sub mepr-widget-error"><?php echo $no_subscriptions_message; ?></div>
        <?php
    } else {
        $prev_dups = [];

        if (!empty($order_by)) {
            $subs_sorted = [];
            foreach ($subs as $prd) {
                if ($order_by == 'date') {
                    $created_at            = MeprUser::get_user_product_signup_date($user->ID, $prd->ID);
                    $subs_sorted[$prd->ID] = $created_at;
                } elseif ($order_by == 'title') {
                    $subs_sorted[$prd->ID] = $prd->post_title;
                }
            }
            if ($order_by == 'date') {
                if ($order == 'asc') {
                    asort($subs_sorted);
                } else {
                    arsort($subs_sorted);
                }
            } elseif ($order_by == 'title') {
                if ($order == 'desc') {
                    arsort($subs_sorted);
                } else {
                    asort($subs_sorted);
                }
            }
            $subs_sorted = array_keys($subs_sorted);

            $subs = [];
            foreach ($subs_sorted as $sub) {
                $subs[] = new MeprProduct($sub);
            }
        }
        ?>

    <ul class="mepr-subscriptions-widget-list">
        <?php foreach ($subs as $prd) {
            if (empty($prev_dups) || !in_array($prd->ID, $prev_dups, false)) {
                $prev_dups[] = $prd->ID;

                if ($use_access_url && !empty($prd->access_url)) { ?>
          <li class="mepr-subscriptions-widget-row mepr-widget-link"><a href="<?php echo stripslashes($prd->access_url); ?>"><?php echo $prd->post_title; ?></a></li>
                    <?php
                } else { ?>
          <li class="mepr-subscriptions-widget-row mepr-widget-text"><?php echo $prd->post_title; ?></li>
                    <?php
                }
            }
        } ?>
    </ul>
    <?php }
} else { ?>
   <p class="mepr-subscriptions-widget-no-logged-in mepr-widget-error"><?php echo $not_logged_in_message; ?></p>
    <?php
} ?>

<?php if ($user !== false && !empty($bottom_desc)) { ?>
  <div class="mepr-subscriptions-widget-bottom-wrapper"><span class="mepr-subscriptions-widget-bottom-desc"><?php echo $bottom_desc; ?></span></div>
<?php } ?>
