<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Post-Setup Checklist Sidebar View
 *
 * @var array   $steps          Grouped steps (completed and pending)
 * @var array   $optional_steps Optional steps array
 * @var integer $progress       Progress percentage
 * @var integer $completed      Number of completed steps
 * @var integer $total          Total number of steps
 * @var boolean $is_minimized   Whether the sidebar is minimized
 * @var array   $next_step      The next incomplete step
 */
?>

<aside
    id="mepr-post-setup-checklist"
    class="mepr-post-setup-checklist <?php echo $is_minimized ? 'is-minimized' : ''; ?>"
    data-progress="<?php echo esc_attr($progress); ?>"
    aria-label="<?php esc_attr_e('Setup Checklist', 'memberpress'); ?>"
>
    <div class="mepr-psc-panel" role="region" aria-label="<?php esc_attr_e('Setup Checklist Panel', 'memberpress'); ?>">
        <!-- Header -->
        <div class="mepr-psc-header">
            <div class="mepr-psc-header-content">
                <h3 class="mepr-psc-title"><?php esc_html_e('Setup Checklist', 'memberpress'); ?></h3>
                <p class="mepr-psc-subtitle">
                    <?php
                    printf(
                        // Translators: 1: number of completed steps, 2: total number of steps.
                        esc_html__('%1$d of %2$d complete', 'memberpress'),
                        esc_html($completed),
                        esc_html($total)
                    );
                    ?>
                </p>
            </div>
            <div class="mepr-psc-header-actions">
                <button type="button" class="mepr-psc-minimize" aria-label="<?php esc_attr_e('Minimize checklist', 'memberpress'); ?>">
                    <span class="dashicons dashicons-minus" aria-hidden="true"></span>
                </button>
                <button type="button" class="mepr-psc-expand" aria-label="<?php esc_attr_e('Expand checklist', 'memberpress'); ?>">
                    <span class="dashicons dashicons-plus" aria-hidden="true"></span>
                </button>
                <button type="button" class="mepr-psc-dismiss" aria-label="<?php esc_attr_e('Dismiss checklist', 'memberpress'); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mepr-psc-progress-bar-container">
            <div
                class="mepr-psc-progress-bar"
                role="progressbar"
                aria-valuenow="<?php echo esc_attr($progress); ?>"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="<?php esc_attr_e('Setup progress', 'memberpress'); ?>"
            >
                <div class="mepr-psc-progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
            </div>
            <span class="mepr-psc-progress-label" aria-hidden="true"><?php echo esc_html($progress); ?>%</span>
        </div>

        <!-- Scrollable Content Area -->
        <div class="mepr-psc-content">
            <!-- Steps List -->
            <ul class="mepr-psc-steps" role="list">
                <?php if (!empty($steps['completed'])) : ?>
                    <?php foreach ($steps['completed'] as $step) : ?>
                        <?php
                        MeprView::render('/admin/post-setup-checklist/step', [
                            'step'       => $step,
                            'is_current' => false,
                        ]);
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($steps['pending'])) : ?>
                    <?php
                    $is_first_pending = true;
                    foreach ($steps['pending'] as $step) :
                        $is_current       = $is_first_pending;
                        $is_first_pending = false;
                        ?>
                        <?php
                        MeprView::render('/admin/post-setup-checklist/step', [
                            'step'       => $step,
                            'is_current' => $is_current,
                        ]);
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($steps['skipped'])) : ?>
                    <?php foreach ($steps['skipped'] as $step) : ?>
                        <?php
                        MeprView::render('/admin/post-setup-checklist/step', [
                            'step'       => $step,
                            'is_current' => false,
                        ]);
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <?php if (!empty($optional_steps)) : ?>
                <!-- Optional Steps -->
                <div class="mepr-psc-optional-section">
                    <h4 class="mepr-psc-optional-title"><?php esc_html_e('Optional', 'memberpress'); ?></h4>
                    <ul class="mepr-psc-steps mepr-psc-optional-steps" role="list">
                        <?php foreach ($optional_steps as $step) : ?>
                            <?php
                            MeprView::render('/admin/post-setup-checklist/optional-step', [
                                'step' => $step,
                            ]);
                            ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mepr-psc-footer">
            <p class="mepr-psc-footer-text">
                <?php esc_html_e('Complete these steps to launch your membership site.', 'memberpress'); ?>
            </p>
            <p class="mepr-psc-footer-support">
                <?php
                printf(
                    // Translators: %1$s: opening anchor tags for support link, %2$s: closing anchor tags for support link.
                    esc_html__('Need help? %1$sContact support%2$s', 'memberpress'),
                    '<a href="' . esc_url(MeprUtils::get_link_url('support')) . '" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
        </div>
    </div>
</aside>
