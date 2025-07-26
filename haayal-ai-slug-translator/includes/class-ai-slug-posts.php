<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Posts {
    private $slug_meta_key = '_slug_source';

    
    public function __construct() {
        add_filter( 'wp_insert_post_data', [ $this, 'generate_slug_on_title_entry' ], 10, 2 );
        add_action( 'save_post', [ $this, 'sync_slug_on_save' ], 10 );
        add_action( 'save_post', [ $this, 'track_slug_edits' ], 10, 2 );
        add_filter( 'get_sample_permalink_html', [ $this, 'add_slug_indicator' ], 10, 5 );         
    }

    /**
     * Generates and stores a slug after the title is entered.
     *
     * @param array $data An array of slashed post data.
     * @param array $postarr The raw post array.
     * @return array Modified post data with the AI-generated slug.
     */
    public function generate_slug_on_title_entry( $data, $postarr ) {

        // Skip autosave, revisions, or auto-drafts
        if ( wp_is_post_autosave( $postarr['ID'] ) || wp_is_post_revision( $postarr['ID'] ) || $data['post_status'] === 'auto-draft' ) {
            return $data;
        }

        // Check if the post type is supported
        $settings = Haayal_AI_Slug_Settings::get_settings();
        if ( ! in_array( $data['post_type'], $settings['enabled_post_types'], true ) ) {
            return $data;
        }

        // Skip if the slug is already manually set
        if ( ! empty( $postarr['post_name'] ) ) {
            return $data;
        }

        $title = $data['post_title'];

        // Generate a new slug using the AI
        $api_key = $settings['api_key'];

        if ( empty( $title ) ) {
            AI_Slug_Log::add_entry(
                __( 'Title is empty or missing.', 'haayal-ai-slug-translator' ),
                __( 'Unknown Title', 'haayal-ai-slug-translator' )
            );
            return $data;
        }
        
        $slug = Haayal_AI_Slug_Helpers::get_translated_slug_auto( $title, $api_key, $settings['max_tokens'] ?? 20 );

        if ( $slug ) {
            // Store the generated slug in a custom field
            update_post_meta( $postarr['ID'], '_generated_slug', $slug );

            // Add the source metadata
            update_post_meta( $postarr['ID'], $this->slug_meta_key, 'ai' );

            // Set the slug for the current post data
            $data['post_name'] = $slug;

            // Increments the slug generation counter.
            Haayal_AI_Slug_Settings::increment_generated_slugs_counter();

        } else {
            Haayal_AI_Slug_Log::add_entry(
                __( 'AI Slug Translator: Failed to generate a valid slug.', 'haayal-ai-slug-translator' ),
                $title
            );
        }

        return $data;
    }

    /**
     * Ensures the slug matches the generated slug on save.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function sync_slug_on_save( $post_id ) {
        // Skip autosave, revisions, or auto-drafts
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Load the post object
        $post = get_post( $post_id );

        // Validate the post object
        if ( ! $post || $post->post_status === 'auto-draft' ) {
            return;
        }

        // Check if the post type is supported
        $settings = Haayal_AI_Slug_Settings::get_settings();
        if ( ! in_array( $post->post_type, $settings['enabled_post_types'], true ) ) {
            return;
        }

        // Retrieve the generated slug
        $generated_slug = get_post_meta( $post_id, '_generated_slug', true );

        // If the current slug matches the generated slug, update the post slug
        if ( $generated_slug && $post->post_name === $generated_slug ) {
            remove_action( 'save_post', [ $this, 'sync_slug_on_save' ], 10 );
            wp_update_post( [
                'ID'        => $post_id,
                'post_name' => $generated_slug,
            ] );
            add_action( 'save_post', [ $this, 'sync_slug_on_save' ], 10 );
        }
    }


    /**
     * Tracks slug edits and updates the meta key accordingly.
     *
     * @param int $post_id The ID of the post being saved.
     * @param WP_Post $post The post object.
     */
    public function track_slug_edits( $post_id, $post ) {
        // Skip autosave, revisions, or auto-drafts
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Retrieve the previous slug from the meta key
        $previous_slug = get_post_meta( $post_id, '_generated_slug', true );

        // If the slug has been edited by the user, update the meta key
        if ( $previous_slug && $post->post_name !== $previous_slug ) {
            update_post_meta( $post_id, '_slug_source', 'user-edited' );
        }
    }


    /**
     * Adds a custom indicator to the permalink HTML based on the slug source.
     *
     * @param string  $permalink_html The permalink HTML.
     * @param int     $post_id The post ID.
     * @param string  $new_title The new title.
     * @param string  $new_slug The new slug.
     * @param WP_Post $post The post object.
     * @return string The modified permalink HTML with the indicator.
     */
    public function add_slug_indicator( $permalink_html, $post_id, $new_title, $new_slug, $post ) {
        // Retrieve the slug source meta key
        $slug_source = get_post_meta( $post_id, '_slug_source', true );

        if ( $slug_source === 'ai' ) {
            $indicator = '<span class="ai-slug-indicator" style="color: green; font-weight: bold; margin-left: 10px;">' . esc_html__( '(Slug generated by AI)', 'haayal-ai-slug-translator' ) . '</span>';
        } elseif ( $slug_source === 'user-edited' ) {
            $indicator = '<span class="ai-slug-indicator" style="color: blue; font-weight: bold; margin-left: 10px;">' . esc_html__( '(Slug manually edited)', 'haayal-ai-slug-translator' ) . '</span>';
        } else {
            $indicator = '';
        }

        return $permalink_html . $indicator;
    }
    
}