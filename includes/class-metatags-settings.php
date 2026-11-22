<?php
/**
 * Settings page (Settings → Meta Tags).
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores which tag groups are active in a single option.
 */
class Metatags_Settings {

	const OPTION = 'metatags_settings';
	const PAGE   = 'metatag_settings';

	/**
	 * Option names used by version 1.x, mapped to the new keys.
	 */
	const LEGACY = array(
		'chk-title'    => 'title',
		'chk-desc'     => 'description',
		'chk-keywords' => 'keywords',
		'chk-robots'   => 'robots',
		'chk-custom'   => 'custom',
		'chk-og'       => 'og',
	);

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'title'               => true,
			'description'         => true,
			'canonical'           => true,
			'robots'              => true,
			'keywords'            => false,
			'custom'              => false,
			'og'                  => true,
			'twitter'             => true,
			'schema'              => true,
			'fallbacks'           => true,
			'default_og_image'    => '',
			'default_og_image_id' => 0,
			'twitter_site'        => '',
			'fediverse_creator'   => '',
			'disabled_post_types' => array(),
			'archives'            => true,
			'noindex_search'      => true,
			'noindex_author'      => false,
			'noindex_tag'         => false,
			'noindex_date'        => false,
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Returns a single setting value.
	 *
	 * @param string $key Settings key.
	 * @return mixed
	 */
	public static function option( $key ) {
		$settings = self::get();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
	}

	/**
	 * Whether a group is active.
	 *
	 * @param string $group Settings key.
	 * @return bool
	 */
	public static function enabled( $group ) {
		$settings = self::get();
		return ! empty( $settings[ $group ] );
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::maybe_migrate();
	}

	/**
	 * Converts the 1.x checkbox options once. If none of them was switched on
	 * (which made 1.x output nothing at all), the defaults are used instead.
	 */
	public static function maybe_migrate() {
		if ( false !== get_option( self::OPTION ) ) {
			return;
		}

		$settings = self::defaults();
		$legacy   = array();
		foreach ( self::LEGACY as $old => $new ) {
			$legacy[ $new ] = '1' === (string) get_option( $old );
		}
		if ( in_array( true, $legacy, true ) ) {
			$settings = array_merge( $settings, $legacy );
		}

		add_option( self::OPTION, $settings );
		foreach ( array_keys( self::LEGACY ) as $old ) {
			delete_option( $old );
		}
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'conflict_notice' ) );
		add_filter( 'user_contactmethods', array( $this, 'user_contact_methods' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( METATAGS_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Adds the Fediverse / Mastodon handle field to user profiles.
	 *
	 * @param array<string, string> $methods Existing user contact methods.
	 * @return array<string, string>
	 */
	public function user_contact_methods( $methods ) {
		$methods['metatags_fediverse'] = __( 'Fediverse / Mastodon handle', 'metatags' );
		return $methods;
	}

	/**
	 * Detects other active SEO plugins that may output duplicate meta tags.
	 *
	 * @return string[] Human-readable names of conflicting active SEO plugins.
	 */
	public static function detect_seo_conflicts() {
		$known = array(
			'WPSEO_VERSION'             => 'Yoast SEO',
			'RANK_MATH_VERSION'         => 'Rank Math',
			'SEOPRESS_VERSION'          => 'SEOPress',
			'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
			'AIOSEO_VERSION'            => 'All in One SEO',
			'SLIM_SEO_VER'              => 'Slim SEO',
		);
		$found = array();
		foreach ( $known as $constant => $label ) {
			if ( defined( $constant ) ) {
				$found[] = $label;
			}
		}
		return $found;
	}

	/**
	 * Shows a warning notice on the settings and plugins screens if another SEO plugin is active.
	 */
	public function conflict_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'settings_page_' . self::PAGE, 'plugins' ), true ) ) {
			return;
		}
		$conflicts = self::detect_seo_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of active SEO plugin names. */
					__( 'Meta Tags detected another active SEO plugin (%s). Consider disabling overlapping tag groups under Settings → Meta Tags to avoid duplicate meta tags.', 'metatags' ),
					implode( ', ', $conflicts )
				)
			)
		);
	}

	/**
	 * Loads media picker assets on the plugin settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'metatags-admin', METATAGS_URL . 'admin/css/metatags-admin.css', array(), METATAGS_VERSION );
		wp_enqueue_script( 'metatags-admin', METATAGS_URL . 'admin/js/metatags-admin.js', array( 'jquery' ), METATAGS_VERSION, true );
		wp_localize_script(
			'metatags-admin',
			'metatagsAdmin',
			array(
				'chooseImage' => __( 'Choose Open Graph image', 'metatags' ),
				'useImage'    => __( 'Use this image', 'metatags' ),
			)
		);
	}

	/**
	 * Registers the option and its fields.
	 */
	public function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'metatags_tags', __( 'Tags', 'metatags' ), '__return_false', self::PAGE );
		add_settings_section( 'metatags_behaviour', __( 'Behaviour', 'metatags' ), '__return_false', self::PAGE );
		add_settings_section( 'metatags_archives', __( 'Archives', 'metatags' ), '__return_false', self::PAGE );

		$tags = array(
			'title'       => array( __( 'Title', 'metatags' ), __( 'Replaces the document &lt;title&gt; with the custom title.', 'metatags' ) ),
			'description' => array( __( 'Description', 'metatags' ), __( 'Outputs &lt;meta name="description"&gt;.', 'metatags' ) ),
			'canonical'   => array( __( 'Canonical URL', 'metatags' ), __( 'Overrides the canonical URL output by WordPress.', 'metatags' ) ),
			'robots'      => array( __( 'Robots', 'metatags' ), __( 'Adds directives such as noindex to the robots meta tag generated by WordPress.', 'metatags' ) ),
			'keywords'    => array( __( 'Keywords', 'metatags' ), __( 'Ignored by Google and Bing – only enable if you need it for another tool.', 'metatags' ) ),
			'og'          => array( __( 'Open Graph', 'metatags' ), __( 'og:* tags for Facebook, LinkedIn, Slack, WhatsApp, Mastodon …', 'metatags' ) ),
			'twitter'     => array( __( 'Twitter / X Card', 'metatags' ), __( 'twitter:card tag, uses the Open Graph values.', 'metatags' ) ),
			'schema'      => array( __( 'Structured data (JSON-LD)', 'metatags' ), __( 'Outputs minimal Schema.org JSON-LD (WebSite, WebPage, Article) in &lt;head&gt;.', 'metatags' ) ),
			'custom'      => array( __( 'Custom HTML', 'metatags' ), __( 'Free-form HTML per post, printed into &lt;head&gt;.', 'metatags' ) ),
		);
		foreach ( $tags as $key => $labels ) {
			$this->add_checkbox( $key, $labels[0], $labels[1], 'metatags_tags' );
		}

		$this->add_checkbox(
			'fallbacks',
			__( 'Automatic fallbacks', 'metatags' ),
			__( 'If a field is empty, use post title, excerpt, permalink, site name and featured image.', 'metatags' ),
			'metatags_behaviour'
		);
		$this->add_image_field( 'metatags_behaviour' );
		$this->add_text_field(
			'twitter_site',
			__( 'Twitter / X site handle', 'metatags' ),
			'@example',
			__( 'Optional site account for &lt;meta name="twitter:site"&gt;.', 'metatags' ),
			'metatags_behaviour'
		);
		$this->add_text_field(
			'fediverse_creator',
			__( 'Fediverse / Mastodon handle', 'metatags' ),
			'@user@mastodon.social',
			__( 'Default handle for &lt;meta name="fediverse:creator"&gt;. Authors can also set their own handle in their user profile.', 'metatags' ),
			'metatags_behaviour'
		);
		$this->add_post_types_field( 'metatags_behaviour' );

		$archives = array(
			'archives'       => array( __( 'Archive meta tags', 'metatags' ), __( 'Output description, Open Graph and Twitter Card tags on category, tag, taxonomy, author and post type archives.', 'metatags' ) ),
			'noindex_search' => array( __( 'Noindex search results', 'metatags' ), __( 'Add noindex to search result pages.', 'metatags' ) ),
			'noindex_author' => array( __( 'Noindex author archives', 'metatags' ), __( 'Add noindex to author archives and exclude users from the XML sitemap.', 'metatags' ) ),
			'noindex_tag'    => array( __( 'Noindex tag archives', 'metatags' ), __( 'Add noindex to tag archives and exclude tags from the XML sitemap.', 'metatags' ) ),
			'noindex_date'   => array( __( 'Noindex date archives', 'metatags' ), __( 'Add noindex to date archives.', 'metatags' ) ),
		);
		foreach ( $archives as $key => $labels ) {
			$this->add_checkbox( $key, $labels[0], $labels[1], 'metatags_archives' );
		}
	}

	/**
	 * Adds the default Open Graph image field with media library picker.
	 *
	 * @param string $section Section ID.
	 */
	private function add_image_field( $section ) {
		add_settings_field(
			'metatags_default_og_image',
			esc_html__( 'Default Open Graph image', 'metatags' ),
			static function () {
				$url = (string) self::option( 'default_og_image' );
				$id  = (int) self::option( 'default_og_image_id' );
				?>
				<div class="metatags-image" data-fallback="">
					<img class="metatags-image__preview" src="<?php echo esc_url( $url ); ?>" alt=""<?php echo $url ? '' : ' hidden'; ?>>
					<div class="metatags-image__controls">
						<input type="hidden" class="metatags-image__id" name="<?php echo esc_attr( self::OPTION . '[default_og_image_id]' ); ?>" value="<?php echo $id ? esc_attr( (string) $id ) : ''; ?>">
						<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION . '[default_og_image]' ); ?>" value="<?php echo esc_attr( $url ); ?>">
						<p>
							<button type="button" class="button metatags-image__select"><?php esc_html_e( 'Select image', 'metatags' ); ?></button>
							<button type="button" class="button-link metatags-image__remove"<?php echo $url ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'metatags' ); ?></button>
						</p>
						<p class="description"><?php esc_html_e( 'Recommended: 1200 × 630 px. Used on the front page, archives, and posts without an own or featured image.', 'metatags' ); ?></p>
					</div>
				</div>
				<?php
			},
			self::PAGE,
			$section
		);
	}

	/**
	 * Adds a single-line text setting field.
	 *
	 * @param string $key         Settings key.
	 * @param string $label       Field label.
	 * @param string $placeholder Placeholder example.
	 * @param string $help        Help text (may contain HTML entities).
	 * @param string $section     Section ID.
	 */
	private function add_text_field( $key, $label, $placeholder, $help, $section ) {
		add_settings_field(
			'metatags_' . $key,
			esc_html( $label ),
			static function () use ( $key, $placeholder, $help ) {
				printf(
					'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s"><p class="description">%5$s</p>',
					esc_attr( self::OPTION ),
					esc_attr( $key ),
					esc_attr( (string) self::option( $key ) ),
					esc_attr( $placeholder ),
					wp_kses( $help, array() )
				);
			},
			self::PAGE,
			$section
		);
	}

	/**
	 * Adds checkboxes to choose which public post types Meta Tags is active for.
	 *
	 * @param string $section Section ID.
	 */
	private function add_post_types_field( $section ) {
		add_settings_field(
			'metatags_post_types',
			esc_html__( 'Post types', 'metatags' ),
			static function () {
				$objects = get_post_types( array( 'public' => true ), 'objects' );
				unset( $objects['attachment'] );
				$disabled = self::option( 'disabled_post_types' );
				$disabled = is_array( $disabled ) ? $disabled : array();

				printf( '<input type="hidden" name="%s[post_types_present]" value="1">', esc_attr( self::OPTION ) );
				echo '<fieldset class="metatags-post-types">';
				foreach ( $objects as $name => $obj ) {
					$label = isset( $obj->labels->name ) ? $obj->labels->name : $name;
					printf(
						'<label><input type="checkbox" name="%1$s[post_types][]" value="%2$s"%3$s> %4$s <code>%5$s</code></label>',
						esc_attr( self::OPTION ),
						esc_attr( $name ),
						checked( ! in_array( $name, $disabled, true ), true, false ),
						esc_html( $label ),
						esc_html( $name )
					);
				}
				echo '</fieldset>';
				echo '<p class="description">' . esc_html__( 'Can also be filtered via the metatags_post_types hook.', 'metatags' ) . '</p>';
			},
			self::PAGE,
			$section
		);
	}

	/**
	 * Adds one checkbox field.
	 *
	 * @param string $key     Settings key.
	 * @param string $label   Label.
	 * @param string $help    Description (may contain entities).
	 * @param string $section Section ID.
	 */
	private function add_checkbox( $key, $label, $help, $section ) {
		add_settings_field(
			'metatags_' . $key,
			esc_html( $label ),
			static function () use ( $key, $help ) {
				printf(
					'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
					esc_attr( self::OPTION ),
					esc_attr( $key ),
					checked( self::enabled( $key ), true, false ),
					wp_kses( $help, array() )
				);
			},
			self::PAGE,
			$section
		);
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();
		$skip  = array( 'default_og_image', 'default_og_image_id', 'twitter_site', 'fediverse_creator', 'disabled_post_types' );
		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		$clean['default_og_image'] = Metatags_Fields::sanitize_url( isset( $input['default_og_image'] ) ? $input['default_og_image'] : '' );
		if ( '' === $clean['default_og_image'] ) {
			$clean['default_og_image_id'] = 0;
		} else {
			$image_id = isset( $input['default_og_image_id'] ) ? absint( $input['default_og_image_id'] ) : 0;
			if ( $image_id <= 0 || null === Metatags_Fields::match_attachment_image( $clean['default_og_image'], $image_id ) ) {
				$image_id = Metatags_Fields::attachment_id_from_url( $clean['default_og_image'] );
			}
			$clean['default_og_image_id'] = $image_id;
		}

		$clean['twitter_site']      = Metatags_Fields::sanitize_twitter_handle( isset( $input['twitter_site'] ) ? $input['twitter_site'] : '' );
		$clean['fediverse_creator'] = Metatags_Fields::sanitize_fediverse( isset( $input['fediverse_creator'] ) ? $input['fediverse_creator'] : '' );

		if ( ! empty( $input['post_types_present'] ) ) {
			$public = get_post_types( array( 'public' => true ), 'names' );
			unset( $public['attachment'] );
			$enabled                      = isset( $input['post_types'] ) && is_array( $input['post_types'] )
				? array_map( 'sanitize_key', $input['post_types'] )
				: array();
			$clean['disabled_post_types'] = array_values( array_diff( array_values( $public ), $enabled ) );
		} elseif ( isset( $input['disabled_post_types'] ) && is_array( $input['disabled_post_types'] ) ) {
			$clean['disabled_post_types'] = array_values( array_unique( array_map( 'sanitize_key', $input['disabled_post_types'] ) ) );
		} else {
			$clean['disabled_post_types'] = array();
		}

		return $clean;
	}

	/**
	 * Adds Settings → Meta Tags.
	 */
	public function add_page() {
		add_options_page(
			__( 'Meta Tags', 'metatags' ),
			__( 'Meta Tags', 'metatags' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
			<?php
			/**
			 * Filters whether to show the developer info card on the plugin settings page.
			 *
			 * @param bool $show Whether to display the card.
			 */
			if ( apply_filters( 'metatags_show_about_card', true ) ) {
				$this->render_about_card();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renders a subtle footer note at the bottom of the settings page.
	 */
	public function render_about_card() {
		?>
		<div class="metatags-about-card">
			<p>
				<?php esc_html_e( 'Meta Tags is maintained by Nick Weschkalnies – freelance web designer & developer for agencies and direct clients.', 'metatags' ); ?>
				<a href="https://www.weschkalnies.de" target="_blank" rel="noopener noreferrer">weschkalnies.de</a>
				<span aria-hidden="true">·</span>
				<a href="https://buymeacoffee.com/weschkalnies" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy me a coffee', 'metatags' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Adds a "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
				esc_html__( 'Settings', 'metatags' )
			)
		);
		return $links;
	}

	/**
	 * Adds a subtle support link in the plugin row meta on the plugins screen.
	 *
	 * @param string[] $links Existing row meta links.
	 * @param string   $file  Plugin basename.
	 * @return string[]
	 */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( METATAGS_FILE ) === $file ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://buymeacoffee.com/weschkalnies' ),
				esc_html__( 'Buy me a coffee', 'metatags' )
			);
		}
		return $links;
	}
}
