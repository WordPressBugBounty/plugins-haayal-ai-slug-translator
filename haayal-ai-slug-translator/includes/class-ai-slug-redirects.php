<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Haayal_AI_Slug_Redirects {

	private static $table_name_suffix = 'haayal_redirects';
	private static $db_version        = '1.0';
	private static $db_version_option = 'haayal_redirects_db_version';

	/**
	 * Returns the full table name with the WP prefix.
	 */
	private static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name_suffix;
	}

	/**
	 * Registers hooks.
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_init', [ __CLASS__, 'maybe_create_table' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
			add_action( 'wp_ajax_haayal_redirects_load', [ __CLASS__, 'ajax_load_redirects' ] );
			add_action( 'wp_ajax_haayal_redirects_delete', [ __CLASS__, 'ajax_delete_redirect' ] );
			add_action( 'wp_ajax_haayal_redirects_delete_all', [ __CLASS__, 'ajax_delete_all_redirects' ] );
		}
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ], 5 );

		// Clean up redirects when a post or term is deleted.
		add_action( 'before_delete_post', [ __CLASS__, 'on_delete_post' ] );
		add_action( 'pre_delete_term', [ __CLASS__, 'on_delete_term' ], 10, 2 );
	}

	/**
	 * Enqueues redirects tab assets on the settings page only.
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
			'haayal-ai-slug-redirects',
			$plugin_url . 'assets/css/ai-slug-redirects.css',
			[],
			$version
		);

		wp_enqueue_script(
			'haayal-ai-slug-redirects',
			$plugin_url . 'assets/js/ai-slug-redirects.js',
			[ 'jquery', 'sweetalert2' ],
			$version,
			true
		);

		wp_localize_script( 'haayal-ai-slug-redirects', 'haayalRedirects', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'haayal_redirects_nonce' ),
			'i18n'     => [
				'no_redirects'   => __( 'No redirect rules found.', 'haayal-ai-slug-translator' ),
				'loading'        => __( 'Loading redirects...', 'haayal-ai-slug-translator' ),
				'error'          => __( 'Error loading redirects.', 'haayal-ai-slug-translator' ),
				'remove'         => __( 'Remove redirect', 'haayal-ai-slug-translator' ),
				'delete_title'       => __( 'Remove Redirect?', 'haayal-ai-slug-translator' ),
				'delete_text'        => __( 'This may affect SEO and cause broken links.', 'haayal-ai-slug-translator' ),
				'delete_all_title'   => __( 'Remove All Redirects?', 'haayal-ai-slug-translator' ),
				'delete_all_text'    => __( 'This cannot be undone and may negatively impact SEO and cause broken links.', 'haayal-ai-slug-translator' ),
				'confirm_btn'        => __( 'Yes, remove', 'haayal-ai-slug-translator' ),
				'cancel_btn'         => __( 'Cancel', 'haayal-ai-slug-translator' ),
				'deleted'        => __( 'Redirect removed.', 'haayal-ai-slug-translator' ),
				'all_deleted'    => __( 'All redirects removed.', 'haayal-ai-slug-translator' ),
				'active_redirects' => __( 'active redirects', 'haayal-ai-slug-translator' ),
				'post'           => __( 'Post', 'haayal-ai-slug-translator' ),
				'term'           => __( 'Term', 'haayal-ai-slug-translator' ),
				'url_mismatch_1' => __( 'The current URL of this item no longer matches the redirect destination.', 'haayal-ai-slug-translator' ),
				'url_mismatch_2' => __( 'The slug may have been changed again since this redirect was created.', 'haayal-ai-slug-translator' ),
			],
		] );
	}

	/**
	 * Renders the Redirects tab content.
	 */
	public static function render_tab() {
		?>
		<h2><?php esc_html_e( 'Redirects', 'haayal-ai-slug-translator' ); ?></h2>
		<p class="haayal-redirects-description"><?php esc_html_e( 'These 301 redirects were created during bulk slug translation. If you prefer to manage redirects using another plugin or .htaccess, you can safely delete them here.', 'haayal-ai-slug-translator' ); ?></p>

		<div id="haayal-redirects-wrapper">
			<div class="haayal-redirects-top-bar">
				<div class="haayal-redirects-filter">
					<label for="haayal-redirects-filter-select"><?php esc_html_e( 'Filter:', 'haayal-ai-slug-translator' ); ?></label>
					<select id="haayal-redirects-filter-select">
						<option value=""><?php esc_html_e( 'All', 'haayal-ai-slug-translator' ); ?></option>
						<option value="post"><?php esc_html_e( 'Posts / Post Types', 'haayal-ai-slug-translator' ); ?></option>
						<option value="term"><?php esc_html_e( 'Terms', 'haayal-ai-slug-translator' ); ?></option>
					</select>
				</div>
				<div id="haayal-redirects-count" class="haayal-redirects-count"></div>
			</div>

			<div id="haayal-redirects-message" class="haayal-redirects-message"></div>

			<table id="haayal-redirects-table" class="widefat striped" style="display:none;">
				<caption class="screen-reader-text"><?php esc_html_e( 'Slug translation redirects', 'haayal-ai-slug-translator' ); ?></caption>
				<thead>
					<tr>
						<th scope="col" class="column-title"><?php esc_html_e( 'Title', 'haayal-ai-slug-translator' ); ?></th>
						<th scope="col" class="column-old-url"><?php esc_html_e( 'Old URL', 'haayal-ai-slug-translator' ); ?></th>
						<th scope="col" class="column-new-url"><?php esc_html_e( 'New URL', 'haayal-ai-slug-translator' ); ?></th>
						<th scope="col" class="column-type"><?php esc_html_e( 'Type', 'haayal-ai-slug-translator' ); ?></th>
						<th scope="col" class="column-created"><?php esc_html_e( 'Redirected On', 'haayal-ai-slug-translator' ); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'haayal-ai-slug-translator' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>

			<div class="haayal-redirects-footer" style="display:none;">
				<div class="haayal-redirects-export-actions">
					<button type="button" id="haayal-redirects-export-csv" class="button">
						<?php esc_html_e( 'Export CSV', 'haayal-ai-slug-translator' ); ?>
					</button>
					<button type="button" id="haayal-redirects-export-htaccess" class="button">
						<?php esc_html_e( 'Export .htaccess rules', 'haayal-ai-slug-translator' ); ?>
					</button>
				</div>
				<div id="haayal-redirects-pagination"></div>
				<button type="button" id="haayal-redirects-delete-all" class="button haayal-redirects-delete-all-btn">
					<?php esc_html_e( 'Remove All Redirects', 'haayal-ai-slug-translator' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Creates the redirects table using dbDelta.
	 */
	public static function install() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			old_url VARCHAR(2048) NOT NULL,
			new_url VARCHAR(2048) NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY old_url_hash (old_url(191)),
			KEY object_lookup (object_type, object_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::$db_version_option, self::$db_version );
	}

	/**
	 * Checks if the DB table needs to be created or updated.
	 */
	public static function maybe_create_table() {
		if ( get_option( self::$db_version_option ) !== self::$db_version ) {
			self::install();
		}
	}

	/**
	 * Adds a redirect record, storing relative paths only.
	 * Prevents redirect chains by updating existing redirects that pointed to the old URL.
	 *
	 * @param string $old_url     The old relative path (e.g. /old-parent/old-page/).
	 * @param string $new_url     The new relative path.
	 * @param string $object_type 'post' or 'term'.
	 * @param int    $object_id   The post ID or term ID.
	 */
	public static function add_redirect( $old_url, $new_url, $object_type, $object_id ) {
		global $wpdb;

		$table = self::table_name();

		// Normalize paths.
		$old_url = untrailingslashit( wp_parse_url( $old_url, PHP_URL_PATH ) );
		$new_url = untrailingslashit( wp_parse_url( $new_url, PHP_URL_PATH ) );

		// Don't create a redirect to itself.
		if ( $old_url === $new_url || empty( $old_url ) || empty( $new_url ) ) {
			return;
		}

		// Prevent chains: update any existing X → old_url to X → new_url.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			[ 'new_url' => $new_url ],
			[ 'new_url' => $old_url ],
			[ '%s' ],
			[ '%s' ]
		);

		// Remove any existing redirect from the same old_url.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			[ 'old_url' => $old_url ],
			[ '%s' ]
		);

		// Insert the new redirect.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			[
				'old_url'     => $old_url,
				'new_url'     => $new_url,
				'object_type' => $object_type,
				'object_id'   => $object_id,
			],
			[ '%s', '%s', '%s', '%d' ]
		);
	}

	/**
	 * Looks up a redirect by the request path.
	 *
	 * @param string $path The request path.
	 * @return string|null  The new path, or null if not found.
	 */
	public static function get_redirect( $path ) {
		global $wpdb;

		$table = self::table_name();
		$path  = untrailingslashit( $path );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe constant from self::table_name().
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT new_url FROM {$table} WHERE old_url = %s LIMIT 1",
				$path
			)
		);
	}

	/**
	 * Handles 301 redirects on 404 pages.
	 * Only fires on 404 — zero overhead on normal page loads.
	 */
	public static function maybe_redirect() {
		if ( ! is_404() ) {
			return;
		}

		// Check if our table exists (avoid errors before activation).
		static $table_exists = null;
		if ( null === $table_exists ) {
			global $wpdb;
			$table        = self::table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check uses static variable for in-request caching.
			$table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
		}
		if ( ! $table_exists ) {
			return;
		}

		// Use esc_url_raw() to sanitize the URI while preserving %-encoded characters needed for path matching.
		$request_path = wp_parse_url( esc_url_raw( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) ), PHP_URL_PATH );
		$new_path     = self::get_redirect( $request_path );

		if ( $new_path ) {
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Destination is an internal path from our own DB table, constructed via home_url().
			wp_redirect( home_url( $new_path ), 301 );
			exit;
		}
	}

	/**
	 * Clean up redirects when a post is permanently deleted.
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public static function on_delete_post( $post_id ) {
		self::delete_redirects_for_object( 'post', $post_id );
	}

	/**
	 * Clean up redirects when a term is deleted.
	 *
	 * @param int    $term_id  The term ID being deleted.
	 * @param string $taxonomy The taxonomy of the term.
	 */
	public static function on_delete_term( $term_id, $taxonomy ) {
		self::delete_redirects_for_object( 'term', $term_id );
	}

	/**
	 * Deletes all redirects for a specific object.
	 *
	 * @param string $object_type 'post' or 'term'.
	 * @param int    $object_id   The post ID or term ID.
	 */
	public static function delete_redirects_for_object( $object_type, $object_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			self::table_name(),
			[
				'object_type' => $object_type,
				'object_id'   => $object_id,
			],
			[ '%s', '%d' ]
		);
	}

	/**
	 * AJAX: Load paginated redirect rows.
	 */
	public static function ajax_load_redirects() {
		check_ajax_referer( 'haayal_redirects_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		global $wpdb;

		$table       = self::table_name();
		$filter_type = isset( $_POST['filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_type'] ) ) : '';
		$export      = ! empty( $_POST['export'] );
		$page        = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page    = 50;
		$offset      = ( $page - 1 ) * $per_page;

		if ( 'post' === $filter_type || 'term' === $filter_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a safe constant derived from $wpdb->prefix.
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_type = %s", $filter_type )
			);

			if ( $export ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$table} WHERE object_type = %s ORDER BY created_at DESC", $filter_type )
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$table} WHERE object_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
						$filter_type,
						$per_page,
						$offset
					)
				);
			}
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a safe constant derived from $wpdb->prefix.
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

			if ( $export ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
						$per_page,
						$offset
					)
				);
			}
		}

		$items = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$object_title   = '';
				$object_subtype = '';
				$permalink      = '';
				if ( 'post' === $row->object_type ) {
					$post         = get_post( $row->object_id );
					$object_title = $post ? $post->post_title : '#' . $row->object_id;
					if ( $post ) {
						$pt_obj         = get_post_type_object( $post->post_type );
						$object_subtype = $pt_obj ? $pt_obj->labels->singular_name : $post->post_type;
						$permalink      = get_permalink( $post );
					}
				} elseif ( 'term' === $row->object_type ) {
					$term         = get_term( $row->object_id );
					$object_title = ( $term && ! is_wp_error( $term ) ) ? $term->name : '#' . $row->object_id;
					if ( $term && ! is_wp_error( $term ) ) {
						$tax_obj        = get_taxonomy( $term->taxonomy );
						$object_subtype = $tax_obj ? $tax_obj->labels->singular_name : $term->taxonomy;
						$term_link      = get_term_link( $term );
						if ( ! is_wp_error( $term_link ) ) {
							$permalink = $term_link;
						}
					}
				}

				$items[] = [
					'id'             => (int) $row->id,
					'old_url'        => $row->old_url,
					'new_url'        => $row->new_url,
					'object_type'    => $row->object_type,
					'object_subtype' => $object_subtype,
					'object_id'      => (int) $row->object_id,
					'object_title'   => $object_title,
					'permalink'      => $permalink,
					'created_at'     => $row->created_at,
				];
			}
		}

		wp_send_json_success( [
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
		] );
	}

	/**
	 * AJAX: Delete a single redirect by ID.
	 */
	public static function ajax_delete_redirect() {
		check_ajax_referer( 'haayal_redirects_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		$redirect_id = isset( $_POST['redirect_id'] ) ? absint( $_POST['redirect_id'] ) : 0;

		if ( ! $redirect_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid redirect ID.', 'haayal-ai-slug-translator' ) ] );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete(
			self::table_name(),
			[ 'id' => $redirect_id ],
			[ '%d' ]
		);

		if ( false === $deleted ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete redirect.', 'haayal-ai-slug-translator' ) ] );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Delete all redirects (or filtered by type).
	 */
	public static function ajax_delete_all_redirects() {
		check_ajax_referer( 'haayal_redirects_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'haayal-ai-slug-translator' ) ] );
		}

		global $wpdb;

		$table       = self::table_name();
		$filter_type = isset( $_POST['filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_type'] ) ) : '';

		if ( 'post' === $filter_type || 'term' === $filter_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$deleted = $wpdb->delete(
				$table,
				[ 'object_type' => $filter_type ],
				[ '%s' ]
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a safe constant derived from $wpdb->prefix. TRUNCATE cannot use prepare() for table names.
			$deleted = $wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		wp_send_json_success( [ 'deleted' => (int) $deleted ] );
	}

}
