<?php
/**
 * Plugin Name: DTW Rebrand (mu)
 * Description: Header/footer-rebrand van deTuinierwinkel — laadt tokens, CSS en JS over Flatsome heen. Beheerd via Git (branch mu-plugins), niet handmatig bewerken.
 * Author: Daan (web-dev-agent)
 * Version: 0.3.0
 */

defined('ABSPATH') || exit;

define('DTW_REBRAND_DIR', WPMU_PLUGIN_DIR . '/dtw-rebrand');
define('DTW_REBRAND_URL', WPMU_PLUGIN_URL . '/dtw-rebrand');

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $css = DTW_REBRAND_DIR . '/rebrand.css';
    $js  = DTW_REBRAND_DIR . '/rebrand.js';

    wp_enqueue_style(
        'dtw-rebrand-fonts',
        'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Manrope:wght@400;500;600;700&display=swap',
        [],
        null
    );
    if (file_exists($css)) {
        wp_enqueue_style('dtw-rebrand', DTW_REBRAND_URL . '/rebrand.css', ['dtw-rebrand-fonts'], (string) filemtime($css));
    }
    if (file_exists($js)) {
        wp_enqueue_script('dtw-rebrand', DTW_REBRAND_URL . '/rebrand.js', [], (string) filemtime($js), true);
    }

    // Beweging (proef 2026-11-21): paginaovergangen en scroll-animaties.
    // Uit voor één pagina: ?beweging=uit achter het adres. Helemaal uit: motion.css en motion.js verwijderen.
    $mcss = DTW_REBRAND_DIR . '/motion.css';
    $mjs  = DTW_REBRAND_DIR . '/motion.js';
    if (!isset($_GET['beweging']) && file_exists($mcss) && file_exists($mjs)) {
        wp_enqueue_style('dtw-motion', DTW_REBRAND_URL . '/motion.css', [], (string) filemtime($mcss));
        wp_enqueue_script('dtw-motion', DTW_REBRAND_URL . '/motion.js', [], (string) filemtime($mjs), true);
    }
}, 99);

/**
 * Tabblad- en app-iconen van het nieuwe logo (v1.3).
 * Vervangt op de voorkant het site-icoon uit de Customizer; beheer en
 * inlogscherm blijven ongemoeid. Weghalen = map dtw-rebrand/logo verwijderen.
 */
add_action('init', function () {
    if (file_exists(DTW_REBRAND_DIR . '/logo/favicon.svg')) {
        remove_action('wp_head', 'wp_site_icon', 99);
    }
});

add_action('wp_head', function () {
    $dir = DTW_REBRAND_DIR . '/logo';
    if (!file_exists($dir . '/favicon.svg')) {
        return;
    }
    $url = esc_url(DTW_REBRAND_URL . '/logo');
    $v   = (string) filemtime($dir . '/favicon.svg');
    echo '<link rel="icon" type="image/svg+xml" href="' . $url . '/favicon.svg?v=' . $v . '">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $url . '/favicon-32.png?v=' . $v . '">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $url . '/favicon-16.png?v=' . $v . '">' . PHP_EOL;
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . $url . '/icon-192.png?v=' . $v . '">' . PHP_EOL;
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $url . '/apple-touch-icon-180.png?v=' . $v . '">' . PHP_EOL;
}, 2);
