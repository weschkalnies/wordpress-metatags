<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FieldsTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset();
	}

	public function test_sanitize_fediverse_normalizes_handles_and_urls(): void {
		$this->assertSame( '@alice@mastodon.social', Metatags_Fields::sanitize_fediverse( 'alice@Mastodon.Social' ) );
		$this->assertSame( '@alice@fosstodon.org', Metatags_Fields::sanitize_fediverse( 'https://Fosstodon.org/@alice/' ) );
		$this->assertSame( '@alice@mastodon.social', Metatags_Fields::sanitize_fediverse( '  @alice@mastodon.social  ' ) );
		$this->assertSame( '', Metatags_Fields::sanitize_fediverse( 'not-a-handle' ) );
		$this->assertSame( '', Metatags_Fields::sanitize_fediverse( 'https://example.com/alice' ) );
	}

	public function test_sanitize_twitter_handle(): void {
		$this->assertSame( '@weschkalnies', Metatags_Fields::sanitize_twitter_handle( 'weschkalnies' ) );
		$this->assertSame( '@weschkalnies', Metatags_Fields::sanitize_twitter_handle( 'https://x.com/weschkalnies?s=20' ) );
		$this->assertSame( '@wp_dev', Metatags_Fields::sanitize_twitter_handle( 'https://twitter.com/@wp_dev/' ) );
		$this->assertSame( '', Metatags_Fields::sanitize_twitter_handle( '!!!' ) );
	}

	public function test_robots_split_combine_merge_and_is_noindex(): void {
		$split = Metatags_Fields::split_robots( 'none, max-snippet:50' );
		$this->assertSame(
			array(
				'noindex'  => true,
				'nofollow' => true,
				'extra'    => 'max-snippet:50',
			),
			$split
		);

		$this->assertSame(
			'noindex, noarchive',
			Metatags_Fields::combine_robots( true, false, 'index, noarchive, bogus' )
		);
		$this->assertSame( 'none', Metatags_Fields::combine_robots( false, false, 'none' ) );

		$this->assertTrue( Metatags_Fields::is_noindex( 'nofollow, noindex' ) );
		$this->assertTrue( Metatags_Fields::is_noindex( 'none' ) );
		$this->assertFalse( Metatags_Fields::is_noindex( 'max-image-preview:none, noimageindex' ) );

		$merged = Metatags_Fields::merge_robots(
			array(
				'index'             => true,
				'follow'            => true,
				'max-image-preview' => 'large',
			),
			'noindex, max-snippet:100'
		);
		$this->assertSame(
			array(
				'follow'      => true,
				'noindex'     => true,
				'max-snippet' => '100',
			),
			$merged
		);
	}

	public function test_sanitize_custom_uses_allowlist_for_unprivileged_users(): void {
		$GLOBALS['wp_test']['caps']['unfiltered_html'] = false;

		$raw = '<script>alert(1)</script><meta http-equiv="refresh" content="0;url=https://evil.test"><meta name="google-site-verification" content="abc123"><link rel="stylesheet" href="https://evil.test/x.css"><link rel="alternate" hreflang="de" href="https://example.com/de/">';
		$clean = Metatags_Fields::sanitize_custom( $raw );

		$this->assertStringNotContainsString( '<script', $clean );
		$this->assertStringNotContainsString( 'http-equiv', $clean );
		$this->assertStringNotContainsString( 'stylesheet', $clean );
		$this->assertStringContainsString( '<meta name="google-site-verification" content="abc123">', $clean );
		$this->assertStringContainsString( '<link rel="alternate" href="https://example.com/de/" hreflang="de">', $clean );
	}

	public function test_post_types_respects_disabled_setting_and_filter(): void {
		$GLOBALS['wp_test']['post_types'] = array(
			'post'       => 'post',
			'page'       => 'page',
			'attachment' => 'attachment',
			'product'    => 'product',
		);
		$GLOBALS['wp_test']['options']['metatags_settings'] = array(
			'disabled_post_types' => array( 'product' ),
		);

		$this->assertSame( array( 'post', 'page' ), Metatags_Fields::post_types() );

		add_filter(
			'metatags_post_types',
			static function ( array $types ): array {
				return array_values( array_diff( $types, array( 'page' ) ) );
			}
		);
		$this->assertSame( array( 'post' ), Metatags_Fields::post_types() );
	}
}
