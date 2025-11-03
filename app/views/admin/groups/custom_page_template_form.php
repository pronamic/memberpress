<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div id="mepr-custom-page-template">
  <input type="checkbox" name="<?php echo esc_attr(MeprGroup::$use_custom_template_str); ?>" id="<?php echo esc_attr(MeprGroup::$use_custom_template_str); ?>" <?php checked($group->use_custom_template); ?> />
  <label for="<?php echo esc_attr(MeprGroup::$use_custom_template_str); ?>"><?php esc_html_e('Use Custom Page Template', 'memberpress'); ?></label>
  <div id="mepr-custom-page-template-select" class="mepr_hidden">
    <br/>
    <?php MeprAppHelper::page_template_dropdown(MeprGroup::$custom_template_str, $group->custom_template); ?>
  </div>
</div>

