<?php
/**
 * Tuinierwinkel Blocks — theme bootstrap.
 *
 * Pure FSE: weinig PHP nodig. Hier alleen:
 *   - patterns registreren
 *   - editor styles (optioneel)
 *   - GTM / tracking-snippets via wp_head als die uit Flatsome moeten worden geport
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'after_setup_theme', static function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'woocommerce' );

	register_block_pattern_category( 'tuinierwinkel', [ 'label' => __( 'deTuinierwinkel', 'tuinierwinkel-blocks' ) ] );
} );

add_action( 'init', static function () {
	// TODO: GTM / Meta pixel / TrackBee snippets uit Flatsome porten — sprint 1.
} );
