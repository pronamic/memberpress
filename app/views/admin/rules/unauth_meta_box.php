<div class="mepr-main-pane">
  <p><strong><?php _e('Unauthorized access handling for content protected by this rule', 'memberpress'); ?></strong></p>
  <p class="description"><?php _e('Note: This overrides the global settings for unauthorized access handling in MemberPress Options for content protected by this rule.', 'memberpress'); ?></p>
  <div class="mepr-main-pane" id="mepr_unauth_modern_paywall">
    <input type="checkbox" name="<?php echo esc_attr(MeprRule::$unauth_modern_paywall_str); ?>" id="<?php echo esc_attr(MeprRule::$unauth_modern_paywall_str); ?>" <?php checked($rule->unauth_modern_paywall); ?> value="1" />
    <label for="<?php echo esc_attr(MeprRule::$unauth_modern_paywall_str); ?>"><strong><?php echo esc_html__('Use modern PayWall', 'memberpress'); ?></strong></label>
  </div>
  <div class="mepr-main-pane">
    <?php
    MeprOptionsHelper::display_show_excerpts_dropdown(
        MeprRule::$unauth_excerpt_type_str,
        $rule->unauth_excerpt_type,
        MeprRule::$unauth_excerpt_size_str,
        $rule->unauth_excerpt_size
    );
    ?>
  </div>
  <div class="mepr-main-pane">
    <?php
    MeprOptionsHelper::display_unauth_message_dropdown(
        MeprRule::$unauth_message_type_str,
        $rule->unauth_message_type,
        MeprRule::$unauth_message_str,
        $rule->unauth_message
    );
    ?>
  </div>
  <div class="mepr-main-pane">
    <?php
    MeprOptionsHelper::display_unauth_login_dropdown(MeprRule::$unauth_login_str, $rule->unauth_login);
    ?>
  </div>
</div>
