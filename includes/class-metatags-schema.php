<?php
/**
 * Minimal Schema.org JSON-LD output.
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Outputs structured data (WebSite, Organization, WebPage, Article/BlogPosting, CollectionPage/ProfilePage).
 */
class Metatags_Schema {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'print_schema' ), 2 );
	}

	/**
	 * Resolves the active singular post for schema output.
	 *
	 * @return WP_Post|null
	 */
	private function current_post() {
		if ( is_singular() || ( is_home() && ! is_front_page() ) ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				return $post;
			}
		}
		return null;
	}

	/**
	 * Builds the Schema.org @graph nodes for the current request or given post.
	 *
	 * @param WP_Post|null $post Optional post for direct testing; defaults to the current query.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_graph( $post = null ) {
		if ( ! Metatags_Settings::enabled( 'schema' ) || is_search() || is_404() ) {
			return array();
		}

		if ( null === $post ) {
			$post = $this->current_post();
		}

		if ( $post instanceof WP_Post ) {
			if ( ! in_array( $post->post_type, Metatags_Fields::post_types(), true ) || post_password_required( $post ) ) {
				return array();
			}
		}

		$home      = home_url( '/' );
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$lang      = get_bloginfo( 'language' );
		if ( '' === $lang ) {
			$lang = str_replace( '_', '-', get_locale() );
		}

		$website = array(
			'@type'     => 'WebSite',
			'@id'       => $home . '#website',
			'url'       => $home,
			'name'      => $site_name,
			'publisher' => array( '@id' => $home . '#organization' ),
		);
		if ( '' !== $site_desc ) {
			$website['description'] = $site_desc;
		}
		if ( '' !== $lang ) {
			$website['inLanguage'] = $lang;
		}
		if ( is_front_page() ) {
			$website['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => $home . '?s={search_term_string}',
				'query-input' => 'required name=search_term_string',
			);
		}

		$organization = array(
			'@type' => 'Organization',
			'@id'   => $home . '#organization',
			'name'  => $site_name,
			'url'   => $home,
		);
		$site_icon    = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
		if ( '' !== $site_icon ) {
			$organization['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $site_icon,
			);
		}

		$graph = array( $website, $organization );

		if ( $post instanceof WP_Post ) {
			$this->append_post_nodes( $graph, $post, $home, $lang );
		} elseif ( ! is_front_page() && Metatags_Settings::enabled( 'archives' ) ) {
			$archive_node = $this->archive_node( $home, $lang );
			if ( $archive_node ) {
				$graph[] = $archive_node;
			}
		}

		/**
		 * Filters the Schema.org @graph nodes before JSON-LD encoding.
		 *
		 * @param array<int, array<string, mixed>> $graph List of Schema.org nodes.
		 * @param WP_Post|null                     $post  Current post, null on non-singular views.
		 */
		return (array) apply_filters( 'metatags_schema', $graph, $post );
	}

	/**
	 * Appends WebPage and (for article-typed posts) BlogPosting/Article nodes.
	 *
	 * @param array<int, array<string, mixed>> $graph Graph nodes (by reference).
	 * @param WP_Post                          $post  Post object.
	 * @param string                           $home  Home URL.
	 * @param string                           $lang  Language tag.
	 */
	private function append_post_nodes( &$graph, WP_Post $post, $home, $lang ) {
		$canonical = (string) wp_get_canonical_url( $post );
		if ( '' === $canonical ) {
			$canonical = (string) get_permalink( $post );
		}

		$fallbacks = Metatags_Settings::enabled( 'fallbacks' );
		$title     = Metatags_Settings::enabled( 'title' ) ? trim( (string) get_post_meta( $post->ID, '_metaTitle', true ) ) : '';
		if ( '' === $title ) {
			$title = wp_strip_all_tags( get_the_title( $post ) );
		}

		$desc = Metatags_Settings::enabled( 'description' ) ? trim( (string) get_post_meta( $post->ID, '_metaDesc', true ) ) : '';
		if ( '' === $desc && $fallbacks ) {
			$desc = Metatags_Fields::excerpt( $post );
		}

		$image_obj = $this->resolve_post_image( $post, $fallbacks );
		$published = get_post_time( 'c', true, $post );
		$modified  = get_post_modified_time( 'c', true, $post );

		$webpage = array(
			'@type'         => 'WebPage',
			'@id'           => $canonical . '#webpage',
			'url'           => $canonical,
			'name'          => $title,
			'isPartOf'      => array( '@id' => $home . '#website' ),
			'datePublished' => $published,
			'dateModified'  => $modified,
		);
		if ( '' !== $desc ) {
			$webpage['description'] = $desc;
		}
		if ( '' !== $lang ) {
			$webpage['inLanguage'] = $lang;
		}
		if ( $image_obj ) {
			$webpage['primaryImageOfPage'] = $image_obj;
		}

		$graph[] = $webpage;

		$og_type = trim( (string) get_post_meta( $post->ID, '_metaOGType', true ) );
		if ( '' === $og_type ) {
			$og_type = Metatags_Fields::default_og_type( $post );
		}

		if ( 'article' === $og_type ) {
			$article = array(
				'@type'            => 'post' === $post->post_type ? 'BlogPosting' : 'Article',
				'@id'              => $canonical . '#article',
				'isPartOf'         => array( '@id' => $canonical . '#webpage' ),
				'mainEntityOfPage' => array( '@id' => $canonical . '#webpage' ),
				'headline'         => $title,
				'datePublished'    => $published,
				'dateModified'     => $modified,
				'publisher'        => array( '@id' => $home . '#organization' ),
			);
			if ( '' !== $desc ) {
				$article['description'] = $desc;
			}
			if ( '' !== $lang ) {
				$article['inLanguage'] = $lang;
			}
			if ( $image_obj ) {
				$article['image'] = $image_obj;
			}
			$author_id   = (int) $post->post_author;
			$author_name = $author_id > 0 ? trim( (string) get_the_author_meta( 'display_name', $author_id ) ) : '';
			if ( '' !== $author_name ) {
				$article['author'] = array(
					'@type' => 'Person',
					'name'  => $author_name,
					'url'   => (string) get_author_posts_url( $author_id ),
				);
			}
			$graph[] = $article;
		}
	}

	/**
	 * Resolves the primary image for a post into a Schema.org ImageObject array.
	 *
	 * @param WP_Post $post      Post object.
	 * @param bool    $fallbacks Whether automatic fallbacks are active.
	 * @return array<string, mixed>|null
	 */
	private function resolve_post_image( WP_Post $post, $fallbacks ) {
		$og_url = trim( (string) get_post_meta( $post->ID, '_metaOGImage', true ) );
		if ( '' !== $og_url ) {
			$meta = Metatags_Fields::image_meta_for_url( $og_url, (int) get_post_meta( $post->ID, '_metaOGImageId', true ) );
			return $this->image_object(
				$og_url,
				isset( $meta['og:image:width'] ) ? (int) $meta['og:image:width'] : 0,
				isset( $meta['og:image:height'] ) ? (int) $meta['og:image:height'] : 0
			);
		}

		if ( ! $fallbacks ) {
			return null;
		}

		$featured = Metatags_Fields::featured_image( $post );
		if ( $featured ) {
			return $this->image_object( $featured['url'], $featured['width'], $featured['height'] );
		}

		$default = Metatags_Fields::default_og_image();
		if ( $default ) {
			return $this->image_object(
				$default['url'],
				isset( $default['meta']['og:image:width'] ) ? (int) $default['meta']['og:image:width'] : 0,
				isset( $default['meta']['og:image:height'] ) ? (int) $default['meta']['og:image:height'] : 0
			);
		}

		return null;
	}

	/**
	 * Formats an ImageObject node.
	 *
	 * @param string $url    Image URL.
	 * @param int    $width  Width in pixels (optional).
	 * @param int    $height Height in pixels (optional).
	 * @return array<string, mixed>
	 */
	private function image_object( $url, $width = 0, $height = 0 ) {
		$obj = array(
			'@type' => 'ImageObject',
			'url'   => $url,
		);
		if ( $width > 0 && $height > 0 ) {
			$obj['width']  = $width;
			$obj['height'] = $height;
		}
		return $obj;
	}

	/**
	 * Builds a CollectionPage or ProfilePage node for supported archives.
	 *
	 * @param string $home Home URL.
	 * @param string $lang Language tag.
	 * @return array<string, mixed>|null
	 */
	private function archive_node( $home, $lang ) {
		$type  = 'CollectionPage';
		$title = '';
		$desc  = '';
		$url   = is_paged() ? (string) get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ) ) : '';

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) {
				return null;
			}
			$title = Metatags_Settings::enabled( 'title' ) ? trim( (string) get_term_meta( $term->term_id, '_metaTitle', true ) ) : '';
			if ( '' === $title ) {
				$title = wp_strip_all_tags( single_term_title( '', false ) );
			}
			$desc = Metatags_Settings::enabled( 'description' ) ? trim( (string) get_term_meta( $term->term_id, '_metaDesc', true ) ) : '';
			if ( '' === $desc ) {
				$desc = Metatags_Fields::plain_text( term_description( $term->term_id ) );
			}
			if ( '' === $url ) {
				$term_link = get_term_link( $term );
				$url       = is_wp_error( $term_link ) ? '' : (string) $term_link;
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();
			if ( ! $author instanceof WP_User ) {
				return null;
			}
			$type  = 'ProfilePage';
			$title = wp_strip_all_tags( $author->display_name );
			$desc  = Metatags_Fields::plain_text( get_the_author_meta( 'description', $author->ID ) );
			if ( '' === $url ) {
				$url = (string) get_author_posts_url( $author->ID );
			}
		} elseif ( is_post_type_archive() ) {
			$obj = get_queried_object();
			if ( ! $obj instanceof WP_Post_Type ) {
				return null;
			}
			$title = wp_strip_all_tags( post_type_archive_title( '', false ) );
			if ( '' === $title && isset( $obj->labels->name ) ) {
				$title = wp_strip_all_tags( $obj->labels->name );
			}
			$desc = Metatags_Fields::plain_text( $obj->description );
			if ( '' === $url ) {
				$url = (string) get_post_type_archive_link( $obj->name );
			}
		} elseif ( is_date() ) {
			$title = wp_strip_all_tags( get_the_archive_title() );
		} else {
			return null;
		}

		$node = array(
			'@type'    => $type,
			'name'     => $title,
			'isPartOf' => array( '@id' => $home . '#website' ),
		);
		if ( '' !== $url ) {
			$node['url'] = $url;
		}
		if ( '' !== $desc ) {
			$node['description'] = $desc;
		}
		if ( '' !== $lang ) {
			$node['inLanguage'] = $lang;
		}
		return $node;
	}

	/**
	 * Prints the JSON-LD script block in wp_head.
	 */
	public function print_schema() {
		$graph = $this->get_graph();
		if ( empty( $graph ) ) {
			return;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);
		$json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
		if ( ! is_string( $json ) ) {
			return;
		}

		echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- encoded with JSON_HEX_TAG.
	}
}
