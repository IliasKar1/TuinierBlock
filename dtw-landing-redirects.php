<?php
/**
 * Plugin Name: DTW Landing-doorverwijzing (mu, alleen staging)
 * Description: Stuurt de oude productpagina van modellen met een nieuwe landing door naar die landing, en haalt de landings uit de zoekresultaten zodat elk model één keer verschijnt. Werkt alleen op een host die met "staging." begint. Oude pagina bekijken: voeg ?oud=1 toe aan het productadres.
 * Author: Daan (web-dev-agent)
 * Version: 0.5.0
 *
 * Uitzetten: dit bestand verwijderen of hernoemen. Er wordt niets in de database gewijzigd.
 */

defined('ABSPATH') || exit;

/** Alleen actief op de testsite. */
function dtw_lr_is_staging() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if (strpos($host, 'staging.') === 0) {
        return true;
    }
    // Productie (livegang 2026-11-21): aan zodra het bestand dtw-live.flag in mu-plugins staat. Uit: dat bestand verwijderen.
    return file_exists(WPMU_PLUGIN_DIR . '/dtw-live.flag');
}

/** Staging: tijdelijk (302). Productie: 302 tot in dtw-live.flag "301" staat; dan blijvend. */
function dtw_lr_status() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if (strpos($host, 'staging.') === 0) {
        return 302;
    }
    $flag = WPMU_PLUGIN_DIR . '/dtw-live.flag';
    if (file_exists($flag) && trim((string) file_get_contents($flag)) === '301') {
        return 301;
    }
    return 302;
}

/** Product-ID => slug van de nieuwe landing. */
function dtw_lr_map() {
    return array(
        3795 => 'kweekkas-hera-4500',
        3804 => 'kweekkas-hera-9000',
        9573 => 'muurkas-ida-900',
        9989 => 'muurkas-ida-1300',
        6644 => 'muurkas-ida-3300',
        3966 => 'muurkas-ida-5200',
        9425 => 'muurkas-ida-6500',
        9458 => 'muurkas-ida-7800',
        6546 => 'tuinkas-diana-5000',
        6556 => 'tuinkas-diana-6700',
        6570 => 'tuinkas-diana-8300',
        6584 => 'tuinkas-diana-9900',
        6598 => 'tuinkas-diana-11500',
        6258 => 'tuinkas-triton-2500',
        6278 => 'tuinkas-triton-3800',
        6298 => 'tuinkas-triton-5000',
        6318 => 'tuinkas-triton-6200',
        6484 => 'tuinkas-uranus-6700',
        6504 => 'tuinkas-uranus-9900',
        6524 => 'tuinkas-uranus-11500',
        // toegevoegd 2026-11-20: Apollo, Planet, Comet, Venus, Orion en Jupiter 11500
        6202 => 'tuinkas-apollo-2500',
        6216 => 'tuinkas-apollo-3800',
        6230 => 'tuinkas-apollo-5000',
        6244 => 'tuinkas-apollo-6200',
        6073 => 'tuinkas-planet-2500',
        5982 => 'tuinkas-planet-3800',
        6087 => 'tuinkas-planet-5000',
        6101 => 'tuinkas-planet-6200',
        5911 => 'tuinkas-comet-2500',
        5919 => 'tuinkas-comet-3800',
        6013 => 'tuinkas-comet-5000',
        6065 => 'tuinkas-comet-6200',
        6115 => 'tuinkas-venus-2500',
        6138 => 'tuinkas-venus-3800',
        6158 => 'tuinkas-venus-5000',
        6180 => 'tuinkas-venus-6200',
        3791 => 'tuinkas-orion-3800',
        3777 => 'tuinkas-orion-5000',
        6380 => 'tuinkas-jupiter-11500',
        // toegevoegd 2026-11-21: orangerie-lijn
        6340 => 'tuinkas-jupiter-6700',
        6360 => 'tuinkas-jupiter-9900',
        6402 => 'tuinkas-olymp-6700',
        6422 => 'tuinkas-olymp-8300',
        6442 => 'tuinkas-olymp-9900',
        6464 => 'tuinkas-olymp-11500',
        6662 => 'tuinkas-meridian-1-6700',
        6683 => 'tuinkas-meridian-1-8300',
        6703 => 'tuinkas-meridian-1-9900',
        6723 => 'tuinkas-meridian-1-11500',
        6743 => 'tuinkas-meridian-2-6700',
        6763 => 'tuinkas-meridian-2-8300',
        6783 => 'tuinkas-meridian-2-9900',
        6803 => 'tuinkas-meridian-2-11500',
        7876 => 'tuinkas-zeus-comfort-6200',
        8986 => 'tuinkas-zeus-comfort-8100',
        9001 => 'tuinkas-playa-5900',
        3821 => 'tuinkas-fortuna',
        3827 => 'tuinkas-sirius',
    );
}

/**
 * Dubbele oude producten van een model dat al een landing heeft (besluit eigenaar 2026-11-22: weg uit de winkel).
 * Landing => product-ID's. Bewust een andere vorm dan dtw_lr_map(): die lijst blijft één product per landing.
 */
function dtw_lr_dubbel() {
    return array(
        'tuinkas-planet-2500' => array(5965),
        'tuinkas-planet-5000' => array(4037),
        'tuinkas-planet-6200' => array(4023),
        'tuinkas-uranus-6700' => array(3953),
        'tuinkas-apollo-3800' => array(3946),
        'tuinkas-apollo-5000' => array(3933),
        'tuinkas-triton-3800' => array(3909),
    );
}

/** Alle producten die doorverwijzen: het product van de landing én de dubbele. Product-ID => slug. */
function dtw_lr_alle() {
    $alle = dtw_lr_map();
    foreach (dtw_lr_dubbel() as $slug => $ids) {
        foreach ($ids as $id) {
            $alle[(int) $id] = $slug;
        }
    }
    return $alle;
}

/** Slug => pagina-ID, alleen voor landings die bestaan en gepubliceerd zijn. */
function dtw_lr_landing_ids() {
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $ids = array();
    foreach (dtw_lr_map() as $slug) {
        $page = get_page_by_path($slug);
        if ($page && $page->post_status === 'publish') {
            $ids[$slug] = (int) $page->ID;
        }
    }
    return $ids;
}

/** Oude productpagina -> nieuwe landing (tijdelijke doorverwijzing, 302). */
add_action('template_redirect', function () {
    if (!dtw_lr_is_staging()) {
        return;
    }
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    if (isset($_GET['oud'])) {
        return;
    }
    $map = dtw_lr_alle();
    $pid = (int) get_queried_object_id();
    if (!isset($map[$pid])) {
        return;
    }
    $slug = $map[$pid];
    $ids  = dtw_lr_landing_ids();
    if (!isset($ids[$slug])) {
        return;
    }
    $url = get_permalink($ids[$slug]);
    if (!$url) {
        return;
    }
    $args = array();
    foreach ($_GET as $key => $value) {
        if (is_scalar($value)) {
            $args[sanitize_key($key)] = sanitize_text_field(wp_unslash((string) $value));
        }
    }
    if ($args) {
        $url = add_query_arg($args, $url);
    }
    wp_safe_redirect($url, dtw_lr_status());
    exit;
}, 1);

/** Landings niet als los zoekresultaat tonen; het product verschijnt en verwijst door. */
add_action('pre_get_posts', function ($query) {
    if (!dtw_lr_is_staging()) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    if (wp_doing_ajax()) {
        $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
        if ($action !== 'flatsome_ajax_search_products') {
            return;
        }
    } elseif (is_admin()) {
        return;
    }
    if ((string) $query->get('s') === '') {
        return;
    }
    $ids = array_values(dtw_lr_landing_ids());
    if (!$ids) {
        return;
    }
    $exclude = (array) $query->get('post__not_in');
    $query->set('post__not_in', array_values(array_unique(array_merge($exclude, $ids))));
});
