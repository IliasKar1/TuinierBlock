<?php
/**
 * Plugin Name: DTW Verzending (mu)
 * Description: Geldt voor een bestelling gratis verzending, dan toont de winkel alleen die keuze en niet daarnaast nog "Vast tarief". Op productie pas actief als het bestand dtw-verzending.flag in mu-plugins staat.
 * Author: Daan (web-dev-agent)
 * Version: 0.1.0
 *
 * Besluit eigenaar 25-11-2026 (via de marketing-manager): bezorgen naar België gelijk aan Nederland; boven € 100 alleen nog "Gratis verzending".
 * Uitzetten: het bestand dtw-verzending.flag verwijderen (productie), of dit bestand verwijderen of hernoemen. Er wordt niets in de database gewijzigd.
 */

defined('ABSPATH') || exit;

/** Testsite: altijd aan. Productie: pas met het bestand dtw-verzending.flag. */
function dtw_verzending_aan() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if (strpos($host, 'staging.') === 0) {
        return true;
    }
    return file_exists(WPMU_PLUGIN_DIR . '/dtw-verzending.flag');
}

add_filter('woocommerce_package_rates', function ($rates) {
    if (!dtw_verzending_aan() || !is_array($rates)) {
        return $rates;
    }
    $gratis = array();
    foreach ($rates as $id => $rate) {
        if (is_object($rate) && method_exists($rate, 'get_method_id') && $rate->get_method_id() === 'free_shipping') {
            $gratis[$id] = $rate;
        }
    }
    return $gratis ? $gratis : $rates;
}, 100);
