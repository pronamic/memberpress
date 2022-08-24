<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php
$features = MeprOnboardingHelper::get_selected_features(get_current_user_id());
$addons_selectable = MeprOnboardingHelper::features_addons_selectable_list();
?>
<h2 class="mepr-wizard-step-title"><?php esc_html_e('What features do you want to enable?', 'memberpress'); ?></h2>
<p class="mepr-wizard-step-description"><?php esc_html_e('MemberPress is chock full of awesome features. Here are a few you can enable right off the bat.', 'memberpress'); ?></p>
<div class="mepr-wizard-features">
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Advanced Content Protection', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Increase perceived value, and protect your bottom line from unpaying eyes. Paywall your valuable content a thousand ways!', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Customizable Checkout', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Sell more memberships by accepting multiple payment types – from PayPal and credit cards to digital wallets, bank checks, and even cash by mail. You decide.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Course Creator', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Sell what you know. Create memorable online courses, including quizzes and progress tracking.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
     <?php if($addons_selectable['memberpress-courses']): ?>
      <input type="checkbox" class="mepr-wizard-feature-input" value="memberpress-courses" <?php checked(in_array('memberpress-courses', $features, true)); ?>>

      <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
      <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
     <?php else: ?>
      <input type="hidden" class="mepr-wizard-feature-input-active" value="memberpress-courses">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
     <?php endif; ?>
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Digital Downloads', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Add value to your memberships by giving users access to downloadable files like white papers, guides, checklists, and videos – the sky’s the limit.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
    <?php if($addons_selectable['memberpress-downloads']): ?>
      <input type="checkbox" class="mepr-wizard-feature-input" value="memberpress-downloads" <?php checked(in_array('memberpress-downloads', $features, true)); ?>>
      <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
      <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
    <?php else: ?>
      <input type="hidden" class="mepr-wizard-feature-input-active" value="memberpress-downloads">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
    <?php endif; ?>
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Member Community', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Keep users coming back for more with a VIP forum or chat room they can access based on membership level. Available with MemberPress Plus or Pro.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
      <?php if($addons_selectable['memberpress-buddypress']): ?>
      <input type="checkbox" class="mepr-wizard-feature-input" value="memberpress-buddypress" <?php checked(in_array('memberpress-buddypress', $features, true)); ?>>
      <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
      <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
     <?php else: ?>
      <input type="hidden" class="mepr-wizard-feature-input-active" value="memberpress-buddypress">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
     <?php endif; ?>
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Zapier Integration', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Work smarter not harder. Connect MemberPress with thousands of your favorite productivity and functionality apps and services. Available with MemberPress Plus or Pro.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
    <?php if($addons_selectable['memberpress-developer-tools']): ?>
      <input type="checkbox" class="mepr-wizard-feature-input" value="memberpress-developer-tools" <?php checked(in_array('memberpress-developer-tools', $features, true)); ?>>
      <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
      <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
    <?php else: ?>
      <input type="hidden" class="mepr-wizard-feature-input-active" value="memberpress-developer-tools">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
    <?php endif; ?>
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Gifting', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Expand your audience and sell more memberships. With Gifting, you can market to people who know people who could use your membership. Available with MemberPress Pro.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
    <?php if($addons_selectable['memberpress-gifting']): ?>
      <input type="checkbox" class="mepr-wizard-feature-input" value="memberpress-gifting" <?php checked(in_array('memberpress-gifting', $features, true)); ?>>
      <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
      <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
    <?php else: ?>
      <input type="hidden" class="mepr-wizard-feature-input-active" value="memberpress-gifting">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
    <?php endif; ?>
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Corporate Accounts', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Allow your members to add sub-accounts to their memberships based on subscription level. Great for families and groups. Available with MemberPress Pro.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
    <?php if($addons_selectable['memberpress-corporate']): ?>
      <input type="checkbox" class="mepr-wizard-feature-input" value="memberpress-corporate" <?php checked(in_array('memberpress-corporate', $features, true)); ?>>
      <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
      <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
    <?php else: ?>
      <input type="hidden" class="mepr-wizard-feature-input-active" value="memberpress-corporate">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
    <?php endif; ?>
    </div>
  </div>
  <div class="mepr-wizard-feature">
    <div>
      <h3><?php esc_html_e('Affiliate Program', 'memberpress'); ?></h3>
      <p><?php esc_html_e('Create your own non-salaried sales team, and make up to 30% more in membership sales with referral marketing.', 'memberpress'); ?></p>
    </div>
    <div class="mepr-wizard-feature-right">
    <?php if($addons_selectable['easy-affiliate']): ?>
      <input type="checkbox" class="mepr-wizard-feature-input" value="easy-affiliate" <?php checked(in_array('easy-affiliate', $features, true)); ?>>
      <img class="mepr-wizard-feature-checked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-checked.svg'); ?>" alt="">
      <img class="mepr-wizard-feature-unchecked" src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-unchecked.svg'); ?>" alt="">
    <?php else: ?>
      <input type="hidden" class="mepr-wizard-feature-input-active" value="easy-affiliate">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/checkbox-disabled.svg'); ?>" alt="">
    <?php endif; ?>
    </div>
  </div>
</div>
<p class="mepr-wizard-plugins-to-install">
  <?php
    printf(
    __('If your subscription level allows, the following plugins will be installed automatically: %s', 'memberpress'),
      '<span></span> <br /><br /><strong>Want a feature your membership level doesn’t support? No worries! You’ll get the chance to upgrade later in the onboarding wizard.</strong>'
    );
  ?>
</p>
