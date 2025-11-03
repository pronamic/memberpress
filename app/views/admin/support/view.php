<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
<div id="help" class="wrap mepr-support-wrapper">
  <h1 class="mepr-support-heading"><?php esc_html_e('Support', 'memberpress');?></h1>
  <h2 class="mepr-support-help"><?php esc_html_e('Frequently Asked Questions', 'memberpress');?></h2>
  <div class="mepr-support-help-wrapper">
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_faqs')); ?>">
        <h3><?php esc_html_e('How To', 'memberpress');?></h3>
        <p><?php esc_html_e('Frequently "How To..." questions.', 'memberpress');?></p>
      </a>
    </div>
  </div>
  <h2 class="mepr-support-help"><?php esc_html_e('Getting Started', 'memberpress');?></h2>
  <div class="mepr-support-help-wrapper">
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_installing_upgrading')); ?>">
        <h3><?php esc_html_e('Installation and Upgrading', 'memberpress');?></h3>
        <p><?php esc_html_e('How to install, activate, and upgrade MemberPress.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_general')); ?>">
        <h3><?php esc_html_e('Configuring Settings', 'memberpress');?></h3>
        <p><?php esc_html_e('How to configure your settings in MemberPress.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_registration_payments')); ?>">
        <h3><?php esc_html_e('Supported Payment Gateways', 'memberpress');?></h3>
        <p><?php esc_html_e('Pick your payment gateway and get it setup.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_migrating')); ?>">
        <h3><?php esc_html_e('Migrating and Importing/Exporting', 'memberpress');?></h3>
        <p><?php esc_html_e('Migrate from a different membership system, or from a different server.', 'memberpress');?></p>
      </a>
    </div>
  </div>

  <h2 class="mepr-support-help"><?php esc_html_e('Using MemberPress', 'memberpress');?></h2>
  <div class="mepr-support-help-wrapper">
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_memberships')); ?>">
        <h3><?php esc_html_e('Creating and Managing Memberships', 'memberpress');?></h3>
        <p><?php esc_html_e('How to configure and use our Memberships.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_protecting_content')); ?>">
        <h3><?php esc_html_e('Protecting Content', 'memberpress');?></h3>
        <p><?php esc_html_e('How to use rules to protect content on your site.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_groups')); ?>">
        <h3><?php esc_html_e('Creating and Managing Groups', 'memberpress');?></h3>
        <p><?php esc_html_e('Configure groups for easy pricing pages and upgrade/downgrade paths.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_pages')); ?>">
        <h3><?php esc_html_e('Front End Pages', 'memberpress');?></h3>
        <p><?php esc_html_e('How to configure and use Login, Account, Registration, and other front end pages.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_general')); ?>">
        <h3><?php esc_html_e('Shortcodes, Widgets, and Email Parameters', 'memberpress');?></h3>
        <p><?php esc_html_e('Customize emails and pages to match your site.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_registration_payments')); ?>">
        <h3><?php esc_html_e('Managing Members and their Subscriptions', 'memberpress');?></h3>
        <p><?php esc_html_e('Managing Members(users) and their subscriptions to memberships.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_coupons')); ?>">
        <h3><?php esc_html_e('Creating and Managing Coupons', 'memberpress');?></h3>
        <p><?php esc_html_e('Create and manage coupons for discounts and promotions.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_reminders')); ?>">
        <h3><?php esc_html_e('Creating and Managing Reminder Emails', 'memberpress');?></h3>
        <p><?php esc_html_e('Create and use reminder emails to keep your members informed.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_reports')); ?>">
        <h3><?php esc_html_e('Viewing Reports', 'memberpress');?></h3>
        <p><?php esc_html_e('Use MemberPress reports to keep a finger on the pulse of your site.', 'memberpress');?></p>
      </a>
    </div>
  </div>

  <h2 class="mepr-support-help"><?php esc_html_e('Extensions', 'memberpress');?></h2>
  <div class="mepr-support-help-wrapper">
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_addons')); ?>">
        <h3><?php esc_html_e('Add-Ons', 'memberpress');?></h3>
        <p><?php esc_html_e('MemberPress created and supported extensions.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_marketing')); ?>">
        <h3><?php esc_html_e('Email Marketing', 'memberpress');?></h3>
        <p><?php esc_html_e('Integrate MemberPress with your email marketing platform.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_third_party')); ?>">
        <h3><?php esc_html_e('Third-Party Integrations', 'memberpress');?></h3>
        <p><?php esc_html_e('Recommend integrations built and supported by others.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_courses')); ?>">
        <h3><?php esc_html_e('MemberPress Courses', 'memberpress');?></h3>
        <p><?php esc_html_e('Offer your courses through MemberPress without our official add-on.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_protecting_files')); ?>">
        <h3><?php esc_html_e('MemberPress Downloads', 'memberpress');?></h3>
        <p><?php esc_html_e('Protect files, images, and more with the official MemberPress Downloads add-on.', 'memberpress');?></p>
      </a>
    </div>
  </div>

  <h2 class="mepr-support-help"><?php esc_html_e('Advanced Topics', 'memberpress');?></h2>
  <div class="mepr-support-help-wrapper">
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_advanced_topics')); ?>">
        <h3><?php esc_html_e('Privacy (GDPR)', 'memberpress');?></h3>
        <p><?php esc_html_e('What data MemberPress stores and how it works with GDPR.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_advanced_topics')); ?>">
        <h3><?php esc_html_e('MemberPress Developer Tools', 'memberpress');?></h3>
        <p><?php esc_html_e('MemberPress offers webhook support and a REST API for developers.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_advanced_topics')); ?>">
        <h3><?php esc_html_e('Other Tools/Suggestions', 'memberpress');?></h3>
        <p><?php esc_html_e('Advanced to ways to further customize and configure MemberPress.', 'memberpress');?></p>
      </a>
    </div>
    <div class="mepr-support-help-topic">
      <a target="_blank" href="<?php echo esc_url(MeprUtils::get_link_url('docs_advanced_topics')); ?>">
        <h3><?php esc_html_e('Developer Resources', 'memberpress');?></h3>
        <p><?php esc_html_e('Resources about the inner workings of MemberPress for developers.', 'memberpress');?></p>
      </a>
    </div>
  </div>
</div>
