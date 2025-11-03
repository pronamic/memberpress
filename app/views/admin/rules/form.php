<?php
/**
 * View admin/rules/form.
 *
 * @var MeprRule $rule
 */

defined('ABSPATH') || exit;

$products               = MeprCptModel::all('MeprProduct');
$rule_access_conditions = $rule->access_conditions();
?>

<?php if (!empty($products)) : ?>
  <div id="mepr-rules-form">
    <div class="mepr-main-pane">
      <h3 class="mepr-page-title">
        <?php esc_html_e('Protected Content', 'memberpress') ?>
        <?php MeprAppHelper::info_tooltip(
            'mepr-rule-protected-content',
            __('Protected Content', 'memberpress'),
            __('This selects the content on your site that will be protected by this rule. If a piece of content is selected by this rule it will be protected from non-logged in visitors and from logged-in users who don\'t meet the conditions you specify in the \'Access Rules\' section below.', 'memberpress')
        ); ?>
      </h3>
      <?php MeprRulesHelper::type_dropdown($rule->mepr_type); ?>:
      <img src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" id="mepr-rule-loading-icon" class="mepr_hidden" />
      <?php MeprRulesHelper::content_dropdown(MeprRule::$mepr_content_str, $rule->mepr_content, $rule->mepr_type, [MeprRule::$is_mepr_content_regexp_str => $rule->is_mepr_content_regexp]); ?>
    </div>

    <div class="mepr-main-pane">
      <h3 class="mepr-page-title">
        <?php esc_html_e('Access Conditions', 'memberpress') ?>
        <?php MeprAppHelper::info_tooltip(
            'mepr-rule-access-conditions',
            __('Access Conditions', 'memberpress'),
            sprintf(
                // Translators: %1$s: opening strong tag, %2$s: closing strong tag.
                __('If %1$sany%2$s of these conditions match for the current user then he / she will be granted access to the protected content for this rule -- otherwise he / she will be denied.', 'memberpress'),
                '<strong>',
                '</strong>'
            )
        ); ?>
      </h3>
      <h4><?php esc_html_e('Grant access to the protected content above if the current user matches any of the following conditions:', 'memberpress') ?></h4>
      <div id="mepr-access-rows">
        <?php
        if (empty($rule_access_conditions)) {
            MeprRulesHelper::access_row();
        } else {
            foreach ($rule_access_conditions as $ac_index => $access_condition) {
                MeprRulesHelper::access_row($access_condition, $ac_index);
            }
        }
        ?>
      </div>
      <div>&nbsp;</div>
      <img src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" id="mepr-condition-loading-icon" class="mepr_hidden" />
      <a href="" id="add-new-rule-condition" title="<?php esc_attr_e('Add Access Rule', 'memberpress'); ?>"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
    </div>

    <div class="mepr-main-pane">
      <h3 class="mepr-page-title"><a href="" class="mepr-toggle-link" data-box="mepr-partial-codes"><?php esc_html_e('Partial Content Codes', 'memberpress'); ?></a></h3>
      <div class="mepr-sub-box mepr-partial-codes mepr-hidden">
        <strong><?php esc_html_e('Examples:', 'memberpress'); ?></strong>
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <?php esc_html_e('Shortcode:', 'memberpress'); ?><br /><strong>[mepr_show rules="<?php echo esc_attr($rule->ID); ?>" unauth="message"] </strong><?php esc_html_e('This content is shown only to authorized members. It is hidden from everyone else.', 'memberpress'); ?><strong> [/mepr_show]</strong>
        <br/><br/>
        <?php esc_html_e('Shortcode:', 'memberpress'); ?><br /><strong>[mepr_hide rules="<?php echo esc_attr($rule->ID); ?>" unauth="message"] </strong><?php esc_html_e('This content is shown to everyone except authorized members.', 'memberpress'); ?><strong> [/mepr_hide]</strong>
        <br/><br/>
        <?php esc_html_e('PHP Snippet:', 'memberpress'); ?><br />
        <strong><?php echo esc_html("<?php if(current_user_can('mepr-active','rules:{$rule->ID}')): ?>"); ?></strong>
          <?php esc_html_e('Content to protect goes inbetween.', 'memberpress'); ?>
        <strong><?php echo esc_html('<?php endif; ?>'); ?></strong>
        <?php MeprHooks::do_action('mepr_partial_content_codes', $rule); ?>
        <br/><br/>
        <strong><?php esc_html_e('Learn More:', 'memberpress'); ?></strong>
        <ul>
          <li><a href="<?php echo esc_url(MeprUtils::get_link_url('docs_show_hide_shortcodes')); ?>">[mepr_show]/[mepr_hide] <?php esc_html_e('shortcode documentation', 'memberpress'); ?></a></li>
          <li><a href="<?php echo esc_url(MeprUtils::get_link_url('docs_partial_content')); ?>"><?php esc_html_e('Protecting partial content', 'memberpress'); ?></a></li>
        </ul>
      </div>
    </div>

    <input type="hidden" name="<?php echo esc_attr(MeprRule::$auto_gen_title_str); ?>" id="<?php echo esc_attr(MeprRule::$auto_gen_title_str); ?>" value="<?php echo ($rule->auto_gen_title) ? 'true' : 'false'; ?>" />
    <!-- The NONCE below prevents post meta from being blanked on move to trash -->
    <input type="hidden" name="<?php echo esc_attr(MeprRule::$mepr_nonce_str); ?>" value="<?php echo esc_attr(wp_create_nonce(MeprRule::$mepr_nonce_str . wp_salt())); ?>" />
    <!-- jQuery i18n data -->
    <div id="save-rule-helper" style="display:none;" data-value="<?php esc_attr_e('Save Rule', 'memberpress'); ?>"></div>
    <div id="rule-message-helper" style="display:none;" data-value="<?php esc_attr_e('Rule Saved', 'memberpress'); ?>"></div>
  </div>
<?php else : ?>
  <div id="mepr-rules-form">
    <strong><?php esc_html_e('You cannot create rules until you have added at least 1 Membership.', 'memberpress'); ?></strong>
    <!-- jQuery i18n data -->
    <div id="save-rule-helper" style="display:none;" data-value="<?php esc_attr_e('Save Rule', 'memberpress'); ?>"></div>
    <div id="rule-message-helper" style="display:none;" data-value="<?php esc_attr_e('Rule Saved', 'memberpress'); ?>"></div>
  </div>
<?php endif;
