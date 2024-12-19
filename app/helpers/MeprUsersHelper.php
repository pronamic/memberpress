<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprUsersHelper
{
    public static function get_email_vars()
    {
        return MeprHooks::apply_filters(
            'mepr_user_email_vars',
            [
                'user_id',
                'user_login',
                'username',
                'user_email',
                'user_first_name',
                'user_last_name',
                'user_full_name',
                'user_address',
                'user_register_date',
                'blog_name',
                'business_name',
                'biz_name',
                'biz_address1',
                'biz_address2',
                'biz_city',
                'biz_state',
                'biz_postcode',
                'biz_country',
                'login_page',
                'account_url',
                'login_url',
                'usermeta:*',
            ]
        );
    }

    public static function get_email_params($usr)
    {
        $mepr_options = MeprOptions::fetch();
        $ts = MeprUtils::db_date_to_ts($usr->user_registered);
        $usr_date = date_i18n(__('F j, Y, g:i a', 'memberpress'), $ts, true);

        $params = [
            'user_id'            => $usr->ID,
            'user_login'         => $usr->user_login,
            'username'           => $usr->user_login,
            'user_email'         => $usr->user_email,
            'user_first_name'    => $usr->first_name,
            'user_last_name'     => $usr->last_name,
            'user_full_name'     => $usr->full_name(),
            'user_address'       => $usr->formatted_address(),
            'user_register_date' => $usr_date,
            'blog_name'          => MeprUtils::blogname(),
            'business_name'      => $mepr_options->attr('biz_name'),
            'biz_name'           => $mepr_options->attr('biz_name'),
            'biz_address1'       => $mepr_options->attr('biz_address1'),
            'biz_address2'       => $mepr_options->attr('biz_address2'),
            'biz_city'           => $mepr_options->attr('biz_city'),
            'biz_state'          => $mepr_options->attr('biz_state'),
            'biz_postcode'       => $mepr_options->attr('biz_postcode'),
            'biz_country'        => $mepr_options->attr('biz_country'),
            'login_page'         => $mepr_options->login_page_url(),
            'account_url'        => $mepr_options->account_page_url(),
            'login_url'          => $mepr_options->login_page_url(),
        ];

        $ums = MeprUtils::get_formatted_usermeta($usr->ID);

        if (!empty($ums)) {
            foreach ($ums as $umkey => $umval) {
                $params["usermeta:{$umkey}"] = $umval;
            }
        }

        // You know we're just going to lump the user record fields in here no problem
        foreach ((array)$usr->rec as $ukey => $uval) {
            $params["usermeta:{$ukey}"] = $uval;
        }

        $params = MeprHooks::apply_filters('mepr_user_notification_params', $params, $usr); // DEPRECATED

        return MeprHooks::apply_filters('mepr_user_email_params', $params, $usr);
    }

    public static function render_custom_field($line, $value = '', $classes = [], $unique_suffix = '')
    {
        $required_attr = $line->required ? 'required' : '';
        $array_types = ['multiselect', 'checkboxes']; // If we update this, we need make sure it doesn't break the {$usermeta:slug} stuff in MeprTransactionsHelper
        $bool_types  = ['checkbox'];
        $classes = MeprHooks::apply_filters('mepr-custom-field-classes', $classes, $line);
        if (isset($line->placeholder)) {
            $placeholder_attr = (isset($line->required) && $line->required) ? 'placeholder="' . $line->placeholder . '*"' : 'placeholder="' . $line->placeholder . '"';
        } else {
            $placeholder_attr = '';
        }

        $required_attr = $placeholder_attr . ' ' . $required_attr;

        // Figure out what type we have here
        $is_array  = in_array($line->field_type, $array_types);
        $is_bool   = in_array($line->field_type, $bool_types);
        $is_string = ( !$is_array && !$is_bool );

        if (isset($_REQUEST[$line->field_key])) {
            if ($is_array) {
                $value = $_REQUEST[$line->field_key];
            } elseif ($is_bool) {
                $value = true;
            } else {
                $value = stripslashes($_REQUEST[$line->field_key]);
            }
        } elseif ($value === '') {
            if ($is_array && $line->field_type === 'multiselect') {
                $value = explode(',', preg_replace('/\s*,\s*/', ',', trim($line->default_value)));
            } elseif ($is_array && $line->field_type === 'checkboxes') {
                $vals = explode(',', preg_replace('/\s*,\s*/', ',', trim($line->default_value)));

                $value = [];
                for ($i = 0; $i < count($vals); $i++) {
                    $value[$vals[$i]] = 'on';
                }
            } elseif ($is_bool) {
                $current_user = MeprUtils::get_currentuserinfo();

                // We have to account for the possibility that the checkbox has been saved
                // with a value of '' instead of false so we have to formally check if the
                // value has been saved at some point in the past otherwise set as default
                if ($current_user !== false) {
                    if (MeprUtils::user_meta_exists($current_user->ID, $line->field_key)) {
                        $value = !empty($value);
                    } else { // User may have unchecked the box during signup
                        $value = false;
                    }
                } else {
                    $value = !empty($line->default_value);
                }
            } else {
                $value = stripslashes($line->default_value);
            }
        } elseif ($is_bool) {
            $value = !empty($value);
        }

        $class = isset($classes[$line->field_type]) ? $classes[$line->field_type] : '';

        ob_start();
        switch ($line->field_type) {
            case 'text':
            case 'email':
                ?><input type="<?php echo $line->field_type; ?>" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" class="mepr-form-input <?php echo $class; ?>" value="<?php echo esc_attr($value); ?>" <?php echo $required_attr; ?> /><?php
                break;

            case 'url':
                ?><input type="<?php echo $line->field_type; ?>" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" class="mepr-form-input <?php echo $class; ?>" value="<?php echo esc_attr($value); ?>" title="<?php _e('A URL must be prefixed with a protocol (eg. http://)', 'memberpress'); ?>" <?php echo $required_attr; ?> /><?php
                break;

            case 'textarea':
                ?><textarea name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" class="mepr-form-textarea mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?>><?php echo esc_textarea($value); ?></textarea><?php
                break;

            case 'checkbox':
                $required = $line->required ? '*' : '';
                ?>
        <label for="<?php echo $line->field_key . $unique_suffix; ?>" class="mepr-checkbox-field mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?>>
          <input type="checkbox" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" <?php checked($value); ?> />
                <?php echo MeprAppHelper::wp_kses(sprintf('%1$s%2$s', stripslashes($line->field_name), $required)); ?>
        </label>
                <?php
                break;

            case 'date':
                ?><input type="text" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" value="<?php echo esc_attr(stripslashes($value)); ?>" class="mepr-date-picker mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?> /><?php
                break;

            case 'file':
                if (self::uploaded_file_exists($value)) {
                    if (MeprUtils::is_logged_in_and_an_admin()) {
                        printf('<a href="%s" class="mepr-view-file" target="_blank">%s | </a>', esc_url($value), esc_html__('View', 'memberpress'));
                    }
                    printf('<a href="#0" id="%s" class="mepr-replace-file">%s</a>', $line->field_key, esc_html__('Replace', 'memberpress'));
                }
                ?><input type="file" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" value="" class="mepr-file-uploader mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?> /><?php
                break;

            case 'tel':
                ?><input type="tel" name="<?php echo $line->field_key; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" value="<?php echo esc_attr(stripslashes($value)); ?>" class="mepr-tel-input mepr-form-input <?php echo $class; ?>" <?php echo $required_attr; ?> /><?php
                break;

            case 'dropdown':
            case 'multiselect':
                $is_multi = $line->field_type === 'multiselect';
                $multiselect = $is_multi ? 'multiple="true"' : '';
                $ms_class = $is_multi ? 'mepr-multi-select-field' : '';
                $select_name = $is_multi ? "{$line->field_key}[]" : $line->field_key;

                ?>
        <select name="<?php echo $select_name; ?>" id="<?php echo $line->field_key . $unique_suffix; ?>" class="mepr-form-input mepr-select-field <?php echo $ms_class; ?> <?php echo $class; ?>" <?php echo $multiselect; ?> <?php echo $required_attr; ?>>
                <?php
                foreach ($line->options as $o) {
                    if ($is_multi) {
                        ?><option value="<?php echo $o->option_value; ?>" <?php selected(in_array($o->option_value, $value), true); ?>><?php echo stripslashes($o->option_name); ?></option><?php
                    } else {
                        ?><option value="<?php echo $o->option_value; ?>" <?php selected(esc_attr($o->option_value), esc_attr($value)); ?>><?php echo stripslashes($o->option_name); ?></option><?php
                    }
                }
                ?>
        </select>
                <?php
                break;

            case 'radios':
            case 'checkboxes':
                ?>
        <div id="<?php echo $line->field_key . $unique_suffix; ?>" class="mepr-<?php echo $line->field_type; ?>-field mepr-form-input" <?php echo $required_attr; ?>>
                <?php
                foreach ($line->options as $o) {
                    $field_id = "{$line->field_key}-{$unique_suffix}-{$o->option_value}";
                    if ($line->field_type === 'radios') {
                        ?>
              <span class="mepr-radios-field-row">
                <input type="radio" name="<?php echo $line->field_key; ?>" id="<?php echo $field_id; ?>" value="<?php echo $o->option_value; ?>" class="mepr-form-radios-input <?php echo $class; ?>" <?php checked(esc_attr($o->option_value), esc_attr($value)); ?>>
                <label for="<?php echo $field_id; ?>" class="mepr-form-radios-label"><?php
                    echo MeprAppHelper::wp_kses(stripslashes($o->option_name));
                ?></label>
              </span>
                        <?php
                    } else {
                        if (!is_array($value)) {
                            $value = [];
                        } //Suppress some errors here

                        $value[$o->option_value] = isset($value[$o->option_value]) ? true : false;

                        ?>
              <span class="mepr-checkboxes-field-row">
                <input type="checkbox" name="<?php echo $line->field_key; ?>[<?php echo $o->option_value; ?>]" id="<?php echo $field_id; ?>" class="mepr-form-checkboxes-input <?php echo $class; ?>" <?php checked($value[$o->option_value]); ?>>
                <label for="<?php echo $field_id; ?>" class="mepr-form-checkboxes-label"><?php
                    echo MeprAppHelper::wp_kses(stripslashes($o->option_name));
                ?></label>
              </span>
                        <?php
                    }
                }
                ?>
        </div>
                <?php
                break;
            case 'countries': // for now only geolocate if the user isn't logged in
                echo MeprAppHelper::countries_dropdown($line->field_key, $value, $class, $required_attr, !MeprUtils::is_user_logged_in(), $unique_suffix);
                break;
            case 'states': // for now only geolocate if the user isn't logged in
                echo MeprAppHelper::states_dropdown($line->field_key, $value, $class, $required_attr, !MeprUtils::is_user_logged_in(), $unique_suffix);
                break;
        }

        return MeprHooks::apply_filters('mepr_custom_field_html', ob_get_clean(), $line, $value);
    }

    public static function render_address_fields()
    {
        $mepr_options = MeprOptions::fetch();
        $unique_suffix = '';

        if ($logged_in = MeprUtils::is_user_logged_in()) {
            $user = MeprUtils::get_currentuserinfo();
        }

        // Give devs a chance to re-order these if they so wish
        $address_fields = MeprHooks::apply_filters('mepr_render_address_fields', $mepr_options->address_fields);

        foreach ($address_fields as $line) {
            $required = $line->required ? '*' : '';
            $value = $logged_in ? get_user_meta($user->ID, $line->field_key, true) : '';
            MeprView::render('checkout/signup_row', get_defined_vars());
        }
    }

    public static function render_custom_fields($product = null, $from_page = null, $unique_suffix = '', $show_address = true)
    {
        $mepr_options = MeprOptions::fetch();

        if ($logged_in = MeprUtils::is_user_logged_in()) {
            $user = MeprUtils::get_currentuserinfo();
        }

        $custom_fields = self::get_custom_fields($product);

        // Maybe show the address fields too
        if ($mepr_options->show_address_fields && $show_address) {
            if (is_null($product)) {
                // Check if any memberships require address fields
                if ($user->show_address_fields()) {
                    $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
                }
            } else {
                if (!$product->disable_address_fields) {
                    $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
                }
            }
        }

        // Give devs a chance to re-order these if they so wish
        $custom_fields = MeprHooks::apply_filters('mepr_render_custom_fields', $custom_fields);

        foreach ($custom_fields as $line) {
            if ('signup' == $from_page && !$line->show_on_signup) {
                continue;
            }
            if ('account' == $from_page && isset($line->show_in_account) && !$line->show_in_account) {
                continue;
            }

            $required = ($line->required ? '*' : '');
            $value    = ($logged_in) ? get_user_meta($user->ID, $line->field_key, true) : '';

            MeprView::render('checkout/signup_row', get_defined_vars());
        }
    }

    /**
     * Render custom field values for the design template
     *
     * @param  object  $field The field object.
     * @param  WP_User $user  WordPress User Object.
     * @return void
     */
    public static function render_pro_templates_custom_field_values($field, $user)
    {
        $value = $user ? get_user_meta($user->ID, $field->field_key, true) : '';
        switch ($field->field_type) {
            case 'dropdown':
            case 'radios':
                $options = $field->options;
                foreach ($options as $option) {
                    if ($option->option_value == $value) {
                        $value = $option->option_name;
                    }
                }
                break;
            case 'multiselect':
            case 'checkboxes':
                $options = $field->options;
                $values = [];
                $value = (array) $value;
                foreach ($options as $option) {
                    if (in_array($option->option_value, $value) || array_key_exists($option->option_value, $value)) {
                        $values[] = $option->option_name;
                    }
                }
                $value = join(', ', $values);
                break;
            case 'file':
                $value = !empty($value) ? '<a href="' . esc_url_raw($value) . '" target="_blank">View</a>' : '';
                break;
            default:
                $value = $value;
                break;
        }

        ?>

      <dt>
        <?php echo esc_html(stripslashes($field->field_name)); ?>
      <button data-name="<?php echo esc_attr($field->field_key) ?>" class="btn btn-link mepr-profile-details__button">
        <svg width="15" height="16" viewBox="0 0 15 16" fill="none"
          xmlns="http://www.w3.org/2000/svg">
          <path
            d="M14.1578 2.99018L12.6403 1.47272C11.9097 0.74209 10.7013 0.74209 9.97069 1.47272L1.03453 10.3527L0.360107 14.5397C0.275804 14.9894 0.66922 15.3828 1.11884 15.2985L5.30591 14.624L14.1859 5.68789C14.9165 4.95726 14.9165 3.74891 14.1578 2.99018ZM5.98033 9.67825C6.09274 9.79065 6.26134 9.84685 6.42995 9.84685C6.57046 9.84685 6.73906 9.79065 6.85147 9.67825L10.1955 6.33421L11.0104 7.14915L6.26134 11.9263V10.7461H4.91249V9.39724H3.73224L8.50943 4.64815L9.32437 5.46308L5.98033 8.80711C5.72742 9.06002 5.72742 9.42534 5.98033 9.67825ZM2.6644 13.8091L1.84947 12.9942L2.21478 10.9428L2.7206 10.4089H3.90085V11.7577H5.2497V12.938L4.71578 13.4438L2.6644 13.8091ZM13.3147 4.81675L11.9378 6.19371L9.46487 3.72081L10.8418 2.34385C11.0947 2.09094 11.5163 2.09094 11.7692 2.34385L13.2866 3.86131C13.5676 4.14233 13.5676 4.56384 13.3147 4.81675Z"
            fill="#777777" />
        </svg>
      </button>
      </dt>
      <dd class="mepr-profile-details__content">
        <?php echo wp_kses_post($value); ?>
      </dd>
        <?php
    }

    public static function get_address_fields($user)
    {
        $mepr_options = MeprOptions::fetch();

        // Maybe show the address fields too
        if ($mepr_options->show_address_fields) {
            // Check if any memberships require address fields
            if ($user && $user->show_address_fields()) {
                return $mepr_options->address_fields;
            }
        }

        return [];
    }

    /**
     * Gets custom fields
     *
     * @param  MeprProduct $product MemberPress Product Object
     * @return array
     */
    public static function get_custom_fields($product = null)
    {
        $mepr_options = MeprOptions::fetch();

        if ($logged_in = MeprUtils::is_user_logged_in()) {
            $user = MeprUtils::get_currentuserinfo();
        }

        // Get the right custom fields
        if ($logged_in && is_admin() && MeprUtils::is_mepr_admin()) {
            // An admin is view the user's profile, so let's view all fields
            $custom_fields = $mepr_options->custom_fields;
        } elseif (!is_null($product) && $product instanceof MeprProduct) {
            if ($product->customize_profile_fields) {
                $custom_fields = $product->custom_profile_fields();
            } else {
                $custom_fields = $mepr_options->custom_fields;
            }
        } elseif ($logged_in) {
            $custom_fields = $user->custom_profile_fields();
        } else {
            $custom_fields = [];
        }

        return $custom_fields;
    }

    // Renders the actual custom fields setup by the admin user. The fields rendered here are
    // to allow admins and the users themselves to display and edit values for the custom fields.
    public static function render_editable_custom_fields($user = null)
    {
        $mepr_options = MeprOptions::fetch();

        if (MeprUtils::is_mepr_admin()) { // Let admins see all fields
            $custom_fields = $mepr_options->custom_fields;
        } elseif (!is_null($user)) {
            $custom_fields = $user->custom_profile_fields();
        } else {
            return; // if we aren't an admin and don't have a user we have no business being here
        }

        if ($mepr_options->show_address_fields) {
            $custom_fields = array_merge($custom_fields, $mepr_options->address_fields); // Genius
        }
        $custom_fields = MeprHooks::apply_filters('mepr_render_editable_custom_fields', $custom_fields);

        if (!empty($custom_fields)) {
            foreach ($custom_fields as $line) {
                $value = '';
                if (!is_null($user)) {
                    $value = get_user_meta($user->ID, $line->field_key, true);
                }

                $required = ($line->required) ? '<span class="description">' . __('(required)', 'memberpress') . '</span>' : '';

                ?>
        <tr>
          <th>
            <label for="<?php echo $line->field_key; ?>"><?php printf(__('%1$s:%2$s', 'memberpress'), stripslashes($line->field_name), $required); ?></label>
          </th>
          <td>
                <?php
                echo self::render_custom_field($line, $value, [
                    'text' => 'regular-text',
                    'email' => 'regular-text',
                    'url' => 'regular-text',
                    'textarea' => 'regular-text',
                    'date' => 'regular-text',
                    'states' => 'regular-text',
                ]);
                ?>
          </td>
        </tr>
                <?php
            }
        }
    }

    /**
     * Allowed upload file type
     *
     * @return array
     */
    public static function get_allowed_mime_types()
    {
        $mimes = [
            'jpg|jpeg|jpe'  => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'tiff|tif'  => 'image/tiff',
            'txt|asc|c|cc|h|srt'  => 'text/plain',
            'csv' => 'text/csv',
            'rtx' => 'text/richtext',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'pot|pps|ppt' => 'application/vnd.ms-powerpoint',
            'xla|xls|xlt|xlw' => 'application/vnd.ms-excel',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'pdf' => 'application/pdf',
        ];

        return MeprHooks::apply_filters('mepr_upload_mimes', $mimes);
    }


    /**
     * Returns user file upload directory
     *
     * @param  mixed $dir
     * @return void
     */
    public static function get_upload_dir($dir)
    {
        $dir['path'] = $dir['basedir'] . '/mepr/userfiles';
        $dir['url'] = $dir['baseurl'] . '/mepr/userfiles';
        return $dir;
    }

    /**
     * Checks of uploaded file exists
     *
     * @param mixed $url accepts URL
     *
     * @return boolean
     */
    public static function uploaded_file_exists($url)
    {
        if (empty(trim($url)) || false === wp_http_validate_url($url)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $upload_dir = wp_get_upload_dir();
        $file_path = $upload_dir['basedir'] . '/mepr/userfiles/' . $filename . '.' . $extension;

        return file_exists($file_path) && is_file($file_path);
    }
}
