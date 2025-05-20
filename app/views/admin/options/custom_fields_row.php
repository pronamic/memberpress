<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<li class="mepr-custom-field postbox">
  <span class="mp-icon mp-icon-drag-target"></span>
  <label><?php _e('Name:', 'memberpress'); ?></label>
  <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][name]" value="<?php echo esc_attr(stripslashes($line->field_name)); ?>" />

  <label><?php _e('Type:', 'memberpress'); ?></label>
  <?php MeprView::render('/admin/options/custom_fields_options', get_defined_vars()); ?>

  <label for="mepr-custom-fields[<?php echo $random_id; ?>][default]"><?php _e('Default Value(s):', 'memberpress'); ?></label>
  <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][default]" value="<?php echo esc_attr(stripslashes($line->default_value)); ?>" />

  <a href="" class="mepr-custom-field-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>

  <p class="mepr-custom-fields-p">
    <input type="checkbox" name="mepr-custom-fields[<?php echo $random_id; ?>][signup]" id="mepr-custom-fields-signup-<?php echo $random_id; ?>" <?php checked($line->show_on_signup); ?> />
    <label for="mepr-custom-fields-signup-<?php echo $random_id; ?>"><?php _e('Show at Signup', 'memberpress'); ?></label>

    &nbsp;&nbsp;&nbsp;<input type="checkbox" name="mepr-custom-fields[<?php echo $random_id; ?>][show_in_account]" id="mepr-custom-fields-account-<?php echo $random_id; ?>" <?php checked(isset($line->show_in_account) ? $line->show_in_account : $blank_line[0]->show_in_account); ?> />
    <label for="mepr-custom-fields-account-<?php echo $random_id; ?>"><?php _e('Show in Account', 'memberpress'); ?></label>

    &nbsp;&nbsp;&nbsp;<input type="checkbox" name="mepr-custom-fields[<?php echo $random_id; ?>][required]" id="mepr-custom-fields-required-<?php echo $random_id; ?>" <?php checked($line->required); ?> />
    <label for="mepr-custom-fields-required-<?php echo $random_id; ?>"><?php _e('Required', 'memberpress'); ?></label>
  </p>
  <input type="hidden" name="mepr-custom-fields-index[]" value="<?php echo $random_id; ?>" />
  <div id="dropdown-hidden-options-<?php echo $random_id; ?>" <?php echo $hide; ?>>
  <?php
    if (empty($hide)) {
        ?>
      <ul class="custom_options_list">
        <?php

        if (empty($line->options)) {
            ?>
        <li>
          <label><?php _e('Option Name:', 'memberpress'); ?></label>
          <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][option][]" value="" />

          <label><?php _e('Option Value:', 'memberpress'); ?></label>
          <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][value][]" value="" />

          <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
        </li>
            <?php
        } else {
            foreach ($line->options as $option) {
                ?>
          <li>
            <label><?php _e('Option Name:', 'memberpress'); ?></label>
            <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][option][]" value="<?php echo esc_attr(stripslashes($option->option_name)); ?>" />

            <label><?php _e('Option Value:', 'memberpress'); ?></label>
            <input type="text" name="mepr-custom-fields[<?php echo $random_id; ?>][value][]" value="<?php echo esc_attr(stripslashes($option->option_value)); ?>" />

            <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
          </li>
                <?php
            }
        }

        ?>
        <a href="" id="mepr-add-new-option" title="Add Option" data-value="<?php echo $random_id; ?>"><i class="mp-icon mp-icon-plus-circled mp-16"></i></a>
      </ul>
        <?php
    }
    ?>
  </div>

  <input type="hidden" name="mepr-custom-fields[<?php echo $random_id; ?>][slug]" value="<?php echo (!empty($line->field_key)) ? $line->field_key : 'mepr_none'; ?>" />
  <?php if (!empty($line->field_key)) : ?>
    <p class="mepr-custom-fields-p"><b><?php _e('Slug:', 'memberpress'); ?></b> <?php echo $line->field_key; ?></p>
  <?php endif; ?>
</li>
