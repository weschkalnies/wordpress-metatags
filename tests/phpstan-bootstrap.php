<?php
/**
 * Bootstrap file for PHPStan static analysis.
 *
 * @package Metatags
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'metatags/metatags.php' );
}
if ( ! defined( 'METATAGS_VERSION' ) ) {
	define( 'METATAGS_VERSION', '2.1.0' );
}
if ( ! defined( 'METATAGS_FILE' ) ) {
	define( 'METATAGS_FILE', dirname( __DIR__ ) . '/metatags.php' );
}
if ( ! defined( 'METATAGS_DIR' ) ) {
	define( 'METATAGS_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'METATAGS_URL' ) ) {
	define( 'METATAGS_URL', 'https://example.com/wp-content/plugins/metatags/' );
}
