<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Admin_Page {

    /**
     * @var array Stashed settings errors for rendering inside the tab.
     */
    private $stashed_errors = [];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'maybe_redirect_bulk_tools' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // Stash settings errors before options-head.php renders them, so we can display inside our tab.
        add_action( 'all_admin_notices', [ $this, 'stash_settings_errors' ], 0 );
    }

    /**
     * Redirects the Tools > Bulk Translate Slugs menu item to the plugin's Bulk tab.
     * Runs on admin_init — before any output — to avoid "headers already sent" errors.
     */
    public function maybe_redirect_bulk_tools() {
        if ( isset( $_GET['page'] ) && 'haayal-bulk-translate' === $_GET['page'] ) {
            wp_safe_redirect( admin_url( 'options-general.php?page=ai-slug-translator&tab=bulk' ) );
            exit;
        }
    }

    /**
     * Registers the admin menu pages.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'AI Slug Translator', 'haayal-ai-slug-translator' ),
            __( 'AI Slug Translator', 'haayal-ai-slug-translator' ),
            'manage_options',
            'ai-slug-translator',
            [ $this, 'render_page' ]
        );

        add_submenu_page(
            'tools.php',
            __( 'Bulk Translate Slugs', 'haayal-ai-slug-translator' ),
            __( 'Bulk Translate Slugs', 'haayal-ai-slug-translator' ),
            'manage_options',
            'haayal-bulk-translate',
            '__return_null'
        );
    }

    /**
     * Enqueues CSS and JS for the settings page.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'settings_page_ai-slug-translator' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ai-slug-admin-settings',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/ai-slug-admin-settings.css',
            [],
            HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION
        );

        // SweetAlert2 (bundled locally).
        wp_enqueue_style(
            'sweetalert2',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/vendor/sweetalert2/sweetalert2.min.css',
            [],
            '11'
        );
        wp_enqueue_script(
            'sweetalert2',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/vendor/sweetalert2/sweetalert2.all.min.js',
            [],
            '11',
            true
        );

        wp_enqueue_script(
            'ai-slug-admin-settings',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/ai-slug-admin-settings.js',
            [ 'jquery', 'sweetalert2' ],
            HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'ai-slug-log',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/ai-slug-log.js',
            [ 'sweetalert2' ],
            HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION,
            true
        );

        wp_localize_script( 'ai-slug-log', 'haayalLog', [
            'title'       => __( 'Clear Error Log?', 'haayal-ai-slug-translator' ),
            'text'        => __( 'This action cannot be undone.', 'haayal-ai-slug-translator' ),
            'confirmText' => __( 'Yes, clear log', 'haayal-ai-slug-translator' ),
            'cancelText'  => __( 'Cancel', 'haayal-ai-slug-translator' ),
        ] );
    }

    /**
     * On our settings page, capture plugin settings errors and clear them from
     * the global so options-head.php doesn't render them at the top of the page.
     * We render them manually inside the Settings tab.
     */
    public function stash_settings_errors() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'settings_page_ai-slug-translator' ) {
            return;
        }

        global $wp_settings_errors;

        if ( empty( $wp_settings_errors ) ) {
            return;
        }

        // Extract our plugin's errors and keep them for later rendering.
        $stashed = [];
        $remaining = [];
        foreach ( (array) $wp_settings_errors as $error ) {
            if ( isset( $error['setting'] ) && 'haayal_slug_translator_settings' === $error['setting'] ) {
                $stashed[] = $error;
            } else {
                $remaining[] = $error;
            }
        }

        // Remove our errors from the global so options-head.php won't display them.
        $wp_settings_errors = $remaining;

        // Store for later use in render_settings_tab_errors().
        $this->stashed_errors = $stashed;
    }

    /**
     * Render the stashed settings errors inside the Settings tab.
     */
    public function render_settings_tab_errors() {
        if ( empty( $this->stashed_errors ) ) {
            return;
        }

        foreach ( $this->stashed_errors as $details ) {
            $type = $details['type'];
            if ( 'updated' === $type ) {
                $type = 'notice-success';
            } elseif ( in_array( $type, [ 'error', 'success', 'warning', 'info' ], true ) ) {
                $type = 'notice-' . $type;
            }

            $css_id = sprintf( 'setting-error-%s', esc_attr( $details['code'] ) );

            printf(
                '<div id="%s" class="notice %s settings-error is-dismissible"><p><strong>%s</strong></p></div>',
                esc_attr( $css_id ),
                esc_attr( $type ),
                wp_kses_post( $details['message'] )
            );
        }
    }

    /**
     * Renders the full admin page with tab navigation and tab panels.
     */
    public function render_page() {
        $counter = get_option( '_ai_slug_generated_slugs_counter', 0 );

        $active_tab = 'tab-btn-settings';
        // Stay on error log tab after clearing the log.
        if ( isset( $_POST['clear_log'] ) ) {
            $active_tab = 'tab-btn-error-log';
        }

        // Allow deep-linking to a specific tab via ?tab= parameter.
        $allowed_tabs = [ 'settings', 'bulk', 'redirects', 'error-log', 'user-guide' ];
        if ( isset( $_GET['tab'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['tab'] ) ), $allowed_tabs, true ) ) {
            $active_tab = 'tab-btn-' . sanitize_text_field( wp_unslash( $_GET['tab'] ) );
        }

        $tabs = [
            'tab-btn-settings'     => __( 'Settings', 'haayal-ai-slug-translator' ),
            'tab-btn-bulk'         => __( 'Bulk Translation', 'haayal-ai-slug-translator' ),
            'tab-btn-redirects'    => __( 'Redirects', 'haayal-ai-slug-translator' ),
            'tab-btn-error-log'    => __( 'Error Log', 'haayal-ai-slug-translator' ),
            'tab-btn-user-guide'   => __( 'Help & Guide', 'haayal-ai-slug-translator' ),
        ];
        $tab_panels = [
            'tab-btn-settings'     => 'tab-settings',
            'tab-btn-bulk'         => 'tab-bulk',
            'tab-btn-redirects'    => 'tab-redirects',
            'tab-btn-error-log'    => 'tab-error-log',
            'tab-btn-user-guide'   => 'tab-user-guide',
        ];

        ?>
        <div class="slug-translator-settings-wrapper">

            <div class="slug-translator-layout">

            <div class="slug-translator-tabs" role="tablist" aria-label="<?php esc_attr_e( 'AI slug translator', 'haayal-ai-slug-translator' ); ?>" aria-orientation="vertical">
                <?php foreach ( $tabs as $btn_id => $label ) :
                    $is_active = ( $btn_id === $active_tab );
                ?>
                <button type="button" role="tab"
                    aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo esc_attr( $tab_panels[ $btn_id ] ); ?>"
                    id="<?php echo esc_attr( $btn_id ); ?>"
                    class="slug-translator-tab<?php echo $is_active ? ' active' : ''; ?>"
                    <?php echo $is_active ? '' : 'tabindex="-1"'; ?>>
                    <?php echo esc_html( $label ); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="slug-translator-content">

            <div class="slug-translator-header">
                <div class="inline-group header">
                    <img src="<?php echo esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'assets/logo-128x128.png' ); ?>" alt="<?php esc_attr_e( 'Ailo robot', 'haayal-ai-slug-translator' ); ?>" class="ai-slug-logo">
                    <div>
                        <h1><?php esc_html_e( 'Ailo - AI Slug Translator', 'haayal-ai-slug-translator' ); ?></h1>
                        <p id="haayal-slugs-counter-wrap"<?php echo intval( $counter ) > 0 ? '' : ' style="display:none;"'; ?>>
                            <?php
                            printf(
                                // Translators: %s is the number of slugs translated using this plugin.
                                esc_html__( 'So far, %s slugs have been translated using this plugin!', 'haayal-ai-slug-translator' ),
                                '<span class="counter" id="haayal-slugs-counter">' . intval( $counter ) . '</span>'
                            );
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Settings tab -->
            <div role="tabpanel" id="tab-settings" aria-labelledby="tab-btn-settings" class="slug-translator-tabpanel"<?php echo 'tab-btn-settings' !== $active_tab ? ' hidden' : ''; ?>>
                <?php $this->render_settings_tab_errors(); ?>
                <?php Haayal_AI_Slug_Settings::render_tab(); ?>
            </div>

            <!-- Bulk Translation tab -->
            <div role="tabpanel" id="tab-bulk" aria-labelledby="tab-btn-bulk" class="slug-translator-tabpanel"<?php echo 'tab-btn-bulk' !== $active_tab ? ' hidden' : ''; ?>>
                <?php Haayal_AI_Slug_Bulk::render_tab(); ?>
            </div>

            <!-- Redirects tab -->
            <div role="tabpanel" id="tab-redirects" aria-labelledby="tab-btn-redirects" class="slug-translator-tabpanel"<?php echo 'tab-btn-redirects' !== $active_tab ? ' hidden' : ''; ?>>
                <?php Haayal_AI_Slug_Redirects::render_tab(); ?>
            </div>

            <!-- Error Log tab -->
            <div role="tabpanel" id="tab-error-log" aria-labelledby="tab-btn-error-log" class="slug-translator-tabpanel"<?php echo 'tab-btn-error-log' !== $active_tab ? ' hidden' : ''; ?>>
                <?php Haayal_AI_Slug_Log::display_log(); ?>
            </div>

            <!-- User Guide tab -->
            <div role="tabpanel" id="tab-user-guide" aria-labelledby="tab-btn-user-guide" class="slug-translator-tabpanel"<?php echo 'tab-btn-user-guide' !== $active_tab ? ' hidden' : ''; ?>>
                <?php Haayal_AI_Slug_User_Guide::render_tab(); ?>
            </div>

            </div><!-- /.slug-translator-content -->
            </div><!-- /.slug-translator-layout -->

            <div class="credit">
                <?php esc_html_e( 'Developed by Elchanan Levavi.', 'haayal-ai-slug-translator' ); ?>
            </div>
        </div>

        <?php
    }
}
