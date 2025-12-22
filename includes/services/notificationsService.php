<?php
/**
 * NotificationsService
 * 
 * Service for handling notifications-related operations.
 * Provides AJAX endpoints for retrieving unread notifications data.
 * 
 * @package WP_Agenda_Automatizada
 */

if (!defined('ABSPATH')) exit;

/**
 * Register AJAX endpoint for getting unread notifications count
 */
add_action('wp_ajax_aa_get_unread_notifications', 'aa_ajax_get_unread_notifications');

/**
 * AJAX handler: Get unread notifications count
 * 
 * Returns JSON with:
 * - total: total count of unread notifications
 * - by_type: object with counts grouped by type
 * 
 * @return void Sends JSON response via wp_send_json_success()
 */
function aa_ajax_get_unread_notifications() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'aa_notifications';
    
    // Verify table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    
    if (!$table_exists) {
        wp_send_json_success([
            'total' => 0,
            'by_type' => []
        ]);
    }
    
    // Query: Get counts grouped by type for unread notifications
    $results = $wpdb->get_results(
        "SELECT type, COUNT(*) as count 
         FROM $table 
         WHERE is_read = 0 
         GROUP BY type",
        ARRAY_A
    );
    
    // Build by_type object and calculate total
    $by_type = [];
    $total = 0;
    
    if ($results && is_array($results)) {
        foreach ($results as $row) {
            $type = sanitize_key($row['type']);
            $count = intval($row['count']);
            $by_type[$type] = $count;
            $total += $count;
        }
    }
    
    // Return response
    wp_send_json_success([
        'total' => $total,
        'by_type' => $by_type
    ]);
}

