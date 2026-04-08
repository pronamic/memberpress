<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<?php
$products         = $group->products();
$carousel_enabled = !empty($group->carousel_enabled);
$template_slug    = esc_attr($group->modern_template);
$style            = MeprGroupsHelper::build_modern_style_string($group);
?>
<?php
$arrows_outside = !empty($group->carousel_arrows_outside);
$menu_classes   = 'mepr-price-menu mepr-modern mepr-tpl-' . esc_attr($template_slug);
$menu_classes  .= !$carousel_enabled ? ' mepr-no-carousel' : '';
$menu_classes  .= ($carousel_enabled && $arrows_outside) ? ' mepr-arrows-outside' : '';
?>
<div class="<?php echo esc_attr($menu_classes); ?>"
     style="<?php echo esc_attr($style); ?>"
     data-carousel-enabled="<?php echo $carousel_enabled ? '1' : '0'; ?>">

  <?php if ($carousel_enabled) : ?>
  <div class="splide mepr-pricing-carousel" aria-label="<?php esc_attr_e('Pricing Plans', 'memberpress'); ?>">
    <div class="splide__track">
      <div class="splide__list mepr-price-boxes">
        <?php
        if (!empty($products)) {
            foreach ($products as $product) {
                echo '<div class="splide__slide">';
                MeprGroupsHelper::group_page_item($product, $group);
                echo '</div>';
            }
        }
        ?>
      </div>
    </div>
  </div>
  <?php else : ?>
  <div class="mepr-price-boxes">
      <?php
        if (!empty($products)) {
            foreach ($products as $product) {
                MeprGroupsHelper::group_page_item($product, $group);
            }
        }
        ?>
  </div>
  <?php endif; ?>

</div>
