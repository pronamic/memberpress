<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprVatTaxCtrl extends MeprBaseCtrl
{
    /**
     * Load hooks for VAT tax functionality.
     *
     * @return void
     */
    public function load_hooks()
    {
        // Filter for MP Options page (field to enable VAT and collect VAT country & VAT ID).
        add_action('mepr_tax_rate_options', [$this,'options']);
        add_action('mepr-process-options', [$this,'store_options']);

        $calculate_taxes    = get_option('mepr_calculate_taxes');
        $vat_enabled        = get_option('mepr_vat_enabled');
        $tax_stripe_enabled = (bool) get_option('mepr_tax_stripe_enabled');

        if ($calculate_taxes && $vat_enabled) {
            // Enqueue scripts.
            add_filter('mepr-signup-scripts', [$this,'product_scripts'], 10, 3);

            // Filter for signup / payment page.
            add_action('mepr-checkout-before-coupon-field', [$this,'signup']);

            // Validate the VAT number.
            add_filter('mepr-validate-signup', [$this,'validate_signup']);

            // STORE THE VAT FIELDS WITH THE USER RECORD.
            add_action('mepr-process-signup', [$this,'process_signup'], 10, 4);

            if (!$tax_stripe_enabled) {
                // Filter for tax calculation.
                add_filter('mepr_find_tax_rate', [$this, 'find_rate'], 20, 8);
            }

            // Follow use merchant address from here on out?
            // add_filter('mepr-tax-rate-use-customer-address', array($this,'use_customer_address'), 10, 2);.
            add_action('mepr_extra_profile_fields', [$this,'extra_profile_fields']);
            add_action('user_profile_update_errors', [$this, 'validate_extra_profile_fields'], 10, 3);
            add_action('edit_user_profile_update', [$this, 'save_extra_profile_fields']);
            add_action('personal_options_update', [$this, 'save_extra_profile_fields']);

            // TODO: VAT collected by month available as CSV download.
            add_action('mepr-report-footer', [$this,'vat_csv_buttons']);
            add_action('wp_ajax_mepr_vat_country_report', [$this,'country_vat_csv']);
            add_filter('mepr_stripe_product_base_price', [$this, 'maybe_apply_vat_reversal'], 10, 3);
        }
    }

    /**
     * Enqueue product scripts for VAT calculation.
     *
     * @param array   $prereqs         Prerequisite scripts.
     * @param boolean $is_product_page Whether it is a product page.
     * @param boolean $is_account_page Whether it is an account page.
     *
     * @return array Updated prerequisite scripts.
     */
    public function product_scripts($prereqs, $is_product_page, $is_account_page)
    {
        $mepr_options = MeprOptions::fetch();

        if ($this->vat_calc_possible()) {
            $vat_enabled = get_option('mepr_vat_enabled');

            if (($mepr_options->global_styles || $is_product_page) && $vat_enabled) {
                $countries   = $this->get_vat_countries();
                $vat_country = strtoupper(get_option('mepr_vat_country'));
                wp_enqueue_script('mpvat', MEPR_JS_URL . '/mpvat.js', ['jquery', 'mp-i18n']);
                wp_localize_script('mpvat', 'MpVat', [
                    'rates'       => $countries,
                    'countries'   => array_keys($countries),
                    'vat_country' => $vat_country,
                ]);
                $prereqs[] = 'mpvat';
            }
        }

        return $prereqs;
    }

    /**
     * Render VAT options in the admin panel.
     *
     * @return void
     */
    public function options()
    {
        $vat_enabled                        = get_option('mepr_vat_enabled');
        $vat_country                        = get_option('mepr_vat_country');
        $vat_tax_businesses                 = get_option('mepr_vat_tax_businesses');
        $vat_disable_vies_service           = get_option('mepr_vat_disable_vies_service');
        $charge_business_customer_net_price = get_option('mepr_charge_business_customer_net_price');
        $show_negative_tax_on_invoice       = get_option('mepr_show_negative_tax_on_invoice');

        $countries = $this->get_vat_countries();

        MeprView::render('/admin/taxes/vat_options', get_defined_vars());
    }

    /**
     * Validate VAT information during signup.
     *
     * @param array $errors Existing errors.
     *
     * @return array Updated errors.
     */
    public function validate_signup($errors)
    {
        $prd = new MeprProduct($_POST['mepr_product_id']);

        if ($this->vat_calc_possible() && ($prd->price > 0.00 || ($prd->price <= 0.00 && !$prd->disable_address_fields))) {
            $country            = $_POST['mepr-address-country'];
            $customer_type      = self::get_customer_type();
            $vat_number         = self::get_vat_number();
            $vat_tax_businesses = get_option('mepr_vat_tax_businesses', false);

            // If customer is a business, then a value must be entered for the vat number
            // Unless tax all eu business is enabled.
            if ($customer_type == 'business' && empty($vat_number) && !$vat_tax_businesses) {
                 $errors['mepr_vat_number'] = __('VAT number is required', 'memberpress');
            } elseif (
                $customer_type == 'business' &&
                !empty($vat_number) &&
                !$this->vat_number_is_valid($vat_number, $country)
            ) {
                $errors['mepr_vat_number'] = __('Your VAT number is invalid', 'memberpress');
            }
        }

        return $errors;
    }

    /**
     * Store VAT options from the admin panel.
     *
     * @return void
     */
    public function store_options()
    {
        $vat_enabled                        = isset($_POST['mepr_vat_enabled']);
        $vat_country                        = isset($_POST['mepr_vat_country']) ? sanitize_text_field($_POST['mepr_vat_country']) : '';
        $vat_tax_businesses                 = isset($_POST['mepr_vat_tax_businesses']);
        $vat_disable_vies_service           = isset($_POST['mepr_vat_disable_vies_service']);
        $charge_business_customer_net_price = isset($_POST['mepr_charge_business_customer_net_price']);
        $show_negative_tax_on_invoice       = isset($_POST['mepr_show_negative_tax_on_invoice']);

        update_option('mepr_vat_enabled', $vat_enabled);
        update_option('mepr_vat_country', $vat_country);
        update_option('mepr_vat_tax_businesses', $vat_tax_businesses);
        update_option('mepr_vat_disable_vies_service', $vat_disable_vies_service);
        update_option('mepr_charge_business_customer_net_price', $charge_business_customer_net_price);
        update_option('mepr_show_negative_tax_on_invoice', $show_negative_tax_on_invoice);
    }

    /**
     * Render VAT fields during signup.
     *
     * @param integer $prd_id The product ID.
     *
     * @return void
     */
    public function signup($prd_id)
    {
        $prd = new MeprProduct($prd_id);

        if ($this->vat_calc_possible() && $prd->price > 0.00 && !isset($_GET['ca'])) {
            $vat_customer_type = self::get_customer_type();
            $vat_number        = self::get_vat_number();

            static $unique_suffix = 0;
            $unique_suffix++;

            MeprView::render('/taxes/vat_signup', get_defined_vars());
        }
    }

    /**
     * Process VAT information during signup.
     *
     * @param float    $amt The amount.
     * @param MeprUser $usr The user object.
     * @param integer  $pid The product ID.
     * @param integer  $tid The transaction ID.
     *
     * @return void
     */
    public function process_signup($amt, $usr, $pid, $tid)
    {
        if ($this->vat_calc_possible()) {
            if (isset($_POST['mepr_vat_customer_type'])) {
                update_user_meta($usr->ID, 'mepr_vat_customer_type', self::get_customer_type());
            }

            if (isset($_POST['mepr_vat_number'])) {
                update_user_meta($usr->ID, 'mepr_vat_number', self::get_vat_number());
            }
        }
    }

    /**
     * VAT overrides anything that could possibly be set by the standard tax rate db tables.
     *
     * @param MeprTaxRate   $tax_rate The tax rate object.
     * @param string        $country  The country code.
     * @param string        $state    The state code.
     * @param string        $postcode The postal code.
     * @param string        $city     The city name.
     * @param string        $street   The street address.
     * @param MeprUser|null $usr      The user object.
     * @param integer|null  $prd_id   The product ID.
     *
     * @return MeprTaxRate The updated tax rate object.
     */
    public function find_rate($tax_rate, $country, $state, $postcode, $city, $street, $usr = null, $prd_id = null)
    {
        $mepr_options       = MeprOptions::fetch();
        $countries          = $this->get_vat_countries();
        $customer_type      = self::get_customer_type($usr);
        $vat_number         = self::get_vat_number($usr);
        $vat_tax_businesses = get_option('mepr_vat_tax_businesses', false);
        $usr_country        = null;
        $vat_country        = get_option('mepr_vat_country');
        $vies_country       = $country;

        if ($customer_type === 'business') {
            $tax_rate->customer_type = 'business';
        }

        if (!empty($usr) && $usr instanceof MeprUser && $usr->address_is_set()) {
            $usr_country              = $usr->address('country');
            $use_address_from_request = $usr->use_address_from_request();

            // When updating pricing terms string with AJAX,user country should be the POST country.
            if ($use_address_from_request) {
                $usr_country = isset($_POST['mepr-address-country']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-country'])) : '';
            }

            if ($customer_type == 'business') {
                $vies_country = $usr_country;
            }

            // If the user's address is set and their country is outside the UK or EU then bail.
            if ($vat_country != $usr_country && !array_key_exists($usr_country, $countries)) {
                return $tax_rate;
            }

            // Canary Islands (Spain) has different VAT Rules.
            if ($usr_country == 'ES') {
                $canary_island_zips = ['35','38','51','52'];
                $usr_zip            = (string)trim($usr->address('zip'));

                if ($use_address_from_request) {
                    $usr_zip = isset($_POST['mepr-address-zip']) ? sanitize_text_field(wp_unslash($_POST['mepr-address-zip'])) : '';
                }

                foreach ($canary_island_zips as $zip_prefix) {
                    if (strpos($usr_zip, $zip_prefix) === 0) {
                        return $tax_rate;
                    }
                }
            }

            if (
                $mepr_options->attr('tax_calc_location') == 'customer'
                && $vat_country === 'GB'
                && $usr_country !== 'GB'
                && strtotime('2020-12-31 23:59:59') < time()
            ) {
                return new MeprTaxRate();
            }
        }

        // Make sure this is an EU country.
        if (array_key_exists($country, $countries)) {
            $is_valid_vat   = $this->vat_number_is_valid($vat_number, $vies_country);
            $is_vat_removal = $this->is_valid_vat_removal_rule($is_valid_vat, $customer_type, $vat_country, $usr_country);

            // Conditions for calculating VAT or not
            // If we're taxing all businesses then vat tax validation doesn't matter.
            if (
                $customer_type == 'consumer' ||
                ( $customer_type == 'business' &&
                ( $vat_country == $usr_country ||
                $vat_tax_businesses ||
                !$is_valid_vat )
                ) || $is_vat_removal
            ) {
                $tax_rate = $this->get_rate($tax_rate, $country, $prd_id);

                if ($is_vat_removal) {
                    $tax_rate->reversal = true;
                }
            }
        }

        return $tax_rate;
    }

    /**
     * Get the VAT rate for a country and product.
     *
     * @param MeprTaxRate  $tax_rate The tax rate object.
     * @param string       $country  The country code.
     * @param integer|null $prd_id   The product ID.
     *
     * @return MeprTaxRate The updated tax rate object.
     */
    private function get_rate(MeprTaxRate $tax_rate, $country, $prd_id = null)
    {
        $countries = $this->get_vat_countries();

        if (empty($prd_id)) {
            $prd_id = !empty($_POST['mepr_product_id']) ? (int) $_POST['mepr_product_id'] : 0;
            $prd_id = !empty($_POST['prd_id']) ? (int) $_POST['prd_id'] : $prd_id;
        }

        $prd = new MeprProduct($prd_id);

        $tax_rate->tax_rate = $countries[$country]['rate'];

        if ($prd->ID && $prd->tax_class == 'reduced' && isset($countries[$country]['reduced_rate'])) {
            $tax_rate->tax_rate = $countries[$country]['reduced_rate'];
        }
        $tax_rate->tax_desc  = sprintf(
            // Translators: %s: country name.
            __('VAT (%s)', 'memberpress'),
            $countries[$country]['name']
        );
        $tax_rate->tax_class = 'vat';

        return MeprHooks::apply_filters('mepr_vat_tax_rate', $tax_rate, $country, $prd_id);
    }

    /**
     * Validate a VAT number for a given country.
     *
     * @param string $vat_number The VAT number.
     * @param string $country    The country code.
     *
     * @return boolean True if valid, false otherwise.
     */
    public function vat_number_is_valid($vat_number, $country)
    {
        $countries  = $this->get_vat_countries();
        $vat_number = trim(preg_replace('/[-.●]/', '', $vat_number));

        return (
        isset($countries[$country]) &&
        preg_match("/^{$countries[$country]['fmt']}$/i", $vat_number) &&
        $this->vies_says_vat_is_valid($vat_number, $country)
        );
    }

    /**
     * Validate a VAT number using the VIES service.
     * https://ec.europa.eu/taxation_customs/vies/#/technical-information
     *
     * @param string $vat_number The VAT number.
     * @param string $country    The country code.
     *
     * @return boolean True if valid, false otherwise.
     */
    private function vies_says_vat_is_valid($vat_number, $country)
    {
        if (get_option('mepr_vat_disable_vies_service')) {
            return true;
        }

        // If the vat number is prefixed by the country code, cut it out.
        $vat_number = preg_replace('/^' . preg_quote($country) . '/i', '', $vat_number);

        static $result = [];

        if (isset($result[$vat_number])) {
            return $result[$vat_number];
        }

        $response = wp_remote_post('https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode(
                [
                    'countryCode' => $country,
                    'vatNumber'   => $vat_number,
                ]
            ),
        ]);

        // Default to true (so we can proceed if the VIES service is down etc.).
        $result[$vat_number] = MeprHooks::apply_filters('mepr_vat_vies_default_result', true, $vat_number, $country);

        if (wp_remote_retrieve_response_code($response) == 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['valid']) && is_bool($body['valid'])) {
                $result[$vat_number] = $body['valid'];
            }
        }

        $result[$vat_number] = MeprHooks::apply_filters(
            'mepr_vat_vies_result',
            $result[$vat_number],
            $vat_number,
            $country
        );

        return $result[$vat_number];
    }

    /**
     * Get the customer type for VAT purposes.
     *
     * @param MeprUser|null $usr The user object.
     *
     * @return string The customer type.
     */
    public static function get_customer_type($usr = null)
    {
        if (array_key_exists('mepr_vat_customer_type', $_POST)) {
            return sanitize_text_field($_POST['mepr_vat_customer_type']);
        }

        // If the vat number is empty then grab the current user info.
        if (MeprUtils::is_user_logged_in() && empty($usr)) {
            $usr = MeprUtils::get_currentuserinfo();
        }

        if (!empty($usr)) {
            $ctype = get_user_meta($usr->ID, 'mepr_vat_customer_type', true);
            if (!empty($ctype)) {
                return $ctype;
            }
        }

        // Default customer type right here people.
        return 'consumer';
    }

    /**
     * Get the VAT number for a user.
     *
     * @param MeprUser|null $usr The user object.
     *
     * @return string The VAT number.
     */
    public static function get_vat_number($usr = null)
    {
        if (array_key_exists('mepr_vat_number', $_POST)) {
            return sanitize_text_field($_POST['mepr_vat_number']);
        }

        // If the vat number is empty then grab the current user info.
        if (MeprUtils::is_user_logged_in() && empty($usr)) {
            $usr = MeprUtils::get_currentuserinfo();
        }

        if (!empty($usr)) {
            $vnum = get_user_meta($usr->ID, 'mepr_vat_number', true);
            if (!empty($vnum)) {
                return $vnum;
            }
        }

        return '';
    }

    /**
     * Render buttons for VAT CSV export.
     *
     * @param string $type The type of export (e.g., 'monthly').
     *
     * @return void
     */
    public function vat_csv_buttons($type = 'monthly')
    {
        // Download transactions with VAT country, business/consumer, and VAT number.
        $totals_url = MeprUtils::admin_url(
            'admin-ajax.php',
            ['export_report','mepr_reports_nonce'],
            [
                'action' => 'mepr_export_report',
                'export' => $type,
                'q'      => [
                    'var' => 'tax_class',
                    'val' => 'vat',
                ],
            ],
            true
        );

        $countries_url = MeprUtils::admin_url(
            'admin-ajax.php',
            ['export_vat_countries', 'mepr_vattaxes_nonce'],
            [
                'action' => 'mepr_vat_country_report',
                'export' => $type,
            ],
            true
        );

        ?>
    <a class="button" href="<?php echo $totals_url; ?>"><?php _e('Export VAT Totals', 'memberpress'); ?></a>
    <a class="button" href="<?php echo $countries_url; ?>"><?php _e('Export VAT by Country', 'memberpress'); ?></a>
        <?php
    }

    /**
     * Generate a CSV of VAT data by country.
     *
     * @return void
     */
    public function country_vat_csv()
    {
        check_ajax_referer('export_vat_countries', 'mepr_vattaxes_nonce');

        $type = (isset($_REQUEST['export']) && !empty($_REQUEST['export'])) ? $_REQUEST['export'] : 'monthly';

        if ($type == 'monthly') {
            $month = (isset($_REQUEST['month']) && !empty($_REQUEST['month'])) ? $_REQUEST['month'] : date('n');
        } else {
            $month = false;
        }

        $year    = (isset($_REQUEST['year']) && !empty($_REQUEST['year'])) ? $_REQUEST['year'] : date('Y');
        $product = (isset($_REQUEST['product']) && $_REQUEST['product'] != 'all') ? $_REQUEST['product'] : 'all';

        $data = $this->get_country_vat_data($year, $month, $product);

        $countries = $this->get_vat_countries();
        for ($i = 0; $i < count($data); $i++) {
            if (isset($countries[$data[$i][__('Country Code', 'memberpress')]]['name'])) {
                $data[$i][__('Country', 'memberpress')] = $countries[$data[$i][__('Country Code', 'memberpress')]]['name'];
            } else {
                $data[$i][__('Country', 'memberpress')] = $data[$i][__('Country Code', 'memberpress')];
            }
        }

        $filename = sprintf(
            'memberpress%s-vat-by-country%s%s-for%s',
            "-{$type}",
            ($month ? "-{$month}" : ''),
            "-{$year}",
            "-{$product}"
        );

        MeprUtils::render_csv($data, $filename);
        exit();
    }

    /**
     * Get VAT tax data collected by country per month.
     *
     * @param integer|boolean $year    The year to get data for.
     * @param integer|boolean $month   The month to get data for.
     * @param string|integer  $product The product ID or 'all' for all products.
     *
     * @return array Array of VAT data by country.
     */
    public function get_country_vat_data($year = false, $month = false, $product = 'all')
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $andmonth   = $month ? $wpdb->prepare(' AND MONTH(created_at) = %d', $month) : '';
        $andyear    = $year ? $wpdb->prepare(' AND YEAR(created_at) = %d', $year) : '';
        $andproduct = (!isset($product) || empty($product) || $product == 'all') ? '' : " AND product_id = {$product}";

        $q = '
      SELECT um.meta_value AS "' . __('Country Code', 'memberpress') . '",
             SUM(tr.tax_amount) AS ' . __('Total', 'memberpress') . "
        FROM {$mepr_db->transactions} AS tr
       INNER JOIN {$wpdb->usermeta} AS um
          ON um.user_id=tr.user_id
         AND um.meta_key='mepr-address-country'
       WHERE tr.tax_class='vat'
         AND tr.txn_type = '" . MeprTransaction::$payment_str . "'
         AND tr.status = '" . MeprTransaction::$complete_str . "'
       {$andmonth}
       {$andyear}
       {$andproduct}
       GROUP BY um.meta_value
    ";

        $res = $wpdb->get_results($q, ARRAY_A);

        return $res;
    }

    /**
     * Determine whether to use customer address for VAT tax calculation.
     * VAT tax always uses the customer address now.
     *
     * @param boolean  $use Current value of whether to use customer address.
     * @param MeprUser $usr The user object.
     *
     * @return boolean True if customer address should be used, false otherwise.
     */
    public function use_customer_address($use, $usr)
    {
        $countries        = $this->get_vat_countries();
        $customer_country = $usr->address('country');

        if (isset($countries[$customer_country])) {
            return true;
        } else {
            return $use;
        }
    }

    /**
     * Render extra profile fields for VAT.
     *
     * @param MeprUser $usr The user object.
     *
     * @return void
     */
    public function extra_profile_fields($usr)
    {
        $tax_rate = $usr->tax_rate();

        // We're showing these regardless.
        $ctype = self::get_customer_type($usr);
        $vnum  = self::get_vat_number($usr);
        MeprView::render('/admin/taxes/vat_profile_fields', get_defined_vars());
    }

    /**
     * Validate extra profile fields for VAT.
     *
     * @param WP_Error $errors The errors object.
     * @param boolean  $update Whether this is an update.
     * @param WP_User  $user   The user object.
     */
    public function validate_extra_profile_fields($errors, $update, $user)
    {
        if (MeprUtils::is_logged_in_and_an_admin()) {
            if ($update === false) {
                return $errors;
            }

            $ctype   = self::get_customer_type($user);
            $vnum    = self::get_vat_number($user);
            $country = get_user_meta($user->ID, 'mepr-address-country', true);

            if ($ctype == 'business' && !empty($vnum) && !empty($country) && !$this->vat_number_is_valid($vnum, $country)) {
                $errors->add('mepr_vat_number', __('VAT number is invalid', 'memberpress'));
            }
        }
    }

    /**
     * Save extra profile fields for VAT.
     *
     * @param integer $user_id The user ID.
     *
     * @return void
     */
    public function save_extra_profile_fields($user_id)
    {
        if (MeprUtils::is_logged_in_and_an_admin()) {
            if (isset($_POST['mepr_vat_number'])) {
                update_user_meta($user_id, 'mepr_vat_number', sanitize_text_field($_POST['mepr_vat_number']));
            }

            if (isset($_POST['mepr_vat_customer_type'])) {
                update_user_meta($user_id, 'mepr_vat_customer_type', sanitize_text_field($_POST['mepr_vat_customer_type']));
            }
        }
    }

    /**
     * Get the list of VAT countries.
     *
     * @return array The list of VAT countries.
     */
    private function get_vat_countries()
    {
        $mepr_options = MeprOptions::fetch();

        $moss_country = get_option('mepr_vat_country');
        $countries    = require(MEPR_DATA_PATH . '/taxes/vat_countries.php');

        return $countries;
    }

    /**
     * Determine if VAT calculation is possible.
     *
     * @return boolean True if possible, false otherwise.
     */
    private function vat_calc_possible()
    {
        $mepr_options = MeprOptions::fetch();
        global $post;

        // Remove VAT Inputs on Tax-Exempt Memberships.
        $prd = MeprProduct::is_product_page($post);
        if (false !== $prd) {
            if ($prd->ID && $prd->is_tax_exempt()) {
                return false;
            }
        }

        return (!MeprUtils::is_user_logged_in() ||
            ($mepr_options->show_fields_logged_in_purchases &&
             $mepr_options->show_address_fields &&
             $mepr_options->require_address_fields));
    }

    /**
     * Check if a VAT removal rule is valid.
     *
     * @param boolean $is_valid_vat  Whether the VAT is valid.
     * @param string  $customer_type The customer type.
     * @param string  $vat_country   The VAT country.
     * @param string  $usr_country   The user country.
     *
     * @return boolean True if valid, false otherwise.
     */
    private function is_valid_vat_removal_rule($is_valid_vat, $customer_type, $vat_country, $usr_country)
    {
        if (!$is_valid_vat) {
            return false;
        }

        if ($customer_type !== 'business') {
            return false;
        }

        if (!MeprTransactionsHelper::is_charging_business_net_price()) {
            return false;
        }

        $vat_countries = $this->get_vat_countries();

        if (!array_key_exists($vat_country, $vat_countries)) {
            return false;
        }

        if (empty($usr_country) || !array_key_exists($usr_country, $vat_countries)) {
            return false;
        }

        // VAT is charged if Customer is a business with a valid VAT number, but the merchant and customer are both from the same country.
        if ($usr_country == $vat_country) {
            return false;
        }

        return true;
    }

    /**
     * Remove the VAT portion from the given price, if charging net price with a valid VAT number.
     *
     * @param string      $price The price.
     * @param MeprProduct $prd   The product object.
     * @param MeprUser    $usr   The user object.
     *
     * @return string The updated price.
     */
    public function maybe_apply_vat_reversal($price, $prd, $usr)
    {
        $mepr_options = MeprOptions::fetch();

        if (
            get_option('mepr_calculate_taxes') &&
            $mepr_options->attr('tax_calc_type') == 'inclusive' &&
            get_option('mepr_vat_enabled') &&
            MeprTransactionsHelper::is_charging_business_net_price()
        ) {
            $tax_rate = $usr->tax_rate($prd->ID);

            if ($tax_rate->customer_type === 'business' && $tax_rate->reversal) {
                $subtotal   = $usr->calculate_subtotal($price, null, 2, $prd);
                $tax_amount = MeprUtils::format_float(($subtotal * ($tax_rate->tax_rate / 100.00)));
                $price      = MeprUtils::format_float((float) $price - (float) $tax_amount);
            }
        }

        return $price;
    }
}
