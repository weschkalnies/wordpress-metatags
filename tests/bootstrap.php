<?php
/**
 * PHPUnit bootstrap with lightweight WordPress test stubs.
 *
 * @package Metatags
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		public $ID = 1;
		public $post_type = 'post';
		public $post_status = 'publish';
		public $post_title = 'Hello World';
		public $post_excerpt = '';
		public $post_content = '';
		public $post_password = '';
		public $post_author = 1;
	}
}
if ( ! class_exists( 'WP_Term', false ) ) {
	class WP_Term {
		public $term_id = 10;
		public $name = 'News';
		public $taxonomy = 'category';
		public $description = '';
	}
}
if ( ! class_exists( 'WP_User', false ) ) {
	class WP_User {
		public $ID = 7;
		public $display_name = 'Jane Doe';
	}
}
if ( ! class_exists( 'WP_Post_Type', false ) ) {
	class WP_Post_Type {
		public $name = 'book';
		public $description = '';
		public $labels;
		public function __construct() {
			$this->labels = (object) array( 'name' => 'Books' );
		}
	}
}
if ( ! class_exists( 'WP_Screen', false ) ) {
	class WP_Screen {
		public $id = 'edit-post';
		public $base = 'post';
		public $post_type = 'post';
		public $taxonomy = '';
		public $block_editor = false;
		public function is_block_editor(): bool {
			return $this->block_editor;
		}
	}
}

$wp_html_api_dir = '/usr/src/wordpress/wp-includes/html-api';
if ( is_file( $wp_html_api_dir . '/class-wp-html-tag-processor.php' ) ) {
	require_once '/usr/src/wordpress/wp-includes/class-wp-token-map.php';
	require_once $wp_html_api_dir . '/class-wp-html-unsupported-exception.php';
	require_once $wp_html_api_dir . '/class-wp-html-attribute-token.php';
	require_once $wp_html_api_dir . '/class-wp-html-span.php';
	require_once $wp_html_api_dir . '/class-wp-html-text-replacement.php';
	require_once $wp_html_api_dir . '/html5-named-character-references.php';
	require_once $wp_html_api_dir . '/class-wp-html-decoder.php';
	require_once $wp_html_api_dir . '/class-wp-html-token.php';
	require_once $wp_html_api_dir . '/class-wp-html-tag-processor.php';
} elseif ( ! class_exists( 'WP_HTML_Tag_Processor', false ) ) {
	class WP_HTML_Tag_Processor {
		private $tags = array();
		private $index = -1;
		public function __construct( $html ) {
			preg_match_all( '/<(\/?)([a-zA-Z0-9-]+)([^>]*)>/', (string) $html, $matches, PREG_SET_ORDER );
			foreach ( $matches as $m ) {
				$attrs = array();
				preg_match_all( '/([a-zA-Z0-9:-]+)\s*=\s*"([^"]*)"/', $m[3], $am, PREG_SET_ORDER );
				foreach ( $am as $a ) {
					$attrs[ strtolower( $a[1] ) ] = $a[2];
				}
				$this->tags[] = array(
					'tag'    => strtoupper( $m[2] ),
					'closer' => '/' === $m[1],
					'attrs'  => $attrs,
				);
			}
		}
		public function next_tag(): bool {
			++$this->index;
			return isset( $this->tags[ $this->index ] );
		}
		public function get_tag(): string {
			return $this->tags[ $this->index ]['tag'] ?? '';
		}
		public function is_tag_closer(): bool {
			return ! empty( $this->tags[ $this->index ]['closer'] );
		}
		public function get_attribute( $name ) {
			return $this->tags[ $this->index ]['attrs'][ strtolower( $name ) ] ?? null;
		}
	}
}

function wp_test_reset(): void {
	$GLOBALS['wp_test'] = array(
		'actions'              => $GLOBALS['wp_test']['plugin_actions'] ?? array(),
		'filters'              => array(),
		'options'              => array(),
		'post_meta'            => array(),
		'term_meta'            => array(),
		'user_meta'            => array(),
		'post_types'           => array(
			'post'       => 'post',
			'page'       => 'page',
			'attachment' => 'attachment',
			'product'    => 'product',
		),
		'post_type_supports'   => array(
			'post'    => array( 'custom-fields' ),
			'page'    => array( 'custom-fields' ),
			'product' => array(),
		),
		'taxonomies'           => array(
			'category'    => 'category',
			'post_tag'    => 'post_tag',
			'post_format' => 'post_format',
		),
		'caps'                 => array(
			'unfiltered_html' => true,
			'manage_options'  => true,
			'edit_post'       => true,
			'edit_term'       => true,
		),
		'registered_meta'      => array(),
		'registered_term_meta' => array(),
		'meta_boxes'           => array(),
		'queried_object'       => null,
		'is_singular'          => false,
		'is_home'              => false,
		'is_front_page'        => false,
		'is_search'            => false,
		'is_author'            => false,
		'is_tag'               => false,
		'is_category'          => false,
		'is_tax'               => false,
		'is_post_type_archive' => false,
		'is_date'              => false,
		'is_404'               => false,
		'is_paged'             => false,
		'paged'                => 1,
		'password_required'    => false,
		'thumbnails'           => array(),
		'attachments'          => array(),
		'url_to_id'            => array(),
		'site_url'             => 'https://example.com',
		'blog_name'            => 'My Test Blog',
		'blog_desc'            => 'Just another WordPress site',
		'site_icon_url'        => '',
		'locale'               => 'de_DE_formal',
		'page_title'           => 'Meta Tags',
		'screen'               => null,
		'enqueued_styles'      => array(),
		'enqueued_scripts'     => array(),
		'localized'            => array(),
		'media_enqueued'       => false,
	);
}

wp_test_reset();

function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://example.com/wp-content/plugins/metatags/'; }
function plugin_basename( $f ) { return 'metatags/metatags.php'; }
function register_activation_hook() {}
function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['wp_test']['actions'][ $hook ][ $prio ][] = $cb;
}
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['wp_test']['filters'][ $hook ][ $prio ][] = $cb;
}
function apply_filters( $hook, $val, ...$args ) {
	if ( empty( $GLOBALS['wp_test']['filters'][ $hook ] ) ) {
		return $val;
	}
	ksort( $GLOBALS['wp_test']['filters'][ $hook ] );
	foreach ( $GLOBALS['wp_test']['filters'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$val = $cb( $val, ...$args );
		}
	}
	return $val;
}
function load_plugin_textdomain() {}
function __return_true() { return true; }
function __return_false() { return false; }
function _doing_it_wrong() {}
function wp_trigger_error() {}
function get_option( $k, $default = false ) {
	return array_key_exists( $k, $GLOBALS['wp_test']['options'] ) ? $GLOBALS['wp_test']['options'][ $k ] : $default;
}
function add_option( $k, $v ) {
	$GLOBALS['wp_test']['options'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['wp_test']['options'][ $k ] );
	return true;
}
function get_post_types( $args = array(), $output = 'names' ) {
	if ( 'objects' === $output ) {
		$objs = array();
		foreach ( $GLOBALS['wp_test']['post_types'] as $name ) {
			$obj         = new WP_Post_Type();
			$obj->name   = $name;
			$obj->labels = (object) array( 'name' => ucfirst( $name ) . 's' );
			$objs[ $name ] = $obj;
		}
		return $objs;
	}
	return $GLOBALS['wp_test']['post_types'];
}
function get_taxonomies() { return $GLOBALS['wp_test']['taxonomies']; }
function post_type_supports( $pt, $feat ) {
	return in_array( $feat, $GLOBALS['wp_test']['post_type_supports'][ $pt ] ?? array(), true );
}
function register_post_meta( $pt, $key, $args ) {
	$GLOBALS['wp_test']['registered_meta'][ $pt ][ $key ] = $args;
}
function register_term_meta( $tax, $key, $args ) {
	$GLOBALS['wp_test']['registered_term_meta'][ $tax ][ $key ] = $args;
}
function current_user_can( $cap ) {
	return $GLOBALS['wp_test']['caps'][ $cap ] ?? true;
}
function absint( $v ) { return abs( (int) $v ); }
function wp_strip_all_tags( $s, $remove_breaks = false ) {
	$s = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s );
	$s = strip_tags( $s );
	if ( $remove_breaks ) {
		$s = preg_replace( '/[\r\n\t ]+/', ' ', $s );
	}
	return trim( $s );
}
function sanitize_textarea_field( $s ) { return trim( wp_strip_all_tags( $s ) ); }
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	$scheme = parse_url( $url, PHP_URL_SCHEME );
	if ( $protocols && ! in_array( strtolower( (string) $scheme ), $protocols, true ) ) {
		return '';
	}
	return $url;
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s ) { return esc_html( $s ); }
function esc_attr__( $s ) { return esc_attr( $s ); }
function esc_html_e( $s ) { echo esc_html( $s ); }
function __( $s ) { return $s; }
function wp_kses( $s ) { return $s; }
function checked( $c, $cur = true, $echo = true ) {
	$r = ( (string) $c === (string) $cur ) ? " checked='checked'" : '';
	if ( $echo ) {
		echo $r;
	}
	return $r;
}
function has_excerpt( $p ) { return '' !== trim( (string) $p->post_excerpt ); }
function strip_shortcodes( $s ) { return preg_replace( '/\[[^\]]+\]/', '', (string) $s ); }
function excerpt_remove_blocks( $s ) { return preg_replace( '/<!--.*?-->/s', '', (string) $s ); }
function get_bloginfo( $show = '' ) {
	if ( 'name' === $show ) return $GLOBALS['wp_test']['blog_name'];
	if ( 'description' === $show ) return $GLOBALS['wp_test']['blog_desc'];
	if ( 'charset' === $show ) return 'UTF-8';
	if ( 'language' === $show ) return str_replace( '_', '-', $GLOBALS['wp_test']['locale'] );
	return '';
}
function home_url( $path = '' ) { return $GLOBALS['wp_test']['site_url'] . $path; }
function admin_url( $path = '' ) { return $GLOBALS['wp_test']['site_url'] . '/wp-admin/' . $path; }
function get_admin_page_title() { return $GLOBALS['wp_test']['page_title']; }
function get_locale() { return $GLOBALS['wp_test']['locale']; }
function get_site_icon_url() { return $GLOBALS['wp_test']['site_icon_url']; }
function is_singular() { return $GLOBALS['wp_test']['is_singular']; }
function is_home() { return $GLOBALS['wp_test']['is_home']; }
function is_front_page() { return $GLOBALS['wp_test']['is_front_page']; }
function is_search() { return $GLOBALS['wp_test']['is_search']; }
function is_author() { return $GLOBALS['wp_test']['is_author']; }
function is_tag() { return $GLOBALS['wp_test']['is_tag']; }
function is_category() { return $GLOBALS['wp_test']['is_category']; }
function is_tax() { return $GLOBALS['wp_test']['is_tax']; }
function is_post_type_archive() { return $GLOBALS['wp_test']['is_post_type_archive']; }
function is_date() { return $GLOBALS['wp_test']['is_date']; }
function is_404() { return $GLOBALS['wp_test']['is_404']; }
function is_paged() { return $GLOBALS['wp_test']['is_paged']; }
function get_query_var( $k ) { return 'paged' === $k ? $GLOBALS['wp_test']['paged'] : ''; }
function get_pagenum_link( $p ) { return 'https://example.com/archive/page/' . $p . '/'; }
function single_term_title() {
	$obj = $GLOBALS['wp_test']['queried_object'];
	return $obj instanceof WP_Term ? $obj->name : '';
}
function term_description( $term ) { return $term instanceof WP_Term ? $term->description : ''; }
function get_term_link( $term ) { return 'https://example.com/category/' . strtolower( $term->name ) . '/'; }
function is_wp_error() { return false; }
function get_the_author_meta( $field, $user_id ) {
	return $GLOBALS['wp_test']['user_meta'][ $user_id ][ $field ] ?? '';
}
function get_author_posts_url( $user_id ) { return 'https://example.com/author/user-' . $user_id . '/'; }
function post_type_archive_title() {
	$obj = $GLOBALS['wp_test']['queried_object'];
	return $obj instanceof WP_Post_Type ? $obj->labels->name : '';
}
function get_post_type_archive_link( $pt ) { return 'https://example.com/' . $pt . '/'; }
function get_the_archive_title() { return 'November 2026'; }
function get_queried_object() { return $GLOBALS['wp_test']['queried_object']; }
function post_password_required() { return $GLOBALS['wp_test']['password_required']; }
function get_post_meta( $id, $key = '', $single = false ) {
	return $GLOBALS['wp_test']['post_meta'][ $id ][ $key ] ?? ( $single ? '' : array() );
}
function update_post_meta( $id, $key, $val ) {
	$GLOBALS['wp_test']['post_meta'][ $id ][ $key ] = $val;
	return true;
}
function delete_post_meta( $id, $key ) {
	unset( $GLOBALS['wp_test']['post_meta'][ $id ][ $key ] );
	return true;
}
function get_term_meta( $id, $key = '', $single = false ) {
	return $GLOBALS['wp_test']['term_meta'][ $id ][ $key ] ?? ( $single ? '' : array() );
}
function update_term_meta( $id, $key, $val ) {
	$GLOBALS['wp_test']['term_meta'][ $id ][ $key ] = $val;
	return true;
}
function delete_term_meta( $id, $key ) {
	unset( $GLOBALS['wp_test']['term_meta'][ $id ][ $key ] );
	return true;
}
function has_post_thumbnail( $p ) {
	return ! empty( $GLOBALS['wp_test']['thumbnails'][ is_object( $p ) ? $p->ID : (int) $p ] );
}
function get_post_thumbnail_id( $p ) {
	return $GLOBALS['wp_test']['thumbnails'][ is_object( $p ) ? $p->ID : (int) $p ] ?? 0;
}
function wp_get_attachment_image_src( $att_id, $size = 'thumbnail' ) {
	return $GLOBALS['wp_test']['attachments'][ $att_id ]['sizes_src'][ $size ] ?? false;
}
function wp_attachment_is_image( $att_id ) {
	return ! empty( $GLOBALS['wp_test']['attachments'][ $att_id ]['is_image'] );
}
function wp_get_attachment_url( $att_id ) {
	return $GLOBALS['wp_test']['attachments'][ $att_id ]['url'] ?? false;
}
function wp_get_attachment_metadata( $att_id ) {
	return $GLOBALS['wp_test']['attachments'][ $att_id ]['meta'] ?? false;
}
function wp_parse_url( $url, $comp = -1 ) { return parse_url( $url, $comp ); }
function wp_basename( $path ) { return basename( $path ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function wp_get_upload_dir() { return array( 'baseurl' => 'https://example.com/wp-content/uploads' ); }
function attachment_url_to_postid( $url ) { return $GLOBALS['wp_test']['url_to_id'][ $url ] ?? 0; }
function get_the_title( $p ) { return $p->post_title; }
function get_permalink( $p ) { return 'https://example.com/post-' . $p->ID . '/'; }
function wp_get_canonical_url( $p ) { return ( new Metatags_Frontend() )->canonical_url( get_permalink( $p ), $p ); }
function get_post_time() { return '2026-11-22T10:00:00+00:00'; }
function get_post_modified_time() { return '2026-11-22T12:00:00+00:00'; }
function wp_nonce_field() {}
function wp_verify_nonce() { return 1; }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function wp_is_post_revision() { return false; }
function get_current_screen() { return $GLOBALS['wp_test']['screen']; }
function add_meta_box( $id, $title, $cb, $screen, $context, $priority, $args = null ) {
	$GLOBALS['wp_test']['meta_boxes'][ $screen ] = $args;
}
function wp_enqueue_media() { $GLOBALS['wp_test']['media_enqueued'] = true; }
function wp_enqueue_style( $h ) { $GLOBALS['wp_test']['enqueued_styles'][] = $h; }
function wp_enqueue_script( $h ) { $GLOBALS['wp_test']['enqueued_scripts'][] = $h; }
function wp_localize_script( $h, $name, $data ) { $GLOBALS['wp_test']['localized'][ $h ][ $name ] = $data; }
function settings_fields() {}
function do_settings_sections() {}
function submit_button() {}
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

require dirname( __DIR__ ) . '/metatags.php';
$GLOBALS['wp_test']['plugin_actions'] = $GLOBALS['wp_test']['actions'];
