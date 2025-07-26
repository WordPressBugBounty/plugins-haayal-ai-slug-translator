<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Terms {
    public function __construct() {
        add_action( 'created_term', [ $this, 'generate_term_slug' ], 10, 3 );
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
        // Generate AI slug only if the current slug matches the default auto-generated slug
        $default_slug = sanitize_title( $term_title );
        if ( $term->slug === $default_slug ) {
            // Generate a new slug using the AI
            $api_key = $settings['api_key'];
            // if ( empty( $api_key ) ) {
            //     Haayal_AI_Slug_Log::add_entry(
            //         __( 'Missing API key. Please configure it in the settings.', 'haayal-ai-slug-translator' ),
            //         $term_title
            //     );

            //     return $data;
            // }
        
            $slug = Haayal_AI_Slug_Helpers::get_translated_slug_auto( $term_title, $api_key, $settings['max_tokens'] ?? 20 );
        
            if ( $slug ) {
                $unique_slug = $this->ensure_unique_term_slug( $slug, $taxonomy );

                // Update the term slug
                wp_update_term( $term_id, $taxonomy, [ 'slug' => $unique_slug ] );

                // Increments the slug generation counter.
                Haayal_AI_Slug_Settings::increment_generated_slugs_counter();

            } else {
                Haayal_AI_Slug_Log::add_entry(
                    __( 'AI Slug Translator: Failed to generate a valid slug.', 'haayal-ai-slug-translator' ),
                    $term_title
                );
            }
        }
    }


    /**
     * Ensures the uniqueness of a term slug within a taxonomy.
     *
     * This function checks if a given slug already exists in the specified taxonomy
     * and appends a numeric suffix to make it unique if necessary.
     *
     * @param string $slug The desired slug for the term.
     * @param string $taxonomy The taxonomy in which the term belongs.
     *
     * @return string The unique slug.
     */
    private function ensure_unique_term_slug( $slug, $taxonomy ) {
        $original_slug = $slug;
        $i = 1;
    
        while ( term_exists( $slug, $taxonomy ) ) {
          $slug = $original_slug . '-' . $i;
          $i++;
        }
    
        return $slug;
    }

}