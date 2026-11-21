<?php
/**
 * Plugin Name:       Meta Tags
 * Plugin URI:        https://github.com/weschkalnies/wordpress-metatags
 * Description:       Per-post meta title, description, robots, keywords, Open Graph and Twitter Card tags.
 * Version:           2.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Nick Weschkalnies
 * Author URI:        https://www.weschkalnies.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       metatags
 * Domain Path:       /languages
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

define( 'METATAGS_VERSION', '2.0.1' );
define( 'METATAGS_FILE', __FILE__ );
define( 'METATAGS_DIR', plugin_dir_path( __FILE__ ) );
define( 'METATAGS_URL', plugin_dir_url( __FILE__ ) );

require_once METATAGS_DIR . 'includes/class-metatags-fields.php';
require_once METATAGS_DIR . 'includes/class-metatags-settings.php';
require_once METATAGS_DIR . 'includes/class-metatags-meta-box.php';
require_once METATAGS_DIR . 'includes/class-metatags-frontend.php';

register_activation_hook( __FILE__, array( 'Metatags_Settings', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Metatags_Settings::maybe_migrate();

		( new Metatags_Settings() )->register();
		( new Metatags_Meta_Box() )->register();
		( new Metatags_Frontend() )->register();
	}
);

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'metatags', false, dirname( plugin_basename( METATAGS_FILE ) ) . '/languages' );
		Metatags_Fields::register_meta();
	}
);
