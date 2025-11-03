<div id="mepr-reminder-emails">
  <?php MeprAppHelper::display_emails('MeprBaseReminderEmail', [['reminder_id' => $reminder->ID]]); ?>
</div>
<div id="mepr-reminder-products">
  <input type="checkbox" name="<?php echo esc_attr(MeprReminder::$filter_products_str); ?>" id="<?php echo esc_attr(MeprReminder::$filter_products_str); ?>" <?php checked($reminder->filter_products); ?> />
  <label for="<?php echo esc_attr(MeprReminder::$filter_products_str); ?>"><?php esc_html_e('Send only for specific Memberships', 'memberpress'); ?></label>
  <div id="mepr-reminder-products-hidden">
    <?php esc_html_e('Memberships for this Reminder', 'memberpress'); ?>
    <br/>
    <?php MeprRemindersHelper::products_multiselect(MeprReminder::$products_str, $reminder->products); ?>
  </div>
</div>
