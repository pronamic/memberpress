<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Post-Setup Checklist Optional Step View
 *
 * @var array $step Step data
 */

$step_classes = ['mepr-psc-step', 'mepr-psc-optional'];

if ($step['completed']) {
    $step_classes[] = 'is-completed';
}

// Check if this step requires a plugin that isn't active.
$has_requirement = isset($step['requires']);
$is_installed    = $step['installed'] ?? false;
$is_active       = $step['active'] ?? false;
$is_installable  = $step['installable'] ?? true;
$needs_upgrade   = $has_requirement && !$is_installed && !$is_installable;
$needs_install   = $has_requirement && !$is_installed && $is_installable;
$needs_activate  = $has_requirement && $is_installed && !$is_active;

if ($needs_upgrade) {
    $step_classes[] = 'needs-upgrade';
} elseif ($needs_install) {
    $step_classes[] = 'needs-install';
} elseif ($needs_activate) {
    $step_classes[] = 'needs-activate';
}

// Determine status text for screen readers.
if ($step['completed']) {
    $status_text = __('Completed', 'memberpress');
} else {
    $status_text = __('Optional', 'memberpress');
}

// Get plugin data for AJAX actions.
$plugin_file  = $step['plugin_file'] ?? '';
$download_url = $step['download_url'] ?? '';
$addon_type   = $step['addon_type'] ?? 'plugin';
?>

<li
    class="<?php echo esc_attr(implode(' ', $step_classes)); ?>"
    data-step-id="<?php echo esc_attr($step['id']); ?>"
    <?php if ($plugin_file) : ?>
        data-plugin-file="<?php echo esc_attr($plugin_file); ?>"
    <?php endif; ?>
    <?php if ($download_url) : ?>
        data-download-url="<?php echo esc_attr($download_url); ?>"
    <?php endif; ?>
    <?php if ($addon_type) : ?>
        data-addon-type="<?php echo esc_attr($addon_type); ?>"
    <?php endif; ?>
>
    <div class="mepr-psc-step-indicator" aria-hidden="true">
        <?php if ($step['completed']) : ?>
            <span class="mepr-psc-check">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>
        <?php else : ?>
            <span class="mepr-psc-optional-marker">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="1"></circle>
                </svg>
            </span>
        <?php endif; ?>
    </div>

    <div class="mepr-psc-step-content">
        <h4 class="mepr-psc-step-title">
            <?php echo esc_html($step['title']); ?>
            <span class="screen-reader-text"> — <?php echo esc_html($status_text); ?></span>
        </h4>

        <?php if (!$step['completed'] && !empty($step['description'])) : ?>
            <p class="mepr-psc-step-description"><?php echo esc_html($step['description']); ?></p>
        <?php endif; ?>

        <?php if (!$step['completed']) : ?>
            <div class="mepr-psc-step-actions">
                <?php if ($needs_upgrade) : ?>
                    <a
                        href="<?php echo esc_url($step['action_url']); ?>"
                        class="mepr-psc-step-action mepr-psc-optional-action mepr-psc-upgrade-action"
                        target="_blank"
                    >
                        <?php echo esc_html($step['action_text']); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mepr-psc-arrow" aria-hidden="true" focusable="false">
                            <line x1="7" y1="17" x2="17" y2="7"></line>
                            <polyline points="7 7 17 7 17 17"></polyline>
                        </svg>
                    </a>
                <?php elseif ($needs_install && !empty($download_url)) : ?>
                    <button
                        type="button"
                        class="mepr-psc-step-action mepr-psc-optional-action mepr-psc-install-plugin"
                        data-action="install"
                    >
                        <?php echo esc_html($step['action_text']); ?>
                        <span class="mepr-psc-spinner" aria-hidden="true"></span>
                    </button>
                <?php elseif ($needs_activate && !empty($plugin_file)) : ?>
                    <button
                        type="button"
                        class="mepr-psc-step-action mepr-psc-optional-action mepr-psc-activate-plugin"
                        data-action="activate"
                    >
                        <?php echo esc_html($step['action_text']); ?>
                        <span class="mepr-psc-spinner" aria-hidden="true"></span>
                    </button>
                <?php elseif (!empty($step['action_url'])) : ?>
                    <a href="<?php echo esc_url($step['action_url']); ?>" class="mepr-psc-step-action mepr-psc-optional-action">
                        <?php echo esc_html($step['action_text']); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mepr-psc-arrow" aria-hidden="true" focusable="false">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</li>
