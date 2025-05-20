<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class MeprTaxRate extends MeprBaseModel
{
    /**
     * The customer type.
     *
     * @var string
     */
    public $customer_type = 'customer';

    /**
     * The tax reversal.
     *
     * @var boolean
     */
    public $reversal = false;

    /**
     * Constructor for the MeprTaxRate class.
     *
     * @param mixed $obj Optional object or ID to initialize the tax rate.
     */
    public function __construct($obj = null)
    {
        $this->initialize(
            [
                'id'           => 0,
                'tax_country'  => '',
                'tax_state'    => '',
                'tax_rate'     => 0.00,
                'tax_desc'     => '',
                'tax_priority' => 0,
                'tax_compound' => 0,
                'tax_shipping' => 1,
                'tax_order'    => 0,
                'tax_class'    => 'standard',
                'cities'       => [],
                'postcodes'    => [],
            ],
            $obj
        );

        if (is_integer($obj) && $obj > 0) {
            $this->rec->cities    = [];
            $this->rec->postcodes = [];

            $locations = self::get_locations_by_tax_rate($this->id);

            if (is_array($locations) && !empty($locations)) {
                foreach ($locations as $location) {
                    if ($location->location_type == 'city') {
                        $this->rec->cities[] = $location->location_code;
                    } elseif ($location->location_type == 'postcode') {
                        $this->rec->postcodes[] = $location->location_code;
                    }
                }
            }
        }
    }

    /**
     * Retrieves a single tax rate by ID.
     *
     * @param integer $id          The ID of the tax rate.
     * @param string  $return_type The return type (default: OBJECT).
     *
     * @return mixed The tax rate object or array.
     */
    public static function get_one($id, $return_type = OBJECT)
    {
        $mepr_db = new MeprDb();
        $args    = compact('id');
        return $mepr_db->get_one_record($mepr_db->tax_rates, $args, $return_type);
    }

    /**
     * Retrieves locations associated with a tax rate.
     *
     * @param integer $tax_rate_id The ID of the tax rate.
     * @param string  $return_type The return type (default: OBJECT).
     *
     * @return mixed The locations associated with the tax rate.
     */
    public static function get_locations_by_tax_rate($tax_rate_id, $return_type = OBJECT)
    {
        $mepr_db = new MeprDb();
        $args    = compact('tax_rate_id');
        return $mepr_db->get_records($mepr_db->tax_rate_locations, $args, '', '', $return_type);
    }

    /**
     * Finds a tax rate based on provided arguments.
     *
     * @param array  $args        The arguments for finding the tax rate.
     * @param string $return_type The return type (default: OBJECT).
     *
     * @return MeprTaxRate The found tax rate.
     */
    public static function find_rate($args, $return_type = OBJECT)
    {
        global $wpdb;

        $mepr_db = new MeprDb();

        // Get product ID if there is data from webhook.
        $prd_id = !empty($args['prd_id']) ? (int) $args['prd_id'] : 0;

        if (empty($prd_id) && !empty($_POST['mepr_product_id'])) {
            $prd_id = (int) $_POST['mepr_product_id'];
        }

        $prd = new MeprProduct($prd_id);

        if ($prd && isset($prd->tax_class) && !empty($prd->tax_class)) {
            $args['tax_class'] = $prd->tax_class;
        }

        $defaults = [
            'street'    => '',
            'country'   => '',
            'state'     => '',
            'city'      => '',
            'postcode'  => '',
            'tax_class' => 'standard',
            'user'      => null,
        ];

        $args = wp_parse_args($args, $defaults);

        extract($args, EXTR_SKIP);

        // Just return defaults.
        if (!$country) {
            return new MeprTaxRate();
        }

        // Handle postcodes.
        $valid_postcodes = ['*', strtoupper(MeprUtils::clean($postcode))];

        // Work out possible valid wildcard postcodes.
        $postcode_length   = strlen($postcode);
        $wildcard_postcode = strtoupper(MeprUtils::clean($postcode));

        for ($i = 0; $i < $postcode_length; $i++) {
            $wildcard_postcode = substr($wildcard_postcode, 0, -1);
            $valid_postcodes[] = $wildcard_postcode . '*';
        }

        // Attempt to cache the rate for a day using transients.
        $rate_transient_key = 'mepr_tax_rate_id_' . md5(sprintf('%s+%s+%s+%s+%s', $country, $state, $city, implode(',', $valid_postcodes), $tax_class));
        $tax_rate           = get_transient($rate_transient_key);

        if (!$tax_rate instanceof MeprTaxRate) {
            $q = "
        SELECT txr.*, pc.location_code AS postcode, ct.location_code AS city
          FROM {$mepr_db->tax_rates} AS txr
          LEFT JOIN {$mepr_db->tax_rate_locations} AS pc
            ON pc.tax_rate_id=txr.id
           AND pc.location_type='postcode'
          LEFT JOIN {$mepr_db->tax_rate_locations} AS ct
            ON ct.tax_rate_id=txr.id
           AND ct.location_type='city'
         WHERE txr.tax_country IN ( %s, '' )
           AND txr.tax_state IN ( %s, '' )
           AND txr.tax_class = %s
           AND (
             (
                pc.location_code IN ('" . implode("','", $valid_postcodes) . "')
                AND ct.location_code = %s
             ) OR (
                pc.location_code IS NULL
                AND ct.location_code = %s
             ) OR (
                pc.location_code IN ('" . implode("','", $valid_postcodes) . "')
                AND ct.location_code IS NULL
             ) OR (
                pc.location_code IS NULL
                AND ct.location_code IS NULL
             )
           )
         ORDER BY txr.tax_priority, txr.tax_order";

            $q = $wpdb->prepare(
                $q,
                strtoupper($country),
                strtoupper($state),
                strtolower($tax_class),
                strtoupper($city),
                strtoupper($city)
            );

            $found_rates = $wpdb->get_results($q);

            if (!empty($found_rates)) {
                $tax_rate = new MeprTaxRate($found_rates[0]->id);
            } else {
                $tax_rate = new MeprTaxRate();
            }

            $tax_rate = MeprHooks::apply_filters('mepr_found_tax_rate', $tax_rate, $country, $state, $postcode, $city, $street, $user);

            if ($tax_rate instanceof MeprTaxRate) {
                set_transient($rate_transient_key, $tax_rate, DAY_IN_SECONDS);
            } else {
                $tax_rate = new MeprTaxRate();
            }
        }

        return MeprHooks::apply_filters('mepr_find_tax_rate', $tax_rate, $country, $state, $postcode, $city, $street, $user, $prd_id);
    }

    /**
     * Retrieves the count of tax rates.
     *
     * @return integer The count of tax rates.
     */
    public static function get_count()
    {
        $mepr_db = new MeprDb();
        return $mepr_db->get_count($mepr_db->tax_rates);
    }

    /**
     * Retrieves all tax rates.
     *
     * @param string $separator   The separator for concatenating location codes.
     * @param string $return_type The return type (default: OBJECT).
     *
     * @return mixed The list of all tax rates.
     */
    public static function get_all($separator = ',', $return_type = OBJECT)
    {
        global $wpdb;
        $mepr_db = new MeprDb();

        $q = $wpdb->prepare(
            "SELECT txr.*,
              ( SELECT GROUP_CONCAT(
                         DISTINCT txrp.location_code
                         ORDER BY txrp.location_code
                         SEPARATOR '{$separator}'
                       )
                  FROM {$mepr_db->tax_rate_locations} AS txrp
                 WHERE txrp.location_type = %s
                   AND txrp.tax_rate_id=txr.id
              ) AS postcodes,
              ( SELECT GROUP_CONCAT(
                         DISTINCT txrc.location_code
                         ORDER BY txrc.location_code
                         SEPARATOR '{$separator}'
                       )
                  FROM {$mepr_db->tax_rate_locations} AS txrc
                 WHERE txrc.location_type = %s
                   AND txrc.tax_rate_id=txr.id
              ) AS cities
         FROM {$mepr_db->tax_rates} AS txr
        ORDER BY txr.tax_country, txr.tax_state, postcodes, cities",
            'postcode',
            'city'
        );

        return $wpdb->get_results($q, $return_type);
    }

    /**
     * Destroys all tax rates and clears related transients.
     *
     * @return void
     */
    public static function destroy_all()
    {
        global $wpdb;
        $tax_rates = self::get_all();

        foreach ($tax_rates as $tr) {
            $obj = new MeprTaxRate($tr->id);
            $obj->destroy();
        }

        // We should prolly clear out all transients here yo!
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_mepr_tax_rate_id_%'");
    }

    /**
     * Stores the tax rate in the database.
     *
     * @return mixed The ID of the stored tax rate.
     */
    public function store()
    {
        $this->prepare_fields();

        $mepr_db            = new MeprDb();
        $create_update_vals = $vals = (array)$this->rec;

        if (isset($create_update_vals['cities'])) {
            unset($create_update_vals['cities']);
        }

        if (isset($create_update_vals['postcodes'])) {
            unset($create_update_vals['postcodes']);
        }

        $this->destroy_locations_by_tax_rate();

        if (isset($this->id) && !is_null($this->id) && (int)$this->id > 0) {
            MeprHooks::apply_filters('mepr-tax-rate-update', $mepr_db->update_record($mepr_db->tax_rates, $this->id, $create_update_vals), $create_update_vals);
        } else {
            $this->id = MeprHooks::apply_filters('mepr-tax-rate-create', $mepr_db->create_record($mepr_db->tax_rates, $create_update_vals, false), $create_update_vals);
        }

        $locations = [
            'cities'    => 'city',
            'postcodes' => 'postcode',
        ];
        foreach ($locations as $col => $location) {
            if (isset($vals[$col]) && is_array($vals[$col])) {
                $this->import_locations($this->id, $location, $vals[$col]);
            }
        }

        return MeprHooks::apply_filters('mepr-tax-rate-store', $this->id, $vals);
    }

    /**
     * Imports tax rates from an array of rows.
     * country code, state code, postcodes, cities, rate, tax name, #priority, #compound, #shipping, #tax class
     *
     * @param array $rows The rows to import.
     *
     * @return void
     */
    public static function import($rows)
    {
        if (!empty($rows) && is_array($rows)) {
            foreach ($rows as $row) {
                self::import_row($row);
            }
        }
    }

    // TODO: Throw some exceptions on error.
    /**
     * Imports a tax rate row.
     *
     * @param array $row The row to import.
     *
     * @return void
     */
    private static function import_row($row)
    {
        $tax_rate_info = [
            'tax_country'  => MeprUtils::clean($row['tax_country']),
            'tax_state'    => MeprUtils::clean($row['tax_state']),
            'tax_rate'     => (float)trim(str_replace('%', '', str_replace(',', '.', $row['tax_rate']))),
            'tax_desc'     => trim($row['tax_desc']),
            'tax_priority' => isset($row['tax_priority']) ? $row['tax_priority'] : '',
            'tax_compound' => isset($row['tax_compound']) ? $row['tax_compound'] : '',
            'tax_shipping' => isset($row['tax_shipping']) ? $row['tax_shipping'] : '',
            // 'tax_order' => 0,
            'tax_class'    => ((isset($row['tax_class']) && !empty($row['tax_class'])) ? $row['tax_class'] : 'standard'),
        ];

        $locations = ['cities', 'postcodes'];
        foreach ($locations as $col) {
            if (isset($row[$col])) {
                $val = trim(MeprUtils::clean($row[$col]));
                if ($val === '*' || empty($val)) {
                    $tax_rate_info[$col] = [];
                } else {
                    $tax_rate_info[$col] = explode(';', preg_replace('#\s*;\s*#', ';', $val));
                }
            }
        }

        $tax_rate = new MeprTaxRate();
        $tax_rate->load_from_array($tax_rate_info);
        $tax_rate->store();
    }

    /**
     * Destroys the tax rate and its associated locations.
     *
     * @return mixed The result of the destruction process.
     */
    public function destroy()
    {
        $mepr_db = new MeprDb();
        $id      = $this->id;
        $args    = compact('id');
        $this->destroy_locations_by_tax_rate();
        return MeprHooks::apply_filters('mepr-tax-rate-destroy', $mepr_db->delete_records($mepr_db->tax_rates, $args), $args);
    }

    /**
     * Imports locations for a tax rate.
     *
     * @param integer $tax_rate_id The ID of the tax rate.
     * @param string  $type        The type of location (e.g., 'city', 'postcode').
     * @param array   $locations   The locations to import.
     *
     * @return void
     */
    public static function import_locations($tax_rate_id, $type, $locations)
    {
        $mepr_db = new MeprDb();
        foreach ($locations as $location) {
            $vals = [
                'location_code' => $location,
                'location_type' => $type,
                'tax_rate_id'   => $tax_rate_id,
            ];
            MeprHooks::apply_filters('mepr-tax-rate-location-create', $mepr_db->create_record($mepr_db->tax_rate_locations, $vals, false), $vals);
        }
    }

    /**
     * Destroys locations associated with a tax rate.
     *
     * @return mixed The result of the destruction process.
     */
    public function destroy_locations_by_tax_rate()
    {
        $mepr_db     = new MeprDb();
        $tax_rate_id = $this->id;
        $args        = compact('tax_rate_id');
        return MeprHooks::apply_filters('mepr-tax-rate-locations-destroy', $mepr_db->delete_records($mepr_db->tax_rate_locations, $args), $args);
    }

    /**
     * Prepares fields for storing the tax rate.
     *
     * @return void
     */
    private function prepare_fields()
    {
        $this->tax_country = MeprUtils::clean(strtoupper($this->tax_country));
        $this->tax_country = $this->tax_country == '*' ? '' : $this->tax_country;

        $this->tax_state = MeprUtils::clean(strtoupper($this->tax_state));
        $this->tax_state = $this->tax_state == '*' ? '' : $this->tax_state;

        foreach ($this->postcodes as $i => $postcode) {
            $postcode = trim(MeprUtils::clean($postcode));
            if ($postcode !== '*' && !empty($postcode)) {
                $this->rec->postcodes[$i] = $postcode = MeprUtils::clean(strtoupper($postcode));
            }
        }

        foreach ($this->cities as $i => $city) {
            $city = trim(MeprUtils::clean($city));
            if ($city !== '*' && !empty($city)) {
                $this->rec->cities[$i] = $city = MeprUtils::clean(strtoupper($city));
            }
        }
    }
}
