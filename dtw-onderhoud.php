<?php
/**
 * Plugin Name: DTW Onderhoud (mu)
 * Description: Eén beheeropdracht via de REST-koppeling: de volledige paginacache van Seraphinite Accelerator legen. Alleen voor beheerders (manage_options). Zo hoeft het legen niet meer via een ingelogd beheerscherm.
 * Author: Daan (web-dev-agent)
 * Version: 0.1.0
 *
 * Gebruik: POST /wp-json/dtw/v1/cache-legen, met een beheerdersaccount.
 * Uitzetten: dit bestand verwijderen. Er wordt niets in de database gewijzigd.
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('dtw/v1', '/cache-legen', array(
        'methods'             => 'POST',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
        'callback'            => function () {
            if (!class_exists('\\seraph_accel\\API')) {
                return new WP_Error('dtw_geen_cache', 'Seraphinite Accelerator is niet actief op deze site.', array('status' => 501));
            }
            $antwoord = \seraph_accel\API::OperateCache(\seraph_accel\API::CACHE_OP_DEL);
            return array(
                'gelukt'   => true,
                'tijd'     => current_time('mysql'),
                'antwoord' => (is_scalar($antwoord) || $antwoord === null) ? $antwoord : 'ok',
            );
        },
    ));
});
