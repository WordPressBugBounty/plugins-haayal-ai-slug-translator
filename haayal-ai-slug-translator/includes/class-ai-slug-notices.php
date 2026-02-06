<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class Haayal_AI_Slug_Notices {

	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'show_welcome_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_review_notice' ] );

		add_action( 'wp_ajax_haayal_dismiss_notice', [ __CLASS__, 'dismiss_welcome_notice' ] );
		add_action( 'wp_ajax_haayal_dismiss_review_notice', [ __CLASS__, 'dismiss_review_notice' ] );

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
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

	public static function dismiss_welcome_notice() {
		update_option( 'haayal_slug_translator_dismissed_notice', 1 );
		wp_send_json_success();
	}

	public static function dismiss_review_notice() {
		update_option( 'haayal_dismissed_review_notice', 1 );
		wp_send_json_success();
	}

	public static function enqueue_assets( $hook ) {
		wp_enqueue_style(
			'ai-slug-admin-notices',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/ai-slug-admin-notices.css',
			[],
			defined( 'Haayal_AI_SLUG_TRANSLATOR_PLUGIN_VERSION' ) ? Haayal_AI_SLUG_TRANSLATOR_PLUGIN_VERSION : time()
		);

		wp_enqueue_script(
			'ai-slug-admin-notices',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/ai-slug-admin-notices.js',
			[ 'jquery' ],
			defined( 'Haayal_AI_SLUG_TRANSLATOR_PLUGIN_VERSION' ) ? Haayal_AI_SLUG_TRANSLATOR_PLUGIN_VERSION : time(),
			true
		);
	}

    private static function get_logo_url() {
		return plugin_dir_url( dirname( __FILE__ ) ) . 'assets/logo-128x128.png';
	}
}
