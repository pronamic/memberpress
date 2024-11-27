<?php

declare(strict_types=1);

?>

<div id="mosh-admin-addons" class="wrap">
    <h2>
        <form method="post" action="">
            <?php esc_html_e('Available Add-ons', 'caseproof-mothership'); ?>
            <input type="submit"
                class="button button-secondary"
                name="submit-button-mosh-refresh-addon"
                value="<?php esc_attr_e('Refresh Add-ons', 'caseproof-mothership'); ?>"
            >
            <input type="search"
                id="mosh-products-search"
                placeholder="<?php esc_attr_e('Search add-ons', 'caseproof-mothership'); ?>"
            >
        </form>
    </h2>
    <?php if (! empty($products)) : ?>
        <div id="mosh-products-container">
            <div class="mosh-products mosh-clearfix">
                <?php
                foreach ($products as $product) :
                    if ($product->type !== 'addon') {
                        continue;
                    }
                    $statusLabel = '';
                    $actionClass = 'mosh-product-action';
                    // Get base folder directory.
                    $directory = dirname($product->main_file);

                    $installed = isset($directory) && is_dir(WP_PLUGIN_DIR . '/' . $directory);
                    $active    = isset($product->main_file) && is_plugin_active($product->main_file);

                    if ($installed && $active) {
                        $status      = 'active';
                        $statusLabel = esc_html__('Active', 'caseproof-mothership');
                    } elseif (! $installed) {
                        $status      = 'download';
                        $statusLabel = esc_html__('Not Installed', 'caseproof-mothership');
                    } elseif ($installed && ! $active) {
                        $status      = 'inactive';
                        $statusLabel = esc_html__('Inactive', 'caseproof-mothership');
                    } else {
                        $status = 'upgrade';
                    }
                    ?>
                <div class="mosh-product mosh-product-status-<?php echo esc_attr($status); ?>">
                    <div class="mosh-product-inner">
                        <div class="mosh-product-details">
                            <div class="mosh-product-image">
                                <img src="<?php echo esc_url($product->image); ?>"
                                    alt="<?php echo esc_attr($product->list_name); ?>"
                                >
                            </div>
                            <div class="mosh-product-info">
                                <h2 class="mosh-product-name">
                                        <?php echo esc_html($product->name); ?>
                                </h2>
                                <p><?php echo esc_html($product->description); ?></p>
                            </div>
                        </div>
                        <div class="mosh-product-actions mosh-clearfix">
                            <?php if ('upgrade' !== $status) : ?>
                                <div class="mosh-product-status">
                                    <strong>
                                <?php
                                printf(
                                    // Translators: %s: add-on status label.
                                    esc_html__('Status: %s', 'caseproof-mothership'),
                                    sprintf(
                                        '<span class="mosh-product-status-label">%s</span>',
                                        esc_html($statusLabel)
                                    )
                                );
                                ?>
                                    </strong>
                                </div>
                            <?php else : ?>
                                <?php $actionClass .= ' mosh-product-action-upgrade'; ?>
                            <?php endif; ?>
                            <div class="<?php echo esc_attr($actionClass); ?>">
                                    <?php if ('active' === $status) : ?>
                                        <button type="button"
                                            data-plugin="<?php echo esc_attr($product->main_file); ?>"
                                            data-type="add-on"
                                        >
                                            <i class="dashicons dashicons-no-alt"></i>
                                            <?php esc_html_e('Deactivate', 'caseproof-mothership'); ?>
                                        </button>
                                    <?php elseif ('inactive' === $status) : ?>
                                        <button type="button"
                                            data-plugin="<?php echo esc_attr($product->main_file); ?>"
                                            data-type="add-on"
                                        >
                                            <i class="dashicons dashicons-yes-alt"></i>
                                            <?php esc_html_e('Activate', 'caseproof-mothership'); ?>
                                        </button>
                                    <?php elseif ('download' === $status) : ?>
                                        <?php $dataPlugin = $product->_embedded->{'version-latest'}->url ?? ''; ?>
                                        <button type="button"
                                            data-plugin="<?php echo esc_attr($dataPlugin); ?>"
                                            data-type="add-on"
                                        >
                                            <i class="dashicons dashicons-download"></i>
                                            <?php esc_html_e('Install Add-on', 'caseproof-mothership'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else : ?>
        <h3><?php esc_html_e('There were no Add-ons found for your License Key.', 'caseproof-mothership'); ?></h3>
    <?php endif; ?>
</div>
