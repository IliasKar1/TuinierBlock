<?php
/**
 * Plugin Name: DTW Rebrand (mu)
 * Description: Header/footer-rebrand van deTuinierwinkel — laadt tokens, CSS en JS over Flatsome heen. Beheerd via Git (branch mu-plugins), niet handmatig bewerken.
 * Author: Daan (web-dev-agent)
 * Version: 0.1.0
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
}, 99);
