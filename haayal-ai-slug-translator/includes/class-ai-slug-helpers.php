<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Helpers {

    /**
     * Automatically routes the slug translation request based on the selected connection method.
     *
     * Supports three methods: WordPress Connectors (WP 7+), direct OpenAI API key, and free proxy.
     * Falls back to legacy behavior (API key if set, else proxy) when no method is explicitly configured.
     *
     * @param string $title      The original title to be translated into a slug.
     * @param string $api_key    The OpenAI API key (may be empty).
     * @param int    $max_tokens The maximum number of tokens to allow in the response.
     *
     * @return string|null       The translated and sanitized slug, or null on failure.
     */
    public static function get_translated_slug_auto( $title, $api_key, $max_tokens = 20 ) {
        $settings = Haayal_AI_Slug_Settings::get_settings();
        $method   = isset( $settings['connection_method'] ) ? $settings['connection_method'] : '';

        // Explicit method selection.
        if ( 'connectors' === $method ) {
            return self::get_translated_slug_via_connectors( $title, $max_tokens );
        }

        if ( 'api_key' === $method && ! empty( $api_key ) ) {
            return self::get_translated_slug( $title, $api_key, $max_tokens );
        }

        if ( 'proxy' === $method ) {
            return self::get_translated_slug_via_proxy( $title, get_site_url() );
        }

        // Backward compatibility: no method explicitly set.
        if ( ! empty( $api_key ) ) {
            return self::get_translated_slug( $title, $api_key, $max_tokens );
        }

        return self::get_translated_slug_via_proxy( $title, get_site_url() );
    }

    /**
     * Translates a title into a slug and tracks the result (increments counter on success, logs on failure).
     *
     * @param string $title      The original title to translate.
     * @param string $api_key    The OpenAI API key (may be empty).
     * @param int    $max_tokens The maximum number of tokens for the response.
     *
     * @return string|null The translated slug, or null if skipped or failed.
     */
    /**
     * Returns true if the string contains at least one non-Latin (non-ASCII) character.
     *
     * @param string $str The string to check.
     * @return bool
     */
    public static function has_non_latin_chars( $str ) {
        return (bool) preg_match( '/[^\x00-\x7F]/', rawurldecode( $str ) );
    }

    public static function translate_and_track( $title, $api_key, $max_tokens = 20 ) {
        // Skip translation if the title contains only ASCII characters (Latin/English).
        if ( ! self::has_non_latin_chars( $title ) ) {
            return null;
        }

        $slug = self::get_translated_slug_auto( $title, $api_key, $max_tokens );

        if ( $slug ) {
            Haayal_AI_Slug_Settings::increment_generated_slugs_counter();
        }

        return $slug;
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
        $models = HAAYAL_AI_SLUG_TRANSLATION_MODELS;
        $last_error = '';

        foreach ( $models as $model ) {
            $response = wp_remote_post(
                HAAYAL_AI_SLUG_OPENAI_ENDPOINT,
                [
                    'timeout' => HAAYAL_AI_SLUG_API_TIMEOUT,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'body' => wp_json_encode( [
                        'model'      => $model,
                        'messages'   => [
                            [
                                'role'    => 'user',
                                'content' => sprintf(
                                    "Translate and simplify the following title to an English slug, limit to 1-4 words, lowercase and replace spaces with hyphens. If the input cannot be meaningfully translated (it is gibberish, random characters, or has no meaning), respond with exactly one word: untranslatable\n\nTitle: \"%s\"",
                                    $title
                                ),
                            ],
                        ],
                        'max_tokens' => $max_tokens,
                    ] ),
                ]
            );

            if ( is_wp_error( $response ) ) {
                $last_error = $response->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            // Auth errors — retrying another model won't help.
            if ( $code === 401 || $code === 403 ) {
                Haayal_AI_Slug_Log::add_entry(
                    sprintf(
                        // Translators: %s is the error message returned by the OpenAI API.
                        __( 'Failed to generate slug. Error communicating with OpenAI API: %s', 'haayal-ai-slug-translator' ),
                        ! empty( $body['error']['message'] ) ? self::fix_api_encoding( $body['error']['message'] ) : $code
                    ),
                    $title
                );
                return null;
            }

            // Quota/rate-limit errors — retrying another model won't help.
            if ( $code === 429 ) {
                Haayal_AI_Slug_Log::add_entry(
                    sprintf(
                        // Translators: %s is the error message returned by the OpenAI API.
                        __( 'Failed to generate slug. Error communicating with OpenAI API: %s', 'haayal-ai-slug-translator' ),
                        ! empty( $body['error']['message'] ) ? self::fix_api_encoding( $body['error']['message'] ) : $code
                    ),
                    $title
                );
                return null;
            }

            // Model not found — try the next one.
            if ( $code === 404 ) {
                continue;
            }

            // Success.
            if ( $code === 200 && ! empty( $body['choices'][0]['message']['content'] ) ) {
                $slug = sanitize_title( $body['choices'][0]['message']['content'] );
                if ( self::is_refusal_slug( $slug ) ) {
                    Haayal_AI_Slug_Log::add_entry(
                        __( 'Slug skipped — title is untranslatable (gibberish or random characters).', 'haayal-ai-slug-translator' ),
                        $title
                    );
                    return null;
                }
                return $slug;
            }

            // Any other failure — capture the error and try the next model.
            if ( ! empty( $body['error']['message'] ) ) {
                $last_error = self::fix_api_encoding( $body['error']['message'] );
            } elseif ( ! empty( $body ) ) {
                $last_error = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            } else {
                $last_error = __( 'No response body.', 'haayal-ai-slug-translator' );
            }
        }

        Haayal_AI_Slug_Log::add_entry(
            sprintf(
                // Translators: %s is the detailed response returned by the API when no valid translation is found.
                __( 'Failed to generate slug. API response did not include a valid translation. Response details: %s', 'haayal-ai-slug-translator' ),
                $last_error
            ),
            $title
        );

        return null;
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
        $response = wp_remote_post( HAAYAL_AI_SLUG_PROXY_ENDPOINT, [
            'timeout' => HAAYAL_AI_SLUG_PROXY_TIMEOUT,
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
                    /* translators: %s is the error message returned by the proxy server */
                    __( 'Failed to generate slug. Error contacting proxy server: %s', 'haayal-ai-slug-translator' ),
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
                __( 'Failed to generate slug. Translation skipped — free quota has been used up for this domain.', 'haayal-ai-slug-translator' ),
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
                __( 'Failed to generate slug. Proxy response did not include a slug.', 'haayal-ai-slug-translator' ),
                $title
            );
            return null;
        }

        $slug = sanitize_title( $body['slug'] );
        if ( self::is_refusal_slug( $slug ) ) {
            Haayal_AI_Slug_Log::add_entry(
                __( 'Slug skipped — title is untranslatable (gibberish or random characters).', 'haayal-ai-slug-translator' ),
                $title
            );
            return null;
        }
        return $slug;
    }

    /**
     * Generates a translated slug using the WordPress Connectors API (WP 7+).
     *
     * Uses the wp_ai_client_prompt() builder which handles provider selection,
     * authentication, and HTTP communication automatically.
     *
     * @param string $title      The title to translate into a slug.
     * @param int    $max_tokens The maximum number of tokens for the response.
     *
     * @return string|null The translated and sanitized slug, or null on failure.
     */
    public static function get_translated_slug_via_connectors( $title, $max_tokens = 20 ) {
        if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
            Haayal_AI_Slug_Log::add_entry(
                __( 'Failed to generate slug. WordPress Connectors API is not available. Please ensure you are running WordPress 7.0 or later.', 'haayal-ai-slug-translator' ),
                $title
            );
            return null;
        }

        $prompt = sprintf(
            "Translate and simplify the following title to an English slug, limit to 1-4 words, lowercase and replace spaces with hyphens. If the input cannot be meaningfully translated (it is gibberish, random characters, or has no meaning), respond with exactly one word: untranslatable\n\nTitle: \"%s\"",
            $title
        );

        $result = wp_ai_client_prompt( $prompt )
            ->using_model_preference(
                'gemini-2.5-flash-lite',
                'gpt-4.1-mini',
                'claude-haiku-4-5',
                'gemini-2.5-flash',
                'gpt-4o-mini',
                'qwen-plus',
                'deepseek-v3',
                'mistral-small'
            )
            ->using_max_tokens( $max_tokens )
            ->generate_text();

        if ( is_wp_error( $result ) ) {
            Haayal_AI_Slug_Log::add_entry(
                sprintf(
                    // Translators: %s is the error message returned by WordPress Connectors.
                    __( 'Failed to generate slug. WordPress Connectors error: %s', 'haayal-ai-slug-translator' ),
                    $result->get_error_message()
                ),
                $title
            );
            return null;
        }

        if ( empty( $result ) ) {
            Haayal_AI_Slug_Log::add_entry(
                __( 'Failed to generate slug. WordPress Connectors returned an empty response.', 'haayal-ai-slug-translator' ),
                $title
            );
            return null;
        }

        $slug = sanitize_title( $result );
        if ( self::is_refusal_slug( $slug ) ) {
            Haayal_AI_Slug_Log::add_entry(
                __( 'Slug skipped — title is untranslatable (gibberish or random characters).', 'haayal-ai-slug-translator' ),
                $title
            );
            return null;
        }
        return $slug;
    }

    /**
     * Ensures the uniqueness of a term slug within a taxonomy.
     *
     * Checks if a given slug already exists in the specified taxonomy
     * and appends a numeric suffix to make it unique if necessary.
     *
     * @param string $slug     The desired slug for the term.
     * @param string $taxonomy The taxonomy in which the term belongs.
     *
     * @return string The unique slug.
     */
    public static function ensure_unique_term_slug( $slug, $taxonomy ) {
        $original_slug = $slug;
        $i = 1;

        while ( term_exists( $slug, $taxonomy ) ) {
            $slug = $original_slug . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * Checks the status of an OpenAI API key.
     *
     * @param string $api_key The OpenAI API key.
     * @return string One of: 'valid', 'insufficient_quota', 'invalid'
     */
    public static function check_api_key_status( $api_key ) {
        $response = wp_remote_post(
            HAAYAL_AI_SLUG_OPENAI_ENDPOINT,
            [
                'timeout' => HAAYAL_AI_SLUG_VALIDATION_TIMEOUT,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => HAAYAL_AI_SLUG_VALIDATION_MODEL,
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

    private static function is_refusal_slug( $slug ) {
        return $slug === 'untranslatable';
    }

    /**
     * Fix API error messages where UTF-8 bytes were encoded as individual \u00XX escapes.
     *
     * Some APIs return multi-byte UTF-8 characters (e.g., Hebrew כ = bytes D7 9B) as
     * separate Unicode code points (\u00D7\u009B). After json_decode these become
     * U+00D7 U+009B instead of the intended character. This collapses them back.
     *
     * @param string $str The potentially mangled string.
     * @return string The fixed string.
     */
    private static function fix_api_encoding( $str ) {
        if ( ! function_exists( 'mb_convert_encoding' ) ) {
            return $str;
        }

        $attempt = mb_convert_encoding( $str, 'ISO-8859-1', 'UTF-8' );
        if ( mb_check_encoding( $attempt, 'UTF-8' ) ) {
            return $attempt;
        }

        return $str;
    }

    /**
     * Encrypts an API key using AES-256-CBC with a key derived from WP secret salts.
     * Returns the key unchanged if openssl is unavailable or the key is empty.
     *
     * @param string $key Plaintext API key.
     * @return string Encrypted value prefixed with 'enc:', or original string on failure.
     */
    public static function encrypt_api_key( string $key ): string {
        if ( empty( $key ) || ! function_exists( 'openssl_encrypt' ) ) {
            return $key;
        }
        $enc_key = substr( hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true ), 0, 32 );
        $iv      = random_bytes( 16 );
        $cipher  = openssl_encrypt( $key, 'AES-256-CBC', $enc_key, OPENSSL_RAW_DATA, $iv );
        return 'enc:' . base64_encode( $iv . $cipher );
    }

    /**
     * Decrypts an API key encrypted by encrypt_api_key().
     * Returns the value unchanged if it has no 'enc:' prefix (backward compatibility).
     *
     * @param string $value Stored value (encrypted or legacy plaintext).
     * @return string Plaintext API key, or empty string on decryption failure.
     */
    public static function decrypt_api_key( string $value ): string {
        if ( empty( $value ) || strpos( $value, 'enc:' ) !== 0 ) {
            return $value;
        }
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }
        $enc_key = substr( hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true ), 0, 32 );
        $raw     = base64_decode( substr( $value, 4 ) );
        $iv      = substr( $raw, 0, 16 );
        $cipher  = substr( $raw, 16 );
        $plain   = openssl_decrypt( $cipher, 'AES-256-CBC', $enc_key, OPENSSL_RAW_DATA, $iv );
        return false !== $plain ? $plain : '';
    }

}
