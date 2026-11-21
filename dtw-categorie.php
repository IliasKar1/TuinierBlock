<?php
/**
 * Plugin Name: DTW Categoriepagina's (mu, alleen staging)
 * Description: Toont op een productcategorie en op de winkelpagina de inhoud van de pagina "cat-inhoud-<slug>" (gemaakt door WebDev/home/build_categorie.py) in plaats van het productraster van het thema. Adres, titel en zoekmachine-instellingen van de categorie blijven gelijk. Oude weergave bekijken: voeg ?oud=1 toe aan het adres. Werkt alleen op een host die met "staging." begint.
 * Author: Daan (web-dev-agent)
 * Version: 0.3.0
 *
 * Uitzetten: dit bestand verwijderen of hernoemen. Er wordt niets in de database gewijzigd.
 */

defined('ABSPATH') || exit;

/** Alleen actief op de testsite. */
function dtw_cat_is_staging() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if (strpos($host, 'staging.') === 0) {
        return true;
    }
    // Productie (livegang 2026-11-21): aan zodra het bestand dtw-live.flag in mu-plugins staat. Uit: dat bestand verwijderen.
    return file_exists(WPMU_PLUGIN_DIR . '/dtw-live.flag');
}

/** De inhoudspagina die bij de bekeken categorie hoort, of null. */
function dtw_cat_content_page() {
    if (!function_exists('is_shop') || !function_exists('is_product_category')) {
        return null;
    }
    if (is_shop() && !is_search()) {
        // de winkelpagina ("Ons aanbod")
        $slug = 'winkel';
    } elseif (is_product_category()) {
        $term = get_queried_object();
        if (!is_object($term) || empty($term->slug)) {
            return null;
        }
        $slug = $term->slug;
    } else {
        return null;
    }
    $page = get_page_by_path('cat-inhoud-' . $slug);
    if (!$page || !in_array($page->post_status, array('publish', 'private'), true)) {
        return null;
    }
    if (trim((string) $page->post_content) === '') {
        return null;
    }
    return $page;
}

add_action('template_redirect', function () {
    if (!dtw_cat_is_staging() || isset($_GET['oud'])) {
        return;
    }
    $page = dtw_cat_content_page();
    if (!$page) {
        return;
    }

    // Alle modellen staan op één pagina: /page/2/ en verder gaan naar de eerste pagina.
    if ((int) get_query_var('paged') > 1) {
        $link = is_shop() ? wc_get_page_permalink('shop') : get_term_link(get_queried_object());
        if (is_string($link) && $link !== '') {
            wp_safe_redirect($link, 302);
            exit;
        }
    }

    // De inhoud is een HTML-blok; zonder de tekstfilters van WordPress tonen, zodat het script heel blijft.
    $html = preg_replace('/<!--\s*\/?wp:html\s*-->/', '', (string) $page->post_content);

    get_header();
    echo '<div id="content" class="dtw-cat-content" role="main" style="--twc-mt:0;">';
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</div>';
    get_footer();
    exit;
}, 20);
