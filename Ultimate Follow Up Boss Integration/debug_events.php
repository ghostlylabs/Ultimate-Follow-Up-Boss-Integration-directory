<?php
/**
 * Debug script to check what events are in the database
 * Run this from WordPress admin or via WP-CLI
 */

// WordPress environment
define('WP_USE_THEMES', false);
require_once('../../../wp-config.php');

global $wpdb;

echo "🔍 DEBUGGING FUB EVENTS DATABASE\n";
echo "=====================================\n\n";

// Check if table exists
$events_table = $wpdb->prefix . 'fub_events';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$events_table'") == $events_table;

echo "📊 Table Status:\n";
echo "Table: $events_table\n";
echo "Exists: " . ($table_exists ? "✅ YES" : "❌ NO") . "\n\n";

if ($table_exists) {
    // Get total count
    $total_events = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");
    echo "📈 Total Events: $total_events\n\n";
    
    // Get event types and counts
    echo "📋 Event Types Breakdown:\n";
    $event_types = $wpdb->get_results("
        SELECT event_type, COUNT(*) as count 
        FROM $events_table 
        GROUP BY event_type 
        ORDER BY count DESC
    ");
    
    foreach ($event_types as $type) {
        echo "  • {$type->event_type}: {$type->count} events\n";
    }
    
    echo "\n";
    
    // Get recent events (last 10)
    echo "🕒 Recent Events (Last 10):\n";
    $recent_events = $wpdb->get_results("
        SELECT event_type, event_data, created_at 
        FROM $events_table 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    foreach ($recent_events as $event) {
        $data = json_decode($event->event_data, true);
        $summary = isset($data['user_email']) ? $data['user_email'] : 
                  (isset($data['page_url']) ? basename($data['page_url']) : 'No summary');
        echo "  • {$event->created_at} | {$event->event_type} | $summary\n";
    }
    
    echo "\n";
    
    // Check for user registrations specifically
    $user_regs = $wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE event_type = 'user_registration'");
    echo "👥 User Registration Events: $user_regs\n";
    
    // Check for property/search events
    $search_events = $wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE event_type LIKE '%search%' OR event_type LIKE '%property%'");
    echo "🏠 Property/Search Events: $search_events\n";
    
    // Check for FUB sync events
    $fub_events = $wpdb->get_var("SELECT COUNT(*) FROM $events_table WHERE event_type LIKE '%fub%'");
    echo "🔄 FUB Sync Events: $fub_events\n";
}

echo "\n=====================================\n";
echo "Debug completed!\n";
?>
