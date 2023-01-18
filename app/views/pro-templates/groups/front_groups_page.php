<?php if (!defined('ABSPATH')) {
  die('You are not allowed to call this page directly.');
} ?>

<?php
$products = $group->products();
$mepr_options = MeprOptions::fetch();

// Limit products to five items for now
if (is_array($products)) {
  $products = array_slice($products, 0, 5);
}
$button_color = isset($args['button_highlight_color']) ? $args['button_highlight_color'] : $mepr_options->design_pricing_cta_color;

if(empty($button_color)){
  $button_color = '#06429E';
}

$group_theme    = preg_replace('~\.css$~', '', (is_null($theme) ? $group->group_theme : $theme));
$group_template = 'pro-template';
$group = null;
$preview = false;
?>
<div class="mepr-price-menu <?php echo $group_theme; ?> <?php echo $group_template; ?>">

  <?php if (!isset($args['show_title']) || (isset($args['show_title']) && $args['show_title'])) : ?>
    <div class="mepr-pricing-title">
      <?php if ($mepr_options->design_pricing_title) { ?>
        <h1><?php echo esc_html($mepr_options->design_pricing_title); ?></h1>
      <?php } ?>
      <?php echo wp_kses_post(wpautop($mepr_options->design_pricing_subheadline)); ?>
    </div>
  <?php endif; ?>

  <div class="mepr-price-boxes mepr-<?php echo count($products); ?>-col">
    <?php
    if (!empty($products)) {
      foreach ($products as $product) {

        ob_start();
        $benefits = '';

        if ($group === null) {
          $group = new MeprGroup();
        }

        if (!empty($product->pricing_benefits)) {
          $benefits = '<div class="mepr-price-box-benefits-list">';

          foreach ($product->pricing_benefits as $index => $b) {
            if ('' !== trim($b)) {
              $benefits .= '<div class="mepr-price-box-benefits-item">';
              $benefits .= '<span class="mepr-price-box-benefits-icon"> <svg width="13" height="11" viewBox="0 0 13 11" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M12.2722 2.07898L6.56801 9.81981C6.43197 10.0003 6.22912 10.1186 6.00503 10.148C5.78094 10.1775 5.55441 10.1157 5.37634 9.97648L1.30301 6.71981C0.943564 6.43216 0.885361 5.90759 1.17301 5.54815C1.46066 5.1887 1.98523 5.1305 2.34468 5.41815L5.74134 8.13565L10.9305 1.09315C11.1007 0.837804 11.3974 0.696631 11.7029 0.725677C12.0083 0.754722 12.2731 0.949287 12.3921 1.23212C12.5111 1.51496 12.465 1.8403 12.2722 2.07898Z" fill="black" /> </svg> </span>';
              $benefits .= MeprHooks::apply_filters('mepr_price_box_benefit', $b, $index);
              $benefits .= '</div>';
            }
          }
          $benefits .= '</div>';
        }

        $user   = MeprUtils::get_currentuserinfo(); // If not logged in, $user will be false
        $active = true; // Always true for now - that way users can click the button and see the custom "you don't have access" message now

        $group_classes_str = ($product->is_highlighted) ? 'highlighted' : '';
        $group_classes_str = MeprHooks::apply_filters('mepr-group-css-classes-string', $group_classes_str, $product, $group, $preview);

    ?>
        <div id="mepr-price-box-<?php echo $product->ID; ?>" class="mepr-price-box <?php echo $group_classes_str; ?>">
          <?php if ($product->is_highlighted) : ?>
            <div class="mepr-most-popular">
              <?php _e('Most Popular', 'memberpress'); ?>
            </div>
          <?php endif ?>
          <div class="mepr-price-box-content">

            <div class="mepr-price-box-head">
              <div class="mepr-price-box-title">
                <?php echo $product->pricing_title; ?>
              </div>
              <?php if ($preview) : ?>
                <div class="mepr-price-box-price"></div>
                <span class="mepr-price-box-price-loading"><img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" /></span>
              <?php elseif ($product->pricing_display !== 'none') : ?>
                <div class="mepr-price-box-price">
                  <?php
                  if (!isset($mepr_coupon_code) || !MeprCoupon::is_valid_coupon_code($mepr_coupon_code, $product->ID)) {
                    $mepr_coupon_code = null;
                  }

                  if ($product->pricing_display == 'auto') {

                    $mepr_options = MeprOptions::fetch();

                    $price = preg_replace('/\/(.*)/', "<span class='mepr-price-box-price-term'>$0</span>", MeprProductsHelper::format_currency($product, true, $mepr_coupon_code, false));
                    $price = str_replace($mepr_options->currency_symbol, '<span class="mepr-price-box-price-currency">' . $mepr_options->currency_symbol . '</span>', $price);
                    echo $price;
                  } else {
                    echo $product->custom_price;
                  }
                  ?>
                </div>
              <?php endif; ?>


              <div class="mepr-price-box-button">
                <?php
                // All this logic is for showing a "VIEW" button instead of "Buy Now" if the member has already purchased it
                // and the membership access URL is set for that membership - and you can't buy the same membership more than once
                if (
                  $user && !$product->simultaneous_subscriptions &&
                  $user->is_already_subscribed_to($product->ID) &&
                  !empty($product->access_url)
                ) :
                ?>
                  <a <?php echo 'href="' . $product->access_url . '"'; ?> class="<?php echo MeprGroupsHelper::price_box_button_classes($group, $product, true); ?>"><?php _e('View', 'memberpress'); ?></a>
                <?php else : ?>
                  <a <?php echo $active ? 'href="' . $product->url() . '"' : ''; ?> class="<?php echo MeprGroupsHelper::price_box_button_classes($group, $product, $active); ?>" style="--tooltip-color: <?php echo esc_attr($button_color) ?>;">
                    <?php echo $product->pricing_button_txt; ?>
                  </a>
                <?php endif; ?>
              </div>
              <?php if (!empty($product->pricing_heading_txt)) : ?>
                <div class="mepr-price-box-heading">
                  <?php echo $product->pricing_heading_txt; ?>
                </div>
              <?php endif; ?>

            </div>

            <div class="mepr-price-box-benefits">
              <?php echo $benefits; ?>
            </div>

            <div class="mepr-price-box-foot">
              <div class="mepr-price-box-footer">
                <?php echo $product->pricing_footer_txt; ?>
              </div>
            </div>

          </div>
        </div>
    <?php
        $output = ob_get_clean();
        echo MeprHooks::apply_filters('mepr-group-page-item-output', $output, $product, $group, $preview);
      }
    }
    ?>
  </div>
</div>