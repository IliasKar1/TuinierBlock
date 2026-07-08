<?php
/**
 * Plugin Name: deTuinierwinkel — Theme Migrator (Strangler Fig)
 * Description: Routeert per request 1 specifieke productpagina naar het nieuwe block theme "tuinierwinkel-blocks". Rest van de site blijft Flatsome. Asset-light pilot.
 * Version: 0.3.0
 * Author: Daan (web-dev-agent)
 *
 * INSTALL: dit bestand komt in wp-content/mu-plugins/tuinierwinkel-theme-migrator.php
 *          (NIET in een subfolder — WP mu-plugins worden alleen top-level geladen)
 * ROLLBACK: dit bestand verwijderen (of hernoemen naar .php.disabled) — alle requests vallen terug op het actieve hoofdtheme. Instant.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const TUINIERWINKEL_BLOCKS_THEME = 'tuinierwinkel-blocks';
const TUINIERWINKEL_PILOT_SLUG   = 'hera-4500';

function tuinierwinkel_is_pilot_request(): bool {
	static $cached = null;
	if ( $cached !== null ) {
		return $cached;
	}
	$uri  = $_SERVER['REQUEST_URI'] ?? '';
	$path = (string) parse_url( $uri, PHP_URL_PATH );
	$path = '/' . trim( $path, '/' );
	$cached = ( $path === '/product/' . TUINIERWINKEL_PILOT_SLUG );
	return $cached;
}

add_filter( 'template',   static function ( $t ) {
	return tuinierwinkel_is_pilot_request() ? TUINIERWINKEL_BLOCKS_THEME : $t;
}, 99 );

add_filter( 'stylesheet', static function ( $s ) {
	return tuinierwinkel_is_pilot_request() ? TUINIERWINKEL_BLOCKS_THEME : $s;
}, 99 );

add_action( 'send_headers', static function () {
	if ( tuinierwinkel_is_pilot_request() ) {
		header( 'X-Tuinierwinkel-Theme: ' . TUINIERWINKEL_BLOCKS_THEME );
	}
} );
