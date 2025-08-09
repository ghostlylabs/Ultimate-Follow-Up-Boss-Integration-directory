<?php
/**
 * Simple Debug Panel - Actually Useful
 * Shows real errors and system status
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get real system status
global $wpdb;
$system_status = array();

// Check database tables
$events_table = $wpdb->prefix . 'fub_events';
$system_status['events_table'] = $wpdb->get_var("SHOW TABLES LIKE '$events_table'") === $events_table;
$system_status['events_count'] = $system_status['events_table'] ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $events_table") : 0;

// Check error log
$error_log = UFUB_PLUGIN_DIR . 'emergency-errors.log';
$system_status['error_log_exists'] = file_exists($error_log);
$system_status['error_log_size'] = $system_status['error_log_exists'] ? filesize($error_log) : 0;

// Check API key
$system_status['api_key_set'] = !empty(get_option('ufub_api_key'));

// Check WPL integration
$wpl_table = $wpdb->prefix . 'wpl_properties';
$system_status['wpl_available'] = $wpdb->get_var("SHOW TABLES LIKE '$wpl_table'") === $wpl_table;
$system_status['wpl_properties'] = $system_status['wpl_available'] ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $wpl_table") : 0;

// Get recent errors
$recent_errors = array();
if ($system_status['error_log_exists']) {
    $log_content = file_get_contents($error_log);
    $lines = explode("\n", $log_content);
    $recent_errors = array_slice($lines, -20);
}
?>

<link rel="stylesheet" href="<?php echo UFUB_PLUGIN_URL; ?>assets/css/ghostly-theme.css">

<div class="ghostly-premium-container">
    <div class="ghostly-premium-header">
        <h1 class="ghostly-premium-title">Debug Panel - Ghostly Labs</h1>
        <p style="color: rgba(255, 255, 255, 0.8);">Simple, functional debugging for logged-in user issues</p>
    </div>

    <!-- System Status -->
    <div class="ghostly-card">
        <h3 style="color: #3ab24a; margin-bottom: 20px;">System Status</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <p><strong>Events Table:</strong> <?php echo $system_status['events_table'] ? '✅ Exists' : '❌ Missing'; ?></p>
                <p><strong>Total Events:</strong> <?php echo number_format($system_status['events_count']); ?></p>
                <p><strong>API Key:</strong> <?php echo $system_status['api_key_set'] ? '✅ Set' : '❌ Not Set'; ?></p>
            </div>
            <div>
                <p><strong>WPL Integration:</strong> <?php echo $system_status['wpl_available'] ? '✅ Available' : '❌ Not Available'; ?></p>
                <p><strong>WPL Properties:</strong> <?php echo number_format($system_status['wpl_properties']); ?></p>
                <p><strong>Error Log:</strong> <?php echo $system_status['error_log_exists'] ? '✅ Active' : '❌ None'; ?></p>
            </div>
        </div>
    </div>

    <!-- Live Error Test -->
    <div class="ghostly-card">
        <h3 style="color: #3ab24a; margin-bottom: 20px;">Test Logged-In User Functions</h3>
        <p style="color: #ffffff; margin-bottom: 15px;">Test the functions that are causing 500 errors:</p>
        
        <div style="background: #000; padding: 15px; border-radius: 8px; color: #00ff00; font-family: monospace;">
            <?php
            // Test the problematic functions
            echo "Testing get_current_user_id(): ";
            try {
                $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 'FUNCTION_NOT_EXISTS';
                echo "✅ Result: " . $user_id . "\n";
            } catch (Exception $e) {
                echo "❌ ERROR: " . $e->getMessage() . "\n";
            }
            
            echo "\nTesting is_user_logged_in(): ";
            try {
                $logged_in = function_exists('is_user_logged_in') ? (is_user_logged_in() ? 'YES' : 'NO') : 'FUNCTION_NOT_EXISTS';
                echo "✅ Result: " . $logged_in . "\n";
            } catch (Exception $e) {
                echo "❌ ERROR: " . $e->getMessage() . "\n";
            }
            
            echo "\nTesting WordPress functions: ";
            $wp_functions = array('current_time', 'sanitize_text_field', 'wp_json_encode');
            foreach ($wp_functions as $func) {
                echo "\n  {$func}: " . (function_exists($func) ? '✅' : '❌');
            }
            ?>
        </div>
    </div>

    <!-- Recent Errors -->
    <?php if (!empty($recent_errors)): ?>
    <div class="ghostly-card">
        <h3 style="color: #dc3232; margin-bottom: 20px;">Recent Errors (Last 20 lines)</h3>
        <pre style="background: #000; padding: 15px; color: #ff6b6b; border-radius: 8px; max-height: 400px; overflow-y: auto; font-size: 12px;"><?php 
        echo htmlspecialchars(implode("\n", $recent_errors)); 
        ?></pre>
        
        <form method="post" style="margin-top: 15px;">
            <button type="submit" name="clear_errors" class="ghostly-premium-button" style="background: #dc3232;">Clear Error Log</button>
        </form>
        
        <?php
        if (isset($_POST['clear_errors'])) {
            file_put_contents($error_log, "UFUB Error Log Cleared: " . date('Y-m-d H:i:s') . "\n");
            echo '<p style="color: #3ab24a; margin-top: 15px;">✅ Error log cleared!</p>';
        }
        ?>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="ghostly-card">
        <h3 style="color: #3ab24a; margin-bottom: 20px;">Quick Actions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <a href="<?php echo admin_url('admin.php?page=ufub-emergency-errors'); ?>" class="ghostly-premium-button">View Full Error Log</a>
            <a href="<?php echo admin_url('admin.php?page=ufub-settings'); ?>" class="ghostly-premium-button">Plugin Settings</a>
            <a href="<?php echo admin_url('admin.php?page=ufub-property-matching'); ?>" class="ghostly-premium-button">Property Matching</a>
        </div>
    </div>
</div>
