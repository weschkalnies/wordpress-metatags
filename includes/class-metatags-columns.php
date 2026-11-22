<?php
/**
 * Meta Tags status column on post list tables.
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a compact Meta Tags status column (noindex & custom overrides) to post list screens.
 */
class Metatags_Columns {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_columns' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Registers column hooks for all enabled post types.
	 */
	public function register_columns() {
		foreach ( Metatags_Fields::post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Enqueues admin badge styles on post list screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, Metatags_Fields::post_types(), true ) ) {
			return;
		}
		wp_enqueue_style( 'metatags-admin', METATAGS_URL . 'admin/css/metatags-admin.css', array(), METATAGS_VERSION );
	}

	/**
	 * Inserts the Meta Tags column before the date column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_column( $columns ) {
		$inserted = array();
		$placed   = false;
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$inserted['metatags'] = __( 'Meta Tags', 'metatags' );
				$placed               = true;
			}
			$inserted[ $key ] = $label;
		}
		if ( ! $placed ) {
			$inserted['metatags'] = __( 'Meta Tags', 'metatags' );
		}
		return $inserted;
	}

	/**
	 * Renders status badges for a post row.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'metatags' !== $column ) {
			return;
		}

		$badges = array();

		if ( Metatags_Settings::enabled( 'robots' ) ) {
			$robots = (string) get_post_meta( $post_id, '_metaRobots', true );
			if ( Metatags_Fields::is_noindex( $robots ) ) {
				$badges[] = sprintf(
					'<span class="metatags-badge metatags-badge--noindex" title="%s">noindex</span>',
					esc_attr__( 'Excluded from search engines and the XML sitemap.', 'metatags' )
				);
			}
		}

		if ( Metatags_Settings::enabled( 'title' ) && '' !== trim( (string) get_post_meta( $post_id, '_metaTitle', true ) ) ) {
			$badges[] = '<span class="metatags-badge">' . esc_html__( 'Title', 'metatags' ) . '</span>';
		}
		if ( Metatags_Settings::enabled( 'description' ) && '' !== trim( (string) get_post_meta( $post_id, '_metaDesc', true ) ) ) {
			$badges[] = '<span class="metatags-badge">' . esc_html__( 'Description', 'metatags' ) . '</span>';
		}
		if ( Metatags_Settings::enabled( 'canonical' ) && '' !== trim( (string) get_post_meta( $post_id, '_metaCanonical', true ) ) ) {
			$badges[] = '<span class="metatags-badge">' . esc_html__( 'Canonical', 'metatags' ) . '</span>';
		}
		if ( Metatags_Settings::enabled( 'og' ) ) {
			$has_og = '' !== trim( (string) get_post_meta( $post_id, '_metaOGTitle', true ) )
				|| '' !== trim( (string) get_post_meta( $post_id, '_metaOGDesc', true ) )
				|| '' !== trim( (string) get_post_meta( $post_id, '_metaOGImage', true ) );
			if ( $has_og ) {
				$badges[] = '<span class="metatags-badge">OG</span>';
			}
		}

		if ( empty( $badges ) ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		echo implode( ' ', $badges ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per badge above.
	}
}
