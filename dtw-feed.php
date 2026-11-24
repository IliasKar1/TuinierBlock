<?php
/**
 * Plugin Name: DTW Productfeed (mu)
 * Description: Eigen productfeed voor Google (/dtw-productfeed.xml) en het direct tonen van één uitvoering als die in de link staat (?variant=<nummer>): prijs, bestelknoppen en gestructureerde gegevens van die ene uitvoering. Zonder dat stuk in de link verandert er niets aan een pagina. Op productie pas actief als het bestand dtw-feed.flag in mu-plugins staat.
 * Author: Daan (web-dev-agent)
 * Version: 0.1.0
 *
 * Uitzetten: het bestand dtw-feed.flag verwijderen (productie), of dit bestand verwijderen of hernoemen.
 * Er wordt niets in de database gewijzigd; de feed staat als bestand in uploads/dtw-feed/.
 */

defined('ABSPATH') || exit;

/** Testsite: altijd aan. Productie: pas met het bestand dtw-feed.flag. */
function dtw_feed_aan() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if (strpos($host, 'staging.') === 0) {
        return true;
    }
    return file_exists(WPMU_PLUGIN_DIR . '/dtw-feed.flag');
}

/** Het nummer van de uitvoering uit de link, of 0. */
function dtw_feed_gevraagd() {
    if (!isset($_GET['variant']) || !is_string($_GET['variant']) || !ctype_digit($_GET['variant'])) {
        return 0;
    }
    return (int) $_GET['variant'];
}

/* ------------------------------------------------------------------------------------------------
 * 1. Eén regel van de feed: titel, prijs, streepjescode, merk. Gedeeld door de feed en de pagina's.
 * ---------------------------------------------------------------------------------------------- */

function dtw_feed_glas() {
    return array(
        'HKP kanaalplaten 4mm'    => 'kanaalplaten 4 mm',
        'HKP kanaalplaten 6mm'    => 'kanaalplaten 6 mm',
        'ESG Veiligheidsglas 3mm' => 'veiligheidsglas 3 mm',
        'HKP 10mm'                => 'kanaalplaten 10 mm',
        'HKP 16mm'                => 'kanaalplaten 16 mm',
        'Gecombineerde beglazing HKP 10mm/ESG 3mm'                    => 'glas en kanaalplaten 10 mm',
        'Gecombineerd Veiligheidsglas ESG 3mm/ HKP kanaalplaten 6mm' => 'glas en kanaalplaten 6 mm',
    );
}

function dtw_feed_kleur() {
    return array('Alu' => 'aluminium', 'Aluminium' => 'aluminium', 'Groen' => 'groen', 'Zwart' => 'zwart', 'Wit' => 'wit');
}

function dtw_feed_fundering() {
    return array('Inclusief fundering' => 'met funderingsframe', 'Inclusief fundering 12,5 cm' => 'met funderingsframe 12,5 cm');
}

/** 'muurkas-ida-900' => array('Ida 900', 'muurkas'). */
function dtw_feed_model($slug) {
    if (!preg_match('/^(tuinkas|muurkas|kweekkas)-(.+?)(?:-(\d+))?$/', (string) $slug, $m)) {
        return null;
    }
    $fam  = array('meridian-1' => 'Meridian 1', 'meridian-2' => 'Meridian 2', 'zeus-comfort' => 'Zeus Comfort');
    $naam = isset($fam[$m[2]]) ? $fam[$m[2]] : ucfirst($m[2]);
    return array(trim($naam . ' ' . (isset($m[3]) ? $m[3] : '')), $m[1]);
}

/** Product-ID => slug van de landing, alleen voor landings die bestaan. */
function dtw_feed_landings() {
    static $uit = null;
    if ($uit !== null) {
        return $uit;
    }
    $uit = array();
    if (function_exists('dtw_lr_map') && function_exists('dtw_lr_landing_ids')) {
        $bestaat = dtw_lr_landing_ids();
        foreach (dtw_lr_map() as $pid => $slug) {
            if (isset($bestaat[$slug])) {
                $uit[(int) $pid] = $slug;
            }
        }
    }
    return $uit;
}

/** De gegevens van het product uit de gestructureerde gegevens van de landing (naam, omschrijving). */
function dtw_feed_landing_schema($slug) {
    static $cache = array();
    if (array_key_exists($slug, $cache)) {
        return $cache[$slug];
    }
    $cache[$slug] = array();
    $page = get_page_by_path($slug);
    if ($page && preg_match_all('#<script type="application/ld\+json">(\{.*?\})</script>#s', (string) $page->post_content, $alle)) {
        foreach ($alle[1] as $json) {
            $d = json_decode($json, true);
            if (is_array($d) && isset($d['@type']) && $d['@type'] === 'Product') {
                $cache[$slug] = $d;
                break;
            }
        }
    }
    return $cache[$slug];
}

/** Kenmerken van een variant: taxonomie of sleutel => naam van de keuze zoals in het beheer. */
function dtw_feed_kenmerken($variant, $ouder) {
    $uit = array();
    foreach ((array) $variant->get_attributes() as $tax => $slug) {
        $slug = (string) $slug;
        if ($slug === '') {
            continue;
        }
        $naam = $slug;
        if (taxonomy_exists($tax)) {
            $term = get_term_by('slug', $slug, $tax);
            if ($term && !is_wp_error($term)) {
                $naam = html_entity_decode($term->name, ENT_QUOTES, 'UTF-8');
            }
        } else {
            $van_ouder = $ouder->get_attributes();
            if (isset($van_ouder[$tax]) && is_object($van_ouder[$tax])) {
                foreach ((array) $van_ouder[$tax]->get_options() as $optie) {
                    if ($optie === $slug || sanitize_title($optie) === $slug) {
                        $naam = (string) $optie;
                    }
                }
            }
        }
        $uit[$tax] = $naam;
    }
    return $uit;
}

/** Streepjescode. Bij een bundel (kas + frame, artikelnummers met een plus) die van de kas. */
function dtw_feed_gtin($product) {
    $eigen = method_exists($product, 'get_global_unique_id') ? (string) $product->get_global_unique_id() : '';
    if (preg_match('/^\d{8,14}$/', $eigen)) {
        return $eigen;
    }
    $sku = (string) $product->get_sku();
    if (strpos($sku, '+') === false) {
        return '';
    }
    $delen = array_values(array_filter(array_map('trim', explode('+', $sku)), 'strlen'));
    if (!$delen) {
        return '';
    }
    $id = wc_get_product_id_by_sku($delen[0]);
    if (!$id) {
        return '';
    }
    $kas  = wc_get_product($id);
    $code = ($kas && method_exists($kas, 'get_global_unique_id')) ? (string) $kas->get_global_unique_id() : '';
    return preg_match('/^\d{8,14}$/', $code) ? $code : '';
}

function dtw_feed_platte_tekst($html) {
    $t = wp_strip_all_tags(strip_shortcodes((string) $html), true);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    $t = trim(preg_replace('/\s+/u', ' ', $t));
    if (function_exists('mb_substr') && mb_strlen($t) > 4900) {
        $t = mb_substr($t, 0, 4900);
    }
    return $t;
}

/** Categoriepad van het product, zonder de verzamelcategorie Best sellers. */
function dtw_feed_soort($product_id) {
    $termen = get_the_terms($product_id, 'product_cat');
    if (!$termen || is_wp_error($termen)) {
        return '';
    }
    $beste = null;
    $diepte = -1;
    foreach ($termen as $t) {
        if ($t->slug === 'best-sellers' || $t->slug === 'uncategorized' || $t->slug === 'geen-categorie') {
            continue;
        }
        $d = count(get_ancestors($t->term_id, 'product_cat'));
        if ($d > $diepte || ($d === $diepte && $beste && $t->term_id < $beste->term_id)) {
            $beste  = $t;
            $diepte = $d;
        }
    }
    if (!$beste) {
        return '';
    }
    $pad = array();
    foreach (array_reverse(get_ancestors($beste->term_id, 'product_cat')) as $aid) {
        $a = get_term($aid, 'product_cat');
        if ($a && !is_wp_error($a)) {
            $pad[] = html_entity_decode($a->name, ENT_QUOTES, 'UTF-8');
        }
    }
    $pad[] = html_entity_decode($beste->name, ENT_QUOTES, 'UTF-8');
    return implode(' > ', $pad);
}

/**
 * Bouwt één regel. $variant is null bij een enkelvoudig product.
 * Geeft null als de regel niet in de feed hoort (uitgeschakeld, geen prijs, variant met een extra erbij).
 */
function dtw_feed_regel($product, $variant = null) {
    $pid      = (int) $product->get_id();
    $landings = dtw_feed_landings();
    $slug     = isset($landings[$pid]) ? $landings[$pid] : '';
    $naam     = trim(html_entity_decode((string) $product->get_name(), ENT_QUOTES, 'UTF-8'));
    $item     = $variant ? $variant : $product;
    if ($variant) {
        if ($variant->get_status() !== 'publish') {
            return null;
        }
    }
    if ((string) $item->get_price() === '') {
        return null;
    }
    $kleur = '';
    $titel = $naam;
    if ($variant) {
        $k = dtw_feed_kenmerken($variant, $product);
        foreach ($k as $tax => $waarde) {
            if (strpos($tax, 'handig-voor') !== false && $waarde !== "Geen extra's" && sanitize_title($waarde) !== 'geen-extras') {
                return null;                      // variant met een raamopener of regenpijp erbij: niet in de feed
            }
        }
        $model = $slug ? dtw_feed_model($slug) : null;
        $delen = array($model ? ('Vitavia ' . $model[0] . ' ' . $model[1]) : $naam);
        if (isset($k['pa_kleur'])) {
            $kaart   = dtw_feed_kleur();
            $kleur   = isset($kaart[$k['pa_kleur']]) ? $kaart[$k['pa_kleur']] : mb_strtolower($k['pa_kleur']);
            $delen[] = $kleur;
        }
        if (isset($k['pa_beglazing'])) {
            $kaart   = dtw_feed_glas();
            $delen[] = isset($kaart[$k['pa_beglazing']]) ? $kaart[$k['pa_beglazing']] : mb_strtolower($k['pa_beglazing']);
        }
        if (isset($k['pa_fundering'])) {
            $kaart = dtw_feed_fundering();
            if (isset($kaart[$k['pa_fundering']])) {
                $delen[] = $kaart[$k['pa_fundering']];
            }
        }
        foreach ($k as $tax => $waarde) {
            if ($tax === 'pa_kleur' || $tax === 'pa_beglazing' || $tax === 'pa_fundering' || strpos($tax, 'handig-voor') !== false) {
                continue;
            }
            $delen[] = mb_strtolower(wc_attribute_label($tax, $product)) . ' ' . $waarde;
        }
        $titel = implode(', ', $delen);
    }
    $sku    = trim((string) $item->get_sku());
    $bundel = strpos($sku, '+') !== false;
    $gtin   = dtw_feed_gtin($item);
    $merken = wp_get_post_terms($pid, 'product_brand', array('fields' => 'names'));
    $merk   = (!is_wp_error($merken) && $merken) ? html_entity_decode((string) $merken[0], ENT_QUOTES, 'UTF-8') : '';
    $mpn    = (!$bundel && preg_match('/^\d{5,}$/', $sku)) ? $sku : '';

    if ($slug) {
        $link = home_url('/' . $slug . '/');
        if ($variant) {
            $link = add_query_arg('variant', (int) $variant->get_id(), $link);
        }
    } elseif ($variant) {
        $link = add_query_arg('variant', (int) $variant->get_id(), $variant->get_permalink());
    } else {
        $link = get_permalink($pid);
    }

    $beeld_id = $variant ? (int) $variant->get_image_id() : 0;
    if (!$beeld_id) {
        $beeld_id = (int) $product->get_image_id();
    }
    $beeld = $beeld_id ? (string) wp_get_attachment_url($beeld_id) : '';
    $meer  = array();
    foreach (array_slice((array) $product->get_gallery_image_ids(), 0, 8) as $gid) {
        $u = wp_get_attachment_url($gid);
        if ($u && $u !== $beeld) {
            $meer[] = $u;
        }
    }

    $tekst = '';
    if ($slug) {
        $schema = dtw_feed_landing_schema($slug);
        $tekst  = isset($schema['description']) ? dtw_feed_platte_tekst($schema['description']) : '';
    }
    if ($tekst === '') {
        $tekst = dtw_feed_platte_tekst($product->get_short_description());
    }
    if ($tekst === '') {
        $tekst = dtw_feed_platte_tekst($product->get_description());
    }
    if ($tekst === '') {
        $tekst = $titel;
    }

    return array(
        'id'       => (int) $item->get_id(),
        'groep'    => $variant ? $pid : 0,
        'titel'    => $titel,
        'tekst'    => $tekst,
        'prijs'    => number_format((float) wc_get_price_to_display($item), 2, '.', ''),
        'link'     => $link,
        'beeld'    => $beeld,
        'meer'     => $meer,
        'sku'      => $sku,
        'gtin'     => $gtin,
        'mpn'      => $mpn,
        'bundel'   => $bundel,
        'merk'     => $merk,
        'kleur'    => $kleur,
        'voorraad' => $item->get_stock_status() === 'outofstock' ? 'out_of_stock' : 'in_stock',
        'soort'    => dtw_feed_soort($pid),
        'landing'  => $slug,
    );
}

/** Alle regels van de feed. */
function dtw_feed_regels() {
    $dubbel = array();
    if (function_exists('dtw_lr_dubbel')) {
        foreach (dtw_lr_dubbel() as $ids) {
            foreach ((array) $ids as $id) {
                $dubbel[(int) $id] = true;
            }
        }
    }
    $ids    = wc_get_products(array('status' => 'publish', 'limit' => -1, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC'));
    $regels = array();
    foreach ($ids as $pid) {
        if (isset($dubbel[(int) $pid])) {
            continue;                               // dubbele oude producten: verborgen, verwijzen door naar de landing
        }
        $p = wc_get_product($pid);
        if (!$p) {
            continue;
        }
        if ($p->is_type('variable')) {
            foreach ($p->get_children() as $vid) {
                $v = wc_get_product($vid);
                if (!$v) {
                    continue;
                }
                $r = dtw_feed_regel($p, $v);
                if ($r) {
                    $regels[] = $r;
                }
            }
        } else {
            $r = dtw_feed_regel($p, null);
            if ($r) {
                $regels[] = $r;
            }
        }
    }
    return $regels;
}

/* ------------------------------------------------------------------------------------------------
 * 2. De feed zelf: /dtw-productfeed.xml (Google), /dtw-productfeed.json (controle)
 * ---------------------------------------------------------------------------------------------- */

function dtw_feed_x($s) {
    $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $s);
    return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function dtw_feed_xml($regels) {
    $x   = array();
    $x[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $x[] = '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
    $x[] = '<channel>';
    $x[] = '<title>deTuinierwinkel</title>';
    $x[] = '<link>' . dtw_feed_x(home_url('/')) . '</link>';
    $x[] = '<description>Productfeed deTuinierwinkel.nl, opgebouwd uit de winkel op ' . gmdate('Y-m-d H:i') . ' UTC</description>';
    foreach ($regels as $r) {
        if ($r['titel'] === '' || $r['beeld'] === '') {
            continue;                               // Google weigert een regel zonder titel of afbeelding
        }
        $x[] = '<item>';
        $x[] = '<g:id>' . (int) $r['id'] . '</g:id>';
        if ($r['groep']) {
            $x[] = '<g:item_group_id>' . (int) $r['groep'] . '</g:item_group_id>';
        }
        $x[] = '<g:title>' . dtw_feed_x($r['titel']) . '</g:title>';
        $x[] = '<g:description>' . dtw_feed_x($r['tekst']) . '</g:description>';
        $x[] = '<g:link>' . dtw_feed_x($r['link']) . '</g:link>';
        $x[] = '<g:image_link>' . dtw_feed_x($r['beeld']) . '</g:image_link>';
        foreach ($r['meer'] as $u) {
            $x[] = '<g:additional_image_link>' . dtw_feed_x($u) . '</g:additional_image_link>';
        }
        $x[] = '<g:availability>' . $r['voorraad'] . '</g:availability>';
        $x[] = '<g:price>' . $r['prijs'] . ' EUR</g:price>';
        $x[] = '<g:condition>new</g:condition>';
        if ($r['merk'] !== '') {
            $x[] = '<g:brand>' . dtw_feed_x($r['merk']) . '</g:brand>';
        }
        if ($r['gtin'] !== '') {
            $x[] = '<g:gtin>' . dtw_feed_x($r['gtin']) . '</g:gtin>';
        }
        if ($r['mpn'] !== '') {
            $x[] = '<g:mpn>' . dtw_feed_x($r['mpn']) . '</g:mpn>';
        }
        if ($r['gtin'] === '' && ($r['merk'] === '' || $r['mpn'] === '')) {
            $x[] = '<g:identifier_exists>no</g:identifier_exists>';
        }
        if ($r['bundel']) {
            $x[] = '<g:is_bundle>yes</g:is_bundle>';
        }
        if ($r['kleur'] !== '') {
            $x[] = '<g:color>' . dtw_feed_x(ucfirst($r['kleur'])) . '</g:color>';
        }
        if ($r['soort'] !== '') {
            $x[] = '<g:product_type>' . dtw_feed_x($r['soort']) . '</g:product_type>';
        }
        $x[] = '</item>';
    }
    $x[] = '</channel>';
    $x[] = '</rss>';
    return implode("\n", $x) . "\n";
}

function dtw_feed_map() {
    $up = wp_upload_dir(null, false);
    return trailingslashit($up['basedir']) . 'dtw-feed';
}

/** Geeft het pad van het bestand; bouwt het opnieuw als het ontbreekt of ouder is dan een uur. */
function dtw_feed_bestand($soort) {
    $map = dtw_feed_map();
    if (!is_dir($map)) {
        wp_mkdir_p($map);
    }
    $pad = $map . '/google.' . ($soort === 'json' ? 'json' : 'xml');
    if (file_exists($pad) && (time() - (int) filemtime($pad)) < HOUR_IN_SECONDS && filesize($pad) > 200) {
        return $pad;
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $regels = dtw_feed_regels();
    if (count($regels) < 1) {
        return file_exists($pad) ? $pad : '';
    }
    file_put_contents($map . '/google.xml.tmp', dtw_feed_xml($regels));
    file_put_contents($map . '/google.json.tmp', wp_json_encode($regels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    rename($map . '/google.xml.tmp', $map . '/google.xml');
    rename($map . '/google.json.tmp', $map . '/google.json');
    return $pad;
}

/** Na een wijziging aan een product: bij het volgende ophalen wordt de feed opnieuw opgebouwd. */
function dtw_feed_verlopen() {
    $map = dtw_feed_map();
    foreach (array('google.xml', 'google.json') as $f) {
        if (file_exists($map . '/' . $f)) {
            @touch($map . '/' . $f, time() - 2 * HOUR_IN_SECONDS);
        }
    }
}
add_action('woocommerce_update_product', 'dtw_feed_verlopen');
add_action('woocommerce_update_product_variation', 'dtw_feed_verlopen');
add_action('woocommerce_new_product', 'dtw_feed_verlopen');
add_action('woocommerce_new_product_variation', 'dtw_feed_verlopen');

add_action('parse_request', function ($wp) {
    $pad = isset($wp->request) ? (string) $wp->request : '';
    if ($pad !== 'dtw-productfeed.xml' && $pad !== 'dtw-productfeed.json') {
        return;
    }
    if (!dtw_feed_aan() || !function_exists('wc_get_products')) {
        return;
    }
    $soort   = substr($pad, -4) === 'json' ? 'json' : 'xml';
    $bestand = dtw_feed_bestand($soort);
    if ($bestand === '' || !file_exists($bestand)) {
        status_header(503);
        exit;
    }
    status_header(200);
    nocache_headers();
    header('Content-Type: ' . ($soort === 'json' ? 'application/json' : 'application/xml') . '; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($bestand)) . ' GMT');
    readfile($bestand);
    exit;
}, 1);

/* ------------------------------------------------------------------------------------------------
 * 3. Een landing toont de uitvoering uit de link (?variant=<nummer>) direct
 * ---------------------------------------------------------------------------------------------- */

function dtw_feed_euro($bedrag) {
    return '&euro; ' . number_format((float) $bedrag, 2, ',', '.');
}

/** Zet de klasse "actief" op de ene knop met de gevraagde waarde, en haalt hem bij de andere weg. */
function dtw_feed_actief($html, $basis, $actief, $attr, $waarde) {
    return preg_replace_callback(
        '/<button class="(' . preg_quote($basis, '/') . '(?: [^"]*)?)" data-' . preg_quote($attr, '/') . '="([^"]*)"/',
        function ($m) use ($actief, $waarde) {
            $klassen = array_values(array_diff(explode(' ', $m[1]), array($actief, '')));
            if ($m[2] === $waarde) {
                $klassen[] = $actief;
            }
            return str_replace('class="' . $m[1] . '"', 'class="' . implode(' ', $klassen) . '"', $m[0]);
        },
        $html
    );
}

function dtw_feed_landing_variant($content) {
    $vid = dtw_feed_gevraagd();
    if (!$vid || !dtw_feed_aan() || !is_page() || (int) get_the_ID() !== (int) get_queried_object_id()) {
        return $content;
    }
    if (strpos($content, 'var MATRIX = ') === false || !preg_match('/var MATRIX = (\{.*\});/', $content, $m)) {
        return $content;
    }
    $matrix = json_decode($m[1], true);
    if (!is_array($matrix)) {
        return $content;
    }
    $gevonden = null;
    foreach ($matrix as $kleur => $glazen) {
        if (!is_array($glazen)) {
            continue;
        }
        foreach ($glazen as $glas => $keuzes) {
            if (!is_array($keuzes)) {
                continue;
            }
            foreach ($keuzes as $fund => $combo) {
                if (is_array($combo) && isset($combo['cart'], $combo['price']) && preg_match('/(?:^|&)variation_id=' . $vid . '(?:&|$)/', (string) $combo['cart'])) {
                    $gevonden = array((string) $kleur, (string) $glas, (string) $fund, $combo);
                    break 3;
                }
            }
        }
    }
    if (!$gevonden) {
        return $content;                            // onbekend nummer: de pagina blijft zoals hij is
    }
    list($kleur, $glas, $fund, $combo) = $gevonden;
    $prijs = (float) $combo['price'];
    $cart  = (string) $combo['cart'];
    $uit   = $content;

    // naam van de kleur zoals op de knop
    $label = $kleur;
    if (preg_match('/<button class="twb-swatch[^"]*" data-slug="' . preg_quote($kleur, '/') . '"[^>]*>.*?<span class="twb-swatch-label">([^<]*)<\/span>/s', $uit, $lm)) {
        $label = $lm[1];
    }

    // de begintoestand van het script van de pagina; dat script werkt bij het laden alles bij vanuit deze waarden
    $toestand = 'var currentColor = { label: ' . wp_json_encode(html_entity_decode($label, ENT_QUOTES, 'UTF-8')) . ', price: ' . wp_json_encode($prijs)
        . ', cart: ' . wp_json_encode($cart) . ', slug: ' . wp_json_encode($kleur) . ' };';
    $uit = preg_replace_callback('/var currentColor = \{[^\n]*\};/', function () use ($toestand) {
        return $toestand;
    }, $uit, 1);
    $uit = preg_replace_callback('/var currentGlas = \'[^\']*\';/', function () use ($glas) {
        return 'var currentGlas = ' . wp_json_encode($glas) . ';';
    }, $uit, 1);

    // wat de server levert: gekozen knoppen, prijs op drie plaatsen, de vier bestelknoppen
    $uit = dtw_feed_actief($uit, 'twb-swatch', 'twb-swatch-active', 'slug', $kleur);
    $uit = dtw_feed_actief($uit, 'twb-tint-card', 'twb-tint-active', 'slug', $kleur);
    $uit = dtw_feed_actief($uit, 'twb-glas-card', 'twb-glas-active', 'glas', $glas);
    $uit = dtw_feed_actief($uit, 'twb-fund-card', 'twb-fund-active', 'fund', $fund);
    $euro = dtw_feed_euro($prijs);
    $uit  = preg_replace_callback('/(<p id="twb-price"[^>]*>)[^<]*(<\/p>)/', function ($x) use ($euro) {
        return $x[1] . $euro . $x[2];
    }, $uit, 1);
    $uit = preg_replace_callback('/(id="twb-bundle-total">)[^<]*(<)/', function ($x) use ($euro) {
        return $x[1] . $euro . $x[2];
    }, $uit, 1);
    $uit = preg_replace_callback('/(<p id="twb-sticky-price"[^>]*>)[^<]*(<\/p>)/', function ($x) use ($euro) {
        return $x[1] . $euro . $x[2];
    }, $uit, 1);
    $uit = preg_replace_callback('/(id="twb-sticky-color">)[^<]*(<)/', function ($x) use ($label) {
        return $x[1] . $label . $x[2];
    }, $uit, 1);
    $knop = esc_attr($cart);
    $uit  = preg_replace_callback('/(<a id="(?:twb-cta|twb-bundle-cart|twb-cta-bottom|twb-sticky-cta)"[^>]*\sdata-c=")[^"]*(")/', function ($x) use ($knop) {
        return $x[1] . $knop . $x[2];
    }, $uit);

    // gestructureerde gegevens: alleen deze ene uitvoering
    $variant = function_exists('wc_get_product') ? wc_get_product($vid) : null;
    $ouder   = ($variant && $variant->get_parent_id()) ? wc_get_product($variant->get_parent_id()) : null;
    $regel   = ($variant && $ouder) ? dtw_feed_regel($ouder, $variant) : null;
    $adres   = add_query_arg('variant', $vid, get_permalink());
    $uit     = preg_replace_callback('#<script type="application/ld\+json">(\{.*?\})</script>#s', function ($x) use ($prijs, $regel, $adres, $variant) {
        $d = json_decode($x[1], true);
        if (!is_array($d) || !isset($d['@type']) || $d['@type'] !== 'Product') {
            return $x[0];
        }
        if ($regel) {
            $d['name'] = $regel['titel'];
            if ($regel['sku'] !== '') {
                $d['sku'] = $regel['sku'];
            }
            if ($regel['mpn'] !== '') {
                $d['mpn'] = $regel['mpn'];
            }
            if (strlen($regel['gtin']) === 13) {
                $d['gtin13'] = $regel['gtin'];
            } elseif ($regel['gtin'] !== '') {
                $d['gtin'] = $regel['gtin'];
            }
            if ($regel['beeld'] !== '') {
                $beelden    = isset($d['image']) ? (array) $d['image'] : array();
                $d['image'] = array_values(array_unique(array_merge(array($regel['beeld']), $beelden)));
            }
        }
        $d['offers'] = array(
            '@type'         => 'Offer',
            'price'         => number_format($prijs, 2, '.', ''),
            'priceCurrency' => 'EUR',
            'availability'  => ($variant && $variant->get_stock_status() === 'outofstock') ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'url'           => $adres,
        );
        if ($regel && $regel['sku'] !== '') {
            $d['offers']['sku'] = $regel['sku'];
        }
        return '<script type="application/ld+json">' . wp_json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }, $uit);

    return is_string($uit) && $uit !== '' ? $uit : $content;
}
add_filter('the_content', 'dtw_feed_landing_variant', 99);

/* ------------------------------------------------------------------------------------------------
 * 4. Een gewone productpagina (zonder landing) meldt in de gestructureerde gegevens de uitvoering uit de link
 * ---------------------------------------------------------------------------------------------- */

add_filter('woocommerce_structured_data_product', function ($markup, $product) {
    $vid = dtw_feed_gevraagd();
    if (!$vid || !dtw_feed_aan() || !is_array($markup) || !$product || !$product->is_type('variable')) {
        return $markup;
    }
    if (!in_array($vid, array_map('intval', $product->get_children()), true)) {
        return $markup;
    }
    $variant = wc_get_product($vid);
    $regel   = $variant ? dtw_feed_regel($product, $variant) : null;
    if (!$regel) {
        return $markup;
    }
    $markup['name'] = $regel['titel'];
    if ($regel['sku'] !== '') {
        $markup['sku'] = $regel['sku'];
    }
    if ($regel['mpn'] !== '') {
        $markup['mpn'] = $regel['mpn'];
    }
    if (strlen($regel['gtin']) === 13) {
        $markup['gtin13'] = $regel['gtin'];
    }
    if ($regel['beeld'] !== '') {
        $markup['image'] = $regel['beeld'];
    }
    $oud = (isset($markup['offers'][0]) && is_array($markup['offers'][0])) ? $markup['offers'][0] : array();
    unset($oud['lowPrice'], $oud['highPrice'], $oud['offerCount']);
    $oud['@type']         = 'Offer';
    $oud['price']         = $regel['prijs'];
    $oud['priceCurrency'] = 'EUR';
    $oud['availability']  = $regel['voorraad'] === 'out_of_stock' ? 'http://schema.org/OutOfStock' : 'http://schema.org/InStock';
    $oud['url']           = $regel['link'];
    if (isset($oud['priceSpecification'][0]) && is_array($oud['priceSpecification'][0])) {
        $oud['priceSpecification'][0]['price'] = $regel['prijs'];
    }
    $markup['offers'] = array($oud);
    return $markup;
}, 99, 2);
