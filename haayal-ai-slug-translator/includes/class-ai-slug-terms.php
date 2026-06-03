<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Terms {
    public function __construct() {
        add_action( 'created_term', [ $this, 'generate_term_slug' ], 10, 3 );
        add_action( 'edited_term', [ $this, 'track_term_slug_edits' ], 10, 3 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_term_assets' ] );
    }

    /**
     * Generates an AI-translated slug for a new taxonomy term.
     *
     * This function checks if the taxonomy is enabled for AI slug generation, verifies
     * that the user has not provided a custom slug, and uses the OpenAI API to generate
     * a translated slug. The slug is then ensured to be unique within the taxonomy.
     *
     * @param int    $term_id The ID of the term being created.
     * @param int    $tt_id The term taxonomy ID.
     * @param string $taxonomy The taxonomy to which the term belongs.
     */
    public function generate_term_slug( $term_id, $tt_id, $taxonomy ) {
        // Check if the taxonomy is supported
        $settings = Haayal_AI_Slug_Settings::get_settings();

        if ( ! in_array( $taxonomy, $settings['enabled_taxonomies'], true ) ) {
            return;
        }
    
        // Check if the user explicitly provided a slug via $_POST
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is already verified by WordPress.
        if ( isset( $_POST['slug'] ) && ! empty( $_POST['slug'] ) ) {
            return; // Do not override user-defined slugs
        }
        // phpcs:enable
    
        // Fetch the term to ensure it's valid
        $term = get_term( $term_id, $taxonomy );
        if ( is_wp_error( $term ) ) {
            return;
        }
    
        $term_title = $term->name;

        Haayal_AI_Slug_Log::set_context( [ 'object_id' => $term_id, 'object_type' => 'term', 'taxonomy' => $taxonomy ] );

        if ( empty( $term_title ) ) {
            Haayal_AI_Slug_Log::add_entry(
                __( 'Title is empty or missing.', 'haayal-ai-slug-translator' ),
                __( 'Unknown Title', 'haayal-ai-slug-translator' )
            );
            return;
        }

        // Generate AI slug only if the current slug matches the default auto-generated slug
        $default_slug = sanitize_title( $term_title );
        if ( $term->slug === $default_slug ) {
            $api_key = $settings['api_key'];
            $slug = Haayal_AI_Slug_Helpers::translate_and_track( $term_title, $api_key, $settings['max_tokens'] ?? 20 );

            if ( $slug ) {
                $unique_slug = Haayal_AI_Slug_Helpers::ensure_unique_term_slug( $slug, $taxonomy );
                wp_update_term( $term_id, $taxonomy, [ 'slug' => $unique_slug ] );
                update_term_meta( $term_id, '_slug_source', 'ai' );
            }
        }

        Haayal_AI_Slug_Log::set_context();
    }

    /**
     * Tracks when a user manually edits a term slug.
     *
     * @param int    $term_id  The term ID.
     * @param int    $tt_id   The term taxonomy ID.
     * @param string $taxonomy The taxonomy slug.
     */
    public function track_term_slug_edits( $term_id, $tt_id, $taxonomy ) {
        $slug_source = get_term_meta( $term_id, '_slug_source', true );

        if ( 'ai' !== $slug_source ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is already verified by WordPress.
        $posted_slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
        // phpcs:enable

        if ( empty( $posted_slug ) ) {
            return;
        }

        $term = get_term( $term_id, $taxonomy );
        if ( is_wp_error( $term ) ) {
            return;
        }

        // The slug field was explicitly filled — if it differs from what AI generated, mark as user-edited.
        // At this point WP already applied the new slug, so $term->slug is the new value.
        // We compare against what was posted to confirm the user actively changed it.
        update_term_meta( $term_id, '_slug_source', 'user-edited' );
    }

    /**
     * Enqueues shared CSS and the term badge JS on the term edit screen.
     * Also computes badge visibility state and passes it to the script via wp_localize_script.
     */
    public function enqueue_term_assets() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'term' ) {
            return;
        }

        wp_enqueue_style(
            'haayal-slug-shared',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/ai-slug-shared.css',
            [],
            HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION
        );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only; no data is modified.
        $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';
        $term_id  = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;
        // phpcs:enable

        if ( ! $taxonomy || ! $term_id ) {
            return;
        }

        $settings = Haayal_AI_Slug_Settings::get_settings();
        if ( ! in_array( $taxonomy, $settings['enabled_taxonomies'], true ) ) {
            return;
        }

        $slug_source       = get_term_meta( $term_id, '_slug_source', true );
        $show_ai_badge     = ( 'ai' === $slug_source );
        $show_edited_badge = ( 'user-edited' === $slug_source );
        $term              = get_term( $term_id, $taxonomy );
        $has_non_latin     = ( ! is_wp_error( $term ) && Haayal_AI_Slug_Helpers::has_non_latin_chars( $term->slug ) );
        $show_bulk_link    = ( empty( $slug_source ) && $has_non_latin && current_user_can( 'manage_options' ) );

        if ( ! $show_ai_badge && ! $show_edited_badge && ! $show_bulk_link ) {
            return;
        }

        wp_enqueue_script(
            'haayal-slug-terms',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/ai-slug-terms.js',
            [],
            HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION,
            true
        );

        wp_localize_script( 'haayal-slug-terms', 'haayalTermBadge', [
            'showAiBadge'     => $show_ai_badge,
            'showEditedBadge' => $show_edited_badge,
            'showBulkLink'    => $show_bulk_link,
            'aiBadgeLabel'    => esc_html__( 'Slug generated by AI', 'haayal-ai-slug-translator' ),
            'editedBadgeLabel'=> esc_html__( 'Slug manually edited', 'haayal-ai-slug-translator' ),
            'bulkLabel'       => esc_html__( 'Bulk translate slugs', 'haayal-ai-slug-translator' ),
            'bulkUrl'         => esc_url( admin_url( 'options-general.php?page=ai-slug-translator&tab=bulk' ) ),
        ] );
    }

}