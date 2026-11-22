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
	 * @return array<string, array{key: string, sanitize: callable, group: string, rest: bool, type?: string, default?: mixed}>
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
			'canonical'    => array(
				'key'      => '_metaCanonical',
				'sanitize' => array( __CLASS__, 'sanitize_url' ),
				'group'    => 'canonical',
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
			'og-image-id'  => array(
				'key'      => '_metaOGImageId',
				'sanitize' => 'absint',
				'group'    => 'og',
				'rest'     => true,
				'type'     => 'integer',
				'default'  => 0,
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
		$disabled = Metatags_Settings::option( 'disabled_post_types' );
		if ( is_array( $disabled ) && ! empty( $disabled ) ) {
			$types = array_diff( $types, $disabled );
		}

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
						'type'              => isset( $field['type'] ) ? $field['type'] : 'string',
						'single'            => true,
						'default'           => array_key_exists( 'default', $field ) ? $field['default'] : '',
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
	 * Default Open Graph type for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function default_og_type( WP_Post $post ) {
		return 'post' === $post->post_type ? 'article' : 'website';
	}

	/**
	 * Plain-text snippet stripped of blocks, shortcodes and tags, optionally trimmed at a word boundary.
	 *
	 * @param string $text       Raw text or HTML.
	 * @param int    $max_length Maximum length in characters, 0 for no truncation.
	 * @return string
	 */
	public static function plain_text( $text, $max_length = 160 ) {
		$text = wp_strip_all_tags( excerpt_remove_blocks( strip_shortcodes( (string) $text ) ), true );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( $max_length > 3 && mb_strlen( $text ) > $max_length ) {
			$text = mb_substr( $text, 0, $max_length - 3 );
			$text = preg_replace( '/\s+\S*$/u', '', $text ) . '…';
		}
		return $text;
	}

	/**
	 * Short plain-text excerpt.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function excerpt( WP_Post $post ) {
		$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		return self::plain_text( $text, 160 );
	}

	/**
	 * Global fallback Open Graph image configured in the plugin settings.
	 *
	 * @return array{url: string, meta: array<string, string>}|null
	 */
	public static function default_og_image() {
		$url = trim( (string) Metatags_Settings::option( 'default_og_image' ) );
		if ( '' === $url ) {
			return null;
		}
		$id = (int) Metatags_Settings::option( 'default_og_image_id' );
		return array(
			'url'  => $url,
			'meta' => self::image_meta_for_url( $url, $id ),
		);
	}

	/**
	 * Featured image URL, dimensions and alt text.
	 *
	 * @param WP_Post $post Post.
	 * @return array{url: string, width: int, height: int, alt: string}|null
	 */
	public static function featured_image( WP_Post $post ) {
		if ( ! has_post_thumbnail( $post ) ) {
			return null;
		}
		$thumb_id = (int) get_post_thumbnail_id( $post );
		$image    = wp_get_attachment_image_src( $thumb_id, '1536x1536' );
		if ( ! $image ) {
			return null;
		}

		return array(
			'url'    => $image[0],
			'width'  => (int) $image[1],
			'height' => (int) $image[2],
			'alt'    => trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ),
		);
	}

	/**
	 * Checks whether a URL belongs to a given image attachment and returns its dimensions.
	 *
	 * @param string $url           Image URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return array{width: int, height: int}|null
	 */
	public static function match_attachment_image( $url, $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$url           = (string) $url;
		if ( '' === $url || $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$attached_url = wp_get_attachment_url( $attachment_id );
		if ( ! $attached_url ) {
			return null;
		}

		if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( $attached_url, PHP_URL_HOST ) ) ) {
			return null;
		}

		$url_path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$att_path = (string) wp_parse_url( $attached_url, PHP_URL_PATH );
		if ( '' === $url_path || '' === $att_path || dirname( $url_path ) !== dirname( $att_path ) ) {
			return null;
		}

		$filename = wp_basename( $url_path );
		$meta     = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
			if ( wp_basename( $meta['file'] ) === $filename ) {
				return array(
					'width'  => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
					'height' => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
				);
			}
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size ) {
					if ( is_array( $size ) && isset( $size['file'] ) && $size['file'] === $filename ) {
						return array(
							'width'  => isset( $size['width'] ) ? (int) $size['width'] : 0,
							'height' => isset( $size['height'] ) ? (int) $size['height'] : 0,
						);
					}
				}
			}
		}

		if ( wp_basename( $att_path ) === $filename ) {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		}

		return null;
	}

	/**
	 * Open Graph image metadata (width, height, alt) when the URL matches the attachment.
	 *
	 * @param string $url           Image URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return array<string, string>
	 */
	public static function image_meta_for_url( $url, $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$match         = self::match_attachment_image( $url, $attachment_id );
		if ( null === $match ) {
			return array();
		}

		$image_meta = array();
		if ( $match['width'] > 0 ) {
			$image_meta['og:image:width'] = (string) $match['width'];
		}
		if ( $match['height'] > 0 ) {
			$image_meta['og:image:height'] = (string) $match['height'];
		}
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt ) {
			$image_meta['og:image:alt'] = $alt;
		}

		return $image_meta;
	}

	/**
	 * Resolves a local upload URL to an image attachment ID (for admin save only).
	 *
	 * @param string $url Image URL.
	 * @return int Attachment ID or 0 if not found.
	 */
	public static function attachment_id_from_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return 0;
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return 0;
		}

		$url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$base_host = strtolower( (string) wp_parse_url( $uploads['baseurl'], PHP_URL_HOST ) );
		$url_path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$base_path = trailingslashit( (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) );

		if ( '' === $url_host || $url_host !== $base_host || 0 !== strpos( $url_path, $base_path ) ) {
			return 0;
		}

		$id = (int) attachment_url_to_postid( $url );
		if ( 0 === $id ) {
			$full_url = preg_replace( '/-\d+x\d+(?=\.[^.]+$)/', '', $url );
			if ( is_string( $full_url ) && $full_url !== $url ) {
				$id = (int) attachment_url_to_postid( $full_url );
			}
		}

		return ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;
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
		$parts = array_filter(
			array_map( 'trim', explode( ',', self::sanitize_line( $value ) ) ),
			static function ( $part ) {
				return '' !== $part;
			}
		);
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
	 * Splits a robots directive string into UI flags and remaining directives.
	 *
	 * @param string $value Stored robots value.
	 * @return array{noindex: bool, nofollow: bool, extra: string}
	 */
	public static function split_robots( $value ) {
		$parsed = self::parse_robots( (string) $value );
		if ( isset( $parsed['none'] ) ) {
			unset( $parsed['none'] );
			$parsed['noindex']  = true;
			$parsed['nofollow'] = true;
		}

		$noindex  = ! empty( $parsed['noindex'] );
		$nofollow = ! empty( $parsed['nofollow'] );
		unset( $parsed['noindex'], $parsed['nofollow'] );

		$extra = array();
		foreach ( $parsed as $directive => $setting ) {
			$extra[] = true === $setting ? $directive : $directive . ':' . $setting;
		}

		return array(
			'noindex'  => $noindex,
			'nofollow' => $nofollow,
			'extra'    => implode( ', ', $extra ),
		);
	}

	/**
	 * Combines UI flags and extra directives into a normalized robots string.
	 *
	 * @param bool   $noindex  Whether noindex is checked.
	 * @param bool   $nofollow Whether nofollow is checked.
	 * @param string $extra    Additional comma/space-separated directives.
	 * @return string
	 */
	public static function combine_robots( $noindex, $nofollow, $extra ) {
		$parsed  = self::parse_robots( (string) $extra );
		$ordered = array();

		if ( $noindex ) {
			$ordered['noindex'] = true;
			unset( $parsed['index'] );
		}
		if ( $nofollow ) {
			$ordered['nofollow'] = true;
			unset( $parsed['follow'] );
		}
		if ( $noindex || $nofollow ) {
			unset( $parsed['noindex'], $parsed['nofollow'], $parsed['none'] );
		}

		$directives = array();
		foreach ( array_merge( $ordered, $parsed ) as $directive => $setting ) {
			$directives[] = true === $setting ? $directive : $directive . ':' . $setting;
		}

		return implode( ', ', $directives );
	}

	/**
	 * Checks whether a stored robots string disables indexing (noindex or none).
	 *
	 * @param string $value Stored robots value.
	 * @return bool
	 */
	public static function is_noindex( $value ) {
		$parsed = self::parse_robots( (string) $value );
		return ! empty( $parsed['noindex'] ) || ! empty( $parsed['none'] );
	}

	/**
	 * Merges a stored robots string into an existing wp_robots array.
	 *
	 * @param array<string, true|string> $robots Existing WordPress robots directives.
	 * @param string                     $value  Stored robots value.
	 * @return array<string, true|string>
	 */
	public static function merge_robots( $robots, $value ) {
		$custom = self::parse_robots( (string) $value );
		if ( empty( $custom ) ) {
			return $robots;
		}
		if ( isset( $custom['none'] ) ) {
			unset( $custom['none'] );
			$custom['noindex']  = true;
			$custom['nofollow'] = true;
		}
		$opposites = array(
			'noindex'  => 'index',
			'nofollow' => 'follow',
			'index'    => 'noindex',
			'follow'   => 'nofollow',
		);
		foreach ( $opposites as $directive => $opposite ) {
			if ( isset( $custom[ $directive ] ) ) {
				unset( $robots[ $opposite ] );
			}
		}
		// Large image previews make no sense on pages that must not be indexed.
		if ( isset( $custom['noindex'] ) ) {
			unset( $robots['max-image-preview'] );
		}

		return array_merge( $robots, $custom );
	}

	/**
	 * Sanitizes a Fediverse / Mastodon handle (e.g. "@user@mastodon.social" or profile URL).
	 *
	 * @param mixed $value Raw value.
	 * @return string Normalized "@user@host" handle or empty string if invalid.
	 */
	public static function sanitize_fediverse( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '#^https?://([a-z0-9.-]+\.[a-z]{2,})/@([a-z0-9._-]+)/?$#i', $value, $matches ) ) {
			$value = '@' . $matches[2] . '@' . strtolower( $matches[1] );
		} else {
			$value = '@' . ltrim( $value, '@' );
			if ( preg_match( '/^(@[a-z0-9._-]+@)([a-z0-9.-]+\.[a-z]{2,})$/i', $value, $matches ) ) {
				$value = $matches[1] . strtolower( $matches[2] );
			}
		}

		return preg_match( '/^@[a-z0-9._-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $value ) ? $value : '';
	}

	/**
	 * Sanitizes a Twitter / X handle (e.g. "@example" or "https://x.com/example").
	 *
	 * @param mixed $value Raw value.
	 * @return string Normalized "@handle" or empty string if invalid.
	 */
	public static function sanitize_twitter_handle( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		$value  = preg_replace( '#^https?://(?:www\.)?(?:twitter|x)\.com/#i', '', $value );
		$value  = strtok( (string) $value, '/?#' );
		$handle = preg_replace( '/[^A-Za-z0-9_]/', '', ltrim( (string) $value, '@' ) );
		$handle = substr( (string) $handle, 0, 15 );

		return '' !== $handle ? '@' . $handle : '';
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
