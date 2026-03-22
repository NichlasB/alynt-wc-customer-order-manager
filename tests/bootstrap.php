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

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook() {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook() {}
}

require_once AWCOM_PLUGIN_TEST_FILE;
