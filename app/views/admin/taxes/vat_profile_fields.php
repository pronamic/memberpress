<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<tr>
  <th>
    <label for="mepr-vat-customer-type"><?php _e('VAT Customer Type', 'memberpress'); ?></label>
  </th>
  <td>
    <?php
      if(MeprUtils::is_logged_in_and_an_admin()) {
    ?>
      <div class="mepr-radios-field">
        <span class="mepr-radios-field-row">
          <input type="radio" name="mepr_vat_customer_type" id="mepr_vat_customer_type-consumer" value="consumer" class="mepr-form-radios-input mepr_vat_customer_type-consumer" <?php echo ($ctype=='consumer') ? 'checked="checked"' : ''; ?>>
          <label for="mepr_vat_customer_type-consumer" class="mepr-form-radios-label"><?php _e('Consumer', 'memberpress'); ?></label>
        </span>
        <span class="mepr-radios-field-row">
          <input type="radio" name="mepr_vat_customer_type" id="mepr_vat_customer_type-business" value="business" class="mepr-form-radios-input mepr_vat_customer_type-business" <?php echo ($ctype=='consumer') ? '' : 'checked="checked"'; ?>>
          <label for="mepr_vat_customer_type-business" class="mepr-form-radios-label"><?php _e('Business', 'memberpress'); ?></label>
        </span>
      </div>
    <?php
      } else {
        if(!empty($ctype)) {
          if($ctype=='consumer') {
            _e('Consumer', 'memberpress');
          }
          else {
            _e('Business', 'memberpress');
          }
        }
        else {
          _e('Unknown', 'memberpress');
        }
      }
    ?>
  </td>
</tr>
<tr>
  <th>
    <label for="mepr-vat-number"><?php _e('VAT Number', 'memberpress'); ?></label>
  </th>
  <td>
    <?php
      if(MeprUtils::is_logged_in_and_an_admin()) {
        ?>
        <input type="text" name="mepr_vat_number" id="mepr_vat_number" class="mepr-form-input regular-text" value="<?php echo $vnum; ?>">
        <?php
      } else {
        if(empty($vnum)) {
          if($ctype=='consumer') {
            _e('Not Applicable', 'memberpress');
          }
          else {
            _e('Not Set', 'memberpress');
          }
        } else {
          echo $vnum;
        }
      }

    ?>
  </td>
</tr>
