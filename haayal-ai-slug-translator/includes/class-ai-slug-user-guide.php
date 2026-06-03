<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_User_Guide {

    /**
     * Renders the User Guide tab content.
     */
    public static function render_tab() {
        ?>
        <div class="menual">
            <h2><?php esc_html_e( 'Why to use this plugin?', 'haayal-ai-slug-translator' ); ?></h2>
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

            <h2><?php esc_html_e( 'How to use this plugin?', 'haayal-ai-slug-translator' ); ?></h2>
            <ol>
                <li><strong><?php esc_html_e( 'Try It Instantly:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Once you activate the plugin, you can start using it right away — no need to purchase OpenAI API credits — you get up to 100 translations for free.', 'haayal-ai-slug-translator' ); ?></li>
                <li><strong><?php esc_html_e( 'WordPress Connectors (Recommended):', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'If you are running WordPress 7.0 or later, you can use WordPress Connectors to connect to multiple AI services (OpenAI, Anthropic, Google) from a single settings page. Configure your providers under Settings > Connectors, then select "WordPress Connectors" as your connection method above.', 'haayal-ai-slug-translator' ); ?></li>
                <li><strong><?php esc_html_e( 'Enable Translation:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Select the post types and taxonomies where the automatic translation feature should be applied.', 'haayal-ai-slug-translator' ); ?></li>
                <li><strong><?php esc_html_e( 'So simple!', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'When creating a new post or taxonomy term, the plugin will automatically generate a translated slug unless you provide a custom slug yourself.', 'haayal-ai-slug-translator' ); ?></li>
                <li><strong><?php esc_html_e( 'Verify Translations for Accuracy', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Words can have multiple meanings, so it\'s important to review the translation and ensure it fits the intended context.', 'haayal-ai-slug-translator' ); ?></li>
                <li><?php esc_html_e( 'If you\'ve used up your 100 free translations, you can keep using the plugin by switching to one of these methods:', 'haayal-ai-slug-translator' ); ?>
                    <ol>
                        <li><strong><?php esc_html_e( 'WordPress Connectors:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'If you are on WordPress 7.0+, configure your AI providers under Settings > Connectors and select "WordPress Connectors" as your connection method.', 'haayal-ai-slug-translator' ); ?></li>
                        <li><strong><?php esc_html_e( 'OpenAI API Key:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Alternatively, use your own OpenAI API key:', 'haayal-ai-slug-translator' ); ?>
                            <ol>
                                <li><strong><?php esc_html_e( 'Create an OpenAI account:', 'haayal-ai-slug-translator' ); ?></strong> <a href="https://platform.openai.com/signup" target="_blank"><?php esc_html_e( 'OpenAI Signup', 'haayal-ai-slug-translator' ); ?></a></li>
                                <li><strong><?php esc_html_e( 'Add funds to your account:', 'haayal-ai-slug-translator' ); ?></strong> <a href="https://platform.openai.com/account/billing" target="_blank"><?php esc_html_e( 'Your Billing Page', 'haayal-ai-slug-translator' ); ?></a></li>
                                <li><strong><?php esc_html_e( 'Generate an API Key:', 'haayal-ai-slug-translator' ); ?></strong> <a href="https://platform.openai.com/account/api-keys" target="_blank"><?php esc_html_e( 'API Keys', 'haayal-ai-slug-translator' ); ?></a></li>
                                <li><strong><?php esc_html_e( 'Paste the API Key:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'Return to this settings page and paste your API key in the "OpenAI API Key" card under the Settings tab.', 'haayal-ai-slug-translator' ); ?></li>
                            </ol>
                        </li>
                    </ol>
                </li>
                <li><strong><?php esc_html_e( 'How can I update existing slugs?', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'You can update existing slugs in bulk using the Bulk Translate tool. The tool can also automatically create 301 redirects. When making changes, it\'s still recommended to review your redirects, as updating URLs may impact SEO if not handled correctly.', 'haayal-ai-slug-translator' ); ?></li>
                <li><strong><?php esc_html_e( 'If the slugs are not being translated:', 'haayal-ai-slug-translator' ); ?></strong>
                    <ol>
                        <li><?php esc_html_e( 'Check if you\'ve used up your free translation quota.', 'haayal-ai-slug-translator' ); ?></li>
                        <li><?php esc_html_e( 'Make sure you have enabled the relevant post types and taxonomies in the plugin\'s settings.', 'haayal-ai-slug-translator' ); ?></li>
                        <li><?php esc_html_e( 'If using WordPress Connectors, verify that at least one AI provider is configured under Settings > Connectors.', 'haayal-ai-slug-translator' ); ?></li>
                        <li><?php esc_html_e( 'If using an OpenAI API key, verify that the key is valid and properly configured.', 'haayal-ai-slug-translator' ); ?></li>
                        <li><?php esc_html_e( 'Ensure your AI provider account has an active payment method and sufficient funds available for use.', 'haayal-ai-slug-translator' ); ?></li>
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

            <h2><?php esc_html_e( 'Costs and Terms of Service', 'haayal-ai-slug-translator' ); ?></h2>
            <ol>
                <li><strong><?php esc_html_e( 'The plugin is completely free to use,', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'and includes 100 slug translations at no cost.', 'haayal-ai-slug-translator' ); ?></li>
                <li><strong><?php esc_html_e( 'After you\'ve used the free quota', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'you can continue using WordPress Connectors (recommended) or a direct OpenAI API key.', 'haayal-ai-slug-translator' ); ?></li>
                <li><strong><?php esc_html_e( 'WordPress Connectors:', 'haayal-ai-slug-translator' ); ?></strong> <?php esc_html_e( 'When using Connectors, pricing depends on the AI provider you configure (OpenAI, Anthropic, Google, etc.). Each provider has its own pricing model.', 'haayal-ai-slug-translator' ); ?></li>
                <li>
                    <?php
                    printf(
                        '<strong>%s</strong> %s',
                        esc_html__( 'It\'s cheap!', 'haayal-ai-slug-translator' ),
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
        <?php
    }
}
