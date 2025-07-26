<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Haayal_AI_Slug_Log {

    private static $log_option_name = '_ai_slug_error_log';
    private static $max_entries = 100;

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
            'time' => current_time( 'mysql' ),
            'message' => $message,
            'title' => $title,
        ];

        // Add the new entry to the beginning of the log
        array_unshift( $log, $entry );

        // Limit the log to the maximum number of entries
        if ( count( $log ) > self::$max_entries ) {
            $log = array_slice( $log, 0, self::$max_entries );
        }

        // Save the updated log
        update_option( self::$log_option_name, $log );
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
     * Display the log in the settings page.
     */
    public static function display_log() {
        $log = self::get_log();
        echo '<div class="ai-slug-translator-error-log"  id="ai-slug-translator-error-log">';
        echo '<h2>' . esc_html__( 'Error Log', 'haayal-ai-slug-translator' ) . '</h2>';
        echo '<table class="widefat striped log-table">';
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
                echo '<tr>';
                echo '<td>' . esc_html( $entry['time'] ) . '</td>';
                echo '<td>' . esc_html( $entry['title'] ) . '</td>';
                echo '<td>' . esc_html( $entry['message'] ) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        if ( !empty( $log ) ) {
        // Button to clear the log
            echo '<form method="post">';
            wp_nonce_field( 'ai_slug_clear_log' );
            echo '<button type="submit" name="clear_log" class="button-secondary">' . esc_html__( 'Clear Log', 'haayal-ai-slug-translator' ) . '</button>';
            echo '</form>';
        }
        echo '</div>';
    }
}
