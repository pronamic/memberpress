<?php

class MeprStripeTaxIntegration
{
    /**
     * Constructor for the MeprStripeTaxIntegration class.
     */
    public function __construct()
    {
        add_action('mepr_tax_rate_options', [$this, 'options']);
        add_action('mepr-process-options', [$this, 'store_options']);
        add_action('admin_notices', [$this, 'new_feature_admin_notice']);
        add_action('admin_notices', [$this, 'deactivated_admin_notice']);
        add_action('wp_ajax_mepr_dismiss_stripe_tax_notice', [$this, 'dismiss_stripe_tax_notice']);
        add_action('wp_ajax_mepr_enable_stripe_tax', [$this, 'enable_stripe_tax']);
        add_action('wp_ajax_mepr_validate_stripe_tax', [$this, 'validate_stripe_tax']);

        $calculate_taxes    = (bool) get_option('mepr_calculate_taxes');
        $tax_stripe_enabled = (bool) get_option('mepr_tax_stripe_enabled');

        if ($calculate_taxes && $tax_stripe_enabled) {
            add_filter('mepr_find_tax_rate', [$this, 'find_rate'], 30, 8);
            add_action('mepr-product-advanced-metabox', [$this, 'display_membership_options'], 15);
            add_action('mepr-membership-save-meta', [$this, 'save_membership_options']);
            add_action('mepr-event-transaction-completed', [$this, 'create_tax_transaction']);
            add_action('mepr-event-transaction-refunded', [$this, 'refund_tax_transaction']);
            add_filter('site_status_tests', [$this, 'add_site_health_test']);
            add_action('admin_notices', [$this, 'admin_notice']);
        }
    }

    /**
     * Render the options for Stripe Tax on the settings page.
     */
    public function options()
    {
        $mepr_options            = MeprOptions::fetch();
        $tax_stripe_enabled      = isset($_POST['mepr_tax_stripe_enabled']) || get_option('mepr_tax_stripe_enabled');
        $selected_payment_method = (string) get_option('mepr_tax_stripe_payment_method', '');
        $stripe_payment_methods  = [];

        foreach ($mepr_options->payment_methods(false) as $payment_method) {
            if ($payment_method instanceof MeprStripeGateway && $payment_method->is_connected()) {
                $stripe_payment_methods[$payment_method->id] = empty($payment_method->label) ? $payment_method->id : $payment_method->label;
            }
        }

        MeprView::render('/admin/taxes/stripe_tax_options', get_defined_vars());
    }

    /**
     * Save the Stripe Tax settings to the database.
     */
    public function store_options()
    {
        update_option('mepr_tax_stripe_enabled', isset($_POST['mepr_tax_stripe_enabled']));
        update_option('mepr_tax_stripe_payment_method', isset($_POST['mepr_tax_stripe_payment_method']) ? sanitize_text_field(wp_unslash($_POST['mepr_tax_stripe_payment_method'])) : '');
        delete_option('mepr_tax_stripe_deactivated');
    }

    /**
     * Determine the tax rate for the customer.
     *
     * @param  object       $tax_rate The current tax rate object.
     * @param  string       $country  The customer's country.
     * @param  string       $state    The customer's state.
     * @param  string       $postcode The customer's postal code.
     * @param  string       $city     The customer's city.
     * @param  string       $street   The customer's street address.
     * @param  object|null  $user     The user object.
     * @param  integer|null $prd_id   The product ID.
     * @return object
     */
    public function find_rate($tax_rate, $country, $state, $postcode, $city, $street, $user = null, $prd_id = null)
    {
        if (empty($country) || !preg_match('/^[A-Z][A-Z]$/', $country)) {
            return $tax_rate;
        }

        $address = [
            'line1'       => $street,
            'line2'       => '',
            'postal_code' => $postcode,
            'city'        => $city,
            'state'       => $this->get_state($state, $country),
            'country'     => $country,
        ];

        $minimum_amount = MeprUtils::get_minimum_amount();
        $amount         = $minimum_amount ? 20 * $minimum_amount : 25;
        $reference      = is_null($prd_id) ? 'L1' : (string) $prd_id;
        $tax_code       = is_null($prd_id) ? '' : $this->get_product_tax_code($prd_id);
        $tax_ids        = $this->get_tax_ids($user);

        $tax_calculation = $this->tax_calculation($address, $amount, $reference, $tax_code, $tax_ids);

        if ($tax_calculation && isset($tax_calculation->tax_breakdown) && is_array($tax_calculation->tax_breakdown)) {
            $percentage  = 0;
            $description = [];
            $tax_classes = [];

            foreach ($tax_calculation->tax_breakdown as $rate) {
                $amount             = isset($rate['amount']) ? $rate['amount'] : 0;
                $percentage_decimal = isset($rate['tax_rate_details']['percentage_decimal']) ? $rate['tax_rate_details']['percentage_decimal'] : '0.0';
                $tax_type           = isset($rate['tax_rate_details']['tax_type']) ? $rate['tax_rate_details']['tax_type'] : '';

                if ($amount > 0 && $percentage_decimal > 0) {
                    $percentage   += $rate['tax_rate_details']['percentage_decimal'];
                    $description[] = $this->get_tax_description($tax_type);
                    $tax_classes[] = empty($tax_type) ? 'standard' : $tax_type;
                }
            }

            $tax_rate->tax_rate  = $percentage;
            $tax_rate->tax_desc  = join(' + ', array_unique($description));
            $tax_rate->tax_class = empty($tax_classes) ? 'standard' : join(',', array_unique($tax_classes));
        }

        return $tax_rate;
    }

    /**
     * Returns the given state if it is a valid ISO 3166-2 subdivision code for the given country, otherwise return an empty string
     *
     * @see    https://en.wikipedia.org/wiki/ISO_3166-2
     * @param  string $state   The state code.
     * @param  string $country The country code.
     * @return string
     */
    private function get_state($state, $country)
    {
        // Countries that have valid ISO 3166-2 states in MEPR_I18N_PATH.
        $iso_3166_2_countries = ['AU', 'BG', 'BR', 'CA', 'DE', 'ES', 'HU', 'ID', 'IN', 'IT', 'JP', 'PE', 'TH', 'TR', 'US', 'ZA'];

        if (preg_match('/^[A-Z][A-Z]$/', $country) && in_array($country, $iso_3166_2_countries, true)) {
            $path = MEPR_I18N_PATH . "/states/$country.php";

            if (file_exists($path)) {
                include $path;

                if (isset($states[$country][$state])) {
                    return $this->convert_state_to_iso_3166_2($state, $country);
                }
            }
        }

        return '';
    }

    /**
     * Convert the given state to ISO 3166-2 format for some selected countries.
     *
     * @param  string $state   The state code.
     * @param  string $country The country code.
     * @return string
     */
    private function convert_state_to_iso_3166_2($state, $country)
    {
        switch ($country) {
            case 'BG':
            case 'TH':
                $state = preg_replace("/^$country-/", '', $state);
                break;
            case 'JP':
            case 'TR':
                $state = preg_replace("/^$country/", '', $state);
                break;
        }

        return $state;
    }

    /**
     * Get the tax code for the given product.
     *
     * @param  integer $product_id The product ID.
     * @return string
     */
    private function get_product_tax_code($product_id)
    {
        $product = new MeprProduct($product_id);

        if ($product->is_tax_exempt()) {
            return 'txcd_00000000';
        }

        $tax_code        = get_post_meta($product_id, '_mepr_tax_stripe_tax_code', true);
        $tax_code_custom = get_post_meta($product_id, '_mepr_tax_stripe_tax_code_custom', true);

        if ($tax_code) {
            return $tax_code == 'custom' ? $tax_code_custom : $tax_code;
        }

        return '';
    }

    /**
     * Get the description for the given tax type.
     *
     * @param  string $tax_type The tax type.
     * @return string
     */
    private function get_tax_description($tax_type)
    {
        switch ($tax_type) {
            case 'sales_tax':
                $description = __('Sales Tax', 'memberpress');
                break;
            case 'vat':
            case 'gst':
            case 'hst':
            case 'pst':
            case 'qst':
            case 'rst':
            case 'jct':
            case 'igst':
                $description = strtoupper($tax_type);
                break;
            case 'lease_tax':
                $description = __('Chicago Lease Tax', 'memberpress');
                break;
            default:
                $description = __('Tax', 'memberpress');
                break;
        }

        return $description;
    }

    /**
     * Create a tax calculation.
     *
     * @param  array         $address   The address array in the format expected by Stripe.
     * @param  integer|float $amount    The subtotal amount.
     * @param  string        $reference Unique reference number for the line-item.
     * @param  string|null   $tax_code  Optional Stripe tax code, or null to use the Stripe account default.
     * @param  array|null    $tax_ids   Optional tax IDs array in the format expected by Stripe.
     * @return stdClass|null
     */
    private function tax_calculation(array $address, $amount, $reference, $tax_code = null, $tax_ids = null)
    {
        $mepr_options = MeprOptions::fetch();
        $pm           = $this->get_payment_method();

        if ($pm instanceof MeprStripeGateway) {
            try {
                $customer_details = [
                    'address'        => $address,
                    'address_source' => 'billing',
                ];

                if ($tax_ids) {
                    $customer_details['tax_ids'] = $tax_ids;
                }

                $line_item = [
                    'amount'       => $pm->to_zero_decimal_amount($amount),
                    'reference'    => $reference,
                    'tax_behavior' => $mepr_options->attr('tax_calc_type'),
                ];

                if ($tax_code) {
                    $line_item['tax_code'] = $tax_code;
                }

                $args = [
                    'currency'         => strtolower($mepr_options->currency_code),
                    'customer_details' => $customer_details,
                    'line_items'       => [$line_item],
                ];

                $transient_key   = sprintf('mepr_stripe_tax_calc_%s', md5(serialize($args)));
                $tax_calculation = get_transient($transient_key);

                if ($tax_calculation instanceof stdClass) {
                    return $tax_calculation;
                }

                $tax_calculation = (object) $pm->send_stripe_request('tax/calculations', $args);

                set_transient($transient_key, $tax_calculation, DAY_IN_SECONDS);

                return $tax_calculation;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Stripe Tax has not been activated on your account') !== false) {
                    update_option('mepr_tax_stripe_enabled', false);
                    update_option('mepr_tax_stripe_payment_method', '');
                    update_option('mepr_tax_stripe_deactivated', true);
                }

                MeprUtils::debug_log($e->getMessage());
            }
        }

        return null;
    }

    /**
     * Get the Stripe payment method to use for Stripe Tax.
     *
     * @return MeprStripeGateway|null
     */
    private function get_payment_method()
    {
        $mepr_options      = MeprOptions::fetch();
        $payment_method_id = (string) get_option('mepr_tax_stripe_payment_method', '');

        if (!empty($payment_method_id)) {
            $payment_method = $mepr_options->payment_method($payment_method_id);

            if ($payment_method instanceof MeprStripeGateway) {
                return $payment_method;
            }
        }

        return null;
    }

    /**
     * Get the ID of the first Stripe payment method that is connected to Stripe.
     *
     * @return string|null
     */
    private function get_first_stripe_payment_method_id()
    {
        $mepr_options = MeprOptions::fetch();

        foreach ($mepr_options->payment_methods(false) as $payment_method) {
            if ($payment_method instanceof MeprStripeGateway && $payment_method->is_connected()) {
                return $payment_method->id;
            }
        }

        return null;
    }

    /**
     * Create a tax transaction.
     *
     * @param MeprEvent $event The event object containing transaction data.
     */
    public function create_tax_transaction($event)
    {
        $pm = $this->get_payment_method();

        if (!$pm instanceof MeprStripeGateway) {
            return;
        }

        $txn = $event->get_data();

        if (!$txn instanceof MeprTransaction || $txn->amount <= 0) {
            return;
        }

        $one      = get_user_meta($txn->user_id, 'mepr-address-one', true);
        $two      = get_user_meta($txn->user_id, 'mepr-address-two', true);
        $city     = get_user_meta($txn->user_id, 'mepr-address-city', true);
        $state    = get_user_meta($txn->user_id, 'mepr-address-state', true);
        $country  = get_user_meta($txn->user_id, 'mepr-address-country', true);
        $postcode = get_user_meta($txn->user_id, 'mepr-address-zip', true);

        if (empty($one) || empty($city) || empty($state) || empty($country) || empty($postcode)) {
            return;
        }

        $address = [
            'line1'       => $one,
            'line2'       => $two,
            'postal_code' => $postcode,
            'city'        => $city,
            'state'       => $this->get_state($state, $country),
            'country'     => $country,
        ];

        $tax_calculation = $this->tax_calculation(
            $address,
            $txn->amount,
            (string) $txn->product_id,
            $this->get_product_tax_code($txn->product_id),
            $this->get_tax_ids($txn->user())
        );

        if ($tax_calculation) {
            try {
                $tax_transaction = (object) $pm->send_stripe_request('tax/transactions/create_from_calculation', [
                    'calculation' => $tax_calculation->id,
                    'reference'   => $txn->trans_num,
                ]);

                $txn->update_meta('stripe_tax_transaction_id', $tax_transaction->id);
            } catch (Exception $e) {
                MeprUtils::debug_log($e->getMessage());
            }
        }
    }

    /**
     * Refund a tax transaction.
     *
     * @param MeprEvent $event The event object containing transaction data.
     */
    public function refund_tax_transaction($event)
    {
        $pm = $this->get_payment_method();

        if (!$pm instanceof MeprStripeGateway) {
            return;
        }

        $txn = $event->get_data();

        if (!$txn instanceof MeprTransaction) {
            return;
        }

        $tax_transaction_id = $txn->get_meta('stripe_tax_transaction_id', true);

        if (empty($tax_transaction_id)) {
            return;
        }

        try {
            $pm->send_stripe_request('tax/transactions/create_reversal', [
                'mode'                 => 'full',
                'original_transaction' => $tax_transaction_id,
                'reference'            => $txn->trans_num . '-refund',
            ]);
        } catch (Exception $e) {
            MeprUtils::debug_log($e->getMessage());
        }
    }

    /**
     * Add options to set a tax code on the Advanced tab of the Membership options.
     *
     * @param MeprProduct $product The product object.
     */
    public function display_membership_options($product)
    {
        $tax_code        = get_post_meta($product->ID, '_mepr_tax_stripe_tax_code', true);
        $tax_code_custom = get_post_meta($product->ID, '_mepr_tax_stripe_tax_code_custom', true);

        MeprView::render('/admin/products/stripe_tax', get_defined_vars());
    }

    /**
     * Save the per-membership tax code options.
     *
     * @param MeprProduct $product The product object.
     */
    public function save_membership_options($product)
    {
        $tax_code        = isset($_POST['_mepr_tax_stripe_tax_code']) ? sanitize_text_field(wp_unslash($_POST['_mepr_tax_stripe_tax_code'])) : '';
        $tax_code_custom = isset($_POST['_mepr_tax_stripe_tax_code_custom']) ? sanitize_text_field(wp_unslash($_POST['_mepr_tax_stripe_tax_code_custom'])) : '';

        update_post_meta($product->ID, '_mepr_tax_stripe_tax_code', $tax_code);
        update_post_meta($product->ID, '_mepr_tax_stripe_tax_code_custom', $tax_code_custom);
    }

    /**
     * Add a site health test for Stripe Tax.
     *
     * @param  array $tests The array of site health tests.
     * @return array
     */
    public function add_site_health_test($tests)
    {
        $tests['direct']['mepr_stripe_tax_test'] = [
            'label' => __('MemberPress - Stripe Tax Payment Method', 'memberpress'),
            'test'  => [$this, 'run_site_health_test'],
        ];

        return $tests;
    }

    /**
     * Run the check to make sure that the Stripe tax payment method is set properly.
     *
     * @return array
     */
    public function run_site_health_test()
    {
        $result = [
            'label'       => __('MemberPress is correctly configured to use Stripe Tax', 'memberpress'),
            'status'      => 'good',
            'badge'       => [
                'label' => __('MemberPress', 'memberpress'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __('The connection between MemberPress and Stripe Tax is correctly configured.', 'memberpress')
            ),
            'test'        => 'mepr_stripe_tax_test',
            'actions'     => '',
        ];

        $pm = $this->get_payment_method();

        if (!$pm instanceof MeprStripeGateway) {
            $result['label']          = __('MemberPress is not correctly configured to use Stripe Tax', 'memberpress');
            $result['status']         = 'critical';
            $result['badge']['color'] = 'orange';

            $result['description'] = sprintf(
                '<p>%s</p>',
                __('The connection between MemberPress and Stripe Tax is not correctly configured. In the MemberPress settings, select a Stripe payment method to use for Stripe Tax.', 'memberpress')
            );

            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                esc_url(admin_url('admin.php?page=memberpress-options#mepr-taxes')),
                __('Configure MemberPress Taxes', 'memberpress')
            );
        }

        return $result;
    }

    /**
     * Display an admin notice if the Stripe Tax connection is not configured correctly.
     */
    public function admin_notice()
    {
        if (!MeprUtils::is_memberpress_admin_page() || !MeprUtils::is_logged_in_and_an_admin() || !empty($_POST['mepr_tax_stripe_payment_method'])) {
            return;
        }

        $pm = $this->get_payment_method();

        if ($pm instanceof MeprStripeGateway) {
            return;
        }

        ?>
    <div class="notice notice-warning mepr-notice-dismiss-daily is-dismissible" data-notice="stripe_tax">
      <p>
        <?php
          printf(
            // Translators: %1$s open link tag, %2$s: close link tag.
              esc_html__('The connection between MemberPress and Stripe Tax is not correctly configured. To fix this issue, %1$sclick here%2$s to visit the MemberPress Tax settings, where you can select a Stripe payment method to use for Stripe Tax.', 'memberpress'),
              '<a href="' . esc_url(admin_url('admin.php?page=memberpress-options#mepr-taxes')) . '">',
              '</a>'
          );
        ?>
      </p>
    </div>
        <?php
    }

    /**
     * Get the tax IDs array for the given user.
     *
     * @param  MeprUser $user The user object.
     * @return array|null
     */
    private function get_tax_ids($user)
    {
        $tax_ids = null;

        if (get_option('mepr_vat_enabled')) {
            $customer_type = MeprVatTaxCtrl::get_customer_type($user);

            if ($customer_type == 'business') {
                $vat_number = MeprVatTaxCtrl::get_vat_number($user);

                if (!empty($vat_number)) {
                    if (substr($vat_number, 0, 2) == 'GB') {
                        $type = 'gb_vat';
                    } else {
                        $type = 'eu_vat';
                    }

                    $tax_ids = [
                        [
                            'type'  => $type,
                            'value' => $vat_number,
                        ],
                    ];
                }
            }
        }

        return $tax_ids;
    }

    /**
     * Display a new feature admin notice for Stripe Tax.
     */
    public function new_feature_admin_notice()
    {
        if (
            !MeprUtils::is_logged_in_and_an_admin() ||
            get_option('mepr_stripe_tax_enabled') ||
            get_option('mepr_stripe_tax_notice_dismissed') ||
            get_transient('mepr_dismiss_notice_enable_stripe_tax')
        ) {
            return;
        }

        if (isset($_GET['page']) && $_GET['page'] == 'memberpress-options') {
            return;
        }

        $payment_method_id = $this->get_first_stripe_payment_method_id();

        if (empty($payment_method_id)) {
            return;
        }
        ?>
    <div class="notice notice-info is-dismissible mepr-stripe-tax-notice mepr-notice-dismiss-weekly" data-notice="enable_stripe_tax">
      <div>
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/info-icon.jpg'); ?>" alt="" style="width: 30px; height: 30px;">
        <div>
          <p class="mepr-stripe-tax-notice-heading"><?php esc_html_e('MemberPress makes taxes fast and easy! Enable automatic tax rate calculation with the Stripe Tax API.', 'memberpress'); ?></p>
          <p class="mepr-stripe-tax-fine-print">
            <?php
              printf(
                // Translators: %1$s: open link tag, %2$s: close link tag.
                  esc_html__('Stripe Tax API pricing starts at 0.50 USD for each transaction calculated within your registered tax location (includes 10 calculation API calls per transaction; 0.05 USD per additional call). To learn more visit the %1$sStripe Tax pricing page%2$s.', 'memberpress'),
                  '<a href="https://stripe.com/tax#pricing" target="_blank">',
                  '</a>'
              );
            ?>
          </p>
          <p class="mepr-stripe-tax-notice-button-row">
            <button type="button" id="mepr-enable-stripe-tax" class="button button-primary" data-gateway-id="<?php echo esc_attr($payment_method_id); ?>"><?php esc_html_e('Activate Automatic Tax Calculations with Stripe', 'memberpress'); ?></button>
            <a href="#" id="mepr-enable-stripe-tax-no"><?php esc_html_e('I don\'t need to collect taxes', 'memberpress'); ?></a>
          </p>
        </div>
      </div>
    </div>
    <div id="mepr-stripe-tax-no-popup" class="mepr-shared-popup mfp-hide">
      <p class="mepr-text-align-center"><?php esc_html_e('Great, if this ever changes you can easily enable this in Settings &rarr; Taxes.', 'memberpress'); ?></p>
    </div>
    <div id="mepr-stripe-tax-enabled-popup" class="mepr-shared-popup mfp-hide">
      <h2 class="mepr-text-align-center"><?php esc_html_e('Stripe Tax is now enabled', 'memberpress'); ?></h2>
      <p class="mepr-text-align-center">
          <?php
            printf(
            // Translators: %1$s: open link tag, %2$s: close link tag.
                esc_html__('In the Stripe dashboard, please ensure that a %1$sRegistration is added%2$s for each location where tax should be collected.', 'memberpress'),
                '<a href="https://dashboard.stripe.com/tax/registrations" target="_blank">',
                '</a>'
            );
            ?>
      </p>
    </div>
    <div id="mepr-stripe-tax-inactive-popup" class="mepr-shared-popup mfp-hide">
      <h2 class="mepr-text-align-center"><?php esc_html_e('Stripe Tax could not be enabled', 'memberpress'); ?></h2>
      <p class="mepr-text-align-center">
        <?php
          printf(
            // Translators: %1$s: open link tag, %2$s: close link tag, %3$s: open link tag, %4$s: close link tag.
              esc_html__('In the Stripe dashboard, please ensure that %1$sStripe Tax is enabled%2$s and that a %3$sRegistration is added%4$s for each location where tax should be collected.', 'memberpress'),
              '<a href="https://dashboard.stripe.com/tax" target="_blank">',
              '</a>',
              '<a href="https://dashboard.stripe.com/tax/registrations" target="_blank">',
              '</a>'
          );
        ?>
      </p>
      <p class="mepr-text-align-center">
        <?php esc_html_e('If you have more than one Stripe payment method, you can configure which payment method to use for Stripe Tax at MemberPress &rarr; Settings &rarr; Taxes.', 'memberpress'); ?>
      </p>
    </div>
        <?php
    }

    /**
     * Display an admin notice if Stripe Tax was deactivated.
     */
    public function deactivated_admin_notice()
    {
        if (
            !MeprUtils::is_memberpress_admin_page() ||
            !MeprUtils::is_logged_in_and_an_admin() ||
            !get_option('mepr_tax_stripe_deactivated') ||
            get_user_meta(get_current_user_id(), 'mepr_dismiss_notice_deactivated_stripe_tax', true) ||
            (isset($_GET['page']) && $_GET['page'] == 'memberpress-options')
        ) {
            return;
        }
        ?>
    <div class="notice notice-warning is-dismissible mepr-notice-dismiss-permanently" data-notice="deactivated_stripe_tax">
      <p>
        <?php
          printf(
            // Translators: %1$s open link tag, %2$s: close link tag, %3$s open link tag, %4$s: close link tag.
              esc_html__('Stripe Tax was deactivated because it is not enabled in the Stripe dashboard. Please ensure that %1$sStripe Tax is enabled%2$s at Stripe, then go to %3$sMemberPress &rarr; Settings &rarr; Taxes%4$s to reactivate Stripe Tax.', 'memberpress'),
              '<a href="https://dashboard.stripe.com/tax" target="_blank">',
              '</a>',
              sprintf('<a href="%s">', esc_url(admin_url('admin.php?page=memberpress-options#mepr-taxes'))),
              '</a>'
          );
        ?>
      </p>
    </div>
        <?php
    }

    /**
     * Dismiss the Stripe Tax notice.
     */
    public function dismiss_stripe_tax_notice()
    {
        if (
            !MeprUtils::is_logged_in_and_an_admin() ||
            !check_ajax_referer('mepr_dismiss_notice', false, false)
        ) {
            wp_send_json_error();
        }

        update_option('mepr_stripe_tax_notice_dismissed', true);

        wp_send_json_success();
    }

    /**
     * Enable Stripe Tax.
     */
    public function enable_stripe_tax()
    {
        if (
            !MeprUtils::is_logged_in_and_an_admin() ||
            !check_ajax_referer('mepr_enable_stripe_tax', false, false)
        ) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $gateway_id = isset($_POST['gateway_id']) ? sanitize_text_field(wp_unslash($_POST['gateway_id'])) : '';

        if (empty($gateway_id)) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        $mepr_options = MeprOptions::fetch();

        $pm = $mepr_options->payment_method($gateway_id);

        if (!$pm instanceof MeprStripeGateway || !$pm->is_connected()) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        try {
            $tax_settings = (object) $pm->send_stripe_request('tax/settings', [], 'get');

            if ($tax_settings->status != 'active') {
                wp_send_json_error(false);
            }

            update_option('mepr_calculate_taxes', true);
            update_option('mepr_tax_stripe_enabled', true);
            update_option('mepr_tax_calc_location', 'customer');
            update_option('mepr_tax_default_address', 'none');
            update_option('mepr_tax_stripe_payment_method', $pm->id);
            update_option('mepr_stripe_tax_notice_dismissed', true);
            update_option('mepr_tax_avalara_enabled', false);
            update_option('mepr_tax_quaderno_enabled', false);
            update_option('mepr_tax_taxjar_enabled', false);
            delete_option('mepr_tax_stripe_deactivated');

            wp_send_json_success();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Validate the Stripe Tax settings.
     */
    public function validate_stripe_tax()
    {
        if (
            !MeprUtils::is_logged_in_and_an_admin() ||
            !check_ajax_referer('mepr_validate_stripe_tax', false, false)
        ) {
            wp_send_json_error(__('Security check failed.', 'memberpress'));
        }

        $gateway_id = isset($_POST['gateway_id']) ? sanitize_text_field(wp_unslash($_POST['gateway_id'])) : '';

        if (empty($gateway_id)) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        $mepr_options = MeprOptions::fetch();

        $pm = $mepr_options->payment_method($gateway_id);

        if (!$pm instanceof MeprStripeGateway || !$pm->is_connected()) {
            wp_send_json_error(__('Bad request.', 'memberpress'));
        }

        try {
            $tax_settings = (object) $pm->send_stripe_request('tax/settings', [], 'get');

            wp_send_json_success($tax_settings->status == 'active');
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

new MeprStripeTaxIntegration();
