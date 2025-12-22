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

/**
 * Register AJAX endpoint for marking notifications as read by type
 */
add_action('wp_ajax_aa_mark_notifications_as_read', 'aa_ajax_mark_notifications_as_read');

/**
 * AJAX handler: Mark notifications as read by type
 * 
 * Updates is_read = 1 for all unread notifications of a specific type.
 * 
 * @return void Sends JSON response via wp_send_json_success()
 */
function aa_ajax_mark_notifications_as_read() {
    global $wpdb;
    
    // Get and validate type parameter
    $type = isset($_REQUEST['type']) ? sanitize_key($_REQUEST['type']) : '';
    
    if (empty($type)) {
        wp_send_json_error(['message' => 'Type parameter is required']);
        return;
    }
    
    // Validate type value (whitelist allowed types)
    $allowed_types = ['pending', 'confirmed', 'cancelled'];
    if (!in_array($type, $allowed_types, true)) {
        wp_send_json_error(['message' => 'Invalid type parameter']);
        return;
    }
    
    $table = $wpdb->prefix . 'aa_notifications';
    
    // Verify table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    
    if (!$table_exists) {
        wp_send_json_error(['message' => 'Notifications table does not exist']);
        return;
    }
    
    // Update notifications: mark as read where is_read = 0 and type matches
    $result = $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table 
             SET is_read = 1 
             WHERE is_read = 0 AND type = %s",
            $type
        )
    );
    
    if ($result === false) {
        error_log("❌ Error marking notifications as read: " . $wpdb->last_error);
        wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
        return;
    }
    
    // Return success response
    wp_send_json_success();
}

