<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset();
	}

	public function test_sanitize_handles_new_options_and_post_types(): void {
		$GLOBALS['wp_test']['post_types'] = array(
			'post'       => 'post',
			'page'       => 'page',
			'attachment' => 'attachment',
			'book'       => 'book',
		);
		$GLOBALS['wp_test']['attachments'][42] = array(
			'is_image' => true,
			'url'      => 'https://example.com/wp-content/uploads/2026/11/hero.jpg',
			'meta'     => array(
				'file'   => '2026/11/hero.jpg',
				'width'  => 1600,
				'height' => 900,
			),
		);
		$GLOBALS['wp_test']['url_to_id']['https://example.com/wp-content/uploads/2026/11/hero.jpg'] = 42;

		$settings = new Metatags_Settings();
		$clean    = $settings->sanitize(
			array(
				'title'               => '1',
				'description'         => '1',
				'canonical'           => '1',
				'robots'              => '1',
				'og'                  => '1',
				'twitter'             => '1',
				'schema'              => '1',
				'fallbacks'           => '1',
				'archives'            => '1',
				'default_og_image'    => 'https://example.com/wp-content/uploads/2026/11/hero.jpg',
				'default_og_image_id' => '',
				'twitter_site'        => 'https://x.com/weschkalnies',
				'fediverse_creator'   => 'https://mastodon.social/@nick',
				'post_types_present'  => '1',
				'post_types'          => array( 'post', 'page' ),
			)
		);

		$this->assertTrue( $clean['schema'] );
		$this->assertSame( 42, $clean['default_og_image_id'] );
		$this->assertSame( '@weschkalnies', $clean['twitter_site'] );
		$this->assertSame( '@nick@mastodon.social', $clean['fediverse_creator'] );
		$this->assertSame( array( 'book' ), $clean['disabled_post_types'] );
	}

	public function test_detect_seo_conflicts_reads_known_constants(): void {
		$conflicts = Metatags_Settings::detect_seo_conflicts();
		$this->assertIsArray( $conflicts );

		if ( ! defined( 'WPSEO_VERSION' ) ) {
			define( 'WPSEO_VERSION', '23.0' );
		}
		$this->assertContains( 'Yoast SEO', Metatags_Settings::detect_seo_conflicts() );
	}

	public function test_about_card_renders_weschkalnies_link_and_can_be_filtered(): void {
		$settings = new Metatags_Settings();
		$GLOBALS['wp_test']['page_title'] = 'Meta Tags';

		ob_start();
		$settings->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'https://www.weschkalnies.de', $html );
		$this->assertStringContainsString( 'https://buymeacoffee.com/weschkalnies', $html );
		$this->assertStringContainsString( 'metatags-about-card', $html );

		add_filter( 'metatags_show_about_card', '__return_false' );
		ob_start();
		$settings->render_page();
		$filtered = ob_get_clean();

		$this->assertStringNotContainsString( 'metatags-about-card', $filtered );
	}

	public function test_row_meta_adds_buy_me_a_coffee_only_for_this_plugin(): void {
		$settings = new Metatags_Settings();

		$other = $settings->row_meta( array( 'Version 1.0' ), 'other-plugin/other.php' );
		$this->assertCount( 1, $other );

		$own = $settings->row_meta( array( 'Version 2.1.0' ), 'metatags/metatags.php' );
		$this->assertCount( 2, $own );
		$this->assertStringContainsString( 'https://buymeacoffee.com/weschkalnies', $own[1] );
	}
}
