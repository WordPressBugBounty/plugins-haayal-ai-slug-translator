<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class Haayal_AI_Slug_Notices {

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'check_version_upgrade' ] );

		add_action( 'admin_notices', [ __CLASS__, 'show_plain_permalink_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_welcome_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_review_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_connection_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_v1_upgrade_notice' ] );

		add_action( 'wp_ajax_haayal_dismiss_notice', [ __CLASS__, 'dismiss_welcome_notice' ] );
		add_action( 'wp_ajax_haayal_dismiss_review_notice', [ __CLASS__, 'dismiss_review_notice' ] );
		add_action( 'wp_ajax_haayal_dismiss_v1_upgrade_notice', [ __CLASS__, 'dismiss_v1_upgrade_notice' ] );

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Detects a plugin upgrade from 0.x to 1.0+ and flags the upgrade notice.
	 *
	 * Runs on admin_init so it fires immediately after an update.
	 * Fresh installs (no prior settings option) are excluded.
	 */
	public static function check_version_upgrade() {
		$current_version = defined( 'HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION' )
			? HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION
			: '1.0';

		$stored_version = get_option( 'haayal_plugin_version', '' );

		// Already up to date.
		if ( $stored_version === $current_version ) {
			return;
		}

		// Detect upgrade from 0.x → 1.0+.
		// If stored version is empty but plugin settings exist, user had a pre-1.0 version
		// (which didn't store haayal_plugin_version). Fresh installs won't have settings.
		if ( '' === $stored_version && false !== get_option( 'haayal_ai_slug_translator_settings' ) ) {
			update_option( 'haayal_show_v1_upgrade_notice', 1 );
		}

		// Encrypt any existing plaintext API key when upgrading to 1.1+.
		if ( version_compare( $stored_version, '1.0', '<' ) ) {
			$raw_settings = get_option( 'haayal_ai_slug_translator_settings', [] );
			$raw_key      = $raw_settings['api_key'] ?? '';
			if ( ! empty( $raw_key ) && strpos( $raw_key, 'enc:' ) !== 0 ) {
				$raw_settings['api_key'] = Haayal_AI_Slug_Helpers::encrypt_api_key( $raw_key );
				update_option( 'haayal_ai_slug_translator_settings', $raw_settings );
			}
		}

		update_option( 'haayal_plugin_version', $current_version );
	}

	/**
	 * Shows a persistent error notice on all admin pages when the site uses plain/simple permalinks,
	 * because custom slugs are not supported and the plugin is effectively inactive.
	 */
	public static function show_plain_permalink_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( '' !== get_option( 'permalink_structure' ) ) {
			return;
		}

		$permalink_url = admin_url( 'options-permalink.php' );
		$logo_url      = self::get_logo_url();
		?>
		<div class="notice notice-info haayal-notice haayal-plain-permalink-notice">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="Ailo Logo" class="haayal-notice-logo">
			<p>
				<strong><?php esc_html_e( 'Ailo – AI Slug Translator is inactive', 'haayal-ai-slug-translator' ); ?></strong><br>
				<?php
				printf(
					wp_kses_post(
						/* translators: %s: link to the Permalink Settings page */
						__( 'Your site uses <strong>Plain permalinks</strong>, which do not support custom slugs. Slug translation, badge indicators, and bulk translation are all disabled. To activate Ailo, <a href="%s">change your permalink structure</a> to any option other than "Plain".', 'haayal-ai-slug-translator' )
					),
					esc_url( $permalink_url )
				);
				?>
			</p>
		</div>
		<?php
	}

	public static function show_welcome_notice() {
		if ( get_option( 'haayal_slug_translator_dismissed_notice' ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=ai-slug-translator' );
		$logo_url   = self::get_logo_url();

		?>
		<div class="notice notice-info is-dismissible haayal-notice haayal-welcome-notice">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="Ailo Logo" class="haayal-notice-logo">
			<p>
				<strong>
					<?php esc_html_e( 'Thanks for installing Ailo – the AI-based slug translator!', 'haayal-ai-slug-translator' ); ?>
				</strong>
				<br>
				<?php esc_html_e( 'Keep your URLs clean, readable, and shareable instead of turning into ugly, unreadable encoded URLs.', 'haayal-ai-slug-translator' ); ?>
				<br>
				<?php esc_html_e( 'Ailo can automatically translate slugs into English for all content types and taxonomy terms when they’re published, and also lets you update existing slugs in bulk using the Bulk Translation tool.', 'haayal-ai-slug-translator' ); ?>
				<br>
				<?php
					printf(
					wp_kses_post(
						// translators: %s: link to settings page
						__( 'To get started, please choose which content types you\'d like to translate in the <a href="%s">settings page</a>.', 'haayal-ai-slug-translator' )
					),
					esc_url( $settings_url )
				);
				?>
			</p>
		</div>
		<?php
	}

	public static function show_review_notice() {
		if (
			get_option( 'haayal_dismissed_review_notice' ) ||
			get_option( '_ai_slug_generated_slugs_counter', 0 ) <= 9
		) {
			return;
		}

        $logo_url   = self::get_logo_url();
		$review_url = 'https://wordpress.org/support/plugin/haayal-ai-slug-translator/reviews/#new-post';
		?>
		<div class="notice notice-success is-dismissible haayal-notice haayal-review-notice">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="Ailo Logo" class="haayal-notice-logo">
			<p>
				<strong><?php esc_html_e( 'Hey, it’s me – Ailo!', 'haayal-ai-slug-translator' ); ?></strong><br>
				<?php esc_html_e(
					'Are you enjoying the slugs I’m generating for you? If so, I’d be incredibly grateful if you could give me a 5-star review. It only takes a moment and means the world!',
					'haayal-ai-slug-translator'
				); ?>
				<br><br>
				<a href="<?php echo esc_url( $review_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Yes! I want to rate you ★★★★★', 'haayal-ai-slug-translator' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Shows a persistent admin notice when the selected connection method is not properly configured.
	 */
	public static function show_connection_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Haayal_AI_Slug_Settings::get_settings();
		$method   = isset( $settings['connection_method'] ) ? $settings['connection_method'] : '';

		// Only check explicit selections.
		if ( '' === $method ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=ai-slug-translator' );
		$logo_url     = self::get_logo_url();
		$message      = '';

		if ( 'connectors' === $method ) {
			if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
				$message = __( 'WordPress Connectors are not available. Slug translation is currently inactive. Please ensure you are running WordPress 7.0 or later with AI features enabled, or switch to a different connection method.', 'haayal-ai-slug-translator' );
			} else {
				// Check if any AI provider has a configured API key.
				$has_configured_provider = false;
				if ( function_exists( 'wp_get_connectors' ) ) {
					foreach ( wp_get_connectors() as $connector_data ) {
						if ( 'ai_provider' !== $connector_data['type'] ) {
							continue;
						}
						$auth = $connector_data['authentication'];
						if ( 'api_key' !== $auth['method'] || empty( $auth['setting_name'] ) ) {
							continue;
						}
						if ( '' !== get_option( $auth['setting_name'], '' ) ) {
							$has_configured_provider = true;
							break;
						}
					}
				}

				if ( ! $has_configured_provider ) {
					$connectors_url = admin_url( 'options-connectors.php' );
					$message = sprintf(
						/* translators: 1: link to Connectors settings, 2: link to plugin settings */
						__( 'Slug translation is currently inactive — no AI providers are configured. Please set up at least one provider in <a href="%1$s">Settings <span class="haayal-arrow">&rarr;</span> Connectors</a>, or <a href="%2$s">choose a different connection method</a>.', 'haayal-ai-slug-translator' ),
						esc_url( $connectors_url ),
						esc_url( $settings_url )
					);
				}
			}
		} elseif ( 'api_key' === $method ) {
			if ( empty( $settings['api_key'] ) ) {
				$message = sprintf(
					/* translators: %s: link to plugin settings */
					__( 'Slug translation is currently inactive — no OpenAI API key has been entered. Please add your API key in the <a href="%s">plugin settings</a>.', 'haayal-ai-slug-translator' ),
					esc_url( $settings_url )
				);
			} else {
				$api_key_status = get_option( 'haayal_ai_api_key_status', '' );
				if ( 'invalid' === $api_key_status ) {
					$message = sprintf(
						/* translators: %s: link to plugin settings */
						__( 'Slug translation is currently inactive — your OpenAI API key is invalid or unauthorized. Please check your key in the <a href="%s">plugin settings</a>.', 'haayal-ai-slug-translator' ),
						esc_url( $settings_url )
					);
				} elseif ( 'insufficient_quota' === $api_key_status ) {
					$message = sprintf(
						/* translators: %s: link to plugin settings */
						__( 'Slug translation is currently inactive — your OpenAI API key has no remaining credit. Please add credit or switch connection method in the <a href="%s">plugin settings</a>.', 'haayal-ai-slug-translator' ),
						esc_url( $settings_url )
					);
				}
			}
		}

		if ( empty( $message ) ) {
			return;
		}

		?>
		<div class="notice notice-error haayal-notice haayal-connection-notice">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="Ailo Logo" class="haayal-notice-logo">
			<p>
				<strong><?php esc_html_e( 'Ailo - AI Slug Translator', 'haayal-ai-slug-translator' ); ?></strong><br>
				<?php echo wp_kses_post( $message ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Shows a festive upgrade notice for users who updated from 0.x to 1.0+.
	 */
	public static function show_v1_upgrade_notice() {
		if ( ! get_option( 'haayal_show_v1_upgrade_notice' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$logo_url     = self::get_logo_url();
		$bulk_url     = admin_url( 'options-general.php?page=ai-slug-translator&tab=bulk' );
		$settings_url = admin_url( 'options-general.php?page=ai-slug-translator' );

		$has_connectors = function_exists( 'wp_supports_ai' ) && wp_supports_ai();

		?>
		<div class="notice is-dismissible haayal-notice haayal-upgrade-notice">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="Ailo Logo" class="haayal-notice-logo">
			<div class="haayal-upgrade-content">
				<div class="haayal-upgrade-header">
					<?php esc_html_e( 'Ailo 1.0 is here!', 'haayal-ai-slug-translator' ); ?>
				</div>
				<p><?php esc_html_e( 'Thanks for updating! Here\'s what\'s new:', 'haayal-ai-slug-translator' ); ?></p>
				<ul class="haayal-upgrade-features">
					<li>
						<span class="haayal-upgrade-icon" aria-hidden="true">&#x1F680;</span>
						<?php
						printf(
							wp_kses_post(
								/* translators: %s: link to Bulk Translation tab */
								__( '<strong>Bulk Translation</strong> — Translate all your existing non-English slugs at once from the <a href="%s">Bulk Translation</a> tab. 301 redirects are created automatically.', 'haayal-ai-slug-translator' )
							),
							esc_url( $bulk_url )
						);
						?>
					</li>
					<?php if ( $has_connectors ) : ?>
					<li>
						<span class="haayal-upgrade-icon" aria-hidden="true">&#x1F517;</span>
						<?php
						printf(
							wp_kses_post(
								/* translators: %s: link to plugin settings page */
								__( '<strong>WordPress Connectors</strong> — Connect to multiple AI providers (OpenAI, Anthropic, Google) through WordPress\'s built-in AI integration. Set it up in <a href="%s">Settings</a>.', 'haayal-ai-slug-translator' )
							),
							esc_url( $settings_url )
						);
						?>
					</li>
					<?php endif; ?>
					<li>
						<span class="haayal-upgrade-icon" aria-hidden="true">&#x1F504;</span>
						<?php
						echo wp_kses_post(
							__( '<strong>Regenerate Slug</strong> — Generate a new AI suggestion if you\'d like to try a different result before saving.', 'haayal-ai-slug-translator' )
						);
						?>
					</li>
				</ul>
			</div>
		</div>
		<?php
	}

	public static function dismiss_v1_upgrade_notice() {
		check_ajax_referer( 'haayal_dismiss_v1_upgrade_notice', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		delete_option( 'haayal_show_v1_upgrade_notice' );
		wp_send_json_success();
	}

	public static function dismiss_welcome_notice() {
		check_ajax_referer( 'haayal_dismiss_notice', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		update_option( 'haayal_slug_translator_dismissed_notice', 1 );
		wp_send_json_success();
	}

	public static function dismiss_review_notice() {
		check_ajax_referer( 'haayal_dismiss_review_notice', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		update_option( 'haayal_dismissed_review_notice', 1 );
		wp_send_json_success();
	}

	public static function enqueue_assets( $hook ) {
		wp_enqueue_style(
			'ai-slug-admin-notices',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/ai-slug-admin-notices.css',
			[],
			defined( 'HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION' ) ? HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION : time()
		);

		wp_enqueue_script(
			'ai-slug-admin-notices',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/ai-slug-admin-notices.js',
			[ 'jquery' ],
			defined( 'HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION' ) ? HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION : time(),
			true
		);

		wp_localize_script( 'ai-slug-admin-notices', 'haayalNotices', [
			'dismiss_nonce'         => wp_create_nonce( 'haayal_dismiss_notice' ),
			'dismiss_review_nonce'  => wp_create_nonce( 'haayal_dismiss_review_notice' ),
			'dismiss_v1_upgrade_nonce' => wp_create_nonce( 'haayal_dismiss_v1_upgrade_notice' ),
		] );
	}

    private static function get_logo_url() {
		return plugin_dir_url( dirname( __FILE__ ) ) . 'assets/logo-128x128.png';
	}
}
