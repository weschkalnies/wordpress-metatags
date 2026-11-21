<?php
/**
 * Removes all plugin data when the plugin is deleted in wp-admin.
 *
 * @package Metatags
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$metatags_options = array( 'metatags_settings', 'chk-title', 'chk-desc', 'chk-keywords', 'chk-robots', 'chk-custom', 'chk-og' );
$metatags_keys    = array( '_metaTitle', '_metaDesc', '_metaKeywords', '_metaRobots', '_metaCustom', '_metaOGTitle', '_metaOGDesc', '_metaOGURL', '_metaOGSiteName', '_metaOGType', '_metaOGImage' );

/**
 * Deletes options and post meta of the current site.
 *
 * @param string[] $options   Option names.
 * @param string[] $meta_keys Post meta keys.
 */
function metatags_uninstall_site( $options, $meta_keys ) {
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	foreach ( $meta_keys as $key ) {
		delete_post_meta_by_key( $key );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $metatags_site_id ) {
		switch_to_blog( $metatags_site_id );
		metatags_uninstall_site( $metatags_options, $metatags_keys );
		restore_current_blog();
	}
} else {
	metatags_uninstall_site( $metatags_options, $metatags_keys );
}
