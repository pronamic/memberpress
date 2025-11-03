<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div id="config-<?php echo esc_attr($email->dashed_name()); ?>" class="mepr-config-email-row">
  <label for="<?php echo esc_attr($email->field_name('enabled', true)); ?>">
    <input type="checkbox"
           name="<?php echo esc_attr($email->field_name('enabled')); ?>"
           id="<?php echo esc_attr($email->field_name('enabled', true)); ?>"<?php checked($email->enabled()); ?>/>
    <?php printf(
        // Translators: %s: email title.
        esc_html__('Send %s', 'memberpress'),
        wp_kses($email->title, ['b' => []])
    ); ?>
  </label>
  <?php MeprAppHelper::info_tooltip(
      esc_attr($email->dashed_name()),
      $email->title,
      $email->description
  ); ?>
  <a href="#"
     class="mepr-edit-email-toggle button"
     data-id="edit-<?php echo esc_attr($email->dashed_name()); ?>"
     data-edit-text="<?php esc_attr_e('Edit', 'memberpress'); ?>"
     data-cancel-text="<?php esc_attr_e('Hide Editor', 'memberpress'); ?>"><?php esc_html_e('Edit', 'memberpress'); ?></a>
  <a href="#"
     class="mepr-send-test-email button"
     data-obj-dashed-name="<?php echo esc_attr($email->dashed_name()); ?>"
     data-obj-name="<?php echo esc_attr(get_class($email)); ?>"
     data-subject-id="<?php echo esc_attr($email->field_name('subject', true)); ?>"
     data-use-template-id="<?php echo esc_attr($email->field_name('use_template', true)); ?>"
     data-body-id="<?php echo esc_attr($email->field_name('body', true)); ?>"><?php esc_html_e('Send Test', 'memberpress'); ?></a>
  <a href="#"
     class="mepr-reset-email button"
     data-obj-dashed-name="<?php echo esc_attr($email->dashed_name()); ?>"
     data-subject-id="<?php echo esc_attr($email->field_name('subject', true)); ?>"
     data-body-obj="<?php echo esc_attr(get_class($email)); ?>"
     data-use-template-id="<?php echo esc_attr($email->field_name('use_template', true)); ?>"
     data-body-id="<?php echo esc_attr($email->field_name('body', true)); ?>"><?php esc_html_e('Reset to Default', 'memberpress'); ?></a>
  <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/square-loader.gif'); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" id="mepr-loader-<?php echo esc_attr($email->dashed_name()); ?>" class="mepr_loader" />

  <div id="edit-<?php echo esc_attr($email->dashed_name()); ?>" class="mepr-hidden mepr-options-pane mepr-edit-email">
    <ul>
      <li>
        <span class="mepr-field-label"><?php esc_html_e('Subject', 'memberpress'); ?></span><br/>
        <input class="form-field" type="text" id="<?php echo esc_attr($email->field_name('subject', true)); ?>" name="<?php echo esc_attr($email->field_name('subject')); ?>" value="<?php echo esc_attr($email->subject()); ?>" />
      </li>
      <li>
        <span class="mepr-field-label"><?php esc_html_e('Body', 'memberpress'); ?></span><br/>
        <?php wp_editor(
            $email->body(),
            $email->field_name('body', true),
            ['textarea_name' => $email->field_name('body')]
        ); ?>
      </li>
      <li>
        <select id="var-<?php echo esc_attr($email->dashed_name()); ?>">
          <?php foreach ($email->variables as $var) : ?>
            <option value="{$<?php echo esc_attr($var); ?>}">{$<?php echo esc_html($var); ?>}</option>
          <?php endforeach; ?>
        </select>

        <a href="#" class="button mepr-insert-email-var" data-variable-id="var-<?php echo esc_attr($email->dashed_name()); ?>"
           data-textarea-id="<?php echo esc_attr($email->field_name('body', true)); ?>"><?php esc_html_e('Insert &uarr;', 'memberpress'); ?></a>
      </li>
      <li>
        <br/>
        <input type="checkbox"
               name="<?php echo esc_attr($email->field_name('use_template')); ?>"
               id="<?php echo esc_attr($email->field_name('use_template', true)); ?>"<?php checked($email->use_template()); ?>/>
        <span class="mepr-field-label">
          <?php esc_html_e('Use default template', 'memberpress'); ?>
          <?php MeprAppHelper::info_tooltip(
              $email->dashed_name() . '-template',
              __('Default Email Template', 'memberpress'),
              __('When this is checked the body of this email will be wrapped in the default email template.', 'memberpress')
          ); ?>
        </span>
      </li>
    </ul>
  </div>
</div>
