<?php
/**
 * Meta field definitions, registration and sanitizing.
 *
 * The meta keys are the same as in version 1.x, so existing data keeps working.
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central place for all per-post meta fields.
 */
class Metatags_Fields {

	/**
	 * Robots directives that take no value.
	 */
	const ROBOTS_FLAGS = array( 'all', 'index', 'noindex', 'follow', 'nofollow', 'none', 'noarchive', 'nosnippet', 'noimageindex', 'notranslate', 'indexifembedded' );

	/**
	 * Field definitions: form name => meta key, sanitizer and the setting that enables it.
	 *
	 * @return array<string, array{key: string, sanitize: callable, group: string, rest: bool}>
	 */
	public static function all() {
		return array(
			'title'        => array(
				'key'      => '_metaTitle',
				'sanitize' => array( __CLASS__, 'sanitize_line' ),
				'group'    => 'title',
				'rest'     => true,
			),
			'desc'         => array(
				'key'      => '_metaDesc',
				'sanitize' => array( __CLASS__, 'sanitize_line' ),
				'group'    => 'description',
				'rest'     => true,
			),
			'keywords'     => array(
				'key'      => '_metaKeywords',
				'sanitize' => array( __CLASS__, 'sanitize_keywords' ),
				'group'    => 'keywords',
				'rest'     => true,
			),
			'robots'       => array(
				'key'      => '_metaRobots',
				'sanitize' => array( __CLASS__, 'sanitize_robots' ),
				'group'    => 'robots',
				'rest'     => true,
			),
			'custom'       => array(
				'key'      => '_metaCustom',
				'sanitize' => array( __CLASS__, 'sanitize_custom' ),
				'group'    => 'custom',
				'rest'     => false,
			),
			'og-title'     => array(
				'key'      => '_metaOGTitle',
				'sanitize' => array( __CLASS__, 'sanitize_line' ),
				'group'    => 'og',
				'rest'     => true,
			),
			'og-desc'      => array(
				'key'      => '_metaOGDesc',
				'sanitize' => array( __CLASS__, 'sanitize_line' ),
				'group'    => 'og',
				'rest'     => true,
			),
			'og-url'       => array(
				'key'      => '_metaOGURL',
				'sanitize' => array( __CLASS__, 'sanitize_url' ),
				'group'    => 'og',
				'rest'     => true,
			),
			'og-site-name' => array(
				'key'      => '_metaOGSiteName',
				'sanitize' => array( __CLASS__, 'sanitize_line' ),
				'group'    => 'og',
				'rest'     => true,
			),
			'og-type'      => array(
				'key'      => '_metaOGType',
				'sanitize' => array( __CLASS__, 'sanitize_og_type' ),
				'group'    => 'og',
				'rest'     => true,
			),
			'og-image'     => array(
				'key'      => '_metaOGImage',
				'sanitize' => array( __CLASS__, 'sanitize_url' ),
				'group'    => 'og',
				'rest'     => true,
			),
		);
	}

	/**
	 * Post types that get the meta box and output.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		/**
		 * Filters the post types Meta Tags is active for.
		 *
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'metatags_post_types', array_values( $types ) );
	}

	/**
	 * Registers the post meta so it is typed, sanitized and (except the raw custom field) editable via REST.
	 */
	public static function register_meta() {
		foreach ( self::post_types() as $post_type ) {
			foreach ( self::all() as $field ) {
				register_post_meta(
					$post_type,
					$field['key'],
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => '',
						'show_in_rest'      => $field['rest'],
						'sanitize_callback' => $field['sanitize'],
						'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Single-line text without tags, whitespace collapsed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_line( $value ) {
		$value = sanitize_textarea_field( (string) $value );
		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Comma-separated keyword list.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_keywords( $value ) {
		$parts = array_filter( array_map( 'trim', explode( ',', self::sanitize_line( $value ) ) ), 'strlen' );
		return implode( ', ', array_unique( $parts ) );
	}

	/**
	 * Comma-separated robots directives; unknown directives are dropped.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_robots( $value ) {
		$directives = array();
		foreach ( self::parse_robots( (string) $value ) as $directive => $setting ) {
			$directives[] = true === $setting ? $directive : $directive . ':' . $setting;
		}
		return implode( ', ', $directives );
	}

	/**
	 * Parses a robots string into the array format of the wp_robots filter.
	 *
	 * @param string $value e.g. "noindex, max-snippet:50".
	 * @return array<string, true|string>
	 */
	public static function parse_robots( $value ) {
		$robots = array();
		foreach ( preg_split( '/[\s,]+/', strtolower( $value ), -1, PREG_SPLIT_NO_EMPTY ) as $directive ) {
			if ( in_array( $directive, self::ROBOTS_FLAGS, true ) ) {
				$robots[ $directive ] = true;
			} elseif ( preg_match( '/^(max-snippet|max-video-preview):(-1|\d+)$/', $directive, $m ) ) {
				$robots[ $m[1] ] = $m[2];
			} elseif ( preg_match( '/^max-image-preview:(none|standard|large)$/', $directive, $m ) ) {
				$robots['max-image-preview'] = $m[1];
			}
		}
		return $robots;
	}

	/**
	 * URL (http/https only).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_url( $value ) {
		return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	}

	/**
	 * Open Graph type such as "article", "website" or "video.movie".
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_og_type( $value ) {
		return preg_replace( '/[^a-z0-9._:]/', '', strtolower( trim( wp_strip_all_tags( (string) $value ) ) ) );
	}

	/**
	 * Custom head HTML.
	 *
	 * Users with unfiltered_html (admins on single sites) may store anything, as they could in post content.
	 * Everyone else may only add <meta> and <link> tags; every tag is rebuilt from a small attribute
	 * allowlist, so nothing else (scripts, text, redirects via http-equiv, stylesheets) gets through.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_custom( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || current_user_can( 'unfiltered_html' ) ) {
			return $value;
		}

		$allowed   = array(
			'META' => array( 'name', 'property', 'content', 'itemprop' ),
			'LINK' => array( 'rel', 'href', 'hreflang', 'type', 'title', 'media', 'sizes' ),
		);
		$link_rels = array( 'alternate', 'author', 'me', 'license', 'help' );
		$tags      = array();
		$processor = new WP_HTML_Tag_Processor( $value );

		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();
			if ( ! isset( $allowed[ $tag ] ) || $processor->is_tag_closer() ) {
				continue;
			}

			$attrs = array();
			foreach ( $allowed[ $tag ] as $attr ) {
				$attr_value = $processor->get_attribute( $attr );
				if ( ! is_string( $attr_value ) || '' === $attr_value ) {
					continue;
				}
				if ( 'href' === $attr ) {
					$attr_value = esc_url_raw( $attr_value, array( 'http', 'https' ) );
				} elseif ( 'rel' === $attr ) {
					$attr_value = implode( ' ', array_intersect( preg_split( '/\s+/', strtolower( $attr_value ) ), $link_rels ) );
				}
				if ( '' !== $attr_value ) {
					$attrs[ $attr ] = $attr_value;
				}
			}

			// A <link> needs an allowed rel and a valid href, a <meta> needs content and a name.
			if ( 'LINK' === $tag && ( empty( $attrs['rel'] ) || empty( $attrs['href'] ) ) ) {
				continue;
			}
			if ( 'META' === $tag && ( ! isset( $attrs['content'] ) || ! array_intersect_key( $attrs, array_flip( array( 'name', 'property', 'itemprop' ) ) ) ) ) {
				continue;
			}

			$html = '<' . strtolower( $tag );
			foreach ( $attrs as $attr => $attr_value ) {
				$html .= sprintf( ' %s="%s"', $attr, 'href' === $attr ? esc_url( $attr_value ) : esc_attr( $attr_value ) );
			}
			$tags[] = $html . '>';
		}

		return implode( "\n", $tags );
	}
}
