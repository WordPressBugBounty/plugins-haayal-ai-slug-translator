<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Log {

    private static $log_option_name = '_ai_slug_error_log';
    private static $max_entries = 100;
    private static $current_context = [];

    /**
     * Set the context for subsequent log entries.
     *
     * @param array $context { object_id, object_type ('post'|'term'), taxonomy (for terms) }
     */
    public static function set_context( $context = [] ) {
        self::$current_context = $context;
    }

    /**
     * Add a new log entry.
     *
     * @param string $message The log message.
     * @param string $title The title of the post or term causing the error.
     */
    public static function add_entry( $message, $title = '' ) {
        $log = get_option( self::$log_option_name, [] );

        // Format the log entry
        $entry = [
            'time'    => current_time( 'mysql' ),
            'message' => $message,
            'title'   => $title,
        ];

        if ( ! empty( self::$current_context['object_id'] ) ) {
            $entry['object_id']   = (int) self::$current_context['object_id'];
            $entry['object_type'] = self::$current_context['object_type'] ?? 'post';
            if ( ! empty( self::$current_context['taxonomy'] ) ) {
                $entry['taxonomy'] = self::$current_context['taxonomy'];
            }
        }

        // Add the new entry to the beginning of the log
        array_unshift( $log, $entry );

        // Limit the log to the maximum number of entries
        if ( count( $log ) > self::$max_entries ) {
            $log = array_slice( $log, 0, self::$max_entries );
        }

        // Save the updated log (autoload disabled — only needed on settings page).
        update_option( self::$log_option_name, $log, false );
    }

    /**
     * Retrieve the log entries.
     *
     * @return array The log entries.
     */
    public static function get_log() {
        return get_option( self::$log_option_name, [] );
    }

    /**
     * Clear the log entries.
     */
    public static function clear_log() {
        delete_option( self::$log_option_name );
    }

    /**
     * Build the edit URL for a log entry from its stored ID and type.
     *
     * @param array $entry A single log entry.
     * @return string|null The edit URL or null if not available.
     */
    private static function get_edit_url( $entry ) {
        if ( empty( $entry['object_id'] ) ) {
            return null;
        }

        $id   = (int) $entry['object_id'];
        $type = $entry['object_type'] ?? 'post';

        if ( 'term' === $type ) {
            $taxonomy = $entry['taxonomy'] ?? '';
            if ( $taxonomy ) {
                return admin_url( 'term.php?taxonomy=' . $taxonomy . '&tag_ID=' . $id );
            }
            return null;
        }

        return get_edit_post_link( $id, 'raw' );
    }

    /**
     * Display the log in the settings page.
     */
    public static function display_log() {
        $log = self::get_log();
        echo '<div class="ai-slug-translator-error-log"  id="ai-slug-translator-error-log">';
        echo '<h2>' . esc_html__( 'Error Log', 'haayal-ai-slug-translator' ) . '</h2>';
        if ( ! empty( $log ) ) {
            echo '<p class="haayal-table-count">' . esc_html( count( $log ) . ' ' . __( 'entries', 'haayal-ai-slug-translator' ) ) . '</p>';
        }
        echo '<table class="widefat striped log-table">';
        echo '<caption class="screen-reader-text">' . esc_html__( 'Slug translation error log', 'haayal-ai-slug-translator' ) . '</caption>';
        echo '<thead>
                <tr>
                    <th>' . esc_html__( 'Time', 'haayal-ai-slug-translator' ) . '</th>
                    <th>' . esc_html__( 'Title', 'haayal-ai-slug-translator' ) . '</th>
                    <th>' . esc_html__( 'Message', 'haayal-ai-slug-translator' ) . '</th>
                </tr>
            </thead>';
        echo '<tbody>';
        if ( empty( $log ) ) {
            echo '<tr><td colspan="3">' . esc_html__( 'No errors logged yet.', 'haayal-ai-slug-translator' ) . '</td></tr>';
        } else {
            foreach ( $log as $entry ) {
                $edit_url = self::get_edit_url( $entry );
                $title_html = esc_html( $entry['title'] );
                if ( $edit_url ) {
                    $title_html = '<a href="' . esc_url( $edit_url ) . '" target="_blank">' . $title_html . '</a>';
                }

                echo '<tr>';
                echo '<td>' . esc_html( $entry['time'] ) . '</td>';
                echo '<td>' . $title_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
                echo '<td>' . esc_html( $entry['message'] ) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        if ( ! empty( $log ) ) {
            // Button to clear the log.
            echo '<form method="post" id="haayal-clear-log-form">';
            wp_nonce_field( 'ai_slug_clear_log' );
            echo '<input type="hidden" name="clear_log" value="1">';
            echo '<button type="button" id="haayal-clear-log-btn" class="button-secondary">' . esc_html__( 'Clear Log', 'haayal-ai-slug-translator' ) . '</button>';
            echo '</form>';
        }
        echo '</div>';
    }
}
