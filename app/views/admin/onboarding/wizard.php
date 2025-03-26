<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div class="mepr-wizard">
  <div class="mepr-wizard-inner">
    <div class="mepr-onboarding-logo">
      <img src="<?php echo esc_url(MEPR_BRAND_URL . '/images/logo.svg'); ?>" alt="">
    </div>
    <div class="mepr-wizard-steps">
      <?php
        $onboarding_steps_completed = MeprOnboardingHelper::get_steps_completed();
        $next_applicable_step       = $onboarding_steps_completed + 1;

        foreach ($steps as $key => $step) {
            printf('<div class="mepr-wizard-step mepr-wizard-step-%s">', $key + 1);
            echo '<div class="mepr-wizard-progress-steps">';

            foreach ($steps as $progress_key => $progress_step) {
                $link_step = $progress_step['step'];

                $skipped_steps = MeprOnboardingHelper::get_skipped_steps();
                $css_class     = '';

                if ($progress_key == $key) {
                    $css_class .= ' mepr-wizard-current-step';
                }

                if (in_array($link_step, $skipped_steps) && $progress_key != $key) {
                    $css_class .= ' mepr-wizard-current-step-skipped';
                }

                printf(
                    '<div class="mepr_onboarding_step_%s mepr-wizard-progress-step%s"><span></span><a href="%s">%s</a></div>',
                    $link_step,
                    $css_class,
                    admin_url('admin.php?page=memberpress-onboarding&step=' . (int)$link_step),
                    esc_html($progress_step['title'])
                );
            }

            echo '</div>';
            if (file_exists($step['content'])) {
                require $step['content'];
            }
            echo '</div>';
        }
        ?>
    </div>
  </div>
  <div class="mepr-wizard-nav">
    <?php
    foreach ($steps as $key => $step) {
        $extra_class = '';

        if ($key == 0) {
            $li = get_site_transient('mepr_license_info');

            if (!$li) {
                $extra_class = ' mepr-hidden';
            }
        }

        printf(
            '<div class="mepr-wizard-nav-step mepr-wizard-nav-step-%1$s%2$s">',
            $key + 1,
            $extra_class
        );

        if (file_exists($step['nav'])) {
            require $step['nav'];
        }

        echo '</div>';
    }
    ?>
  </div>
</div>
