<?php
defined('ABSPATH') || exit;
?>
<div class="esaf-mepr-coupons">
    <table id="esaf-mepr-coupons-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Coupon Code', 'memberpress'); ?></th>
                <th><?php esc_html_e('Valid Products', 'memberpress'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($my_coupons as $c_id) : ?>
                <?php $c = new MeprCoupon($c_id); ?>
                <tr>
                    <td><?php echo esc_html($c->post_title); ?></td>
                    <td>
                        <?php if (count($c->valid_products)) : ?>
                            <table class="esaf-mepr-coupon-valid-products">
                                <tbody>
                                    <?php foreach ($c->valid_products as $p_id) : ?>
                                        <?php
                                        $p    = new MeprProduct($p_id);
                                        $url  = $p->url('?coupon=' . urlencode($c->post_title) . '&aff=' . urlencode($affiliate->ID), true);
                                        $html = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($p->post_title));
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($p->post_title); ?></td>
                                            <td>
                                                <a href="#" class="esaf-coupon-get-html-code" data-html-code="<?php echo esc_attr($html); ?>" data-url-only="<?php echo esc_attr($url); ?>"><?php esc_html_e('Get Link', 'memberpress'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <?php esc_html_e('None', 'memberpress'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div id="esaf-coupon-get-html-code-popup" class="esaf-popup mfp-hide">
        <div class="esaf-popup-content">
            <h4 class="esaf-get-html-code-title"><?php esc_html_e('Copy and Paste code', 'memberpress'); ?></h4>
            <textarea id="esaf-coupon-get-html-code-field" class="esaf-code-textarea" readonly></textarea>
            <input type="text" id="esaf-coupon-get-html-code-url-only" class="esaf-invisible-field" />
            <div class="esaf-get-html-code-copy-buttons">
                <button id="esaf-coupon-get-html-code-copy-all" class="esaf-transparent-button esaf-copy-clipboard" type="button" data-clipboard-target="#esaf-coupon-get-html-code-field">
                    <i class="ea-icon ea-icon-docs"></i>
                    <?php esc_html_e('Copy All', 'memberpress'); ?>
                </button>
                <button id="esaf-coupon-get-html-code-copy-url-only" class="esaf-transparent-button esaf-copy-clipboard" type="button" data-clipboard-target="#esaf-coupon-get-html-code-url-only">
                    <i class="ea-icon ea-icon-docs"></i>
                    <?php esc_html_e('Copy URL Only', 'memberpress'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
