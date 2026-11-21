<?php
/**
 * Output in the front end <head>.
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prints the meta tags, sets the document title and extends wp_robots.
 */
class Metatags_Frontend {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'print_tags' ), 1 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( $this, 'robots' ), 20 );
	}

	/**
	 * The post whose meta applies to the current request (single post/page, static front page, posts page).
	 *
	 * @return WP_Post|null
	 */
	private function current_post() {
		if ( is_singular() || ( is_home() && ! is_front_page() ) ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post && in_array( $post->post_type, Metatags_Fields::post_types(), true ) ) {
				return $post;
			}
		}
		return null;
	}

	/**
	 * Reads one field of the current post.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $name Field name from Metatags_Fields::all().
	 * @return string
	 */
	private function meta( WP_Post $post, $name ) {
		$fields = Metatags_Fields::all();
		return trim( (string) get_post_meta( $post->ID, $fields[ $name ]['key'], true ) );
	}

	/**
	 * Replaces the document title with the custom title.
	 *
	 * @param string $title Title from earlier filters.
	 * @return string
	 */
	public function document_title( $title ) {
		$post = $this->current_post();
		if ( $post && Metatags_Settings::enabled( 'title' ) && ! post_password_required( $post ) ) {
			$custom = $this->meta( $post, 'title' );
			if ( '' !== $custom ) {
				// Core prints a pre_get_document_title result without escaping it.
				return esc_html( $custom );
			}
		}
		return $title;
	}

	/**
	 * Merges the per-post robots directives into the WordPress robots meta tag.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public function robots( $robots ) {
		$post = $this->current_post();
		if ( ! $post || ! Metatags_Settings::enabled( 'robots' ) ) {
			return $robots;
		}

		$custom = Metatags_Fields::parse_robots( $this->meta( $post, 'robots' ) );
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
	 * Collects all tags for the current request.
	 *
	 * @return array<int, array{0: string, 1: string, 2: string}> List of [attribute, name, content].
	 */
	public function get_tags() {
		$post      = $this->current_post();
		$fallbacks = Metatags_Settings::enabled( 'fallbacks' );
		$tags      = array();

		if ( ! $post ) {
			if ( $fallbacks && is_front_page() && Metatags_Settings::enabled( 'og' ) ) {
				$tags[] = array( 'property', 'og:type', 'website' );
				$tags[] = array( 'property', 'og:title', get_bloginfo( 'name' ) );
				$tags[] = array( 'property', 'og:description', get_bloginfo( 'description' ) );
				$tags[] = array( 'property', 'og:url', home_url( '/' ) );
				$tags[] = array( 'property', 'og:site_name', get_bloginfo( 'name' ) );
				$tags[] = array( 'property', 'og:locale', $this->og_locale() );
				if ( Metatags_Settings::enabled( 'twitter' ) ) {
					$tags[] = array( 'name', 'twitter:card', 'summary' );
				}
			}
			return $this->filter_tags( $tags, null );
		}

		$protected   = post_password_required( $post );
		$description = $this->meta( $post, 'desc' );
		if ( '' === $description && $fallbacks && ! $protected ) {
			$description = $this->excerpt( $post );
		}

		if ( Metatags_Settings::enabled( 'description' ) && '' !== $description ) {
			$tags[] = array( 'name', 'description', $description );
		}
		if ( Metatags_Settings::enabled( 'keywords' ) ) {
			$keywords = $this->meta( $post, 'keywords' );
			if ( '' !== $keywords ) {
				$tags[] = array( 'name', 'keywords', $keywords );
			}
		}

		if ( Metatags_Settings::enabled( 'og' ) ) {
			$og         = array(
				'og:type'        => $this->meta( $post, 'og-type' ),
				'og:title'       => $this->meta( $post, 'og-title' ),
				'og:description' => $this->meta( $post, 'og-desc' ),
				'og:url'         => $this->meta( $post, 'og-url' ),
				'og:site_name'   => $this->meta( $post, 'og-site-name' ),
				'og:locale'      => '',
				'og:image'       => $this->meta( $post, 'og-image' ),
			);
			$image_meta = array();

			if ( $fallbacks ) {
				$is_article = 'post' === $post->post_type;
				$title      = $this->meta( $post, 'title' );

				$og['og:type']        = $og['og:type'] ? $og['og:type'] : ( $is_article ? 'article' : 'website' );
				$og['og:title']       = $og['og:title'] ? $og['og:title'] : ( $title ? $title : wp_strip_all_tags( get_the_title( $post ) ) );
				$og['og:description'] = $og['og:description'] ? $og['og:description'] : $description;
				$og['og:url']         = $og['og:url'] ? $og['og:url'] : (string) wp_get_canonical_url( $post );
				$og['og:site_name']   = $og['og:site_name'] ? $og['og:site_name'] : get_bloginfo( 'name' );

				if ( '' === $og['og:image'] && has_post_thumbnail( $post ) && ! $protected ) {
					$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'large' );
					if ( $image ) {
						$og['og:image'] = $image[0];
						$image_meta     = array(
							'og:image:width'  => (string) $image[1],
							'og:image:height' => (string) $image[2],
							'og:image:alt'    => trim( (string) get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true ) ),
						);
					}
				}
				$og['og:locale'] = $this->og_locale();
			}

			foreach ( array_merge( $og, $image_meta ) as $property => $content ) {
				if ( '' !== $content ) {
					$tags[] = array( 'property', $property, $content );
				}
			}

			if ( $fallbacks && 'article' === $og['og:type'] ) {
				$tags[] = array( 'property', 'article:published_time', get_post_time( 'c', true, $post ) );
				$tags[] = array( 'property', 'article:modified_time', get_post_modified_time( 'c', true, $post ) );
			}

			if ( Metatags_Settings::enabled( 'twitter' ) ) {
				$tags[] = array( 'name', 'twitter:card', '' !== $og['og:image'] ? 'summary_large_image' : 'summary' );
			}
		}

		return $this->filter_tags( $tags, $post );
	}

	/**
	 * Applies the metatags_tags filter.
	 *
	 * @param array        $tags Tags.
	 * @param WP_Post|null $post Post or null.
	 * @return array
	 */
	private function filter_tags( $tags, $post ) {
		/**
		 * Filters the tags before output.
		 *
		 * @param array        $tags List of [attribute ("name"|"property"), name, content].
		 * @param WP_Post|null $post Current post, null outside single views.
		 */
		return (array) apply_filters( 'metatags_tags', $tags, $post );
	}

	/**
	 * Locale in Open Graph format (ll_CC), e.g. "de_DE" for "de_DE_formal".
	 *
	 * @return string
	 */
	private function og_locale() {
		return preg_match( '/^[a-z]{2,3}_[A-Z]{2}/', get_locale(), $m ) ? $m[0] : '';
	}

	/**
	 * Short plain-text excerpt.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function excerpt( WP_Post $post ) {
		$text = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		$text = wp_strip_all_tags( excerpt_remove_blocks( $text ), true );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( mb_strlen( $text ) > 160 ) {
			$text = mb_substr( $text, 0, 157 );
			$text = preg_replace( '/\s+\S*$/u', '', $text ) . '…';
		}
		return $text;
	}

	/**
	 * wp_head callback.
	 */
	public function print_tags() {
		$tags = $this->get_tags();
		$post = $this->current_post();
		$html = '';

		foreach ( $tags as $tag ) {
			if ( ! is_array( $tag ) || 3 !== count( $tag ) ) {
				continue;
			}
			$tag = array_values( $tag );
			if ( ! is_scalar( $tag[1] ) || ! is_scalar( $tag[2] ) || '' === (string) $tag[2] ) {
				continue;
			}
			$attr  = 'property' === $tag[0] ? 'property' : 'name';
			$html .= sprintf( '<meta %s="%s" content="%s" />' . "\n", $attr, esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
		}

		if ( $post && Metatags_Settings::enabled( 'custom' ) && ! post_password_required( $post ) ) {
			$custom = $this->meta( $post, 'custom' );
			if ( '' !== $custom ) {
				// Saved through Metatags_Fields::sanitize_custom(): raw only for users with unfiltered_html.
				$html .= $custom . "\n";
			}
		}

		if ( '' !== $html ) {
			echo '<!-- Meta Tags ' . esc_html( METATAGS_VERSION ) . " -->\n" . $html . "<!-- /Meta Tags -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		}
	}
}
