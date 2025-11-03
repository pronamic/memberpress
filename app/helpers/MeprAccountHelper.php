<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprAccountHelper
{
    /**
     * Active nav.
     *
     * @param  string $tab          The tab.
     * @param  string $active_class The active class.
     * @return void
     */
    public static function active_nav($tab = 'home', $active_class = 'mepr-active-nav-tab')
    {
        $class  = 'mepr-' . $tab;
        $action = '';

        if (isset($_REQUEST['action'])) {
            $action = sanitize_text_field(wp_unslash($_REQUEST['action']));
        } else {
            $action = 'home';
        }

        if ($tab === $action) {
            $class = $class . ' ' . $active_class;
        }

        echo esc_attr(MeprHooks::apply_filters('mepr_active_nav_tab', $class, $tab, $active_class));
    }

    /**
     * Purchase link.
     *
     * @param  MeprProduct $product The product.
     * @param  string      $name    The name.
     * @return void
     */
    public static function purchase_link($product, $name = null)
    {
        $name = is_null($name) ? _x('Subscribe', 'ui', 'memberpress') : $name;

        ?>
    <a href="<?php echo esc_url($product->url()); ?>" class="mepr-account-row-action mepr-account-purchase"><?php echo esc_html($name); ?></a>
        <?php
    }

    /**
     * Group link.
     *
     * @param  MeprTransaction $txn The transaction.
     * @return void
     */
    public static function group_link($txn)
    {
        $product = $txn->product();
        $user    = $txn->user();
        ?>
        <?php
        $grp = $product->group();
        if ($grp && $grp->is_upgrade_path && count($grp->products('ids')) > 1 && count($grp->buyable_products()) >= 1) : // Can't upgrade to no other options. ?>
        <div id="mepr-upgrade-txn-<?php echo esc_attr($txn->id); ?>" class="mepr-white-popup mfp-hide">
          <center>
            <div class="mepr-upgrade-txn-text">
              <?php esc_html_e('Please select a new plan', 'memberpress'); ?>
            </div>
            <br/>
            <div>
              <select id="mepr-upgrade-dropdown-<?php echo esc_attr($txn->id); ?>" class="mepr-upgrade-dropdown">
                <?php foreach ($grp->products() as $p) : ?>
                    <?php if ($p->can_you_buy_me()) : ?>
                    <option value="<?php echo esc_attr(esc_url_raw($p->url())); ?>"><?php echo esc_html(sprintf('%1$s (%2$s)', $p->post_title, MeprProductsHelper::product_terms($p, $user))); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            <br/>
            <div class="mepr-cancel-txn-buttons">
              <button class="mepr-btn mepr-upgrade-buy-now" data-id="<?php echo esc_attr($txn->id); ?>"><?php esc_html_e('Select Plan', 'memberpress'); ?></button>
              <button class="mepr-btn mepr-upgrade-cancel"><?php esc_html_e('Cancel', 'memberpress'); ?></button>
            </div>
          </center>
        </div>

            <?php ob_start(); ?>
            <?php if (!$grp->disable_change_plan_popup) : ?>
          <a href="#mepr-upgrade-txn-<?php echo esc_attr($txn->id); ?>" class="mepr-open-upgrade-popup mepr-account-row-action mepr-account-upgrade"><?php esc_html_e('Change Plan', 'memberpress'); ?></a>
            <?php else : ?>
          <a href="<?php echo esc_url($grp->url()); ?>" class="mepr-account-row-action mepr-account-upgrade"><?php esc_html_e('Change Plan', 'memberpress'); ?></a>
            <?php endif; ?>
            <?php echo wp_kses_post(MeprHooks::apply_filters('mepr_custom_upgrade_link_txn', ob_get_clean(), $txn)); ?>

        <?php endif; ?>
        <?php
    }
}
