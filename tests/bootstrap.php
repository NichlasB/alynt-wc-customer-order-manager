<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );

define( 'AWCOM_PLUGIN_TEST_FILE', dirname( __DIR__ ) . '/alynt-wc-customer-order-manager.php' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( str_replace( '\\', '/', dirname( $file ) ), '/' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['awcom_test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		$GLOBALS['awcom_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		unset( $GLOBALS['awcom_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( wp_strip_all_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook() {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook() {}
}

require_once AWCOM_PLUGIN_TEST_FILE;
