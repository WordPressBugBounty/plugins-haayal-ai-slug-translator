<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Settings {
    private static $option_name = 'haayal_ai_slug_translator_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_plugin_settings_assets' ] );
    }

    /**
     * Adds a settings link to the plugin's row on the Plugins page.
     *
     * @param array $links The existing plugin action links.
     * @return array The modified action links with the settings link added.
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=ai-slug-translator">' . __( 'Settings', 'haayal-ai-slug-translator' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function add_settings_page() {
        add_options_page(
            __( 'AI Slug Translator', 'haayal-ai-slug-translator' ),
            __( 'AI Slug Translator', 'haayal-ai-slug-translator' ),
            'manage_options',
            'ai-slug-translator',
            [ $this, 'settings_page_content' ]
        );
    }

    public function enqueue_plugin_settings_assets( $hook ) {
        if ( $hook === 'settings_page_ai-slug-translator' ) {
            wp_enqueue_style(
                'ai-slug-admin-settings',
                plugin_dir_url( dirname( __FILE__ ) ) . 'assets/ai-slug-admin-settings.css',
                [],
                Haayal_AI_SLUG_TRANSLATOR_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'ai-slug-admin-settings',
                plugin_dir_url( dirname( __FILE__ ) ) . 'assets/ai-slug-admin-settings.js',
                ['jquery'],
                Haayal_AI_SLUG_TRANSLATOR_PLUGIN_VERSION,
                true // Load in footer
            );
        }
    }

    public function settings_page_content() {
        if ( isset( $_POST['submit'] ) ) {
            check_admin_referer( 'ai_slug_translator_save' );

            // Get existing saved settings
            $saved_settings = get_option( self::$option_name, [] );

            // Get submitted API key
            $submitted_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

            // If the key contains only asterisks, keep the saved one
            $api_key = ( strpos( $submitted_key, '*' ) === false ) ? $submitted_key : ( $saved_settings['api_key'] ?? '' );

            $settings = [
                'api_key' => $api_key,
                'enabled_post_types' => isset( $_POST['enabled_post_types'] ) && is_array( $_POST['enabled_post_types'] )
                    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_post_types'] ) )
                    : [],
                'enabled_taxonomies' => isset( $_POST['enabled_taxonomies'] ) && is_array( $_POST['enabled_taxonomies'] )
                    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_taxonomies'] ) )
                    : [],
                'max_tokens' => isset( $_POST['max_tokens'] ) ? intval( $_POST['max_tokens'] ) : 20,
            ];

            update_option( self::$option_name, $settings );            

            // General success message
            add_settings_error(
                'haayal_slug_translator_settings',
                'settings_saved',
                __( 'Your settings have been saved.', 'haayal-ai-slug-translator' ),
                'updated'
            );

            // Show warning only if API key is missing AND the free proxy quota is depleted
            $raw_remaining = get_option( 'haayal_ai_proxy_quota_remaining', null );
            $remaining = is_numeric( $raw_remaining ) ? intval( $raw_remaining ) : null;

            if ( $api_key === '' && $remaining === 0 ) {
                add_settings_error(
                    'haayal_slug_translator_settings',
                    'missing_api_key',
                    __( 'You’ve used all your free translations. To keep using AI slug translation, please enter your OpenAI API key.', 'haayal-ai-slug-translator' ),
                    'notice-warning'
                );
            }


            // Validate API key if provided
            if ( ! empty( $api_key ) ) {
                $status = Haayal_AI_Slug_Helpers::check_api_key_status( $api_key );

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
            }
        }

        $settings = self::get_settings();

        $counter = get_option( '_ai_slug_generated_slugs_counter', 0 );

        // Ensure taxonomies setting is always an array
        if ( ! is_array( $settings['enabled_taxonomies'] ) ) {
            $settings['enabled_taxonomies'] = [];
        }

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        // Remove "attachment" post type (Media) from the list of post types
        unset( $post_types['attachment'] );
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        // Remove the built-in 'post_format' taxonomy
        unset( $taxonomies['post_format'] );

        ?>
        <div class="slug-translator-settings-wrapper">

            <?php
                if ( isset( $_POST['clear_log'] ) && check_admin_referer( 'ai_slug_clear_log' ) ) {
                    Haayal_AI_Slug_Log::clear_log();
                    add_settings_error(
                        'haayal_slug_translator_settings',
                        'log_cleared',
                        __( 'Log cleared successfully.', 'haayal-ai-slug-translator' ),
                        'updated'
                    );
                }
            ?>
            <div class="inline-group header">
                <img src="<?php echo esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'assets/logo-128x128.png' ); ?>" alt="<?php esc_attr_e( 'Ailo robot', 'haayal-ai-slug-translator' ); ?>" class="ai-slug-logo">
                <div>
                    <h1><?php esc_html_e( 'Ailo - AI Slug Translator', 'haayal-ai-slug-translator' ); ?></h1>
                    <p>
                        <?php
                        if (intval( $counter ) > 0) {
                            printf(
                                // Translators: %s is the number of slugs translated using the plugin.
                                esc_html__( 'So far, %s slugs have been translated using this plugin!', 'haayal-ai-slug-translator' ),
                                '<span class="counter">' . intval( $counter ) . '</span>'
                            );
                        }
                        ?>
                    </p>
                </div>
            </div>

            <nav aria-label="<?php esc_html__( 'AI slug translator', 'haayal-ai-slug-translator' ) ?>">
                <ul>
                    <li><a href="#settings"><?php esc_html_e( 'Settings', 'haayal-ai-slug-translator' ); ?></a></li>
                    <li><a href="#why"><?php esc_html_e( 'Why to use this plugin?', 'haayal-ai-slug-translator' ); ?></a></li>
                    <li><a href="#how"><?php esc_html_e( 'How to use this plugin?', 'haayal-ai-slug-translator' ); ?></a></li>
                    <li><a href="#costs"><?php esc_html_e( 'Costs and Terms', 'haayal-ai-slug-translator' ); ?></a></li>
                    <li><a href="#ai-slug-translator-error-log"><?php esc_html_e( 'Error Log', 'haayal-ai-slug-translator' ); ?></a></li>
                </ul>
            </nav>
            <h2 id="settings"><?php esc_html_e( 'Settings', 'haayal-ai-slug-translator' ); ?></h2>

            <?php settings_errors( 'haayal_slug_translator_settings' ); ?>
            
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

                <div class="form-field-wrapper">
                    <label for="api_key"><?php esc_html_e( 'OpenAI API Key:', 'haayal-ai-slug-translator' ); ?></label>
                        <?php
                            $remaining = get_option( 'haayal_ai_proxy_quota_remaining' );
                            $remaining = is_numeric( $remaining ) ? intval( $remaining ) : 100;

                            if ( empty( $settings['api_key'] ) ) {

                                if ( $remaining === 0 ) {
                                    echo '<p class="out-of-quota-alert">' . __( 'You’ve used up your free translations. To keep translating slugs, please add your own OpenAI API key.', 'haayal-ai-slug-translator' ) . '</p>';
                                } else {
                                    printf(
                                        '<p>%s</p>',
                                        sprintf(
                                            /* translators: %d is the number of remaining free translations */
                                            __( 'You don’t need an OpenAI account to try this out — the plugin gives you 100 free slug translations for your site.<br><strong>Free translations remaining:</strong> %d out of 100', 'haayal-ai-slug-translator' ),
                                            $remaining
                                        )
                                    );
                                    echo '<p>' . __( 'You can enter your OpenAI API key here to use the plugin without relying on the free quota.', 'haayal-ai-slug-translator' ) . '</p>';
                                }

                            } elseif ( $remaining > 0 ) {

                                printf(
                                    '<p>%s</p>',
                                    sprintf(
                                        __( 'If you’d like to use the free built-in quota (%d), simply remove your OpenAI API key.', 'haayal-ai-slug-translator' ),
                                        $remaining
                                    )
                                );

                            }

                        ?>
                    <input type="text" name="api_key" id="api_key"
                        value="<?php echo ! empty( $settings['api_key'] ) ? str_repeat( '*', strlen( $settings['api_key'] ) ) : ''; ?>"
                        size="50">  
                    <small><a href="#how"><?php esc_html_e( 'Where do I get an API key?', 'haayal-ai-slug-translator' ); ?></a></small>
                </div>

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
                            foreach ( $options as $value => $label ) {
                                $max_tokens = $settings['max_tokens'] ?? 20; // Default to 20 if not set
                                $selected = ( $max_tokens == $value ) ? 'selected' : '';
                                echo '<option value="' . esc_attr( $value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
                            }
                            ?>
                        </select>
                        <p id="max-tokens-description"><small><?php esc_html_e( 'A smaller value can help cap costs, ensuring they don\'t exceed your intended budget. For extremely long titles or in case of process failures, selecting a higher value may resolve the issue. If there\'s no specific need, it is recommended to leave this setting at the default value.', 'haayal-ai-slug-translator' ); ?></small></p>
                    </div>
                </fieldset>

                <button type="submit" name="submit" class="save-settings-button"><?php esc_attr_e( 'Save Settings', 'haayal-ai-slug-translator' ); ?></button>
            </form>
            <div class="menual">
                <h2 id="why"><?php esc_html_e( 'Why to use this plugin?', 'haayal-ai-slug-translator' ); ?></h2>
                <p><?php esc_html_e( 'When sharing links with titles in non-English languages, such as Hebrew, Arabic, Chinese, or Russian, on social media platforms like Facebook or WhatsApp, the characters often get transformed into a confusing string of symbols and codes. This not only looks unprofessional but can also discourage users from clicking the link.', 'haayal-ai-slug-translator' ); ?></p>
                <p><?php esc_html_e( 'The automatic slug converter to English solves this problem seamlessly. It translates the slug into a clear, concise English version, making the link much more user-friendly and visually appealing when shared.', 'haayal-ai-slug-translator' ); ?></p>
                <p><?php esc_html_e( 'Additionally, the tool shortens long titles, resulting in cleaner and more elegant links. This is not just convenient for sharing but also improves SEO, as search engines prioritize clear and descriptive URLs.', 'haayal-ai-slug-translator' ); ?></p>
                <h3><?php esc_html_e( 'Example:', 'haayal-ai-slug-translator' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'Original title in Hebrew: איך להשתמש בממיר אוטומטי לסלאג באנגלית', 'haayal-ai-slug-translator' ); ?></li>
                    <li><?php esc_html_e( 'Page slug: /איך-להשתמש-בממיר-אוטומטי-לסלאג-באנגלית', 'haayal-ai-slug-translator' ); ?></li>
                    <li><?php esc_html_e( 'Broken URL when shared:', 'haayal-ai-slug-translator' ); ?> /%D7%90%D7%99%D7%9A-%D7%9C%D7%94%D7%A9%D7%AA%D7%9E%D7%A9-%D7%91%D7%9E%D7%9E%D7%99%D7%A8-%D7%90%D7%95%D7%98%D7%95%D7%9E%D7%98%D7%99-%D7%9C%D7%A1%D7%9C%D7%90%D7%92-%D7%91%D7%90%D7%A0%D7%92%D7%9C%D7%99%D7%AA</li>
                    <li><?php esc_html_e( 'Clean English slug: /how-to-use-automatic-slug-converter', 'haayal-ai-slug-translator' ); ?></li>
                </ol>
                <p><?php esc_html_e( 'By converting the slug to English, your links become easier to read, more attractive to share, and highly optimized for search engines. A small fix like this can have a big impact on user experience and website performance.', 'haayal-ai-slug-translator' ); ?></p>

                <h2 id="how"><?php esc_html_e( 'How to use this plugin?', 'haayal-ai-slug-translator' ); ?></h2>
                <ol>
                    <li><strong><?php esc_html_e( 'Try It Instantly:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Once you activate the plugin, you can start using it right away — no need to purchase OpenAI API credits — you get up to 100 translations for free.', 'haayal-ai-slug-translator' ); ?></li>
                    <li><strong><?php esc_html_e( 'Enable Translation:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Select the post types and taxonomies where the automatic translation feature should be applied.', 'haayal-ai-slug-translator' ); ?></li>
                    <li><strong><?php esc_html_e( 'So simple!', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'When creating a new post or taxonomy term, the plugin will automatically generate a translated slug unless you provide a custom slug yourself.', 'haayal-ai-slug-translator' ); ?></li>
                    <li><strong><?php esc_html_e( 'Verify Translations for Accuracy', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Words can have multiple meanings, so it\'s important to review the translation and ensure it fits the intended context.', 'haayal-ai-slug-translator' ); ?></li>
                    <li><?php esc_html_e( 'If you’ve used up your 100 free translations, you can keep using the plugin by doing the following:' ); ?>
                        <ol>
                            <li><strong><?php esc_html_e( 'Create an OpenAI account:', 'haayal-ai-slug-translator' ); ?></strong> <a href="https://platform.openai.com/signup" target="_blank"><?php esc_html_e( 'OpenAI Signup', 'haayal-ai-slug-translator' ); ?></a></li>
                            <li><strong><?php esc_html_e( 'Add funds to your account:', 'haayal-ai-slug-translator' ); ?></strong> <a href="https://platform.openai.com/account/billing" target="_blank"><?php esc_html_e( 'Your Billing Page', 'haayal-ai-slug-translator' ); ?></a></li>
                            <li><strong><?php esc_html_e( 'Generate an API Key:', 'haayal-ai-slug-translator' ); ?></strong> <a href="https://platform.openai.com/account/api-keys" target="_blank"><?php esc_html_e( 'API Keys', 'haayal-ai-slug-translator' ); ?></a></li>
                            <li><strong><?php esc_html_e( 'Paste the API Key:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Return to this settings page and paste your API key in the field above.', 'haayal-ai-slug-translator' ); ?></li>
                        </ol>
                    </li>
                    <li><strong><?php esc_html_e( 'Why Existing Slugs Aren’t Changed?', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'The plugin only translates slugs for posts and taxonomies at the time of creation. Automatically updating slugs for existing content is problematic, as such changes without proper 301 redirects may negatively impact SEO.', 'haayal-ai-slug-translator' ); ?></li>
                    <li><strong><?php esc_html_e( 'If the slugs are not being translated:', 'haayal-ai-slug-translator' ); ?></strong>
                        <ol>
                            <li><?php esc_html_e( 'Check if you’ve used up your free translation quota.', 'haayal-ai-slug-translator' ); ?></li>
                            <li><?php esc_html_e( 'Make sure you have enabled the relevant post types and taxonomies in the plugin\'s settings.', 'haayal-ai-slug-translator' ); ?></li>
                            <li><?php esc_html_e( 'Verify that your API key is valid and properly configured.', 'haayal-ai-slug-translator' ); ?></li>
                            <li><?php esc_html_e( 'Ensure your OpenAI account has an active payment method and sufficient funds available for use.', 'haayal-ai-slug-translator' ); ?></li>
                            <li>
                                <?php 
                                printf(
                                    // Translators: %s is a link to the OpenAI status page.
                                    esc_html__( 'Check for potential downtime or service interruptions with OpenAI by visiting their %s, as temporary unavailability may cause translation issues.', 'haayal-ai-slug-translator' ),
                                    '<a href="https://status.openai.com/" target="_blank" aria-label="OpenAI status page">' . esc_html__( 'status page', 'haayal-ai-slug-translator' ) . '</a>'
                                ); 
                                ?>
                            </li>
                        </ol>
                    </li>
                </ol>

                <h2 id="costs"><?php esc_html_e( 'Costs and Terms of Service', 'haayal-ai-slug-translator' ); ?></h2>
                <ol>
                    <li><strong><?php esc_html_e( 'The plugin is completely free to use,', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'and includes 100 slug translations at no cost.', 'haayal-ai-slug-translator' ); ?></li>
                    <li><strong><?php esc_html_e( 'After you’ve used the free quota', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'you’ll need a paid OpenAI subscription to continue.', 'haayal-ai-slug-translator' ); ?></li>
                    <li>
                        <?php 
                        printf(
                            '<strong>%s</strong> %s',
                            esc_html__( 'It’s cheap!', 'haayal-ai-slug-translator' ),
                            sprintf(
                                // Translators: 1: OpenAI Pricing Page link, 2: Cost estimation.
                                esc_html__(
                                    'Discover the pricing on the %1$s. For $1, you can perform between 10,000 and 20,000 translations depending on the title length.',
                                    'haayal-ai-slug-translator'
                                ),
                                '<a href="https://openai.com/pricing" target="_blank">' . esc_html__( 'OpenAI Pricing Page', 'haayal-ai-slug-translator' ) . '</a>'
                            )
                        );
                        ?>
                    </li>
                    <li><strong><?php esc_html_e( 'Usage and Cost Disclaimer:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'The plugin has been tested and proven to be both cost-effective and highly efficient, with near-negligible costs. However, the plugin creator is not responsible for any cost overruns or high charges resulting from improper use of the plugin, website issues or plugin errors. Always monitor your usage on the OpenAI platform to ensure it aligns with your expectations.', 'haayal-ai-slug-translator' ); ?></li>
                    <li>
                        <?php 
                        printf(
                            // Translators: 1: OpenAI Terms of Service link, 2: OpenAI Privacy Policy link.
                            esc_html__(
                                'By using this plugin, you agree to %1$s and %2$s.',
                                'haayal-ai-slug-translator'
                            ),
                            '<a href="https://openai.com/policies/terms-of-use/" target="_blank">' . esc_html__( 'OpenAI\'s Terms of Service', 'haayal-ai-slug-translator' ) . '</a>',
                            '<a href="https://openai.com/policies/privacy-policy/" target="_blank">' . esc_html__( 'OpenAI\'s Privacy Policy', 'haayal-ai-slug-translator' ) . '</a>'
                        );                        
                        ?>
                    </li>
                </ol>
            </div>

            <?php Haayal_AI_Slug_Log::display_log(); ?>

            <div class="credit">
                <?php esc_html_e( 'Developed by Elchanan Levavi.', 'haayal-ai-slug-translator' ); ?>
            </div>
        </div>

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
        ];
        return get_option( self::$option_name, $defaults );
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


