<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>
    <div class="mp-form-row">
        <label><?php echo esc_html_x('First Name on Card', 'ui', 'memberpress'); ?></label>
        <input type="text" name="mepr_first_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_first_name'])) ? esc_attr(sanitize_text_field(wp_unslash($_POST['mepr_first_name']))) : ''; ?>" />
    </div>

    <div class="mp-form-row">
        <label><?php echo esc_html_x('Last Name on Card', 'ui', 'memberpress'); ?></label>
        <input type="text" name="mepr_last_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_last_name'])) ? esc_attr(sanitize_text_field(wp_unslash($_POST['mepr_last_name']))) : ''; ?>" />
    </div>

    <div class="mp-form-row">
        <div class="mp-form-label">
            <label><?php echo esc_html_x('Zip code for Card', 'ui', 'memberpress'); ?></label>
        </div>
        <input type="text" name="mepr_zip_post_code" class="mepr-form-input" autocomplete="off" value="" required />
    </div>
