<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset();
		$GLOBALS['wp_test']['options']['metatags_settings'] = Metatags_Settings::defaults();
	}

	public function test_front_page_outputs_website_search_action_and_organization(): void {
		$GLOBALS['wp_test']['is_front_page'] = true;
		$GLOBALS['wp_test']['site_icon_url'] = 'https://example.com/wp-content/uploads/icon.png';

		$schema = new Metatags_Schema();
		$graph  = $schema->get_graph();

		$this->assertCount( 2, $graph );
		$this->assertSame( 'WebSite', $graph[0]['@type'] );
		$this->assertArrayHasKey( 'potentialAction', $graph[0] );
		$this->assertSame( 'Organization', $graph[1]['@type'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/icon.png', $graph[1]['logo']['url'] );
	}

	public function test_singular_post_outputs_webpage_and_blog_posting_and_escapes_script_tag(): void {
		$post               = new WP_Post();
		$post->ID           = 9;
		$post->post_type    = 'post';
		$post->post_title   = 'Hello </script> World';
		$post->post_content = 'Some post content for schema.';
		$post->post_author  = 3;

		$GLOBALS['wp_test']['is_singular']    = true;
		$GLOBALS['wp_test']['queried_object'] = $post;
		$GLOBALS['wp_test']['user_meta'][3]['display_name'] = 'Max Mustermann';

		$schema = new Metatags_Schema();
		$graph  = $schema->get_graph();

		$types = array_column( $graph, '@type' );
		$this->assertSame( array( 'WebSite', 'Organization', 'WebPage', 'BlogPosting' ), $types );
		$this->assertSame( 'Max Mustermann', $graph[3]['author']['name'] );

		ob_start();
		$schema->print_schema();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="application/ld+json">', $output );
		$this->assertStringNotContainsString( 'Hello </script>', $output );
	}

	public function test_schema_disabled_or_password_protected_returns_empty(): void {
		$post = new WP_Post();
		$GLOBALS['wp_test']['is_singular']       = true;
		$GLOBALS['wp_test']['queried_object']    = $post;
		$GLOBALS['wp_test']['password_required'] = true;

		$schema = new Metatags_Schema();
		$this->assertSame( array(), $schema->get_graph() );

		$GLOBALS['wp_test']['password_required']             = false;
		$GLOBALS['wp_test']['options']['metatags_settings']['schema'] = false;
		$this->assertSame( array(), $schema->get_graph() );
	}

	public function test_archive_outputs_collection_or_profile_page(): void {
		$GLOBALS['wp_test']['is_author']      = true;
		$GLOBALS['wp_test']['queried_object'] = new WP_User();

		$schema = new Metatags_Schema();
		$graph  = $schema->get_graph();

		$this->assertSame( 'ProfilePage', $graph[2]['@type'] );
	}
}
