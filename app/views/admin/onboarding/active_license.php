<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="mepr-license-container" class="mepr-wizard-license-container">
  <div class="mepr-wizard-license">
    <div class="mepr-wizard-license-notice">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/green-check.svg'); ?>" alt="">
      <?php
        $expires_at = null;

        if (isset($li['license_key']['expires_at'])) {
            $expires_at = date_create($li['license_key']['expires_at']);
        }

        if ($expires_at instanceof DateTime) {
            echo esc_html(
                sprintf(
                    // Translators: %s: date.
                    __('License activated until %s', 'memberpress'),
                    MeprUtils::date('F j, Y', $expires_at)
                )
            );
        } else {
            esc_html_e('License activated', 'memberpress');
        }
        ?>
    </div>
    <div class="mepr-wizard-license-details">
      <div>
        <div class="mepr-wizard-license-label">
          <?php esc_html_e('Account email', 'memberpress'); ?>
        </div>
        <div class="mepr-wizard-license-value">
          <?php echo esc_html(!empty($li['user']['email']) ? $li['user']['email'] : __('Unknown', 'memberpress')); ?>
        </div>
      </div>
      <div>
        <div class="mepr-wizard-license-label">
          <?php esc_html_e('Product', 'memberpress'); ?>
        </div>
        <div class="mepr-wizard-license-value">
          <?php echo esc_html($li['product_name']); ?>
        </div>
      </div>
      <div>
        <div class="mepr-wizard-license-label">
          <?php esc_html_e('Activations', 'memberpress'); ?>
        </div>
        <div class="mepr-wizard-license-value">
          <?php
            printf(
              // Translators: %1$s: open b tag, %2$d: activation count, %3$s: max activations, %4$s close b tag.
                esc_html__('%1$s%2$d of %3$s%4$s sites have been activated with this license key', 'memberpress'),
                '<b>',
                esc_html($li['activation_count']),
                esc_html(ucwords($li['max_activations'])),
                '</b>'
            );
            ?>
        </div>
      </div>
    </div>
    <div class="mepr-wizard-license-manage">
      <a href="https://memberpress.com/ipob/downloads" target="_blank"><?php esc_html_e('Manage activations', 'memberpress'); ?></a>
    </div>
    <div class="mepr-wizard-license-deactivate">
      <button type="button" id="mepr-deactivate-license-key" class="mepr-wizard-button-secondary"><?php esc_html_e('Deactivate License', 'memberpress'); ?></button>
    </div>
  </div>
</div>
