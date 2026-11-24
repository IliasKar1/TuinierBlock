<?php
/**
 * Plugin Name: DTW Titel en omschrijving (mu)
 * Description: Geeft pagina's een eigen titel en omschrijving voor zoekmachines via de velden dtw_seo_title en dtw_seo_desc (gevuld door WebDev/landings/publish.py en WebDev/home/build_categorie.py). Op een productcategorie en de winkelpagina gelden de velden van de pagina "cat-inhoud-<slug>". Is een veld leeg, dan blijft de instelling van Yoast gelden. Haalt daarnaast de oude productpagina's die naar een landing doorverwijzen uit de sitemap.
 * Author: Daan (web-dev-agent)
 * Version: 0.1.4
 *
 * Uitzetten: dit bestand verwijderen of hernoemen. De velden blijven staan maar doen dan niets.
 */

defined('ABSPATH') || exit;

/** De twee velden, te vullen via de REST API door wie pagina's mag bewerken. */
add_action('init', function () {
    $velden = array(
        'page' => array('dtw_seo_title', 'dtw_seo_desc', 'dtw_seo_h1'),
        'post' => array('dtw_seo_title', 'dtw_seo_desc'),
    );
    foreach ($velden as $soort => $keys) {
        foreach ($keys as $key) {
            register_post_meta($soort, $key, array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function () {
                    return current_user_can('edit_pages');
                },
            ));
        }
    }
});

/** De pagina of het bericht waarvan de velden gelden voor wat de bezoeker nu bekijkt, of null. */
function dtw_seo_bron() {
    if (is_page()) {
        $page = get_queried_object();
        return ($page instanceof WP_Post) ? $page : null;
    }
    // De berichtenpagina (/blogs/): het thema bouwt hem op; de velden staan op de pagina die als berichtenpagina is ingesteld.
    if (is_home() && !is_front_page()) {
        $id   = (int) get_option('page_for_posts');
        $page = $id ? get_post($id) : null;
        return ($page instanceof WP_Post) ? $page : null;
    }
    // Een los blogbericht.
    if (is_singular('post')) {
        $post = get_queried_object();
        return ($post instanceof WP_Post) ? $post : null;
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

/**
 * De vaste zin "Bestel de ... tegen de beste prijzen online met gratis verzending" (standaardomschrijving voor producten
 * in de instellingen van Yoast) gaat van alle productpagina's: bezorging is alleen gratis vanaf 100 euro en
 * "de beste prijzen" is niet te onderbouwen (besluit marketing-manager 24-11-2026).
 * De omschrijving voor delen (og:description) wordt gelijk aan de eigen omschrijving van het product; is die er niet, dan blijft het veld leeg.
 */
function dtw_seo_is_sjabloonzin($tekst) {
    return is_string($tekst) && stripos($tekst, 'tegen de beste prijzen online met gratis verzending') !== false;
}

function dtw_seo_eigen_productomschrijving() {
    if (!is_singular('product')) {
        return '';
    }
    $tekst = trim((string) get_post_meta(get_queried_object_id(), '_yoast_wpseo_metadesc', true));
    return dtw_seo_is_sjabloonzin($tekst) ? '' : $tekst;
}

add_filter('wpseo_metadesc', function ($tekst) {
    return dtw_seo_is_sjabloonzin($tekst) ? dtw_seo_eigen_productomschrijving() : $tekst;
}, 100);

foreach (array('wpseo_opengraph_desc', 'wpseo_twitter_description') as $dtw_seo_filter) {
    add_filter($dtw_seo_filter, function ($tekst) {
        if (!is_singular('product')) {
            return $tekst;
        }
        $eigen = dtw_seo_eigen_productomschrijving();
        if ($eigen !== '') {
            return $eigen;
        }
        return dtw_seo_is_sjabloonzin($tekst) ? '' : $tekst;
    }, 100);
}
unset($dtw_seo_filter);

add_filter('wpseo_schema_webpage', function ($data) {
    if (is_array($data) && isset($data['description']) && dtw_seo_is_sjabloonzin($data['description'])) {
        $eigen = dtw_seo_eigen_productomschrijving();
        if ($eigen !== '') {
            $data['description'] = $eigen;
        } else {
            unset($data['description']);
        }
    }
    return $data;
}, 100);

/**
 * De categorie Best sellers blijft bestaan maar hoort niet in Google (besluit marketing-manager 24-11-2026):
 * "niet indexeren" op de pagina en niet in de sitemap.
 */
add_filter('wpseo_robots', function ($robots) {
    return is_tax('product_cat', 'best-sellers') ? 'noindex, follow' : $robots;
}, 100);

add_filter('wpseo_exclude_from_sitemap_by_term_ids', function ($ids) {
    $ids  = is_array($ids) ? $ids : array();
    $term = get_term_by('slug', 'best-sellers', 'product_cat');
    if ($term && !is_wp_error($term)) {
        $ids[] = (int) $term->term_id;
    }
    return $ids;
});

/**
 * Hoofdkop (h1) op twee pagina's die het thema zonder kop toont:
 * de berichtenpagina (tekst uit het veld dtw_seo_h1 van die pagina) en de merkpagina van Vitavia.
 */
add_action('flatsome_after_header', function () {
    $kop = '';
    if (is_home() && !is_front_page()) {
        $kop = dtw_seo_veld('dtw_seo_h1', '');
    } elseif (is_tax('product_brand', 'vitavia')) {
        $kop = 'Vitavia tuinkassen';
    }
    if ($kop === '') {
        return;
    }
    echo '<div class="container" style="padding-top:30px;"><h1 style="text-align:center; font-size:clamp(34px, 5vw, 46px); line-height:1.15; margin-bottom:0.3em;">' . esc_html($kop) . '</h1></div>';
}, 30);

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
