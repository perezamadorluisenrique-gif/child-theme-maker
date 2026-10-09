<?php
/**
 * Unit test bootstrap: minimal stand-ins for the WordPress functions the
 * generator uses, so its pure logic runs without a WordPress install.
 * The end-to-end behavior is covered by tests/integration/smoke.php.
 *
 * @package ChildThemeMaker
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) {
	return $text;
}

function remove_accents( $text ) {
	$converted = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
	return false === $converted ? $text : $converted;
}

function sanitize_title( $title ) {
	$title = strtolower( wp_strip_all_tags( $title ) );
	$title = preg_replace( '/[^a-z0-9 _-]/', '', $title );
	return trim( preg_replace( '/[\s_-]+/', '-', $title ), '-' );
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) ) );
}

function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

function wp_http_validate_url( $url ) {
	return filter_var( $url, FILTER_VALIDATE_URL ) && preg_match( '#^https?://#', $url ) ? $url : false;
}

require_once dirname( __DIR__, 2 ) . '/includes/class-ctmaker-generator.php';
