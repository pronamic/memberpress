<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Post-Setup Checklist Step View
 *
 * @var array   $step       Step data
 * @var boolean $is_current Whether this is the current (next) step
 */

$step_classes = ['mepr-psc-step'];

if ($step['completed']) {
    $step_classes[] = 'is-completed';
} elseif (!empty($step['skipped'])) {
    $step_classes[] = 'is-skipped';
}

if ($is_current) {
    $step_classes[] = 'is-current';
}

// Determine status text for screen readers.
if ($step['completed']) {
    $status_text = __('Completed', 'memberpress');
} elseif (!empty($step['skipped'])) {
    $status_text = __('Skipped', 'memberpress');
} elseif ($is_current) {
    $status_text = __('Current step', 'memberpress');
} else {
    $status_text = __('Pending', 'memberpress');
}
?>

<li class="<?php echo esc_attr(implode(' ', $step_classes)); ?>" data-step-id="<?php echo esc_attr($step['id']); ?>">
    <div class="mepr-psc-step-indicator" aria-hidden="true">
        <?php if ($step['completed']) : ?>
            <span class="mepr-psc-check">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>
        <?php elseif (!empty($step['skipped'])) : ?>
            <span class="mepr-psc-skipped-marker">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </span>
        <?php elseif ($is_current) : ?>
            <span class="mepr-psc-current-marker">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
            </span>
        <?php else : ?>
            <span class="mepr-psc-pending-marker"></span>
        <?php endif; ?>
    </div>

    <div class="mepr-psc-step-content">
        <h4 class="mepr-psc-step-title">
            <?php echo esc_html($step['title']); ?>
            <span class="screen-reader-text"> — <?php echo esc_html($status_text); ?></span>
        </h4>

        <?php if ($is_current && !empty($step['description'])) : ?>
            <p class="mepr-psc-step-description"><?php echo esc_html($step['description']); ?></p>
        <?php endif; ?>

        <?php if ($is_current) : ?>
            <div class="mepr-psc-step-actions">
                <?php if (!empty($step['action_url'])) : ?>
                    <a href="<?php echo esc_url($step['action_url']); ?>" class="mepr-psc-step-action">
                        <?php echo esc_html($step['action_text']); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mepr-psc-arrow" aria-hidden="true" focusable="false">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($step['skippable'])) : ?>
                    <button type="button" class="mepr-psc-step-skip" data-step-id="<?php echo esc_attr($step['id']); ?>">
                        <?php esc_html_e('Skip', 'memberpress'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</li>
