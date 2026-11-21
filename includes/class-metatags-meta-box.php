<?php
/**
 * Meta box on the post edit screen (classic editor and block editor).
 *
 * @package Metatags
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the "Meta Tags" meta box.
 */
class Metatags_Meta_Box {

	const NONCE_ACTION = 'metatags_save_meta';
	const NONCE_NAME   = 'metatags_nonce';
	const INPUT_PREFIX = 'metatags';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the meta box to all supported post types.
	 *
	 * @param string $post_type Current post type.
	 */
	public function add( $post_type ) {
		if ( ! in_array( $post_type, Metatags_Fields::post_types(), true ) ) {
			return;
		}
		add_meta_box( 'metatags', __( 'Meta Tags', 'metatags' ), array( $this, 'render' ), $post_type, 'normal', 'high' );
	}

	/**
	 * Loads CSS/JS on post edit screens only.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, Metatags_Fields::post_types(), true ) ) {
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
	 * Renders the meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$values = array();
		foreach ( Metatags_Fields::all() as $name => $field ) {
			$values[ $name ] = (string) get_post_meta( $post->ID, $field['key'], true );
		}

		$fallbacks = Metatags_Settings::enabled( 'fallbacks' );
		$excerpt   = $fallbacks ? wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 30, '…' ) : '';
		$title     = $fallbacks ? get_the_title( $post ) : '';
		$shown     = 0;

		echo '<div class="metatags-box">';

		$general = '';
		ob_start();
		if ( Metatags_Settings::enabled( 'title' ) ) {
			$this->text_row( 'title', __( 'Title', 'metatags' ), $values['title'], $title, '', 60 );
		}
		if ( Metatags_Settings::enabled( 'description' ) ) {
			$this->textarea_row( 'desc', __( 'Description', 'metatags' ), $values['desc'], $excerpt, '', 160 );
		}
		if ( Metatags_Settings::enabled( 'robots' ) ) {
			$this->text_row(
				'robots',
				__( 'Robots', 'metatags' ),
				$values['robots'],
				'',
				__( 'Comma separated, e.g. <code>noindex, nofollow</code>. Allowed: noindex, nofollow, none, noarchive, nosnippet, noimageindex, notranslate, max-snippet:N, max-image-preview:none|standard|large, max-video-preview:N. Leave empty for the default (index, follow).', 'metatags' )
			);
		}
		if ( Metatags_Settings::enabled( 'keywords' ) ) {
			$this->text_row( 'keywords', __( 'Keywords', 'metatags' ), $values['keywords'], '', __( 'Comma separated.', 'metatags' ) );
		}
		if ( Metatags_Settings::enabled( 'custom' ) ) {
			$this->textarea_row(
				'custom',
				__( 'Custom HTML', 'metatags' ),
				$values['custom'],
				'',
				current_user_can( 'unfiltered_html' )
					? __( 'Printed as-is into &lt;head&gt;, e.g. <code>&lt;meta name="google-site-verification" content="…"&gt;</code>', 'metatags' )
					: __( 'Only &lt;meta&gt; and &lt;link&gt; tags are allowed for your user role.', 'metatags' ),
				0,
				true
			);
		}
		$general = ob_get_clean();

		if ( '' !== $general ) {
			++$shown;
			echo '<h3 class="metatags-heading">' . esc_html__( 'Search engines', 'metatags' ) . '</h3>';
			echo '<table class="form-table metatags-table" role="presentation"><tbody>' . $general . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped rows.
		}

		if ( Metatags_Settings::enabled( 'og' ) ) {
			++$shown;
			$og_image_fallback = $fallbacks ? (string) get_the_post_thumbnail_url( $post, 'large' ) : '';
			$permalink         = $fallbacks && 'publish' === $post->post_status ? get_permalink( $post ) : '';

			echo '<h3 class="metatags-heading">' . esc_html__( 'Social media (Open Graph)', 'metatags' ) . '</h3>';
			echo '<table class="form-table metatags-table" role="presentation"><tbody>';
			$this->text_row( 'og-title', __( 'Title', 'metatags' ), $values['og-title'], $values['title'] ? $values['title'] : $title, '', 60 );
			$this->textarea_row( 'og-desc', __( 'Description', 'metatags' ), $values['og-desc'], $values['desc'] ? $values['desc'] : $excerpt, '', 200 );
			$this->image_row( $values['og-image'], $og_image_fallback );
			$this->text_row( 'og-type', __( 'Type', 'metatags' ), $values['og-type'], $fallbacks ? ( 'page' === $post->post_type ? 'website' : 'article' ) : '', __( 'e.g. <code>article</code>, <code>website</code>, <code>product</code>', 'metatags' ) );
			$this->text_row( 'og-url', __( 'URL', 'metatags' ), $values['og-url'], $permalink, __( 'Only needed if it differs from the permalink.', 'metatags' ), 0, 'url' );
			$this->text_row( 'og-site-name', __( 'Site name', 'metatags' ), $values['og-site-name'], $fallbacks ? get_bloginfo( 'name' ) : '' );
			echo '</tbody></table>';
		}

		if ( 0 === $shown ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'All meta tags are switched off.', 'metatags' ),
				esc_url( admin_url( 'options-general.php?page=' . Metatags_Settings::PAGE ) ),
				esc_html__( 'Open settings', 'metatags' )
			);
		} elseif ( $fallbacks ) {
			echo '<p class="description">' . esc_html__( 'Grey placeholder text shows the value used automatically when a field is left empty.', 'metatags' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Input name/id for a field.
	 *
	 * @param string $name Field name.
	 * @return array{0: string, 1: string}
	 */
	private function ids( $name ) {
		return array( self::INPUT_PREFIX . '[' . $name . ']', 'metatags-' . $name );
	}

	/**
	 * Allowed HTML in help texts.
	 *
	 * @return array
	 */
	private function help_html() {
		return array( 'code' => array() );
	}

	/**
	 * One-line text row.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Label.
	 * @param string $value       Current value.
	 * @param string $placeholder Placeholder (fallback value).
	 * @param string $help        Help text (code tags allowed).
	 * @param int    $max         Recommended max length for the counter, 0 for none.
	 * @param string $type        Input type.
	 */
	private function text_row( $name, $label, $value, $placeholder = '', $help = '', $max = 0, $type = 'text' ) {
		list( $input_name, $id ) = $this->ids( $name );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>" class="large-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo $max ? ' data-metatags-max="' . (int) $max . '"' : ''; ?>>
				<?php $this->help( $help ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Textarea row.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Label.
	 * @param string $value       Current value.
	 * @param string $placeholder Placeholder (fallback value).
	 * @param string $help        Help text (code tags allowed).
	 * @param int    $max         Recommended max length for the counter, 0 for none.
	 * @param bool   $code        Monospace textarea.
	 */
	private function textarea_row( $name, $label, $value, $placeholder = '', $help = '', $max = 0, $code = false ) {
		list( $input_name, $id ) = $this->ids( $name );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea class="large-text<?php echo $code ? ' code' : ''; ?>" rows="3" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo $max ? ' data-metatags-max="' . (int) $max . '"' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
				<?php $this->help( $help ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Open Graph image row with media library picker.
	 *
	 * @param string $value    Current URL.
	 * @param string $fallback Featured image URL.
	 */
	private function image_row( $value, $fallback ) {
		list( $input_name, $id ) = $this->ids( 'og-image' );
		$preview                 = $value ? $value : $fallback;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Image', 'metatags' ); ?></label></th>
			<td>
				<div class="metatags-image" data-fallback="<?php echo esc_url( $fallback ); ?>">
					<img class="metatags-image__preview" src="<?php echo esc_url( $preview ); ?>" alt=""<?php echo $preview ? '' : ' hidden'; ?>>
					<div class="metatags-image__controls">
						<input type="url" class="large-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $fallback ); ?>">
						<p>
							<button type="button" class="button metatags-image__select"><?php esc_html_e( 'Select image', 'metatags' ); ?></button>
							<button type="button" class="button-link metatags-image__remove"<?php echo $value ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'metatags' ); ?></button>
						</p>
						<p class="description"><?php esc_html_e( 'Recommended: 1200 × 630 px. Without an image the featured image is used.', 'metatags' ); ?></p>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Prints a help text.
	 *
	 * @param string $help Help text.
	 */
	private function help( $help ) {
		if ( '' !== $help ) {
			echo '<p class="description">' . wp_kses( $help, $this->help_html() ) . '</p>';
		}
	}

	/**
	 * Saves submitted fields. Fields not present in the form (switched off) stay untouched.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Metatags_Fields::post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( empty( $_POST[ self::INPUT_PREFIX ] ) || ! is_array( $_POST[ self::INPUT_PREFIX ] ) ) {
			return;
		}

		$input = wp_unslash( $_POST[ self::INPUT_PREFIX ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.

		foreach ( Metatags_Fields::all() as $name => $field ) {
			if ( ! array_key_exists( $name, $input ) || ! is_string( $input[ $name ] ) ) {
				continue;
			}
			$value = call_user_func( $field['sanitize'], $input[ $name ] );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $field['key'] );
			} else {
				update_post_meta( $post_id, $field['key'], wp_slash( $value ) );
			}
		}
	}
}
