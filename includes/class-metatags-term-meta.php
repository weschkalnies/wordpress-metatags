<?php
/**
 * Per-term meta fields for public taxonomies (categories, tags, custom taxonomies).
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers, renders and saves term meta fields on taxonomy edit screens.
 */
class Metatags_Term_Meta {

	const NONCE_ACTION = 'metatags_save_term_meta';
	const NONCE_NAME   = 'metatags_term_nonce';
	const INPUT_PREFIX = 'metatags';

	/**
	 * Public taxonomies supported by term meta fields.
	 *
	 * @return string[]
	 */
	public static function taxonomies() {
		$taxes = get_taxonomies( array( 'public' => true ), 'names' );
		unset( $taxes['post_format'] );

		/**
		 * Filters the taxonomies Meta Tags provides term fields for.
		 *
		 * @param string[] $taxes Taxonomy names.
		 */
		return (array) apply_filters( 'metatags_taxonomies', array_values( $taxes ) );
	}

	/**
	 * Term meta field definitions.
	 *
	 * @return array<string, array{key: string, sanitize: callable, group: string, rest: bool, type?: string, default?: mixed}>
	 */
	public static function fields() {
		return array(
			'title'       => array(
				'key'      => '_metaTitle',
				'sanitize' => array( 'Metatags_Fields', 'sanitize_line' ),
				'group'    => 'title',
				'rest'     => true,
			),
			'desc'        => array(
				'key'      => '_metaDesc',
				'sanitize' => array( 'Metatags_Fields', 'sanitize_line' ),
				'group'    => 'description',
				'rest'     => true,
			),
			'robots'      => array(
				'key'      => '_metaRobots',
				'sanitize' => array( 'Metatags_Fields', 'sanitize_robots' ),
				'group'    => 'robots',
				'rest'     => true,
			),
			'og-image'    => array(
				'key'      => '_metaOGImage',
				'sanitize' => array( 'Metatags_Fields', 'sanitize_url' ),
				'group'    => 'og',
				'rest'     => true,
			),
			'og-image-id' => array(
				'key'      => '_metaOGImageId',
				'sanitize' => 'absint',
				'group'    => 'og',
				'rest'     => true,
				'type'     => 'integer',
				'default'  => 0,
			),
		);
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ), 99 );
		add_action( 'init', array( $this, 'register_hooks' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Registers term meta for REST and sanitization.
	 */
	public function register_meta() {
		foreach ( self::taxonomies() as $taxonomy ) {
			foreach ( self::fields() as $field ) {
				register_term_meta(
					$taxonomy,
					$field['key'],
					array(
						'type'              => isset( $field['type'] ) ? $field['type'] : 'string',
						'single'            => true,
						'default'           => array_key_exists( 'default', $field ) ? $field['default'] : '',
						'show_in_rest'      => $field['rest'],
						'sanitize_callback' => $field['sanitize'],
						'auth_callback'     => static function ( $allowed, $meta_key, $term_id ) {
							return current_user_can( 'edit_term', $term_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Attaches edit form and save hooks for every supported taxonomy.
	 */
	public function register_hooks() {
		foreach ( self::taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render' ), 10, 2 );
			add_action( "edited_{$taxonomy}", array( $this, 'save' ), 10, 1 );
		}
	}

	/**
	 * Loads media picker and counter scripts on taxonomy term edit screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'term.php' !== $hook_suffix ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->taxonomy, self::taxonomies(), true ) ) {
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
				/* translators: 1: current number of characters, 2: recommended maximum. */
				'counter'     => __( '%1$d / %2$d characters', 'metatags' ),
			)
		);
	}

	/**
	 * Renders term meta rows on the edit term screen.
	 *
	 * @param WP_Term $term     Term object.
	 * @param string  $taxonomy Taxonomy slug.
	 */
	public function render( $term, $taxonomy ) {
		unset( $taxonomy );
		if ( ! Metatags_Settings::enabled( 'title' ) && ! Metatags_Settings::enabled( 'description' ) && ! Metatags_Settings::enabled( 'robots' ) && ! Metatags_Settings::enabled( 'og' ) ) {
			return;
		}

		$values = array();
		foreach ( self::fields() as $name => $field ) {
			$values[ $name ] = (string) get_term_meta( $term->term_id, $field['key'], true );
		}

		$fallbacks     = Metatags_Settings::enabled( 'fallbacks' );
		$default_image = $fallbacks ? Metatags_Fields::default_og_image() : null;
		$img_fallback  = $default_image ? $default_image['url'] : '';
		$desc_fallback = $fallbacks ? Metatags_Fields::plain_text( $term->description ) : '';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<tr class="form-field">
			<th scope="row" colspan="2">
				<h2><?php esc_html_e( 'Meta Tags', 'metatags' ); ?></h2>
			</th>
		</tr>
		<?php if ( Metatags_Settings::enabled( 'title' ) ) : ?>
			<tr class="form-field">
				<th scope="row"><label for="metatags-term-title"><?php esc_html_e( 'Title', 'metatags' ); ?></label></th>
				<td>
					<input type="text" id="metatags-term-title" name="<?php echo esc_attr( self::INPUT_PREFIX . '[title]' ); ?>" value="<?php echo esc_attr( $values['title'] ); ?>" placeholder="<?php echo esc_attr( $term->name ); ?>" data-metatags-max="60">
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( Metatags_Settings::enabled( 'description' ) ) : ?>
			<tr class="form-field">
				<th scope="row"><label for="metatags-term-desc"><?php esc_html_e( 'Description', 'metatags' ); ?></label></th>
				<td>
					<textarea id="metatags-term-desc" rows="3" name="<?php echo esc_attr( self::INPUT_PREFIX . '[desc]' ); ?>" placeholder="<?php echo esc_attr( $desc_fallback ); ?>" data-metatags-max="160"><?php echo esc_textarea( $values['desc'] ); ?></textarea>
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( Metatags_Settings::enabled( 'robots' ) ) : ?>
			<?php $parts = Metatags_Fields::split_robots( $values['robots'] ); ?>
			<tr class="form-field">
				<th scope="row"><?php esc_html_e( 'Robots', 'metatags' ); ?></th>
				<td>
					<fieldset class="metatags-robots-flags">
						<label for="metatags-term-robots-noindex">
							<input type="checkbox" id="metatags-term-robots-noindex" name="<?php echo esc_attr( self::INPUT_PREFIX . '[robots-noindex]' ); ?>" value="1"<?php checked( $parts['noindex'] ); ?>>
							<?php esc_html_e( 'Discourage search engines from indexing this page (noindex)', 'metatags' ); ?>
						</label>
						<label for="metatags-term-robots-nofollow">
							<input type="checkbox" id="metatags-term-robots-nofollow" name="<?php echo esc_attr( self::INPUT_PREFIX . '[robots-nofollow]' ); ?>" value="1"<?php checked( $parts['nofollow'] ); ?>>
							<?php esc_html_e( 'Do not follow links on this page (nofollow)', 'metatags' ); ?>
						</label>
					</fieldset>
					<div class="metatags-robots-extra">
						<label for="metatags-term-robots"><?php esc_html_e( 'More directives', 'metatags' ); ?></label>
						<input type="text" id="metatags-term-robots" name="<?php echo esc_attr( self::INPUT_PREFIX . '[robots]' ); ?>" value="<?php echo esc_attr( $parts['extra'] ); ?>">
						<p class="description"><?php esc_html_e( 'Optional, comma separated: noarchive, nosnippet, noimageindex, notranslate, max-snippet:N, max-image-preview:none|standard|large, max-video-preview:N.', 'metatags' ); ?></p>
					</div>
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( Metatags_Settings::enabled( 'og' ) ) : ?>
			<?php
			$image_id = absint( $values['og-image-id'] );
			$preview  = $values['og-image'] ? $values['og-image'] : $img_fallback;
			?>
			<tr class="form-field">
				<th scope="row"><label for="metatags-term-og-image"><?php esc_html_e( 'Open Graph image', 'metatags' ); ?></label></th>
				<td>
					<div class="metatags-image" data-fallback="<?php echo esc_url( $img_fallback ); ?>">
						<img class="metatags-image__preview" src="<?php echo esc_url( $preview ); ?>" alt=""<?php echo $preview ? '' : ' hidden'; ?>>
						<div class="metatags-image__controls">
							<input type="hidden" class="metatags-image__id" name="<?php echo esc_attr( self::INPUT_PREFIX . '[og-image-id]' ); ?>" value="<?php echo $image_id ? esc_attr( (string) $image_id ) : ''; ?>">
							<input type="url" id="metatags-term-og-image" name="<?php echo esc_attr( self::INPUT_PREFIX . '[og-image]' ); ?>" value="<?php echo esc_attr( $values['og-image'] ); ?>" placeholder="<?php echo esc_attr( $img_fallback ); ?>">
							<p>
								<button type="button" class="button metatags-image__select"><?php esc_html_e( 'Select image', 'metatags' ); ?></button>
								<button type="button" class="button-link metatags-image__remove"<?php echo $values['og-image'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'metatags' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'Recommended: 1200 × 630 px. Without an image the default image is used.', 'metatags' ); ?></p>
						</div>
					</div>
				</td>
			</tr>
		<?php endif; ?>
		<?php
	}

	/**
	 * Saves submitted term meta fields.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		if ( empty( $_POST[ self::INPUT_PREFIX ] ) || ! is_array( $_POST[ self::INPUT_PREFIX ] ) ) {
			return;
		}

		$input = wp_unslash( $_POST[ self::INPUT_PREFIX ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.
		if ( array_key_exists( 'robots', $input ) && is_string( $input['robots'] ) ) {
			$input['robots'] = Metatags_Fields::combine_robots(
				! empty( $input['robots-noindex'] ),
				! empty( $input['robots-nofollow'] ),
				$input['robots']
			);
		}
		$saved = array();

		foreach ( self::fields() as $name => $field ) {
			if ( ! array_key_exists( $name, $input ) || ! is_string( $input[ $name ] ) ) {
				continue;
			}
			$value          = call_user_func( $field['sanitize'], $input[ $name ] );
			$saved[ $name ] = $value;
			if ( '' === $value || 0 === $value ) {
				delete_term_meta( $term_id, $field['key'] );
			} else {
				update_term_meta( $term_id, $field['key'], is_string( $value ) ? wp_slash( $value ) : $value );
			}
		}

		if ( array_key_exists( 'og-image', $saved ) ) {
			if ( '' === $saved['og-image'] ) {
				delete_term_meta( $term_id, '_metaOGImageId' );
			} else {
				$image_id = isset( $saved['og-image-id'] ) ? (int) $saved['og-image-id'] : 0;
				if ( $image_id <= 0 || null === Metatags_Fields::match_attachment_image( $saved['og-image'], $image_id ) ) {
					$image_id = Metatags_Fields::attachment_id_from_url( $saved['og-image'] );
				}
				if ( $image_id > 0 ) {
					update_term_meta( $term_id, '_metaOGImageId', $image_id );
				} else {
					delete_term_meta( $term_id, '_metaOGImageId' );
				}
			}
		}
	}
}
