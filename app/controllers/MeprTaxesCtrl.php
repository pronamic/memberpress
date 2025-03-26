<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprTaxesCtrl extends MeprBaseCtrl
{
    /**
     * Loads the hooks.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('mepr_display_options_tabs', [$this,'display_option_tab']);
        add_action('mepr_display_options', [$this,'display_option_fields']);
        add_action('mepr-process-options', [$this,'store_option_fields']);
        add_action('wp_ajax_mepr_export_tax_rates', [$this,'export_tax_rates']);
        add_action('wp_ajax_mepr_remove_tax_rate', [$this,'remove_tax_rate']);
    }

    /**
     * Displays the option tab.
     *
     * @return void
     */
    public function display_option_tab()
    {
        ?>
      <a class="nav-tab" id="taxes" href="#"><?php _e('Taxes', 'memberpress'); ?><?php echo MeprUtils::new_badge(); ?></a>
        <?php
    }

    /**
     * Displays the option fields.
     *
     * @return void
     */
    public function display_option_fields()
    {
        $mepr_options = MeprOptions::fetch();

        if (isset($_POST['mepr_calculate_taxes']) and !empty($_POST['mepr_calculate_taxes'])) {
            $calculate_taxes = isset($_POST['mepr_calculate_taxes']);
        } else {
            $calculate_taxes = get_option('mepr_calculate_taxes');
        }

        $tax_rates = MeprTaxRate::get_all(', ');

        require(MEPR_VIEWS_PATH . '/admin/taxes/options.php');
    }

    /**
     * Stores the option fields.
     *
     * @return void
     */
    public function store_option_fields()
    {
        update_option('mepr_calculate_taxes', isset($_POST['mepr_calculate_taxes']));

        if (
            isset($_FILES['mepr_tax_rates_csv']) && !empty($_FILES['mepr_tax_rates_csv']) &&
            isset($_FILES['mepr_tax_rates_csv']['tmp_name']) &&
            !empty($_FILES['mepr_tax_rates_csv']['tmp_name'])
        ) {
            $mapping = [
                'country code'    => 'tax_country',
                'Country Code'    => 'tax_country',
                'state code'      => 'tax_state',
                'State Code'      => 'tax_state',
                'postcodes'       => 'postcodes',
                'Postcodes'       => 'postcodes',
                'postcode'        => 'postcodes',
                'Postcode'        => 'postcodes',
                'zip/postcodes'   => 'postcodes',
                'ZIP/Postcodes'   => 'postcodes',
                'zip/postcode'    => 'postcodes',
                'ZIP/Postcode'    => 'postcodes',
                'city'            => 'cities',
                'City'            => 'cities',
                'rate'            => 'tax_rate',
                'Rate'            => 'tax_rate',
                'Rate %'          => 'tax_rate',
                'tax name'        => 'tax_desc',
                'Tax Name'        => 'tax_desc',
                'Tax Description' => 'tax_desc',
                'tax description' => 'tax_desc',
                'priority'        => 'tax_priority',
                'Priority'        => 'tax_priority',
                'compound'        => 'tax_compound',
                'Compound'        => 'tax_compound',
                'shipping'        => 'tax_shipping',
                'Shipping'        => 'tax_shipping',
                'tax class'       => 'tax_class',
                'Tax Class'       => 'tax_class',
            ];

            $validations = [
                'required'  => [
                    'tax_country',
                    'tax_state',
                    'tax_rate',
                    'tax_desc',
                    'postcodes',
                    'cities',
                ],
                'not_empty' => ['tax_rate'],
                'number'    => ['tax_rate'],
                // 'tax_priority' => array('required'),
                // 'tax_compound' => array('required'),
                // 'tax_shipping' => array('required'),
                // 'tax_class' => array('required')
            ];

            $tax_rates = MeprUtils::parse_csv_file($_FILES['mepr_tax_rates_csv']['tmp_name'], $validations, $mapping);
            MeprTaxRate::import($tax_rates);
        }
    }

    /**
     * Exports the tax rates.
     *
     * @return void
     */
    public function export_tax_rates()
    {
        check_ajax_referer('export_tax_rates', 'mepr_taxes_nonce');

        $tax_rates = MeprTaxRate::get_all(';', ARRAY_A);

        if (!empty($tax_rates) && is_array($tax_rates)) {
            $header   = array_keys($tax_rates[0]);
            $filename = time() . '_tax_rates.csv';
            MeprAppHelper::render_csv($tax_rates, $header, $filename);
        } else {
            header('HTTP/1.0 403 Forbidden');
        }

        exit;
    }

    /**
     * Removes a tax rate.
     *
     * @return void
     */
    public function remove_tax_rate()
    {
        check_ajax_referer('mepr_taxes', 'tax_nonce');

        if (!MeprUtils::is_mepr_admin()) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }

        if (!isset($_POST['id'])) {
            header('HTTP/1.0 404 Not Found');
            exit(json_encode(['error' => __('A valid tax rate id must be set', 'memberpress')]));
        }

        $tax_rate = new MeprTaxRate($_POST['id']);
        if (empty($tax_rate->id)) {
            header('HTTP/1.0 404 Not Found');
            exit(json_encode(['error' => __('A valid tax rate id must be set', 'memberpress')]));
        }

        $tax_rate->destroy();

        exit(json_encode(['message' => __('This tax rate was successfully deleted', 'memberpress')]));
    }
}

