<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>

<div class="wrap">

    <div class="mepr-sister-plugin mepr-sister-plugin-wp-mail-smtp">

        <div class="mepr-sister-plugin-image mp-courses-image">
            <img src="<?php echo esc_url(MEPR_BRAND_URL . '/images/coachkit-logo.svg'); ?>" width="800" height="216" alt="MemberPress CoachKit™">
        </div>

        <div class="mepr-sister-plugin-title">
            <?php esc_html_e('Next-Level Coaching Tools', 'memberpress'); ?>
        </div>

        <div class="mepr-sister-plugin-description">
            <?php esc_html_e("Customize your coaching to thrive or expand with the industry's only membership-coaching platform!", 'memberpress'); ?>
        </div>

        <div class="mepr-sister-plugin-info mepr-clearfix">
            <div class="mepr-sister-plugin-info-image">
                <div>
                    <img src="<?php echo esc_url(MEPR_BRAND_URL . '/images/coachkit-screenshot.png'); ?>" alt="<?php esc_attr_e('MemberPress CoachKit™', 'memberpress'); ?>">
                </div>
            </div>
            <div class="mepr-sister-plugin-info-features">
                <?php
                $bullets = [
                    esc_html__('Sell unlimited coaching programs', 'memberpress'),
                    esc_html__('Track success with habit log', 'memberpress'),
                    esc_html__('Celebrate mini-wins with milestones', 'memberpress'),
                    esc_html__('Keep clients on track with due dates', 'memberpress'),
                    esc_html__('1:1 in-platform private messaging', 'memberpress'),
                    esc_html__('Match coaches to specific cohorts', 'memberpress'),
                ];
                ?>
                <ul>
                    <?php
                    foreach ($bullets as $bullet) {
                        echo '<li style="margin-bottom: 5px; font-size: 13px;"><i class="mp-icon mp-icon-right-big"></i>';
                        echo esc_html($bullet);
                        echo '</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>

        <div
            class="mepr-sister-plugin-step mepr-sister-plugin-step-no-number mepr-sister-plugin-step-current mepr-clearfix">
            <div class="mepr-sister-plugin-step-detail">
                <div class="mepr-sister-plugin-step-title">
                    <?php if (!empty($plugins['memberpress-coachkit/main.php'])) : // Installed but not active. ?>
                        <?php esc_html_e('Enable MemberPress CoachKit™', 'memberpress'); ?>
                    <?php elseif (false !== $coachkit_addon) : // Not installed and available for install. ?>
                        <?php esc_html_e('Install and Activate MemberPress CoachKit™', 'memberpress'); ?>
                    <?php else : ?>
                        <?php esc_html_e('MemberPress CoachKit™', 'memberpress'); ?>
                    <?php endif; ?>
                </div>
                <div class="mepr-sister-plugin-step-button">
                    <?php if (!empty($plugins['memberpress-coachkit/main.php'])) : // Installed but not active. ?>
                        <button type="button" class="mepr-courses-action button button-primary button-hero"
                                data-action="activate"><?php esc_html_e('Activate MemberPress CoachKit™ Add-On', 'memberpress'); ?></button>
                    <?php elseif (false !== $coachkit_addon) : // Not installed and available for install. ?>
                        <button type="button" class="mepr-courses-action button button-primary button-hero"
                                data-action="install-activate"><?php esc_html_e('Install & Activate MemberPress CoachKit™ Add-On', 'memberpress'); ?></button>
                    <?php else : ?>
                        <a target="_blank"
                           href="https://memberpress.com/sign-in/?redirect_to=/register/coachkit-add-on/">
                            <button type="button"
                                    class=" button button-primary button-hero"><?php esc_html_e('Purchase', 'memberpress'); ?></button>
                        </a>
                    <?php endif; ?>
                </div>
                <div id="mepr-courses-action-notice" class="mepr-courses-action-notice notice inline"><p></p></div>
            </div>
        </div>

    </div>
</div>

<script>
  jQuery(document).ready(function($) {
    $('.mepr-courses-action').click(function(event) {
      event.preventDefault();
      var $this = $(this);
      $this.prop('disabled', 'disabled');
      var notice = $('#mepr-courses-action-notice');
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'mepr_coachkit_action',
          nonce: "<?php echo wp_create_nonce('mepr_coachkit_action'); ?>",
          type: $this.data('action')
        },
      })
      .done(function(data) {
        $this.remove();
        if ( data.data.redirect.length > 0 ) {
          window.location.href = data.data.redirect;
        } else {
          notice.find('p').html(data.data.message);
          notice.addClass('notice-' + data.data.result);
          notice.show();
          $this.removeProp('disabled');
        }
      })
      .fail(function(data) {
        notice.find('p').html(data.data.message);
        notice.addClass('notice-' + data.data.result);
        notice.show();
        $this.removeProp('disabled');
      })
      .always(function(data) {

      });
    });
  });
</script>
