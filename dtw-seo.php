<?php
/**
 * Plugin Name: DTW Titel en omschrijving (mu)
 * Description: Geeft pagina's een eigen titel en omschrijving voor zoekmachines via de velden dtw_seo_title en dtw_seo_desc (gevuld door WebDev/landings/publish.py en WebDev/home/build_categorie.py). Op een productcategorie en de winkelpagina gelden de velden van de pagina "cat-inhoud-<slug>". Is een veld leeg, dan blijft de instelling van Yoast gelden. Haalt daarnaast de oude productpagina's die naar een landing doorverwijzen uit de sitemap.
 * Author: Daan (web-dev-agent)
 * Version: 0.1.2
 *
 * Uitzetten: dit bestand verwijderen of hernoemen. De velden blijven staan maar doen dan niets.
 */

defined('ABSPATH') || exit;

/** De twee velden, te vullen via de REST API door wie pagina's mag bewerken. */
add_action('init', function () {
    foreach (array('dtw_seo_title', 'dtw_seo_desc') as $key) {
        register_post_meta('page', $key, array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function () {
                return current_user_can('edit_pages');
            },
        ));
    }
});

/** De pagina waarvan de velden gelden voor wat de bezoeker nu bekijkt, of null. */
function dtw_seo_bron() {
    if (is_page()) {
        $page = get_queried_object();
        return ($page instanceof WP_Post) ? $page : null;
    }
    if (isset($_GET['oud'])) {
        return null;
    }
    if (function_exists('dtw_cat_is_staging') && function_exists('dtw_cat_content_page') && dtw_cat_is_staging()) {
        $page = dtw_cat_content_page();
        return ($page instanceof WP_Post) ? $page : null;
    }
    return null;
}

function dtw_seo_veld($key, $standaard) {
    $page = dtw_seo_bron();
    if (!$page) {
        return $standaard;
    }
    $waarde = trim((string) get_post_meta($page->ID, $key, true));
    return $waarde !== '' ? $waarde : $standaard;
}

foreach (array('wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title') as $dtw_seo_filter) {
    add_filter($dtw_seo_filter, function ($titel) {
        return dtw_seo_veld('dtw_seo_title', $titel);
    }, 99);
}
foreach (array('wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description') as $dtw_seo_filter) {
    add_filter($dtw_seo_filter, function ($tekst) {
        return dtw_seo_veld('dtw_seo_desc', $tekst);
    }, 99);
}
unset($dtw_seo_filter);

/** Ook in de gestructureerde gegevens van Yoast (naam en omschrijving van de pagina). */
add_filter('wpseo_schema_webpage', function ($data) {
    if (!is_array($data)) {
        return $data;
    }
    $titel = dtw_seo_veld('dtw_seo_title', '');
    $tekst = dtw_seo_veld('dtw_seo_desc', '');
    if ($titel !== '') {
        $data['name'] = $titel;
    }
    if ($tekst !== '') {
        $data['description'] = $tekst;
    }
    return $data;
}, 99);

/** Oude productpagina's die naar een landing doorverwijzen horen niet in de sitemap. */
add_filter('wpseo_exclude_from_sitemap_by_post_ids', function ($ids) {
    $ids = is_array($ids) ? $ids : array();
    if (!function_exists('dtw_lr_is_staging') || !dtw_lr_is_staging() || !function_exists('dtw_lr_map')) {
        return $ids;
    }
    $landings = dtw_lr_landing_ids();
    foreach ((function_exists('dtw_lr_alle') ? dtw_lr_alle() : dtw_lr_map()) as $product_id => $slug) {
        if (isset($landings[$slug])) {
            $ids[] = (int) $product_id;
        }
    }
    return array_values(array_unique(array_map('intval', $ids)));
});
