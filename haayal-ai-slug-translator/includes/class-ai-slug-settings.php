<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Settings {
    private static $option_name = 'haayal_ai_slug_translator_settings';

    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
        add_action( 'admin_init', [ $this, 'handle_clear_log' ] );
    }

    /**
     * Handles the settings form submission on admin_init.
     */
    public function handle_form_submission() {
        // Only process on our settings page.
        if ( ! isset( $_POST['submit'] ) || ! isset( $_GET['page'] ) || 'ai-slug-translator' !== $_GET['page'] ) {
            return;
        }

        check_admin_referer( 'ai_slug_translator_save' );

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get existing saved settings (decrypted so we can fall back to the plaintext key when asterisks are submitted).
        $saved_settings = self::get_settings();

        // Get submitted API key.
        $submitted_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        // If the key contains only asterisks, keep the saved one.
        $api_key = ( strpos( $submitted_key, '*' ) === false ) ? $submitted_key : ( $saved_settings['api_key'] ?? '' );

        $connection_method = isset( $_POST['connection_method'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_method'] ) ) : '';
        if ( ! in_array( $connection_method, [ 'proxy', 'connectors', 'api_key' ], true ) ) {
            $connection_method = '';
        }

        $settings = [
            'api_key' => Haayal_AI_Slug_Helpers::encrypt_api_key( $api_key ),
            'enabled_post_types' => isset( $_POST['enabled_post_types'] ) && is_array( $_POST['enabled_post_types'] )
                ? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_post_types'] ) )
                : [],
            'enabled_taxonomies' => isset( $_POST['enabled_taxonomies'] ) && is_array( $_POST['enabled_taxonomies'] )
                ? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_taxonomies'] ) )
                : [],
            'max_tokens' => isset( $_POST['max_tokens'] ) ? intval( $_POST['max_tokens'] ) : 20,
            'connection_method' => $connection_method,
            'enable_redirects' => isset( $_POST['enable_redirects'] ) ? 1 : 0,
        ];

        update_option( self::$option_name, $settings );

        // General success message.
        add_settings_error(
            'haayal_slug_translator_settings',
            'settings_saved',
            __( 'Your settings have been saved.', 'haayal-ai-slug-translator' ),
            'updated'
        );

        // Connection-method-specific messages.
        if ( 'connectors' === $connection_method ) {
            if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
                add_settings_error(
                    'haayal_slug_translator_settings',
                    'connectors_unavailable',
                    __( 'WordPress Connectors are not available. Please ensure you are running WordPress 7.0 or later with AI features enabled.', 'haayal-ai-slug-translator' ),
                    'error'
                );
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
                    add_settings_error(
                        'haayal_slug_translator_settings',
                        'connectors_no_providers',
                        sprintf(
                            /* translators: %s is a link to the WordPress Connectors settings page */
                            __( 'No AI providers are configured yet. Slug translation will not work until you set up at least one provider in %s.', 'haayal-ai-slug-translator' ),
                            '<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">' . __( 'Settings', 'haayal-ai-slug-translator' ) . ' <span class="haayal-arrow">&rarr;</span> ' . __( 'Connectors', 'haayal-ai-slug-translator' ) . '</a>'
                        ),
                        'error'
                    );
                } else {
                    add_settings_error(
                        'haayal_slug_translator_settings',
                        'connectors_active',
                        __( 'WordPress Connectors mode is active. Translations will use your configured AI providers.', 'haayal-ai-slug-translator' ),
                        'updated'
                    );
                }
            }
        }

        // Show warning only if using proxy and quota is depleted.
        if ( 'proxy' === $connection_method || ( '' === $connection_method && empty( $api_key ) ) ) {
            $raw_remaining = get_option( 'haayal_ai_proxy_quota_remaining', null );
            $remaining = is_numeric( $raw_remaining ) ? intval( $raw_remaining ) : null;

            if ( $remaining === 0 ) {
                add_settings_error(
                    'haayal_slug_translator_settings',
                    'missing_api_key',
                    __( 'You\'ve used all your free translations. To keep using AI slug translation, please choose another connection method.', 'haayal-ai-slug-translator' ),
                    'notice-warning'
                );
            }
        }

        // Validate API key if the API key method is selected (or legacy behavior).
        if ( ! empty( $api_key ) && ( 'api_key' === $connection_method || '' === $connection_method ) ) {
            $status = Haayal_AI_Slug_Helpers::check_api_key_status( $api_key );
            update_option( 'haayal_ai_api_key_status', $status );

            if ( $status === 'valid' ) {
                add_settings_error(
                    'haayal_slug_translator_settings',
                    'valid_api_key',
                    __( 'OpenAI API key is valid and working.', 'haayal-ai-slug-translator' ),
                    'updated'
                );
            } elseif ( $status === 'insufficient_quota' ) {
                add_settings_error(
                    'haayal_slug_translator_settings',
                    'quota_warning',
                    __( 'OpenAI API key is valid, but you have no remaining credit.', 'haayal-ai-slug-translator' ),
                    'notice-warning'
                );
            } else {
                add_settings_error(
                    'haayal_slug_translator_settings',
                    'invalid_api_key',
                    __( 'The provided OpenAI API key is invalid or unauthorized. Please double-check your key.', 'haayal-ai-slug-translator' ),
                    'error'
                );
            }
        } elseif ( empty( $api_key ) ) {
            delete_option( 'haayal_ai_api_key_status' );
        }

        return;
    }

    /**
     * Handles the clear log form submission on admin_init.
     */
    public function handle_clear_log() {
        if ( ! isset( $_POST['clear_log'] ) || ! isset( $_GET['page'] ) || 'ai-slug-translator' !== $_GET['page'] ) {
            return;
        }

        check_admin_referer( 'ai_slug_clear_log' );

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        Haayal_AI_Slug_Log::clear_log();
    }

    /**
     * Renders the Settings tab content (form).
     */
    public static function render_tab() {
        $settings = self::get_settings();

        if ( '' === get_option( 'permalink_structure' ) ) {
            $permalink_url = admin_url( 'options-permalink.php' );
            ?>
            <div class="haayal-plain-permalink-banner">
                <div class="haayal-plain-permalink-banner__icon" aria-hidden="true">&#8505;</div>
                <div class="haayal-plain-permalink-banner__body">
                    <strong><?php esc_html_e( 'Plain permalinks detected — Ailo is fully inactive', 'haayal-ai-slug-translator' ); ?></strong>
                    <p>
                        <?php
                        printf(
                            wp_kses_post(
                                /* translators: %s: link to the Permalink Settings page */
                                __( 'Your site is currently set to <strong>Plain</strong> permalinks, which do not support custom slugs. While this setting is active, <strong>no slug translation will occur</strong> for any post type or taxonomy term, no AI badge will appear in the editor, and bulk translation is unavailable.<br>To activate Ailo, please <a href="%s">update your permalink structure</a> to any option other than "Plain".', 'haayal-ai-slug-translator' )
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
        }

        // Ensure taxonomies setting is always an array
        if ( ! is_array( $settings['enabled_taxonomies'] ) ) {
            $settings['enabled_taxonomies'] = [];
        }

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        // Remove post types that are not relevant for slug translation.
        unset( $post_types['attachment'], $post_types['e-floating-buttons'], $post_types['elementor_library'], $post_types['bricks_template'] );
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        // Remove the built-in 'post_format' taxonomy and builder-internal taxonomies
        unset( $taxonomies['post_format'], $taxonomies['template_bundle'], $taxonomies['template_tag'] );

        ?>
        <div class="slug-translator-settings-layout">
        <form method="post" class="slug-translator-settings-form">
            <?php wp_nonce_field( 'ai_slug_translator_save' ); ?>
            <div class="inline-group">
                <fieldset class="form-field-wrapper">
                    <legend><?php esc_html_e( 'Enabled Post Types', 'haayal-ai-slug-translator' ); ?></legend>
                    <?php foreach ( $post_types as $post_type ) :
                        $checked = in_array( $post_type->name, $settings['enabled_post_types'], true ) ? 'checked' : '';
                        ?>
                        <div>
                            <label>
                                <input type="checkbox" name="enabled_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php echo esc_attr( $checked ); ?>>
                                <?php echo esc_html( $post_type->label ); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset class="form-field-wrapper">
                    <legend><?php esc_html_e( 'Enabled Taxonomies', 'haayal-ai-slug-translator' ); ?></legend>
                    <?php foreach ( $taxonomies as $taxonomy ) :
                        $checked = in_array( $taxonomy->name, $settings['enabled_taxonomies'], true ) ? 'checked' : '';
                        ?>
                        <div>
                            <label>
                                <input type="checkbox" name="enabled_taxonomies[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php echo esc_attr( $checked ); ?>>
                                <?php echo esc_html( $taxonomy->label ); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            </div>

            <fieldset class="form-field-wrapper">
                <legend><?php esc_html_e( 'Connection Method', 'haayal-ai-slug-translator' ); ?></legend>
                <p><?php esc_html_e( 'Choose how the plugin connects to AI services for slug translation.', 'haayal-ai-slug-translator' ); ?></p>

                <?php
                    $remaining = get_option( 'haayal_ai_proxy_quota_remaining' );
                    $remaining = is_numeric( $remaining ) ? intval( $remaining ) : 100;

                    $connection_method = isset( $settings['connection_method'] ) ? $settings['connection_method'] : '';

                    // Check WordPress Connectors availability.
                    $connectors_available = function_exists( 'wp_supports_ai' ) && wp_supports_ai();

                    // Determine which card should be selected.
                    if ( '' === $connection_method ) {
                        $active_method = ! empty( $settings['api_key'] ) ? 'api_key' : 'proxy';
                    } else {
                        $active_method = $connection_method;
                    }

                    // If the selected method's card won't be shown, fall back.
                    if ( 'connectors' === $active_method && ! $connectors_available ) {
                        $active_method = 'proxy';
                    }
                    if ( 'api_key' === $active_method && $connectors_available && empty( $settings['api_key'] ) ) {
                        $active_method = 'connectors';
                    }

                    // Check which providers are configured.
                    $configured_providers = [];
                    if ( $connectors_available && function_exists( 'wp_get_connectors' ) ) {
                        foreach ( wp_get_connectors() as $connector_id => $connector_data ) {
                            if ( 'ai_provider' !== $connector_data['type'] ) {
                                continue;
                            }
                            $auth = $connector_data['authentication'];
                            if ( 'api_key' !== $auth['method'] || empty( $auth['setting_name'] ) ) {
                                continue;
                            }
                            if ( '' !== get_option( $auth['setting_name'], '' ) ) {
                                $configured_providers[] = $connector_data['name'];
                            }
                        }
                    }

                    // Determine which cards to show:
                    // - Connectors NOT available: Proxy + API Key only
                    // - Connectors available + no API key set: Proxy + Connectors only
                    // - Connectors available + API key set: all 3
                    $show_connectors_card = $connectors_available;
                ?>

                <div class="connection-method-cards">

                    <div class="connection-method-card<?php echo 'proxy' === $active_method ? ' selected' : ''; ?>">
                        <label class="connection-method-card__label">
                            <input type="radio" name="connection_method" value="proxy" <?php checked( $active_method, 'proxy' ); ?>>
                            <span class="connection-method-card__check" aria-hidden="true"></span>
                            <span class="connection-method-card__title">
                                <?php esc_html_e( 'Free Translations', 'haayal-ai-slug-translator' ); ?>
                            </span>
                            <span class="connection-method-card__description">
                                <?php esc_html_e( 'No account needed — get started instantly with 100 free slug translations for your site.', 'haayal-ai-slug-translator' ); ?>
                            </span>
                        </label>
                        <div class="connection-method-card__status <?php echo $remaining === 0 ? 'status-warning' : 'status-ok'; ?>">
                            <?php
                            if ( $remaining === 0 ) {
                                esc_html_e( 'Quota used up — translation will not work until you switch to a different connection method.', 'haayal-ai-slug-translator' );
                            } else {
                                printf(
                                    /* translators: %d is the number of remaining free translations */
                                    esc_html__( '%d out of 100 remaining', 'haayal-ai-slug-translator' ),
                                    absint( $remaining )
                                );
                            }
                            ?>
                        </div>
                    </div>

                    <?php if ( $show_connectors_card ) : ?>
                    <div class="connection-method-card<?php echo 'connectors' === $active_method ? ' selected' : ''; ?>">
                        <span class="connection-method-card__badge"><?php esc_html_e( 'Recommended', 'haayal-ai-slug-translator' ); ?></span>
                        <label class="connection-method-card__label">
                            <input type="radio" name="connection_method" value="connectors" <?php checked( $active_method, 'connectors' ); ?>>
                            <span class="connection-method-card__check" aria-hidden="true"></span>
                            <span class="connection-method-card__title">
                                <?php esc_html_e( 'WordPress Connectors', 'haayal-ai-slug-translator' ); ?>
                            </span>
                            <span class="connection-method-card__description">
                                <?php esc_html_e( 'Use WordPress\'s built-in AI integration. Connect multiple AI services (OpenAI, Anthropic, Google) from one place.', 'haayal-ai-slug-translator' ); ?>
                            </span>
                        </label>
                        <?php if ( ! empty( $configured_providers ) ) : ?>
                            <div class="connection-method-card__status status-ok">
                                <?php
                                printf(
                                    /* translators: %s is a comma-separated list of configured AI provider names */
                                    esc_html__( 'Connected: %s', 'haayal-ai-slug-translator' ),
                                    esc_html( implode( ', ', $configured_providers ) )
                                );
                                ?>
                            </div>
                            <a class="connection-method-card__link" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>" target="_blank"><?php esc_html_e( 'Configure Connectors', 'haayal-ai-slug-translator' ); ?> <span class="haayal-arrow">&rarr;</span></a>
                        <?php else : ?>
                            <div class="connection-method-card__status status-warning">
                                <?php esc_html_e( 'No providers configured yet — translation will not work until at least one provider is set up.', 'haayal-ai-slug-translator' ); ?>
                            </div>
                            <a class="connection-method-card__link save-and-redirect" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Save & Go to Connectors', 'haayal-ai-slug-translator' ); ?> <span class="haayal-arrow">&rarr;</span></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="connection-method-card<?php echo 'api_key' === $active_method ? ' selected' : ''; ?>">
                        <label class="connection-method-card__label">
                            <input type="radio" name="connection_method" value="api_key" <?php checked( $active_method, 'api_key' ); ?>>
                            <span class="connection-method-card__check" aria-hidden="true"></span>
                            <span class="connection-method-card__title">
                                <?php esc_html_e( 'OpenAI API Key', 'haayal-ai-slug-translator' ); ?>
                            </span>
                            <span class="connection-method-card__description">
                                <?php esc_html_e( 'Connect directly using your own OpenAI API key.', 'haayal-ai-slug-translator' ); ?>
                            </span>
                        </label>
                        <div class="api-key-input-wrapper">
                            <label for="api_key"><?php esc_html_e( 'API Key:', 'haayal-ai-slug-translator' ); ?></label>
                            <input type="text" name="api_key" id="api_key"
                                value="<?php echo esc_attr( ! empty( $settings['api_key'] ) ? str_repeat( '*', 32 ) : '' ); ?>"
                                size="50">
                            <small><a href="#" class="switch-to-tab" data-tab="tab-btn-user-guide"><?php esc_html_e( 'Where do I get an API key?', 'haayal-ai-slug-translator' ); ?></a></small>
                        </div>
                        <?php
                        $api_key_status = get_option( 'haayal_ai_api_key_status', '' );
                        if ( empty( $settings['api_key'] ) ) : ?>
                            <div class="connection-method-card__status status-warning">
                                <?php esc_html_e( 'No API key entered — translation will not work until a valid key is provided.', 'haayal-ai-slug-translator' ); ?>
                            </div>
                        <?php elseif ( 'invalid' === $api_key_status ) : ?>
                            <div class="connection-method-card__status status-warning">
                                <?php esc_html_e( 'API key is invalid or unauthorized — please double-check your key.', 'haayal-ai-slug-translator' ); ?>
                            </div>
                        <?php elseif ( 'insufficient_quota' === $api_key_status ) : ?>
                            <div class="connection-method-card__status status-warning">
                                <?php esc_html_e( 'API key is valid but has no remaining credit — translation will not work until you add credit.', 'haayal-ai-slug-translator' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </fieldset>

            <fieldset class="form-field-wrapper max-tokens-wrapper">
                <legend><?php esc_html_e( 'Max Tokens', 'haayal-ai-slug-translator' ); ?></legend>
                <div>
                    <label for="max_tokens"><?php esc_html_e( 'Select the maximum number of tokens the AI will use to generate a single response:', 'haayal-ai-slug-translator' ); ?></label>
                    <select name="max_tokens" id="max_tokens" aria-describedby="max-tokens-description">
                        <?php
                        $options = [
                            20 => __( '20 (default)', 'haayal-ai-slug-translator' ),
                            5 => '5',
                            10 => '10',
                            30 => '30',
                            40 => '40'
                        ];
                        $max_tokens = $settings['max_tokens'] ?? 20;
                        foreach ( $options as $value => $label ) {
                            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $max_tokens, $value, false ) . '>' . esc_html( $label ) . '</option>';
                        }
                        ?>
                    </select>
                    <p id="max-tokens-description"><small><?php esc_html_e( 'A smaller value can help cap costs, ensuring they don\'t exceed your intended budget. For extremely long titles or in case of process failures, selecting a higher value may resolve the issue. If there\'s no specific need, it is recommended to leave this setting at the default value.', 'haayal-ai-slug-translator' ); ?></small></p>
                </div>
            </fieldset>

            <fieldset class="form-field-wrapper">
                <legend><?php esc_html_e( '301 Redirects', 'haayal-ai-slug-translator' ); ?></legend>
                <div>
                    <label>
                        <input type="checkbox" name="enable_redirects" value="1" <?php checked( $settings['enable_redirects'], 1 ); ?>>
                        <?php esc_html_e( 'By default, create 301 redirects for URLs changed during bulk translation.', 'haayal-ai-slug-translator' ); ?>
                    </label>
                    <p><small><?php esc_html_e( 'When enabled, the plugin will automatically create 301 redirects from old URLs to new ones during bulk slug translation. This helps preserve SEO and prevents broken links.', 'haayal-ai-slug-translator' ); ?></small></p>
                    <?php if ( self::is_redirection_plugin_active() ) : ?>
                        <div class="notice notice-info inline" style="margin-top: 8px; padding: 8px 12px;">
                            <p>
                                <?php
                                printf(
                                    /* translators: %1$s/%2$s wrap "Redirection" link, %3$s/%4$s wrap "handle redirects automatically when permalinks change" link */
                                    esc_html__( '%1$sRedirection%2$s is active and may already %3$shandle redirects automatically when permalinks change%4$s, but it does not handle slug changes for taxonomy terms (such as categories or tags).', 'haayal-ai-slug-translator' ),
                                    '<a href="' . esc_url( admin_url( 'tools.php?page=redirection.php' ) ) . '" target="_blank">',
                                    '</a>',
                                    '<a href="' . esc_url( admin_url( 'tools.php?page=redirection.php&sub=options#monitor-type-post' ) ) . '" target="_blank">',
                                    '</a>'
                                );
                                ?>
                            </p>
                            <p><?php esc_html_e( 'AILO can track URL changes and create redirects for both posts/custom post types and taxonomy terms when using the Bulk Translate process, depending on your settings.', 'haayal-ai-slug-translator' ); ?></p>
                            <p><?php esc_html_e( 'To avoid conflicts or duplicate redirects, make sure only one plugin handles redirects for the same content and review your setup to avoid overlaps.', 'haayal-ai-slug-translator' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <button type="submit" name="submit" class="save-settings-button"><?php esc_html_e( 'Save Settings', 'haayal-ai-slug-translator' ); ?></button>
        </form>

        <aside class="slug-translator-settings-sidebar">
            <div class="keepinmind-banner">
                <img src="<?php echo esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'assets/png/keepinmindlogo.png' ); ?>" alt="<?php esc_attr_e( 'KeepInMind Dashboard Notes', 'haayal-ai-slug-translator' ); ?>" class="keepinmind-banner__logo">
                <h3 class="keepinmind-banner__title"><?php esc_html_e( 'KeepInMind Dashboard Notes', 'haayal-ai-slug-translator' ); ?></h3>
                <p class="keepinmind-banner__desc"><?php esc_html_e( 'Add contextual notes directly inside your WordPress admin - so your team sees exactly what matters, exactly where it matters.', 'haayal-ai-slug-translator' ); ?></p>
                <a href="https://wordpress.org/plugins/keepinmind-dashboard-notes/" target="_blank" rel="noopener noreferrer" class="keepinmind-banner__cta"><?php esc_html_e( 'Start with Notes', 'haayal-ai-slug-translator' ); ?></a>
            </div>
        </aside>

        </div><?php /* .slug-translator-settings-layout */ ?>
        <?php
    }

    /**
     * Retrieves the plugin settings from the database.
     *
     * @return array The plugin settings with default values.
     */
    public static function get_settings() {
        $defaults = [
            'api_key' => '',
            'enabled_post_types' => [],
            'enabled_taxonomies' => [],
            'max_tokens' => 20,
            'connection_method' => '',
            'enable_redirects' => 1,
        ];
        $settings              = wp_parse_args( get_option( self::$option_name, [] ), $defaults );
        $settings['api_key']   = Haayal_AI_Slug_Helpers::decrypt_api_key( $settings['api_key'] );
        return $settings;
    }

    /**
     * Writes default settings on first install. No-op if the option already exists
     * (re-activation, update) so existing user configuration is never overwritten.
     */
    public static function maybe_set_defaults() {
        if ( false !== get_option( self::$option_name ) ) {
            return;
        }
        add_option( self::$option_name, [
            'api_key'             => '',
            'enabled_post_types'  => [ 'post', 'page', 'product' ],
            'enabled_taxonomies'  => [],
            'max_tokens'          => 20,
            'connection_method'   => '',
            'enable_redirects'    => 1,
        ] );
    }

    /**
     * Checks if the Redirection plugin is active.
     *
     * @return bool
     */
    public static function is_redirection_plugin_active() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active( 'redirection/redirection.php' );
    }

    /**
     * Saves the plugin settings to the database.
     *
     * @param array $settings The settings to save.
     */
    public static function save_settings( $settings ) {
        update_option( self::$option_name, $settings );
    }

    /**
     * Increments the slug generation counter.
     */
    public static function increment_generated_slugs_counter() {
        $counter = get_option( '_ai_slug_generated_slugs_counter', 0 );
        $counter++;
        update_option( '_ai_slug_generated_slugs_counter', $counter );
    }
}
