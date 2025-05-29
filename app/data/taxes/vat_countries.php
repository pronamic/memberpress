<?php

/**
 * See https://www.avalara.com/vatlive/en/vat-rates/european-vat-rates.html
 * Reduced Rates are currently a "best guess" based on information in above link.
 */

return MeprHooks::apply_filters('mepr-vat-countries', [
    'AT' => [
        'name'         => __('Austria', 'memberpress'),
        'rate'         => 20,
        'reduced_rate' => 10,
        'fmt'          => '(AT)?U[0-9]{8}',
    ],
    'BE' => [
        'name'         => __('Belgium', 'memberpress'),
        'rate'         => 21,
        'reduced_rate' => 6,
        'fmt'          => '(BE)?[0-1][0-9]{9}',
    ],
    'BG' => [
        'name' => __('Bulgaria', 'memberpress'),
        'rate' => 20,
        'fmt'  => '(BG)?[0-9]{9,10}',
    ],
    'CY' => [
        'name'         => __('Cyprus', 'memberpress'),
        'rate'         => 19,
        'reduced_rate' => 5,
        'fmt'          => '(CY)?[0-9]{8}[a-zA-Z]{1}',
    ],
    'CZ' => [
        'name'         => __('Czech Republic', 'memberpress'),
        'rate'         => 21,
        'reduced_rate' => 10,
        'fmt'          => '(CZ)?[0-9]{8,10}',
    ],
    'DE' => [
        'name'         => __('Germany', 'memberpress'),
        'rate'         => 19,
        'reduced_rate' => 7,
        'fmt'          => '(DE)?[0-9]{9}',
    ],
    'DK' => [
        'name'         => __('Denmark', 'memberpress'),
        'rate'         => 25,
        'reduced_rate' => 0,
        'fmt'          => '(DK)?[0-9]{8}',
    ],
    'EE' => [
        'name'         => __('Estonia', 'memberpress'),
        'rate'         => 22,
        'reduced_rate' => 9,
        'fmt'          => '(EE)?[0-9]{9}',
    ],
    'GR' => [
        'name'         => __('Greece', 'memberpress'),
        'rate'         => 24,
        'reduced_rate' => 6,
        'fmt'          => '(EL|GR)?[0-9]{9}',
    ],
    'ES' => [
        'name'         => __('Spain', 'memberpress'),
        'rate'         => 21,
        'reduced_rate' => 4,
        'fmt'          => '(ES)?[0-9A-Z][0-9]{7}[0-9A-Z]',
    ],
    'FI' => [
        'name'         => __('Finland', 'memberpress'),
        'rate'         => 25.5,
        'reduced_rate' => 10,
        'fmt'          => '(FI)?[0-9]{8}',
    ],
    'FR' => [
        'name'         => __('France', 'memberpress'),
        'rate'         => 20,
        'reduced_rate' => 5.5,
        'fmt'          => '(FR)?[0-9A-Z]{2}[0-9]{9}',
    ],
    'HR' => [
        'name'         => __('Croatia', 'memberpress'),
        'rate'         => 25,
        'reduced_rate' => 5,
        'fmt'          => '(HR)?[0-9]{11}',
    ],
    'GB' => [
        'name'         => __('United Kingdom', 'memberpress'),
        'rate'         => 20,
        'reduced_rate' => 0,
        'fmt'          => '(GB)?([0-9]{9}([0-9]{3})?|[A-Z]{2}[0-9]{3})',
    ],
    'HU' => [
        'name'         => __('Hungary', 'memberpress'),
        'rate'         => 27,
        'reduced_rate' => 5,
        'fmt'          => '(HU)?[0-9]{8}',
    ],
    'IE' => [
        'name'         => __('Ireland', 'memberpress'),
        'rate'         => 23,
        'reduced_rate' => 9,
        'fmt'          => '(IE)?[0-9][0-9|A-Z][0-9]{5}[0-9|A-Z]{1,2}',
    ],
    'IT' => [
        'name'         => __('Italy', 'memberpress'),
        'rate'         => 22,
        'reduced_rate' => 4,
        'fmt'          => '(IT)?[0-9]{11}',
    ],
    'LT' => [
        'name'         => __('Lithuania', 'memberpress'),
        'rate'         => 21,
        'reduced_rate' => 5,
        'fmt'          => '(LT)?([0-9]{9}|[0-9]{12})',
    ],
    'LU' => [
        'name'         => __('Luxembourg', 'memberpress'),
        'rate'         => 17,
        'reduced_rate' => 3,
        'fmt'          => '(LU)?[0-9]{8}',
    ],
    'LV' => [
        'name'         => __('Latvia', 'memberpress'),
        'rate'         => 21,
        'reduced_rate' => 12,
        'fmt'          => '(LV)?[0-9]{11}',
    ],
    'MT' => [
        'name'         => __('Malta', 'memberpress'),
        'rate'         => 18,
        'reduced_rate' => 5,
        'fmt'          => '(MT)?[0-9]{8}',
    ],
    'NL' => [
        'name'         => __('Netherlands', 'memberpress'),
        'rate'         => 21,
        'reduced_rate' => 9,
        'fmt'          => '(NL)?[0-9]{9}B[0-9]{2}',
    ],
    'PL' => [
        'name'         => __('Poland', 'memberpress'),
        'rate'         => 23,
        'reduced_rate' => 5,
        'fmt'          => '(PL)?[0-9]{10}',
    ],
    'PT' => [
        'name'         => __('Portugal', 'memberpress'),
        'rate'         => 23,
        'reduced_rate' => 6,
        'fmt'          => '(PT)?[0-9]{9}',
    ],
    'RO' => [
        'name'         => __('Romania', 'memberpress'),
        'rate'         => 19,
        'reduced_rate' => 5,
        'fmt'          => '(RO)?[0-9]{2,10}',
    ],
    'SE' => [
        'name'         => __('Sweden', 'memberpress'),
        'rate'         => 25,
        'reduced_rate' => 6,
        'fmt'          => '(SE)?[0-9]{12}',
    ],
    'SI' => [
        'name'         => __('Slovenia', 'memberpress'),
        'rate'         => 22,
        'reduced_rate' => 5,
        'fmt'          => '(SI)?[0-9]{8}',
    ],
    'SK' => [
        'name'         => __('Slovakia', 'memberpress'),
        'rate'         => 23,
        'reduced_rate' => 19,
        'fmt'          => '(SK)?[0-9]{10}',
    ],
    'GF' => [
        'name'         => __('French Guiana', 'memberpress'),
        'rate'         => 0,
        'reduced_rate' => 0,
        'fmt'          => '[0-9A-Z]{2}[0-9]{9}',
    ],
    'MF' => [
        'name'         => __('Saint Martin (French part)', 'memberpress'),
        'rate'         => 0,
        'reduced_rate' => 0,
        'fmt'          => '?[0-9A-Z]{2}[0-9]{9}',
    ],
    'MQ' => [
        'name'         => __('Martinique', 'memberpress'),
        'rate'         => 0,
        'reduced_rate' => 0,
        'fmt'          => '[0-9A-Z]{2}[0-9]{9}',
    ],
    'RE' => [
        'name'         => __('Reunion', 'memberpress'),
        'rate'         => 0,
        'reduced_rate' => 0,
        'fmt'          => '[0-9A-Z]{2}[0-9]{9}',
    ],
    'YT' => [
        'name'         => __('Mayotte', 'memberpress'),
        'rate'         => 0,
        'reduced_rate' => 0,
        'fmt'          => '[0-9A-Z]{2}[0-9]{9}',
    ],
    'PM' => [
        'name'         => __('Saint Pierre and Miquelon', 'memberpress'),
        'rate'         => 0,
        'reduced_rate' => 0,
        'fmt'          => '[0-9A-Z]{2}[0-9]{9}',
    ],
    'GP' => [
        'name'         => __('Guadeloupe', 'memberpress'),
        'rate'         => 0,
        'reduced_rate' => 0,
        'fmt'          => '[0-9A-Z]{2}[0-9]{9}',
    ],
    'MC' => [
        'name'         => __('Monaco', 'memberpress'),
        'rate'         => 20,
        'reduced_rate' => 5.5,
        'fmt'          => '(FR)?[0-9A-Z]{2}[0-9]{9}',
    ],
]);
