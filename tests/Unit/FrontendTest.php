<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FrontendTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset();
		$GLOBALS['wp_test']['options']['metatags_settings'] = Metatags_Settings::defaults();
	}

	public function test_single_post_emits_twitter_site_and_author_fediverse_with_global_fallback(): void {
		$post              = new WP_Post();
		$post->ID          = 5;
		$post->post_author = 7;

		$GLOBALS['wp_test']['is_singular']    = true;
		$GLOBALS['wp_test']['queried_object'] = $post;
		$GLOBALS['wp_test']['options']['metatags_settings']['twitter_site']      = '@mysite';
		$GLOBALS['wp_test']['options']['metatags_settings']['fediverse_creator'] = '@site@mastodon.social';
		$GLOBALS['wp_test']['user_meta'][7]['metatags_fediverse']                = 'https://fosstodon.org/@author';

		$frontend = new Metatags_Frontend();
		$tags     = $frontend->get_tags();

		$this->assertContains( array( 'name', 'twitter:site', '@mysite' ), $tags );
		$this->assertContains( array( 'name', 'fediverse:creator', '@author@fosstodon.org' ), $tags );

		$GLOBALS['wp_test']['user_meta'][7]['metatags_fediverse'] = '';
		$tags_fallback = $frontend->get_tags();
		$this->assertContains( array( 'name', 'fediverse:creator', '@site@mastodon.social' ), $tags_fallback );
	}

	public function test_term_meta_overrides_title_description_robots_and_og_image(): void {
		$term              = new WP_Term();
		$term->term_id     = 12;
		$term->name        = 'PHP';
		$term->taxonomy    = 'category';
		$term->description = 'Original term description';

		$GLOBALS['wp_test']['is_category']    = true;
		$GLOBALS['wp_test']['queried_object'] = $term;
		$GLOBALS['wp_test']['attachments'][99] = array(
			'is_image' => true,
			'url'      => 'https://example.com/wp-content/uploads/2026/11/php.jpg',
			'meta'     => array(
				'file'   => '2026/11/php.jpg',
				'width'  => 1200,
				'height' => 630,
			),
		);
		update_term_meta( 12, '_metaTitle', 'Custom PHP Title' );
		update_term_meta( 12, '_metaDesc', 'Custom PHP Meta Desc' );
		update_term_meta( 12, '_metaRobots', 'noindex, noarchive' );
		update_term_meta( 12, '_metaOGImage', 'https://example.com/wp-content/uploads/2026/11/php.jpg' );
		update_term_meta( 12, '_metaOGImageId', 99 );

		$frontend = new Metatags_Frontend();

		$this->assertSame( 'Custom PHP Title', $frontend->document_title( 'Default' ) );
		$this->assertSame(
			array(
				'noindex'   => true,
				'noarchive' => true,
			),
			$frontend->robots( array( 'index' => true, 'max-image-preview' => 'large' ) )
		);

		$tags = $frontend->get_tags();
		$this->assertContains( array( 'name', 'description', 'Custom PHP Meta Desc' ), $tags );
		$this->assertContains( array( 'property', 'og:title', 'Custom PHP Title' ), $tags );
		$this->assertContains( array( 'property', 'og:image', 'https://example.com/wp-content/uploads/2026/11/php.jpg' ), $tags );
		$this->assertContains( array( 'property', 'og:image:width', '1200' ), $tags );
	}

	public function test_taxonomy_sitemap_excludes_noindex_terms(): void {
		$frontend = new Metatags_Frontend();
		$args     = $frontend->sitemap_taxonomies_query_args( array(), 'category' );

		$this->assertArrayHasKey( 'meta_query', $args );
		$this->assertSame( 'NOT REGEXP', $args['meta_query'][0][1]['compare'] );
	}

	public function test_password_protected_post_hides_tags_and_canonical(): void {
		$post = new WP_Post();
		update_post_meta( 1, '_metaCanonical', 'https://other.example/secret' );
		update_post_meta( 1, '_metaDesc', 'Secret' );

		$GLOBALS['wp_test']['is_singular']       = true;
		$GLOBALS['wp_test']['queried_object']    = $post;
		$GLOBALS['wp_test']['password_required'] = true;

		$frontend = new Metatags_Frontend();
		$this->assertSame( 'https://example.com/post-1/', $frontend->canonical_url( 'https://example.com/post-1/', $post ) );
		$this->assertSame( array(), $frontend->get_tags() );
	}
}
