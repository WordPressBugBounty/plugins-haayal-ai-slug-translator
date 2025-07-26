<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Helpers {

    /**
     * Automatically routes the slug translation request to either the OpenAI API or the proxy server,
     * depending on whether an API key is provided in the plugin settings.
     *
     * @param string $title      The original title to be translated into a slug.
     * @param string $api_key    The OpenAI API key (may be empty).
     * @param int    $max_tokens The maximum number of tokens to allow in the response.
     *
     * @return string|null       The translated and sanitized slug, or null on failure.
     */
    public static function get_translated_slug_auto( $title, $api_key, $max_tokens = 20 ) {
        if ( ! empty( $api_key ) ) {
            return self::get_translated_slug( $title, $api_key, $max_tokens );
        }

        return self::get_translated_slug_via_proxy( $title, get_site_url() );
    }


    /**
     * Generates a translated slug for a given title using the OpenAI API.
     *
     * @param string $title The title to be translated.
     * @param string $api_key The OpenAI API key.
     * @param int    $max_tokens The maximum number of tokens allowed for the API response.
     *
     * @return string|null The translated and sanitized slug, or null if the request fails.
     */
    public static function get_translated_slug( $title, $api_key, $max_tokens = 20 ) {
        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'timeout' => 20, 
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => sprintf(
                                'Translate and simplify the following title to an English slug, limit to 1-4 words, lowercase and replace spaces with hyphens: "%s"',
                                $title
                            )
                        ]
                    ],
                    'max_tokens' => $max_tokens
                ])
            ]
        );        

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            $title = $post->post_title ?? __( 'Unknown Title', 'haayal-ai-slug-translator' );
        
            Haayal_AI_Slug_Log::add_entry(
                sprintf(
                    // Translators: %s is the error message returned by the OpenAI API.
                    __( 'Error communicating with OpenAI API: %s', 'haayal-ai-slug-translator' ),
                    $error_message
                ),
                $title
            );            
        
            return null;
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['choices'][0]['message']['content'] ) ) {
            $response_details = isset( $body ) ? wp_json_encode( $body ) : __( 'No response body.', 'haayal-ai-slug-translator' );
            $title = $post->post_title ?? __( 'Unknown Title', 'haayal-ai-slug-translator' );
        
            Haayal_AI_Slug_Log::add_entry(
                sprintf(
                    // Translators: %s is the detailed response returned by the API when no valid translation is found.
                    __( 'API response did not include a valid translation. Response details: %s', 'haayal-ai-slug-translator' ),
                    $response_details
                ),
                $title
            );
        
            return null;
        }
        

        return sanitize_title( $body['choices'][0]['message']['content'] );
    }

    /**
     * Sends a request to the proxy server to generate a translated slug using OpenAI,
     * without exposing the main API key.
     *
     * @param string $title     The title to translate into a slug.
     * @param string $site_url  The current site URL, used to track per-domain usage on the proxy.
     *
     * @return string|null      The translated and sanitized slug, or null if the request fails.
     */
    public static function get_translated_slug_via_proxy( $title, $site_url ) {
        $endpoint = 'https://dev.ha-ayal.co.il/slug-translator/wp-json/ai-slug/v1/translate';
        $response = wp_remote_post( $endpoint, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'title'    => $title,
                'site_url' => $site_url,
            ]),
        ]);

        if ( is_wp_error( $response ) ) {
            Haayal_AI_Slug_Log::add_entry(
                sprintf(
                    __( 'Error contacting proxy server: %s', 'haayal-ai-slug-translator' ),
                    $response->get_error_message()
                ),
                $title
            );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 429 && isset( $body['error'] ) && $body['error'] === 'Limit exceeded' ) {
            Haayal_AI_Slug_Log::add_entry(
                __( 'Translation skipped — free quota has been used up for this domain.', 'haayal-ai-slug-translator' ),
                $title
            );
            update_option( 'haayal_ai_proxy_quota_remaining', 0 );
            return null;
        }

        if ( isset( $body['remaining'] ) ) {
            update_option( 'haayal_ai_proxy_quota_remaining', (int) $body['remaining'] );
        }

        if ( empty( $body['slug'] ) ) {
            Haayal_AI_Slug_Log::add_entry(
                __( 'Proxy response did not include a slug.', 'haayal-ai-slug-translator' ),
                $title
            );
            return null;
        }

        return sanitize_title( $body['slug'] );
    }

    /**
     * Checks the status of an OpenAI API key.
     *
     * @param string $api_key The OpenAI API key.
     * @return string One of: 'valid', 'insufficient_quota', 'invalid'
     */
    public static function check_api_key_status( $api_key ) {
        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [ 'role' => 'user', 'content' => 'Hello' ]
                    ],
                    'max_tokens' => 1,
                ]),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return 'invalid';
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            return 'valid';
        }

        if ( $code === 429 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['error']['code'] ) && $body['error']['code'] === 'insufficient_quota' ) {
                return 'insufficient_quota';
            }
        }

        return 'invalid';
    }

}
