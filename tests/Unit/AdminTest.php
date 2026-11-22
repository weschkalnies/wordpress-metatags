<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset();
		$GLOBALS['wp_test']['options']['metatags_settings'] = Metatags_Settings::defaults();
	}

	public function test_columns_shows_noindex_and_override_badges(): void {
		update_post_meta( 10, '_metaRobots', 'noindex, nofollow' );
		update_post_meta( 10, '_metaTitle', 'SEO Title' );
		update_post_meta( 10, '_metaCanonical', 'https://example.com/canon/' );

		$columns = new Metatags_Columns();
		$cols    = $columns->add_column( array( 'title' => 'Title', 'date' => 'Date' ) );
		$this->assertSame( array( 'title', 'metatags', 'date' ), array_keys( $cols ) );

		ob_start();
		$columns->render_column( 'metatags', 10 );
		$cell = ob_get_clean();

		$this->assertStringContainsString( 'metatags-badge--noindex', $cell );
		$this->assertStringContainsString( 'Title', $cell );
		$this->assertStringContainsString( 'Canonical', $cell );
	}

	public function test_term_meta_save_stores_sanitized_fields_and_image_id(): void {
		$GLOBALS['wp_test']['attachments'][55] = array(
			'is_image' => true,
			'url'      => 'https://example.com/wp-content/uploads/2026/11/term.jpg',
			'meta'     => array(
				'file'   => '2026/11/term.jpg',
				'width'  => 1200,
				'height' => 630,
			),
		);
		$GLOBALS['wp_test']['url_to_id']['https://example.com/wp-content/uploads/2026/11/term.jpg'] = 55;

		$_POST = array(
			Metatags_Term_Meta::NONCE_NAME   => 'ok',
			Metatags_Term_Meta::INPUT_PREFIX => array(
				'title'          => 'Term SEO Title',
				'desc'           => 'Term SEO Desc',
				'robots-noindex' => '1',
				'robots'         => 'noarchive',
				'og-image'       => 'https://example.com/wp-content/uploads/2026/11/term.jpg',
				'og-image-id'    => '',
			),
		);

		$term_meta = new Metatags_Term_Meta();
		$term_meta->save( 15 );

		$this->assertSame( 'Term SEO Title', get_term_meta( 15, '_metaTitle', true ) );
		$this->assertSame( 'Term SEO Desc', get_term_meta( 15, '_metaDesc', true ) );
		$this->assertSame( 'noindex, noarchive', get_term_meta( 15, '_metaRobots', true ) );
		$this->assertSame( 55, get_term_meta( 15, '_metaOGImageId', true ) );
	}

	public function test_meta_box_and_block_editor_enqueue(): void {
		$metabox = new Metatags_Meta_Box();
		$metabox->add( 'post' );
		$this->assertSame( array( '__back_compat_meta_box' => true ), $GLOBALS['wp_test']['meta_boxes']['post'] );

		$screen               = new WP_Screen();
		$screen->base         = 'post';
		$screen->post_type    = 'post';
		$screen->block_editor = true;
		$GLOBALS['wp_test']['screen'] = $screen;

		$editor = new Metatags_Block_Editor();
		$editor->enqueue();
		$this->assertContains( 'metatags-editor', $GLOBALS['wp_test']['enqueued_scripts'] );
	}
}
