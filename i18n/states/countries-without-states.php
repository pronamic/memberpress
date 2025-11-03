<?php

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

/**
 * Countries that don't typically have states/provinces for addressing purposes.
 *
 * This list is based on WooCommerce's authoritative implementation where these
 * countries are explicitly marked as not requiring state fields in their
 * checkout process (see: WooCommerce's class-wc-countries.php locale settings).
 *
 * Each country code follows ISO 3166-1 alpha-2 standard.
 */

$countries = [
    'AE', // United Arab Emirates.
    'AF', // Afghanistan.
    'AT', // Austria.
    'AX', // Åland Islands.
    'BA', // Bosnia and Herzegovina.
    'BE', // Belgium.
    'BG', // Bulgaria.
    'BH', // Bahrain.
    'BI', // Burundi.
    'CY', // Cyprus.
    'CZ', // Czech Republic.
    'DE', // Germany.
    'DK', // Denmark.
    'EE', // Estonia.
    'ET', // Ethiopia.
    'FR', // France.
    'IM', // Isle of Man.
    'IS', // Iceland.
    'IL', // Israel.
    'KR', // South Korea.
    'KW', // Kuwait.
    'LB', // Lebanon.
    'LI', // Liechtenstein.
    'LK', // Sri Lanka.
    'LU', // Luxembourg.
    'MF', // Saint Martin.
    'MQ', // Martinique.
    'MT', // Malta.
    'NL', // Netherlands.
    'NO', // Norway.
    'PL', // Poland.
    'PT', // Portugal.
    'RE', // Réunion.
    'RW', // Rwanda.
    'SE', // Sweden.
    'SG', // Singapore.
    'SI', // Slovenia.
    'SK', // Slovakia.
];

return MeprHooks::apply_filters('mepr_countries_without_states', $countries);
