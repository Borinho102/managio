<?php

/* namespace Corbital\Rightful\Helpers; */

if (!function_exists('update_log')) {
    /**
     * Log update message to a file.
     *
     * @param string $message       The message to log
     * @param string $log_file_path Optional log file path (defaults to application/logs/ctl_update_log.log)
     *
     * @return void
     */
    function update_log($message='', $log_file_path='', $add_separator = false)
    {
        // Check if the logs directory exists, if not create it
        $logs_dir = dirname($log_file_path);
        if (!is_dir($logs_dir)) {
            mkdir($logs_dir, 0755, true);
        }

        // Prepare the formatted log entry
        $timestamp         = date('Y-m-d H:i:s');
        $formatted_message = !empty($message) ? "[$timestamp] $message".\PHP_EOL : \PHP_EOL;

        // Add two new lines after the message for group separation
        if (str_contains($message, '\\n\\n')) {
            $formatted_message .= \PHP_EOL;
        }

        // Add a separator if specified
        if ($add_separator) {
            $formatted_message .= str_repeat('=', 80).\PHP_EOL.\PHP_EOL;
        }

        if (!empty($log_file_path)) {
            // Write the message to the log file, append if file exists
            file_put_contents($log_file_path, $formatted_message, \FILE_APPEND);
        }
    }
}
