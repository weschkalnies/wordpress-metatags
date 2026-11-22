<?php
/**
 * Block editor sidebar integration.
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the native block editor sidebar script and localized configuration.
 */
class Metatags_Block_Editor {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Loads the sidebar script and settings on block editor post screens.
	 */
	public function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! Metatags_Meta_Box::supports_block_sidebar( $screen->post_type ) ) {
			return;
		}

		if ( Metatags_Settings::enabled( 'og' ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style( 'metatags-admin', METATAGS_URL . 'admin/css/metatags-admin.css', array(), METATAGS_VERSION );
		wp_enqueue_script(
			'metatags-editor',
			METATAGS_URL . 'admin/js/metatags-editor.js',
			array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
			METATAGS_VERSION,
			true
		);
		wp_localize_script(
			'metatags-editor',
			'metatagsEditor',
			array(
				'settings'       => array(
					'title'       => Metatags_Settings::enabled( 'title' ),
					'description' => Metatags_Settings::enabled( 'description' ),
					'canonical'   => Metatags_Settings::enabled( 'canonical' ),
					'robots'      => Metatags_Settings::enabled( 'robots' ),
					'keywords'    => Metatags_Settings::enabled( 'keywords' ),
					'custom'      => Metatags_Settings::enabled( 'custom' ),
					'og'          => Metatags_Settings::enabled( 'og' ),
					'fallbacks'   => Metatags_Settings::enabled( 'fallbacks' ),
				),
				'siteName'       => get_bloginfo( 'name' ),
				'defaultOgImage' => (string) Metatags_Settings::option( 'default_og_image' ),
				'defaultOgType'  => 'post' === $screen->post_type ? 'article' : 'website',
				'settingsUrl'    => admin_url( 'options-general.php?page=' . Metatags_Settings::PAGE ),
				'i18n'           => array(
					'panelTitle'      => __( 'Meta Tags', 'metatags' ),
					'searchEngines'   => __( 'Search engines', 'metatags' ),
					'socialMedia'     => __( 'Social media (Open Graph)', 'metatags' ),
					'title'           => __( 'Title', 'metatags' ),
					'description'     => __( 'Description', 'metatags' ),
					'canonical'       => __( 'Canonical URL', 'metatags' ),
					'canonicalHelp'   => __( 'Only needed if the canonical URL differs from the permalink.', 'metatags' ),
					'noindex'         => __( 'Discourage indexing (noindex)', 'metatags' ),
					'nofollow'        => __( 'Do not follow links (nofollow)', 'metatags' ),
					'moreDirectives'  => __( 'More directives', 'metatags' ),
					'moreHelp'        => __( 'Optional, comma separated: noarchive, nosnippet, noimageindex, notranslate, max-snippet:N, max-image-preview:none|standard|large, max-video-preview:N.', 'metatags' ),
					'keywords'        => __( 'Keywords', 'metatags' ),
					'keywordsHelp'    => __( 'Comma separated.', 'metatags' ),
					'customHint'      => __( 'Custom HTML can be edited in the Meta Tags box below the editor.', 'metatags' ),
					'image'           => __( 'Image', 'metatags' ),
					'chooseImage'     => __( 'Choose Open Graph image', 'metatags' ),
					'useImage'        => __( 'Use this image', 'metatags' ),
					'selectImage'     => __( 'Select image', 'metatags' ),
					'remove'          => __( 'Remove', 'metatags' ),
					'imageHelp'       => __( 'Recommended: 1200 × 630 px. Without an image the featured image or default image is used.', 'metatags' ),
					'ogType'          => __( 'Type', 'metatags' ),
					'ogTypeHelp'      => __( 'e.g. article, website, product', 'metatags' ),
					'ogUrl'           => __( 'URL', 'metatags' ),
					'ogUrlHelp'       => __( 'Only needed if it differs from the permalink.', 'metatags' ),
					'ogSiteName'      => __( 'Site name', 'metatags' ),
					'allDisabled'     => __( 'All meta tags are switched off.', 'metatags' ),
					'openSettings'    => __( 'Open settings', 'metatags' ),
					'placeholderHint' => __( 'Grey placeholder text shows the value used automatically when a field is left empty.', 'metatags' ),
					/* translators: 1: current number of characters, 2: recommended maximum. */
					'counter'         => __( '%1$d / %2$d characters', 'metatags' ),
				),
			)
		);
	}
}
