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
		add_filter( 'get_canonical_url', array( $this, 'canonical_url' ), 20, 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ), 20 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemap_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( $this, 'sitemap_taxonomies_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'sitemap_provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'sitemap_taxonomies' ), 10, 1 );
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
		if ( ! Metatags_Settings::enabled( 'title' ) ) {
			return $title;
		}
		$post = $this->current_post();
		if ( $post && ! post_password_required( $post ) ) {
			$custom = $this->meta( $post, 'title' );
			if ( '' !== $custom ) {
				// Core prints a pre_get_document_title result without escaping it.
				return esc_html( $custom );
			}
		} elseif ( Metatags_Settings::enabled( 'archives' ) && ( is_category() || is_tag() || is_tax() ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term && in_array( $term->taxonomy, Metatags_Term_Meta::taxonomies(), true ) ) {
				$custom = trim( (string) get_term_meta( $term->term_id, '_metaTitle', true ) );
				if ( '' !== $custom ) {
					return esc_html( $custom );
				}
			}
		}
		return $title;
	}

	/**
	 * Overrides the post canonical URL when a custom canonical URL is set.
	 *
	 * @param string  $canonical_url Default canonical URL.
	 * @param WP_Post $post          Post object.
	 * @return string
	 */
	public function canonical_url( $canonical_url, $post ) {
		if ( ! $post instanceof WP_Post || ! Metatags_Settings::enabled( 'canonical' ) || ! in_array( $post->post_type, Metatags_Fields::post_types(), true ) || post_password_required( $post ) ) {
			return $canonical_url;
		}
		$custom = $this->meta( $post, 'canonical' );
		return '' !== $custom ? $custom : $canonical_url;
	}

	/**
	 * Merges archive and per-post robots directives into the WordPress robots meta tag.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public function robots( $robots ) {
		$archive_noindex = ( is_search() && Metatags_Settings::enabled( 'noindex_search' ) )
			|| ( is_author() && Metatags_Settings::enabled( 'noindex_author' ) )
			|| ( is_tag() && Metatags_Settings::enabled( 'noindex_tag' ) )
			|| ( is_date() && Metatags_Settings::enabled( 'noindex_date' ) );

		if ( $archive_noindex ) {
			unset( $robots['index'], $robots['max-image-preview'] );
			$robots['noindex'] = true;
			return $robots;
		}

		if ( ( is_category() || is_tag() || is_tax() ) && Metatags_Settings::enabled( 'robots' ) && Metatags_Settings::enabled( 'archives' ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term && in_array( $term->taxonomy, Metatags_Term_Meta::taxonomies(), true ) ) {
				return Metatags_Fields::merge_robots( $robots, (string) get_term_meta( $term->term_id, '_metaRobots', true ) );
			}
			return $robots;
		}

		$post = $this->current_post();
		if ( ! $post || ! Metatags_Settings::enabled( 'robots' ) ) {
			return $robots;
		}

		return Metatags_Fields::merge_robots( $robots, $this->meta( $post, 'robots' ) );
	}

	/**
	 * Adds a meta_query clause excluding items whose _metaRobots contains noindex or none.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	private function exclude_noindex_meta_query( $args ) {
		$exclude = array(
			'relation' => 'OR',
			array(
				'key'     => '_metaRobots',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_metaRobots',
				'value'   => '(^|[,[:space:]])(noindex|none)([,[:space:]]|$)',
				'compare' => 'NOT REGEXP',
			),
		);

		if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array( $exclude ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- excludes noindex entries from sitemap.
		} else {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- preserves existing query while excluding noindex.
				'relation' => 'AND',
				$args['meta_query'],
				$exclude,
			);
		}

		return $args;
	}

	/**
	 * Excludes posts marked noindex or none from the WordPress XML sitemaps.
	 *
	 * @param array<string, mixed> $args      WP_Query arguments for the post type sitemap.
	 * @param string               $post_type Post type name.
	 * @return array<string, mixed>
	 */
	public function sitemap_query_args( $args, $post_type ) {
		if ( ! Metatags_Settings::enabled( 'robots' ) || ! in_array( $post_type, Metatags_Fields::post_types(), true ) ) {
			return $args;
		}
		return $this->exclude_noindex_meta_query( $args );
	}

	/**
	 * Excludes terms marked noindex or none from the WordPress taxonomy XML sitemaps.
	 *
	 * @param array<string, mixed> $args     WP_Term_Query arguments for the taxonomy sitemap.
	 * @param string               $taxonomy Taxonomy name.
	 * @return array<string, mixed>
	 */
	public function sitemap_taxonomies_query_args( $args, $taxonomy ) {
		if ( ! Metatags_Settings::enabled( 'robots' ) || ! in_array( $taxonomy, Metatags_Term_Meta::taxonomies(), true ) ) {
			return $args;
		}
		return $this->exclude_noindex_meta_query( $args );
	}

	/**
	 * Excludes the users sitemap provider when author archives are set to noindex.
	 *
	 * @param WP_Sitemaps_Provider|false $provider Sitemap provider instance.
	 * @param string                     $name     Provider name.
	 * @return WP_Sitemaps_Provider|false
	 */
	public function sitemap_provider( $provider, $name ) {
		if ( 'users' === $name && Metatags_Settings::enabled( 'noindex_author' ) ) {
			return false;
		}
		return $provider;
	}

	/**
	 * Excludes post tags from the taxonomy sitemap when tag archives are set to noindex.
	 *
	 * @param array<string, WP_Taxonomy> $taxonomies Taxonomies included in the sitemap.
	 * @return array<string, WP_Taxonomy>
	 */
	public function sitemap_taxonomies( $taxonomies ) {
		if ( Metatags_Settings::enabled( 'noindex_tag' ) ) {
			unset( $taxonomies['post_tag'] );
		}
		return $taxonomies;
	}

	/**
	 * Builds metadata context for supported archive views.
	 *
	 * @return array{type: string, title: string, description: string, url: string, image?: array{url: string, meta: array<string, string>}|null, fediverse?: string}|null
	 */
	private function archive_context() {
		$paged = is_paged() ? (string) get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ) ) : '';

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) {
				return null;
			}
			$url   = '' !== $paged ? $paged : get_term_link( $term );
			$title = Metatags_Settings::enabled( 'title' ) ? trim( (string) get_term_meta( $term->term_id, '_metaTitle', true ) ) : '';
			if ( '' === $title ) {
				$title = wp_strip_all_tags( single_term_title( '', false ) );
			}
			$desc = Metatags_Settings::enabled( 'description' ) ? trim( (string) get_term_meta( $term->term_id, '_metaDesc', true ) ) : '';
			if ( '' === $desc ) {
				$desc = Metatags_Fields::plain_text( term_description( $term->term_id ) );
			}
			$term_image = null;
			if ( Metatags_Settings::enabled( 'og' ) ) {
				$term_img_url = trim( (string) get_term_meta( $term->term_id, '_metaOGImage', true ) );
				if ( '' !== $term_img_url ) {
					$term_img_id = (int) get_term_meta( $term->term_id, '_metaOGImageId', true );
					$term_image  = array(
						'url'  => $term_img_url,
						'meta' => Metatags_Fields::image_meta_for_url( $term_img_url, $term_img_id ),
					);
				}
			}
			return array(
				'type'        => 'website',
				'title'       => '' !== $title ? $title : wp_strip_all_tags( $term->name ),
				'description' => $desc,
				'url'         => is_wp_error( $url ) ? '' : (string) $url,
				'image'       => $term_image,
			);
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( ! $author instanceof WP_User ) {
				return null;
			}
			return array(
				'type'        => 'profile',
				'title'       => wp_strip_all_tags( $author->display_name ),
				'description' => Metatags_Fields::plain_text( get_the_author_meta( 'description', $author->ID ) ),
				'url'         => '' !== $paged ? $paged : (string) get_author_posts_url( $author->ID ),
				'fediverse'   => Metatags_Fields::sanitize_fediverse( get_the_author_meta( 'metatags_fediverse', $author->ID ) ),
			);
		}

		if ( is_post_type_archive() ) {
			$obj = get_queried_object();
			if ( ! $obj instanceof WP_Post_Type ) {
				return null;
			}
			$title = wp_strip_all_tags( post_type_archive_title( '', false ) );
			if ( '' === $title && isset( $obj->labels->name ) ) {
				$title = wp_strip_all_tags( $obj->labels->name );
			}
			return array(
				'type'        => 'website',
				'title'       => $title,
				'description' => Metatags_Fields::plain_text( $obj->description ),
				'url'         => '' !== $paged ? $paged : (string) get_post_type_archive_link( $obj->name ),
			);
		}

		if ( is_date() ) {
			return array(
				'type'        => 'website',
				'title'       => wp_strip_all_tags( get_the_archive_title() ),
				'description' => '',
				'url'         => $paged,
			);
		}

		return null;
	}

	/**
	 * Appends description, Open Graph, Twitter and Fediverse tags for non-singular views (home/archives).
	 *
	 * @param array<int, array{0: string, 1: string, 2: string}>   $tags          Tag list (by reference).
	 * @param string                                               $type          Open Graph type.
	 * @param string                                               $title         Title.
	 * @param string                                               $desc          Description.
	 * @param string                                               $url           Canonical/archive URL.
	 * @param array{url: string, meta: array<string, string>}|null $default_image Fallback OG image.
	 * @param string                                               $twitter_site  Optional Twitter site handle.
	 * @param string                                               $fediverse     Optional Fediverse creator handle.
	 */
	private function append_social_tags_for_context( &$tags, $type, $title, $desc, $url, $default_image, $twitter_site = '', $fediverse = '' ) {
		if ( Metatags_Settings::enabled( 'description' ) && '' !== $desc ) {
			$tags[] = array( 'name', 'description', $desc );
		}

		$og_enabled = Metatags_Settings::enabled( 'og' );
		if ( $og_enabled ) {
			$tags[] = array( 'property', 'og:type', $type );
			if ( '' !== $title ) {
				$tags[] = array( 'property', 'og:title', $title );
			}
			if ( '' !== $desc ) {
				$tags[] = array( 'property', 'og:description', $desc );
			}
			if ( '' !== $url ) {
				$tags[] = array( 'property', 'og:url', $url );
			}
			$site_name = get_bloginfo( 'name' );
			if ( '' !== $site_name ) {
				$tags[] = array( 'property', 'og:site_name', $site_name );
			}
			$locale = $this->og_locale();
			if ( '' !== $locale ) {
				$tags[] = array( 'property', 'og:locale', $locale );
			}
			if ( $default_image ) {
				$tags[] = array( 'property', 'og:image', $default_image['url'] );
				foreach ( $default_image['meta'] as $property => $content ) {
					$tags[] = array( 'property', $property, $content );
				}
			}
		}

		if ( Metatags_Settings::enabled( 'twitter' ) ) {
			$tags[] = array( 'name', 'twitter:card', $default_image ? 'summary_large_image' : 'summary' );
			if ( '' !== $twitter_site ) {
				$tags[] = array( 'name', 'twitter:site', $twitter_site );
			}
			if ( ! $og_enabled ) {
				if ( '' !== $title ) {
					$tags[] = array( 'name', 'twitter:title', $title );
				}
				if ( '' !== $desc ) {
					$tags[] = array( 'name', 'twitter:description', $desc );
				}
				if ( $default_image ) {
					$tags[] = array( 'name', 'twitter:image', $default_image['url'] );
					if ( ! empty( $default_image['meta']['og:image:alt'] ) ) {
						$tags[] = array( 'name', 'twitter:image:alt', $default_image['meta']['og:image:alt'] );
					}
				}
			}
		}

		if ( '' !== $fediverse ) {
			$tags[] = array( 'name', 'fediverse:creator', $fediverse );
		}
	}

	/**
	 * Collects all tags for the current request.
	 *
	 * @return array<int, array{0: string, 1: string, 2: string}> List of [attribute, name, content].
	 */
	public function get_tags() {
		$post             = $this->current_post();
		$fallbacks        = Metatags_Settings::enabled( 'fallbacks' );
		$twitter_site     = Metatags_Fields::sanitize_twitter_handle( Metatags_Settings::option( 'twitter_site' ) );
		$global_fediverse = Metatags_Fields::sanitize_fediverse( Metatags_Settings::option( 'fediverse_creator' ) );
		$tags             = array();

		if ( ! $post ) {
			$default_image = $fallbacks ? Metatags_Fields::default_og_image() : null;

			if ( $fallbacks && is_front_page() ) {
				$this->append_social_tags_for_context(
					$tags,
					'website',
					get_bloginfo( 'name' ),
					get_bloginfo( 'description' ),
					home_url( '/' ),
					$default_image,
					$twitter_site,
					$global_fediverse
				);
			} elseif ( ! is_front_page() && Metatags_Settings::enabled( 'archives' ) ) {
				$archive = $this->archive_context();
				if ( $archive ) {
					$archive_image = ! empty( $archive['image'] ) ? $archive['image'] : $default_image;
					$fediverse     = ! empty( $archive['fediverse'] ) ? $archive['fediverse'] : $global_fediverse;
					$this->append_social_tags_for_context(
						$tags,
						$archive['type'],
						$archive['title'],
						$archive['description'],
						$archive['url'],
						$archive_image,
						$twitter_site,
						$fediverse
					);
				}
			}
			return $this->filter_tags( $tags, null );
		}

		if ( post_password_required( $post ) ) {
			return $this->filter_tags( $tags, $post );
		}

		$desc_enabled    = Metatags_Settings::enabled( 'description' );
		$custom_title    = Metatags_Settings::enabled( 'title' ) ? $this->meta( $post, 'title' ) : '';
		$description     = $desc_enabled ? $this->meta( $post, 'desc' ) : '';
		$og_enabled      = Metatags_Settings::enabled( 'og' );
		$twitter_enabled = Metatags_Settings::enabled( 'twitter' );

		if ( '' === $description && $fallbacks ) {
			$description = Metatags_Fields::excerpt( $post );
		}

		if ( $desc_enabled && '' !== $description ) {
			$tags[] = array( 'name', 'description', $description );
		}
		if ( Metatags_Settings::enabled( 'keywords' ) ) {
			$keywords = $this->meta( $post, 'keywords' );
			if ( '' !== $keywords ) {
				$tags[] = array( 'name', 'keywords', $keywords );
			}
		}

		if ( $og_enabled ) {
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

			if ( '' !== $og['og:image'] ) {
				$image_meta = Metatags_Fields::image_meta_for_url( $og['og:image'], (int) $this->meta( $post, 'og-image-id' ) );
			} elseif ( $fallbacks ) {
				$featured = Metatags_Fields::featured_image( $post );
				if ( $featured ) {
					$og['og:image'] = $featured['url'];
					if ( $featured['width'] > 0 ) {
						$image_meta['og:image:width'] = (string) $featured['width'];
					}
					if ( $featured['height'] > 0 ) {
						$image_meta['og:image:height'] = (string) $featured['height'];
					}
					if ( '' !== $featured['alt'] ) {
						$image_meta['og:image:alt'] = $featured['alt'];
					}
				} else {
					$default_image = Metatags_Fields::default_og_image();
					if ( $default_image ) {
						$og['og:image'] = $default_image['url'];
						$image_meta     = $default_image['meta'];
					}
				}
			}

			if ( $fallbacks ) {
				$og['og:type']        = $og['og:type'] ? $og['og:type'] : Metatags_Fields::default_og_type( $post );
				$og['og:title']       = $og['og:title'] ? $og['og:title'] : ( $custom_title ? $custom_title : wp_strip_all_tags( get_the_title( $post ) ) );
				$og['og:description'] = $og['og:description'] ? $og['og:description'] : $description;
				$og['og:url']         = $og['og:url'] ? $og['og:url'] : (string) wp_get_canonical_url( $post );
				$og['og:site_name']   = $og['og:site_name'] ? $og['og:site_name'] : get_bloginfo( 'name' );
				$og['og:locale']      = $this->og_locale();
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

			if ( $twitter_enabled ) {
				$tags[] = array( 'name', 'twitter:card', '' !== $og['og:image'] ? 'summary_large_image' : 'summary' );
				if ( '' !== $twitter_site ) {
					$tags[] = array( 'name', 'twitter:site', $twitter_site );
				}
			}
		} elseif ( $twitter_enabled ) {
			$tw_title = $custom_title ? $custom_title : ( $fallbacks ? wp_strip_all_tags( get_the_title( $post ) ) : '' );
			$tw_image = null;
			if ( $fallbacks ) {
				$featured = Metatags_Fields::featured_image( $post );
				if ( $featured ) {
					$tw_image = array(
						'url' => $featured['url'],
						'alt' => $featured['alt'],
					);
				} else {
					$default_image = Metatags_Fields::default_og_image();
					if ( $default_image ) {
						$tw_image = array(
							'url' => $default_image['url'],
							'alt' => isset( $default_image['meta']['og:image:alt'] ) ? $default_image['meta']['og:image:alt'] : '',
						);
					}
				}
			}

			$tags[] = array( 'name', 'twitter:card', $tw_image ? 'summary_large_image' : 'summary' );
			if ( '' !== $twitter_site ) {
				$tags[] = array( 'name', 'twitter:site', $twitter_site );
			}
			if ( '' !== $tw_title ) {
				$tags[] = array( 'name', 'twitter:title', $tw_title );
			}
			if ( '' !== $description ) {
				$tags[] = array( 'name', 'twitter:description', $description );
			}
			if ( $tw_image ) {
				$tags[] = array( 'name', 'twitter:image', $tw_image['url'] );
				if ( '' !== $tw_image['alt'] ) {
					$tags[] = array( 'name', 'twitter:image:alt', $tw_image['alt'] );
				}
			}
		}

		$author_fediverse = $post->post_author ? Metatags_Fields::sanitize_fediverse( get_the_author_meta( 'metatags_fediverse', (int) $post->post_author ) ) : '';
		$fediverse        = '' !== $author_fediverse ? $author_fediverse : $global_fediverse;
		if ( '' !== $fediverse ) {
			$tags[] = array( 'name', 'fediverse:creator', $fediverse );
		}

		return $this->filter_tags( $tags, $post );
	}

	/**
	 * Applies the metatags_tags filter.
	 *
	 * @param array<int, array{0: string, 1: string, 2: string}> $tags Tags.
	 * @param WP_Post|null                                       $post Post or null.
	 * @return array<int, mixed>
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
	 * Prints meta tags in wp_head.
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

		if ( ! is_singular() && $post && Metatags_Settings::enabled( 'canonical' ) && ! post_password_required( $post ) ) {
			$canonical = $this->meta( $post, 'canonical' );
			if ( '' !== $canonical ) {
				$html .= sprintf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
			}
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
