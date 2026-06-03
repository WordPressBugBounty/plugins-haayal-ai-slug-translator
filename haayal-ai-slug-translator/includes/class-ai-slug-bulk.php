<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Haayal_AI_Slug_Bulk {

	/**
	 * Registers hooks.
	 */
	private static $dismissed_option = 'haayal_bulk_dismissed_ids';

	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_haayal_bulk_load_items', [ __CLASS__, 'ajax_load_items' ] );
		add_action( 'wp_ajax_haayal_bulk_translate', [ __CLASS__, 'ajax_translate' ] );
		add_action( 'wp_ajax_haayal_bulk_save', [ __CLASS__, 'ajax_save' ] );
		add_action( 'wp_ajax_haayal_bulk_dismiss', [ __CLASS__, 'ajax_dismiss' ] );
		add_action( 'wp_ajax_haayal_bulk_restore', [ __CLASS__, 'ajax_restore' ] );
		add_action( 'wp_ajax_haayal_bulk_load_excluded', [ __CLASS__, 'ajax_load_excluded' ] );
		add_action( 'wp_ajax_haayal_bulk_get_sources', [ __CLASS__, 'ajax_get_sources' ] );
	}

	/**
	 * Enqueues bulk translation assets on the settings page only.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_ai-slug-translator' !== $hook_suffix ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		$version    = defined( 'HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION' )
			? HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION
			: '0.7.4';

		wp_enqueue_style(
			'haayal-slug-shared',
			$plugin_url . 'assets/css/ai-slug-shared.css',
			[],
			$version
		);

		wp_enqueue_style(
			'haayal-ai-slug-bulk',
			$plugin_url . 'assets/css/ai-slug-bulk.css',
			[ 'haayal-slug-shared' ],
			$version
		);

		wp_enqueue_script(
			'haayal-ai-slug-bulk',
			$plugin_url . 'assets/js/ai-slug-bulk.js',
			[ 'jquery' ],
			$version,
			true
		);

		$settings = Haayal_AI_Slug_Settings::get_settings();

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $post_types['attachment'] );
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

		$enabled_pt = [];
		foreach ( $settings['enabled_post_types'] as $pt ) {
			if ( isset( $post_types[ $pt ] ) ) {
				$enabled_pt[] = [
					'name'  => $pt,
					'label' => $post_types[ $pt ]->label,
				];
			}
		}

		$enabled_tax = [];
		foreach ( $settings['enabled_taxonomies'] as $tax ) {
			if ( isset( $taxonomies[ $tax ] ) ) {
				$enabled_tax[] = [
					'name'  => $tax,
					'label' => $taxonomies[ $tax ]->label,
				];
			}
		}

		wp_localize_script( 'haayal-ai-slug-bulk', 'haayalBulk', [
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'haayal_bulk_nonce' ),
			'enabled_post_types' => $enabled_pt,
			'enabled_taxonomies' => $enabled_tax,
			'connection_method'  => $settings['connection_method'],
			'proxy_quota'        => (int) get_option( 'haayal_ai_proxy_quota_remaining', 100 ),
			'translate_batch'    => HAAYAL_AI_SLUG_BULK_TRANSLATE_BATCH,
			'save_batch'         => HAAYAL_AI_SLUG_BULK_SAVE_BATCH,
			'i18n'               => [
				'translate_all'    => __( 'Translate All', 'haayal-ai-slug-translator' ),
				'save_all'         => __( 'Save All', 'haayal-ai-slug-translator' ),
				'stop'             => __( 'Stop', 'haayal-ai-slug-translator' ),
				'keep_original'    => __( 'Keep Original', 'haayal-ai-slug-translator' ),
				'exclude'          => __( 'Exclude from translation', 'haayal-ai-slug-translator' ),
				'restore'          => __( 'Restore', 'haayal-ai-slug-translator' ),
				'translating'      => __( 'Translating...', 'haayal-ai-slug-translator' ),
				'saving'           => __( 'Saving...', 'haayal-ai-slug-translator' ),
				'no_items'         => __( 'No items with non-Latin slugs found.', 'haayal-ai-slug-translator' ),
				'loading'          => __( 'Loading items...', 'haayal-ai-slug-translator' ),
				'error'            => __( 'Error', 'haayal-ai-slug-translator' ),
				'translated'       => __( 'translated', 'haayal-ai-slug-translator' ),
				'saved'            => __( 'saved', 'haayal-ai-slug-translator' ),
				'failed'           => __( 'failed', 'haayal-ai-slug-translator' ),
				'stopped'          => __( 'Stopped by user.', 'haayal-ai-slug-translator' ),
				'complete'         => __( 'Complete!', 'haayal-ai-slug-translator' ),
				'translate_cta'    => __( "These won't be saved until you click Save All. You can also edit them manually before saving.", 'haayal-ai-slug-translator' ),
				'translate_none'   => __( 'No slugs were translated. Check the Error Log tab for more details.', 'haayal-ai-slug-translator' ),
				'quota_exhausted'  => __( 'You\'ve used up your free quota, so we\'ve paused translation for now. Connect an API key or use Connectors to keep going.', 'haayal-ai-slug-translator' ),
				'quota_zero'       => __( 'Your free translation quota has been used up. To continue translating, please add your own OpenAI API key or use a Connector in the Settings tab.', 'haayal-ai-slug-translator' ),
				'select_source'    => __( 'Select a post type or taxonomy to load items.', 'haayal-ai-slug-translator' ),
				'of'               => __( 'of', 'haayal-ai-slug-translator' ),
				'redirects_created' => __( 'redirects created', 'haayal-ai-slug-translator' ),
				'show_excluded'    => __( 'Show excluded items', 'haayal-ai-slug-translator' ),
				'hide_excluded'    => __( 'Hide excluded items', 'haayal-ai-slug-translator' ),
				'restore_item'     => __( 'Restore', 'haayal-ai-slug-translator' ),
				'restore_hint'     => __( 'Return this item to the translation list', 'haayal-ai-slug-translator' ),
				'no_excluded'      => __( 'No excluded items for this type.', 'haayal-ai-slug-translator' ),
				'timeout'          => __( 'Request timed out, please try again', 'haayal-ai-slug-translator' ),
				'items_count'      => __( 'items', 'haayal-ai-slug-translator' ),
				/* translators: %1$s is the current page number, %2$s is the total number of pages */
				'page_of'          => __( 'Page %1$s of %2$s', 'haayal-ai-slug-translator' ),
				'regenerate'       => __( 'Regenerate slug', 'haayal-ai-slug-translator' ),
				'already_best'     => __( 'I tried, but this is already the best option', 'haayal-ai-slug-translator' ),
			],
		] );
	}

	/**
	 * Renders the Bulk Translation tab content.
	 */
	public static function render_tab() {
		if ( '' === get_option( 'permalink_structure' ) ) {
			$permalink_url = admin_url( 'options-permalink.php' );
			?>
			<h2><?php esc_html_e( 'Bulk Translation', 'haayal-ai-slug-translator' ); ?></h2>
			<div class="haayal-plain-permalink-banner">
				<div class="haayal-plain-permalink-banner__icon" aria-hidden="true">&#8505;</div>
				<div class="haayal-plain-permalink-banner__body">
					<strong><?php esc_html_e( 'Bulk translation is unavailable', 'haayal-ai-slug-translator' ); ?></strong>
					<p>
						<?php
						printf(
							wp_kses_post(
								/* translators: %s: link to the Permalink Settings page */
								__( 'Your site uses <strong>Plain permalinks</strong>, which do not support custom slugs. Bulk translation has no effect in this mode. To use this feature, <a href="%s">change your permalink structure</a> to any option other than "Plain".', 'haayal-ai-slug-translator' )
							),
							esc_url( $permalink_url )
						);
						?>
					</p>
					<a href="<?php echo esc_url( $permalink_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Go to Permalink Settings', 'haayal-ai-slug-translator' ); ?>
					</a>
				</div>
			</div>
			<?php
			return;
		}
		if ( self::is_proxy_mode() ) {
			?>
			<h2><?php esc_html_e( 'Bulk Translation', 'haayal-ai-slug-translator' ); ?></h2>
			<div class="haayal-bulk-proxy-gate">
				<div class="haayal-bulk-proxy-gate__icon" aria-hidden="true">&#128268;</div>
				<div class="haayal-bulk-proxy-gate__body">
					<strong><?php esc_html_e( 'Bulk Translation Requires Your Own AI Connection', 'haayal-ai-slug-translator' ); ?></strong>
					<p><?php esc_html_e( 'You get 100 free translations on us - plenty to try things out and translate some content.', 'haayal-ai-slug-translator' ); ?></p>
					<p><?php esc_html_e( 'If you\'d like to translate your entire site at once, you\'ll need to connect your own AI provider. This isn\'t a paid plugin feature - it simply uses your own AI account instead of ours.', 'haayal-ai-slug-translator' ); ?></p>
					<p><?php esc_html_e( 'Supported options:', 'haayal-ai-slug-translator' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'OpenAI API key', 'haayal-ai-slug-translator' ); ?></li>
						<li><?php esc_html_e( 'WordPress AI Connectors (OpenAI, Anthropic, Google, and more)', 'haayal-ai-slug-translator' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Once connected, you can bulk translate as much content as you\'d like.', 'haayal-ai-slug-translator' ); ?></p>
					<div class="haayal-bulk-proxy-gate__actions">
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-slug-translator&tab=settings' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Go to Settings', 'haayal-ai-slug-translator' ); ?>
						</a>
						<a href="#" class="button switch-to-tab" data-tab="tab-btn-user-guide">
							<?php esc_html_e( 'How to get an API key', 'haayal-ai-slug-translator' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<h2><?php esc_html_e( 'Bulk Translation', 'haayal-ai-slug-translator' ); ?></h2>
		<p class="haayal-bulk-description"><?php esc_html_e( 'Translate existing non-English slugs into clean English URLs. Select a post type or taxonomy to see all items with non-Latin slugs.', 'haayal-ai-slug-translator' ); ?></p>

		<div id="haayal-bulk-wrapper">
			<div class="haayal-bulk-top-bar">
				<div class="haayal-bulk-controls">
					<div class="haayal-bulk-source">
						<label for="haayal-bulk-source-select">
							<?php esc_html_e( 'Select type:', 'haayal-ai-slug-translator' ); ?>
							<span class="haayal-info-icon" aria-label="<?php esc_attr_e( 'Info', 'haayal-ai-slug-translator' ); ?>" tabindex="0"><span class="dashicons dashicons-info-outline"></span><span class="haayal-info-popover"><span class="haayal-info-popover-inner"><?php
								printf(
									/* translators: %s is a link to the Settings tab */
									esc_html__( 'Only post types and taxonomies enabled in %s are listed here. If you don\'t see what you\'re looking for, enable it there first.', 'haayal-ai-slug-translator' ),
									'<a href="#" class="switch-to-tab" data-tab="tab-btn-settings">' . esc_html__( 'Settings', 'haayal-ai-slug-translator' ) . '</a>'
								);
							?></span></span></span>
						</label>
						<select id="haayal-bulk-source-select">
							<option value=""><?php esc_html_e( '— Choose —', 'haayal-ai-slug-translator' ); ?></option>
						</select>
					</div>
				</div>

				<div class="haayal-bulk-options">
					<label>
						<input type="checkbox" id="haayal-bulk-redirects" <?php checked( Haayal_AI_Slug_Settings::get_settings()['enable_redirects'], 1 ); ?>>
						<?php esc_html_e( 'Create 301 redirects for changed URLs', 'haayal-ai-slug-translator' ); ?>
					</label>
					<p class="haayal-bulk-redirect-note" data-note-on="<?php esc_attr_e( '301 redirects are created automatically, but may not cover all edge cases. Please review them after saving to avoid potential SEO issues.', 'haayal-ai-slug-translator' ); ?>" data-note-off="<?php esc_attr_e( 'Redirects are disabled — visitors following old URLs may encounter 404 errors. If redirects are not handled elsewhere, consider enabling them above to help prevent broken links.', 'haayal-ai-slug-translator' ); ?>"></p>
				</div>
			</div>

			<div id="haayal-bulk-proxy-warning" class="haayal-bulk-warning" style="display:none;">
				<?php esc_html_e( 'You are using the free plan. Remaining translations:', 'haayal-ai-slug-translator' ); ?>
				<strong id="haayal-bulk-quota-count"></strong>
			</div>

			<div id="haayal-bulk-message" class="haayal-bulk-message"></div>
			<div id="haayal-bulk-count" class="haayal-table-count" style="display:none;"></div>

			<div id="haayal-bulk-table-wrapper">
				<table id="haayal-bulk-table" class="widefat striped" style="display:none;">
					<caption class="screen-reader-text"><?php esc_html_e( 'Items available for slug translation', 'haayal-ai-slug-translator' ); ?></caption>
					<thead>
						<tr>
							<th scope="col" class="column-title"><?php esc_html_e( 'Title', 'haayal-ai-slug-translator' ); ?></th>
							<th scope="col" class="column-current-slug"><?php esc_html_e( 'Current Slug', 'haayal-ai-slug-translator' ); ?></th>
							<th scope="col" class="column-new-slug"><?php esc_html_e( 'New Slug', 'haayal-ai-slug-translator' ); ?></th>
							<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'haayal-ai-slug-translator' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>

			<div class="haayal-bulk-bottom-row">
				<div class="haayal-bulk-footer" style="display:none;">
					<div class="haayal-bulk-bottom-actions">
						<button type="button" id="haayal-bulk-translate-btn" class="button button-primary" style="display:none;">
							<?php esc_html_e( 'Translate All', 'haayal-ai-slug-translator' ); ?>
						</button>
						<button type="button" id="haayal-bulk-save-btn" class="button button-primary" style="display:none;">
							<?php esc_html_e( 'Save All', 'haayal-ai-slug-translator' ); ?>
						</button>
						<button type="button" id="haayal-bulk-stop-btn" class="button" style="display:none;">
							<?php esc_html_e( 'Stop', 'haayal-ai-slug-translator' ); ?>
						</button>
					</div>
					<div id="haayal-bulk-pagination"></div>
				</div>
				<div id="haayal-bulk-progress" style="display:none;">
					<div class="haayal-bulk-progress-bar">
						<div class="haayal-bulk-progress-fill"></div>
					</div>
					<span class="haayal-bulk-progress-text"></span>
				</div>
			</div>

			<div id="haayal-bulk-summary" class="haayal-bulk-summary" style="display:none;"></div>

			<div id="haayal-bulk-excluded-section" style="display:none;">
				<button type="button" id="haayal-bulk-show-excluded" class="button-link haayal-bulk-show-excluded-btn" aria-expanded="false" aria-controls="haayal-bulk-excluded-list">
					<?php esc_html_e( 'Show excluded items', 'haayal-ai-slug-translator' ); ?>
				</button>
				<div id="haayal-bulk-excluded-list" style="display:none;">
					<h3 class="haayal-bulk-excluded-title"><?php esc_html_e( 'Excluded Items', 'haayal-ai-slug-translator' ); ?></h3>
					<p class="haayal-bulk-excluded-description"><?php esc_html_e( 'These items were excluded from translation. Click Restore to include them in the translation list again.', 'haayal-ai-slug-translator' ); ?></p>
					<div id="haayal-bulk-excluded-count" class="haayal-table-count"></div>
					<table id="haayal-bulk-excluded-table" class="widefat striped">
						<caption class="screen-reader-text"><?php esc_html_e( 'Items excluded from slug translation', 'haayal-ai-slug-translator' ); ?></caption>
						<thead>
							<tr>
								<th scope="col" class="column-title"><?php esc_html_e( 'Title', 'haayal-ai-slug-translator' ); ?></th>
								<th scope="col" class="column-current-slug"><?php esc_html_e( 'Current Slug', 'haayal-ai-slug-translator' ); ?></th>
								<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'haayal-ai-slug-translator' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Dismiss an item so it won't appear in future bulk loads.
	 */
	public static function ajax_dismiss() {
		check_ajax_referer( 'haayal_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		$source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : '';
		$source_name = isset( $_POST['source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['source_name'] ) ) : '';
		$id          = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( ! $id || empty( $source_type ) || empty( $source_name ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid item.', 'haayal-ai-slug-translator' ) ] );
		}

		$dismissed = get_option( self::$dismissed_option, [] );
		$key       = $source_type . ':' . $source_name;

		if ( ! isset( $dismissed[ $key ] ) ) {
			$dismissed[ $key ] = [];
		}

		if ( ! in_array( $id, $dismissed[ $key ], true ) ) {
			$dismissed[ $key ][] = $id;
		}

		update_option( self::$dismissed_option, $dismissed );

		wp_send_json_success();
	}

	/**
	 * AJAX: Restore a dismissed item so it appears again in bulk loads.
	 */
	public static function ajax_restore() {
		check_ajax_referer( 'haayal_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		$source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : '';
		$source_name = isset( $_POST['source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['source_name'] ) ) : '';
		$id          = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( ! $id || empty( $source_type ) || empty( $source_name ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid item.', 'haayal-ai-slug-translator' ) ] );
		}

		$dismissed = get_option( self::$dismissed_option, [] );
		$key       = $source_type . ':' . $source_name;

		if ( isset( $dismissed[ $key ] ) ) {
			$dismissed[ $key ] = array_values( array_diff( $dismissed[ $key ], [ $id ] ) );
			if ( empty( $dismissed[ $key ] ) ) {
				unset( $dismissed[ $key ] );
			}
			update_option( self::$dismissed_option, $dismissed );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Load excluded/dismissed items for the current source.
	 */
	public static function ajax_load_excluded() {
		check_ajax_referer( 'haayal_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		$source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : '';
		$source_name = isset( $_POST['source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['source_name'] ) ) : '';

		if ( empty( $source_type ) || empty( $source_name ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid source.', 'haayal-ai-slug-translator' ) ] );
		}

		$dismissed    = get_option( self::$dismissed_option, [] );
		$key          = $source_type . ':' . $source_name;
		$excluded_ids = isset( $dismissed[ $key ] ) ? array_map( 'absint', $dismissed[ $key ] ) : [];

		if ( empty( $excluded_ids ) ) {
			wp_send_json_success( [ 'items' => [] ] );
		}

		$items = [];

		if ( 'post_type' === $source_type ) {
			foreach ( $excluded_ids as $id ) {
				$post = get_post( $id );
				if ( $post && $post->post_type === $source_name ) {
					$items[] = [
						'id'           => (int) $post->ID,
						'title'        => $post->post_title,
						'current_slug' => urldecode( $post->post_name ),
					];
				}
			}
		} elseif ( 'taxonomy' === $source_type ) {
			foreach ( $excluded_ids as $id ) {
				$term = get_term( $id, $source_name );
				if ( $term && ! is_wp_error( $term ) ) {
					$items[] = [
						'id'           => (int) $term->term_id,
						'title'        => $term->name,
						'current_slug' => urldecode( $term->slug ),
					];
				}
			}
		}

		wp_send_json_success( [ 'items' => $items ] );
	}

	/**
	 * AJAX: Returns current enabled post types and taxonomies.
	 */
	public static function ajax_get_sources() {
		check_ajax_referer( 'haayal_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		$settings   = Haayal_AI_Slug_Settings::get_settings();
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $post_types['attachment'] );
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

		$enabled_pt = [];
		foreach ( $settings['enabled_post_types'] as $pt ) {
			if ( isset( $post_types[ $pt ] ) ) {
				$enabled_pt[] = [
					'name'  => $pt,
					'label' => $post_types[ $pt ]->label,
				];
			}
		}

		$enabled_tax = [];
		foreach ( $settings['enabled_taxonomies'] as $tax ) {
			if ( isset( $taxonomies[ $tax ] ) ) {
				$enabled_tax[] = [
					'name'  => $tax,
					'label' => $taxonomies[ $tax ]->label,
				];
			}
		}

		wp_send_json_success( [
			'enabled_post_types' => $enabled_pt,
			'enabled_taxonomies' => $enabled_tax,
			'connection_method'  => $settings['connection_method'],
			'proxy_quota'        => (int) get_option( 'haayal_ai_proxy_quota_remaining', 100 ),
		] );
	}

	/**
	 * AJAX: Load items with non-Latin slugs.
	 */
	public static function ajax_load_items() {
		check_ajax_referer( 'haayal_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		if ( self::is_proxy_mode() ) {
			wp_send_json_error( [ 'message' => 'bulk_proxy_blocked' ] );
		}

		$source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : '';
		$source_name = isset( $_POST['source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['source_name'] ) ) : '';
		$page        = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page    = 25;
		$offset      = ( $page - 1 ) * $per_page;

		if ( empty( $source_type ) || empty( $source_name ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid source.', 'haayal-ai-slug-translator' ) ] );
		}

		// Validate source_name against plugin-enabled types to prevent loading arbitrary content.
		$_load_settings = Haayal_AI_Slug_Settings::get_settings();
		if ( 'post_type' === $source_type && ! in_array( $source_name, $_load_settings['enabled_post_types'], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid source.', 'haayal-ai-slug-translator' ) ] );
		}
		if ( 'taxonomy' === $source_type && ! in_array( $source_name, $_load_settings['enabled_taxonomies'], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid source.', 'haayal-ai-slug-translator' ) ] );
		}
		unset( $_load_settings );

		// Get dismissed IDs for this source.
		$dismissed    = get_option( self::$dismissed_option, [] );
		$key          = $source_type . ':' . $source_name;
		$excluded_ids = isset( $dismissed[ $key ] ) ? array_map( 'absint', $dismissed[ $key ] ) : [];

		global $wpdb;

		$items = [];
		$total = 0;

		if ( 'post_type' === $source_type ) {
			$exclude_sql = '';
			if ( ! empty( $excluded_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $excluded_ids ), '%d' ) );
				$exclude_sql  = $wpdb->prepare( " AND ID NOT IN ($placeholders)", $excluded_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}

			// Non-Latin slugs: URL-encoded (contain %) or raw non-ASCII.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_type = %s
					AND post_status IN ('publish','draft','pending','future','private')
					AND (post_name LIKE %s OR post_name REGEXP %s)",
					$source_name,
					'%' . $wpdb->esc_like( '%' ) . '%',
					'[^a-zA-Z0-9_-]'
				) . $exclude_sql
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title, post_name FROM {$wpdb->posts}
					WHERE post_type = %s
					AND post_status IN ('publish','draft','pending','future','private')
					AND (post_name LIKE %s OR post_name REGEXP %s)",
					$source_name,
					'%' . $wpdb->esc_like( '%' ) . '%',
					'[^a-zA-Z0-9_-]'
				) . $exclude_sql . $wpdb->prepare(
					' ORDER BY post_title ASC LIMIT %d OFFSET %d',
					$per_page,
					$offset
				)
			);

			if ( $rows ) {
				foreach ( $rows as $row ) {
					$items[] = [
						'id'           => (int) $row->ID,
						'title'        => $row->post_title,
						'current_slug' => urldecode( $row->post_name ),
						'edit_url'     => get_edit_post_link( $row->ID, 'raw' ),
					];
				}
			}
		} elseif ( 'taxonomy' === $source_type ) {
			$exclude_sql = '';
			if ( ! empty( $excluded_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $excluded_ids ), '%d' ) );
				$exclude_sql  = $wpdb->prepare( " AND t.term_id NOT IN ($placeholders)", $excluded_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->terms} t
					INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					AND (t.slug LIKE %s OR t.slug REGEXP %s)",
					$source_name,
					'%' . $wpdb->esc_like( '%' ) . '%',
					'[^a-zA-Z0-9_-]'
				) . $exclude_sql
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id, t.name, t.slug FROM {$wpdb->terms} t
					INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					AND (t.slug LIKE %s OR t.slug REGEXP %s)",
					$source_name,
					'%' . $wpdb->esc_like( '%' ) . '%',
					'[^a-zA-Z0-9_-]'
				) . $exclude_sql . $wpdb->prepare(
					' ORDER BY t.name ASC LIMIT %d OFFSET %d',
					$per_page,
					$offset
				)
			);

			if ( $rows ) {
				foreach ( $rows as $row ) {
					$edit_url = admin_url( 'term.php?taxonomy=' . $source_name . '&tag_ID=' . $row->term_id );
					$items[]  = [
						'id'           => (int) $row->term_id,
						'title'        => $row->name,
						'current_slug' => urldecode( $row->slug ),
						'edit_url'     => $edit_url,
					];
				}
			}
		}

		wp_send_json_success( [
			'items' => $items,
			'total' => $total,
			'pages' => ceil( $total / $per_page ),
			'page'  => $page,
		] );
	}

	/**
	 * AJAX: Translate a batch of titles.
	 */
	public static function ajax_translate() {
		check_ajax_referer( 'haayal_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		if ( self::is_proxy_mode() ) {
			wp_send_json_error( [ 'message' => 'bulk_proxy_blocked' ] );
		}

		// Allow long-running translation.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@set_time_limit( 300 );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each element is sanitized individually below.
		$raw_items   = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : [];
		$source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : '';
		$source_name = isset( $_POST['source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['source_name'] ) ) : '';

		if ( empty( $raw_items ) ) {
			wp_send_json_error( [ 'message' => __( 'No items to translate.', 'haayal-ai-slug-translator' ) ] );
		}

		$settings        = Haayal_AI_Slug_Settings::get_settings();
		$api_key         = $settings['api_key'];
		$max_tokens      = isset( $settings['max_tokens'] ) ? (int) $settings['max_tokens'] : 20;
		$connection       = $settings['connection_method'] ?? 'proxy';
		$quota_exhausted = false;
		$results         = [];

		foreach ( $raw_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id    = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';

			if ( ! $id || empty( $title ) ) {
				$results[] = [
					'id'    => $id,
					'slug'  => '',
					'error' => __( 'Invalid item.', 'haayal-ai-slug-translator' ),
				];
				continue;
			}

			// Stop calling the API once proxy quota is exhausted.
			if ( $quota_exhausted ) {
				$results[] = [
					'id'    => $id,
					'slug'  => '',
					'error' => __( 'Quota exhausted.', 'haayal-ai-slug-translator' ),
				];
				continue;
			}

			$log_context = [ 'object_id' => $id, 'object_type' => 'taxonomy' === $source_type ? 'term' : 'post' ];
			if ( 'taxonomy' === $source_type && $source_name ) {
				$log_context['taxonomy'] = $source_name;
			}
			Haayal_AI_Slug_Log::set_context( $log_context );

			$slug = Haayal_AI_Slug_Helpers::translate_and_track( $title, $api_key, $max_tokens );

			Haayal_AI_Slug_Log::set_context();

			if ( ! $slug && 'proxy' === $connection && (int) get_option( 'haayal_ai_proxy_quota_remaining', 100 ) <= 0 ) {
				$quota_exhausted = true;
			}

			$results[] = [
				'id'    => $id,
				'slug'  => $slug ? $slug : '',
				'error' => $slug ? '' : ( $quota_exhausted ? __( 'Quota exhausted.', 'haayal-ai-slug-translator' ) : __( 'Translation failed.', 'haayal-ai-slug-translator' ) ),
			];
		}

		wp_send_json_success( [
			'results'         => $results,
			'quota_remaining' => (int) get_option( 'haayal_ai_proxy_quota_remaining', 100 ),
		] );
	}

	/**
	 * AJAX: Save translated slugs in batch.
	 */
	public static function ajax_save() {
		check_ajax_referer( 'haayal_bulk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		if ( self::is_proxy_mode() ) {
			wp_send_json_error( [ 'message' => 'bulk_proxy_blocked' ] );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each element is sanitized individually below.
		$raw_items         = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : [];
		$enable_redirects  = isset( $_POST['enable_redirects'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['enable_redirects'] ) );

		if ( empty( $raw_items ) ) {
			wp_send_json_error( [ 'message' => __( 'No items to save.', 'haayal-ai-slug-translator' ) ] );
		}

		$results           = [];
		$redirects_created = 0;

		foreach ( $raw_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id          = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$new_slug    = isset( $item['new_slug'] ) ? sanitize_title( $item['new_slug'] ) : '';
			$source_type = isset( $item['source_type'] ) ? sanitize_text_field( $item['source_type'] ) : '';
			$source_name = isset( $item['source_name'] ) ? sanitize_text_field( $item['source_name'] ) : '';

			if ( ! $id || empty( $new_slug ) || empty( $source_type ) || empty( $source_name ) ) {
				$results[] = [
					'id'       => $id,
					'success'  => false,
					'new_slug' => '',
					'error'    => __( 'Invalid item data.', 'haayal-ai-slug-translator' ),
				];
				continue;
			}

			if ( 'post_type' === $source_type ) {
				$result = self::save_post_slug( $id, $new_slug, $source_name, $enable_redirects );
			} elseif ( 'taxonomy' === $source_type ) {
				$result = self::save_term_slug( $id, $new_slug, $source_name, $enable_redirects );
			} else {
				$result = [
					'id'       => $id,
					'success'  => false,
					'new_slug' => '',
					'error'    => __( 'Unknown source type.', 'haayal-ai-slug-translator' ),
				];
			}

			if ( ! empty( $result['redirect_created'] ) ) {
				$redirects_created += $result['redirect_created'];
			}
			unset( $result['redirect_created'] );

			$results[] = $result;
		}

		wp_send_json_success( [
			'results'           => $results,
			'redirects_created' => $redirects_created,
		] );
	}

	/**
	 * Saves a new slug for a post and optionally creates redirects.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $new_slug   The new slug.
	 * @param string $post_type  The post type name.
	 * @param bool   $redirects  Whether to create redirects.
	 *
	 * @return array Result with id, success, new_slug, error, redirect_created.
	 */
	private static function save_post_slug( $post_id, $new_slug, $post_type, $redirects ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== $post_type ) {
			return [
				'id'               => $post_id,
				'success'          => false,
				'new_slug'         => '',
				'error'            => __( 'Post not found.', 'haayal-ai-slug-translator' ),
				'redirect_created' => 0,
			];
		}

		$old_slug = $post->post_name;

		// Concurrency guard: slug hasn't changed since load.
		if ( sanitize_title( urldecode( $old_slug ) ) === $new_slug ) {
			return [
				'id'               => $post_id,
				'success'          => false,
				'new_slug'         => $new_slug,
				'error'            => __( 'Slug is already the same.', 'haayal-ai-slug-translator' ),
				'redirect_created' => 0,
			];
		}

		$redirect_count = 0;
		$is_hierarchical = is_post_type_hierarchical( $post_type );

		// Capture old URLs before update.
		$old_urls = [];
		if ( $redirects ) {
			$old_urls[ $post_id ] = wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH );

			// Capture descendant URLs for hierarchical types (pages).
			if ( $is_hierarchical ) {
				$children = get_pages( [ 'child_of' => $post_id, 'post_type' => $post_type ] );
				if ( $children ) {
					foreach ( $children as $child ) {
						$old_urls[ $child->ID ] = wp_parse_url( get_permalink( $child->ID ), PHP_URL_PATH );
					}
				}
			}
		}

		// Update the post slug.
		$result = wp_update_post( [
			'ID'        => $post_id,
			'post_name' => $new_slug,
		], true );

		if ( is_wp_error( $result ) ) {
			return [
				'id'               => $post_id,
				'success'          => false,
				'new_slug'         => '',
				'error'            => $result->get_error_message(),
				'redirect_created' => 0,
			];
		}

		// Set meta.
		update_post_meta( $post_id, '_slug_source', 'ai' );
		update_post_meta( $post_id, '_generated_slug', $new_slug );

		// Get the actual saved slug (WP may have appended -2, etc.).
		$updated_post = get_post( $post_id );
		$actual_slug  = $updated_post->post_name;

		// Create redirects.
		if ( $redirects && ! empty( $old_urls ) ) {
			foreach ( $old_urls as $obj_id => $old_path ) {
				$new_path = wp_parse_url( get_permalink( $obj_id ), PHP_URL_PATH );
				if ( $old_path && $new_path && $old_path !== $new_path ) {
					Haayal_AI_Slug_Redirects::add_redirect( $old_path, $new_path, 'post', $obj_id );
					$redirect_count++;
				}
			}
		}

		return [
			'id'               => $post_id,
			'success'          => true,
			'new_slug'         => $actual_slug,
			'error'            => '',
			'redirect_created' => $redirect_count,
		];
	}

	/**
	 * Saves a new slug for a term and optionally creates a redirect.
	 *
	 * @param int    $term_id   The term ID.
	 * @param string $new_slug  The new slug.
	 * @param string $taxonomy  The taxonomy name.
	 * @param bool   $redirects Whether to create redirects.
	 *
	 * @return array Result with id, success, new_slug, error, redirect_created.
	 */
	private static function save_term_slug( $term_id, $new_slug, $taxonomy, $redirects ) {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return [
				'id'               => $term_id,
				'success'          => false,
				'new_slug'         => '',
				'error'            => __( 'Term not found.', 'haayal-ai-slug-translator' ),
				'redirect_created' => 0,
			];
		}

		$old_slug = $term->slug;

		if ( sanitize_title( urldecode( $old_slug ) ) === $new_slug ) {
			return [
				'id'               => $term_id,
				'success'          => false,
				'new_slug'         => $new_slug,
				'error'            => __( 'Slug is already the same.', 'haayal-ai-slug-translator' ),
				'redirect_created' => 0,
			];
		}

		$redirect_count = 0;

		// Capture old URL.
		$old_url = null;
		if ( $redirects ) {
			$old_link = get_term_link( $term_id, $taxonomy );
			if ( ! is_wp_error( $old_link ) ) {
				$old_url = wp_parse_url( $old_link, PHP_URL_PATH );
			}
		}

		// Ensure unique slug.
		$unique_slug = Haayal_AI_Slug_Helpers::ensure_unique_term_slug( $new_slug, $taxonomy );

		$result = wp_update_term( $term_id, $taxonomy, [ 'slug' => $unique_slug ] );

		if ( is_wp_error( $result ) ) {
			return [
				'id'               => $term_id,
				'success'          => false,
				'new_slug'         => '',
				'error'            => $result->get_error_message(),
				'redirect_created' => 0,
			];
		}

		// Create redirect.
		if ( $redirects && $old_url ) {
			$new_link = get_term_link( $term_id, $taxonomy );
			if ( ! is_wp_error( $new_link ) ) {
				$new_path = wp_parse_url( $new_link, PHP_URL_PATH );
				if ( $old_url !== $new_path ) {
					Haayal_AI_Slug_Redirects::add_redirect( $old_url, $new_path, 'term', $term_id );
					$redirect_count++;
				}
			}
		}

		return [
			'id'               => $term_id,
			'success'          => true,
			'new_slug'         => $unique_slug,
			'error'            => '',
			'redirect_created' => $redirect_count,
		];
	}

	/**
	 * Returns true when the site is configured to use the shared free proxy,
	 * including the legacy empty-method fallback when no API key is set.
	 */
	private static function is_proxy_mode(): bool {
		$settings = Haayal_AI_Slug_Settings::get_settings();
		$method   = $settings['connection_method'] ?? '';
		return 'proxy' === $method || ( '' === $method && empty( $settings['api_key'] ) );
	}
}
