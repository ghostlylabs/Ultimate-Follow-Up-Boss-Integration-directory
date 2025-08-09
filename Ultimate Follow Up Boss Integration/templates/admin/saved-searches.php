<?php
/**
 * Saved Searches Admin Template
 * Ultimate Follow Up Boss Integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Templates
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define debug and version constants if not already defined
if (!defined('UFUB_DEBUG')) {
    define('UFUB_DEBUG', false);
}
if (!defined('UFUB_VERSION')) {
    define('UFUB_VERSION', '2.1.2');
}
// --- WordPress function stubs for IntelliSense (not executed in production) ---
if (php_sapi_name() === 'cli') {
    // Skip stubs in CLI context
} elseif (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}
if (!function_exists('wp_die')) {
    function wp_die($message) { exit($message); }
}
if (!function_exists('get_option')) {
    function get_option($name, $default = false) { return $default; }
}
if (!function_exists('update_option')) {
    function update_option($name, $value) { return true; }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) { return true; }
}
if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action, $name) { echo "<input type='hidden' name='".esc_attr($name)."' value='dummy_nonce' />"; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return $str; }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return $str; }
}
if (!function_exists('esc_html')) {
    function esc_html($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return '/wp-admin/' . ltrim($path, '/'); }
}
if (!function_exists('current_time')) {
    function current_time($type) { return date('Y-m-d H:i:s'); }
}
if (!function_exists('human_time_diff')) {
    function human_time_diff($from, $to) { $diff = abs($to - $from); return round($diff/3600) . ' hours'; }
}
if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return 1; }
}
// --- End WordPress stubs ---

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Component health checking with error handling
$component_health = array();
$error_messages = array();
$saved_searches = array();
$component_available = true; 
$health_status = 'unknown'; // Will be determined by actual checks

// Test component health by checking if AJAX system works
try {
    // Check if the main plugin class exists
    if (!class_exists('Ultimate_FUB_Integration')) {
        throw new Exception('Main plugin class not found');
    }
    
    // Check if AJAX endpoints are registered
    $ajax_actions = array('ufub_save_searches', 'ufub_track_event');
    $missing_actions = array();
    
    foreach ($ajax_actions as $action) {
        if (!has_action("wp_ajax_$action")) {
            $missing_actions[] = $action;
        }
    }
    
    if (!empty($missing_actions)) {
        throw new Exception('Missing AJAX actions: ' . implode(', ', $missing_actions));
    }
    
    // Check database connectivity
    global $wpdb;
    $events_table = $wpdb->prefix . 'fub_events';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$events_table'") === $events_table;
    
    if (!$table_exists) {
        $health_status = 'warning';
        $error_messages[] = 'Events table not found. Some features may not work.';
    } else {
        $health_status = 'healthy';
    }
    
    // Load saved searches
    $saved_searches = get_option('ufub_saved_searches', array());
    
    // Generate some sample data if none exists
    if (empty($saved_searches)) {
        $saved_searches = array(
            'search_1' => array(
                'name' => 'Downtown Properties',
                'criteria' => 'Price: $200,000 - $500,000, Location: Downtown',
                'active' => true,
                'created' => current_time('mysql'),
                'last_results' => 15,
                'notifications' => 3
            ),
            'search_2' => array(
                'name' => 'Luxury Homes',
                'criteria' => 'Price: $800,000+, Bedrooms: 4+, Property Type: House',
                'active' => true,
                'created' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'last_results' => 8,
                'notifications' => 1
            )
        );
    }
    
    $component_health = array(
        'status' => $health_status,
        'message' => $health_status === 'healthy' ? 'All systems operational' : 'Some issues detected',
        'total_searches' => count($saved_searches),
        'active_searches' => count(array_filter($saved_searches, function($s) { return $s['active'] ?? false; })),
        'ajax_endpoints' => count($ajax_actions) - count($missing_actions) . '/' . count($ajax_actions),
        'database_status' => $table_exists ? 'Connected' : 'Warning'
    );
    
} catch (Exception $e) {
    $error_messages[] = 'System error: ' . $e->getMessage();
    $health_status = 'error';
    $component_available = false;
    error_log('UFUB Saved Searches Error: ' . $e->getMessage());
}

// Handle form submissions with error handling
$action_message = '';
if (isset($_POST['action']) && wp_verify_nonce($_POST['nonce'], 'ufub_saved_searches_action')) {
    try {
        switch ($_POST['action']) {
            case 'create_search':
                if ($component_available) {
                    $search_name = sanitize_text_field($_POST['search_name'] ?? '');
                    $search_criteria = sanitize_textarea_field($_POST['search_criteria'] ?? '');
                    
                    if (empty($search_name) || empty($search_criteria)) {
                        throw new Exception('Search name and criteria are required.');
                    }
                    
                    // Use direct option storage
                    $searches = get_option('ufub_saved_searches', array());
                    $search_id = 'search_' . time();
                    $searches[$search_id] = array(
                        'name' => $search_name,
                        'criteria' => $search_criteria,
                        'created' => current_time('mysql'),
                        'active' => true,
                        'last_results' => 0,
                        'notifications' => 0
                    );
                    update_option('ufub_saved_searches', $searches);
                    $action_message = '<div class="ghostly-success">✅ Saved search created successfully!</div>';
                    $saved_searches = $searches;
                } else {
                    throw new Exception('Saved Searches component not available.');
                }
                break;
                
            case 'delete_search':
                if ($component_available && isset($saved_searches_manager)) {
                    $search_id = sanitize_text_field($_POST['search_id'] ?? '');
                    
                    if (empty($search_id)) {
                        throw new Exception('Search ID is required.');
                    }
                    
                    if (method_exists($saved_searches_manager, 'delete_search')) {
                        $result = $saved_searches_manager->delete_search($search_id);
                        if ($result) {
                            $action_message = '<div class="ghostly-success">✅ Saved search deleted successfully!</div>';
                            // Refresh data
                            $saved_searches = $saved_searches_manager->get_all_searches();
                        } else {
                            throw new Exception('Failed to delete saved search.');
                        }
                    } else {
                        // Fallback implementation
                        $searches = get_option('ufub_saved_searches', array());
                        if (isset($searches[$search_id])) {
                            unset($searches[$search_id]);
                            update_option('ufub_saved_searches', $searches);
                            $action_message = '<div class="ghostly-success">✅ Saved search deleted successfully!</div>';
                            $saved_searches = $searches;
                        } else {
                            throw new Exception('Saved search not found.');
                        }
                    }
                } else {
                    throw new Exception('Saved Searches component not available.');
                }
                break;
                
            case 'toggle_search':
                if ($component_available && isset($saved_searches_manager)) {
                    $search_id = sanitize_text_field($_POST['search_id'] ?? '');
                    
                    if (method_exists($saved_searches_manager, 'toggle_search')) {
                        $result = $saved_searches_manager->toggle_search($search_id);
                        if ($result) {
                            $action_message = '<div class="ghostly-success">✅ Search status updated successfully!</div>';
                            $saved_searches = $saved_searches_manager->get_all_searches();
                        } else {
                            throw new Exception('Failed to toggle search status.');
                        }
                    } else {
                        // Fallback implementation
                        $searches = get_option('ufub_saved_searches', array());
                        if (isset($searches[$search_id])) {
                            $searches[$search_id]['active'] = !($searches[$search_id]['active'] ?? true);
                            update_option('ufub_saved_searches', $searches);
                            $action_message = '<div class="ghostly-success">✅ Search status updated successfully!</div>';
                            $saved_searches = $searches;
                        }
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $action_message = '<div class="ghostly-error">❌ Error: ' . esc_html($e->getMessage()) . '</div>';
        error_log('UFUB Saved Searches Action Error: ' . $e->getMessage());
    }
}

// Get statistics with error handling
$search_stats = array(
    'total_searches' => count($saved_searches),
    'active_searches' => 0,
    'inactive_searches' => 0,
    'last_updated' => 'Unknown'
);

if (!empty($saved_searches)) {
    foreach ($saved_searches as $search) {
        if (isset($search['active']) && $search['active']) {
            $search_stats['active_searches']++;
        } else {
            $search_stats['inactive_searches']++;
        }
    }
    
    // Get last updated time
    $latest_time = 0;
    foreach ($saved_searches as $search) {
        if (isset($search['created'])) {
            $time = strtotime($search['created']);
            if ($time > $latest_time) {
                $latest_time = $time;
            }
        }
    }
    if ($latest_time > 0) {
        $search_stats['last_updated'] = human_time_diff($latest_time, current_time('timestamp')) . ' ago';
    }
}
?>

<div class="wrap ghostly-container">
    <div class="ghostly-header-section" style="background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2d2d2d 100%); padding: 30px; margin: -20px -20px 20px -20px; border-radius: 0 0 15px 15px;">
        <h1 class="ghostly-header" style="color: #ffffff; font-size: 2.2em; margin: 0; display: flex; align-items: center; gap: 15px;">
            <span class="dashicons dashicons-search" style="color: #3ab24a; font-size: 1.2em; animation: ghostly-glow 2s infinite alternate;"></span>
            Ghostly Labs Saved Searches
            <span class="ghostly-status-indicator" style="background: <?php echo $health_status === 'healthy' ? '#3ab24a' : ($health_status === 'warning' ? '#ffb900' : '#dc3232'); ?>; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.4em; font-weight: 500; margin-left: auto;">
                <?php
                if ($health_status === 'healthy') {
                    echo '✅ System Active';
                } elseif ($health_status === 'warning') {
                    echo '⚠️ Partial Function';
                } else {
                    echo '❌ System Error';
                }
                ?>
            </span>
        </h1>
        <p style="color: #ffffff; opacity: 0.8; margin: 8px 0 0 0; font-size: 1.1em;">
            Intelligent Search Management • Auto-Saved Criteria • Real Estate Lead Automation
        </p>
    </div>
    
    <?php echo $action_message; ?>
    
    <?php if (!empty($error_messages)): ?>
        <div class="ghostly-card" style="background: rgba(220, 50, 50, 0.1); border: 1px solid rgba(220, 50, 50, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 2em;">⚠️</span>
                <div>
                    <h3 style="color: #dc3232; margin: 0 0 10px 0;">Component Issues Detected</h3>
                    <?php foreach ($error_messages as $error): ?>
                        <p style="color: #ffffff; margin: 5px 0; opacity: 0.9;">• <?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                    <p style="color: #ffffff; margin: 10px 0 0 0; opacity: 0.7; font-size: 0.9em;">
                        The system will operate in fallback mode. Some features may be limited.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="ufub-saved-searches-container" style="max-width: 1400px;">
        
        <!-- Saved Searches Statistics -->
        <div class="ghostly-card" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 25px;">
            <h2 class="ghostly-header" style="color: #ffffff; margin: 0 0 25px 0; display: flex; align-items: center; gap: 12px;">
                <span class="dashicons dashicons-chart-bar" style="color: #3ab24a; font-size: 1.3em;"></span>
                Search Analytics Overview
            </h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3); transition: transform 0.3s ease;">
                    <div style="font-size: 2.5em; margin-bottom: 12px;">🔍</div>
                    <div style="font-size: 2.4em; font-weight: bold; margin-bottom: 8px;"><?php echo $search_stats['total_searches']; ?></div>
                    <div style="font-size: 1.1em; opacity: 0.9;">Total Saved Searches</div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 8px 25px rgba(67, 233, 123, 0.3); transition: transform 0.3s ease;">
                    <div style="font-size: 2.5em; margin-bottom: 12px;">✅</div>
                    <div style="font-size: 2.4em; font-weight: bold; margin-bottom: 8px;"><?php echo $search_stats['active_searches']; ?></div>
                    <div style="font-size: 1.1em; opacity: 0.9;">Active Searches</div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 8px 25px rgba(240, 147, 251, 0.3); transition: transform 0.3s ease;">
                    <div style="font-size: 2.5em; margin-bottom: 12px;">⏸️</div>
                    <div style="font-size: 2.4em; font-weight: bold; margin-bottom: 8px;"><?php echo $search_stats['inactive_searches']; ?></div>
                    <div style="font-size: 1.1em; opacity: 0.9;">Inactive Searches</div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 8px 25px rgba(79, 172, 254, 0.3); transition: transform 0.3s ease;">
                    <div style="font-size: 2.5em; margin-bottom: 12px;">🕒</div>
                    <div style="font-size: 1.4em; font-weight: bold; margin-bottom: 8px;"><?php echo $search_stats['last_updated']; ?></div>
                    <div style="font-size: 1.1em; opacity: 0.9;">Last Updated</div>
                </div>
            </div>
        </div>

        <!-- Create New Saved Search -->
        <div class="ghostly-card" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 25px;">
            <h2 class="ghostly-header" style="color: #ffffff; margin: 0 0 20px 0; display: flex; align-items: center; gap: 12px;">
                <span class="dashicons dashicons-plus-alt" style="color: #3ab24a; font-size: 1.3em;"></span>
                Create New Saved Search
            </h2>
            
            <form method="post" style="max-width: 800px;">
                <?php wp_nonce_field('ufub_saved_searches_action', 'nonce'); ?>
                <input type="hidden" name="action" value="create_search">
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                    <div>
                        <label for="search_name" style="color: #ffffff; font-weight: 600; display: block; margin-bottom: 8px;">
                            Search Name <span style="color: #3ab24a;">*</span>
                        </label>
                        <input type="text" 
                               id="search_name" 
                               name="search_name" 
                               required
                               style="width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; padding: 12px 16px; color: #ffffff;" 
                               placeholder="Enter a descriptive name for this search...">
                    </div>
                    
                    <div>
                        <label for="search_criteria" style="color: #ffffff; font-weight: 600; display: block; margin-bottom: 8px;">
                            Search Criteria <span style="color: #3ab24a;">*</span>
                        </label>
                        <textarea id="search_criteria" 
                                  name="search_criteria" 
                                  required
                                  rows="4"
                                  style="width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; padding: 12px 16px; color: #ffffff; resize: vertical;" 
                                  placeholder="Define your search criteria (price range, location, property type, etc.)..."></textarea>
                        <p style="color: #ffffff; opacity: 0.7; margin: 8px 0 0 0; font-size: 0.9em;">
                            Example: "3+ bedrooms, $300k-500k, Downtown area, Single family homes"
                        </p>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="submit" 
                                class="ghostly-button" 
                                style="background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%); color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                            <span style="margin-right: 8px;">💾</span>
                            Create Saved Search
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Saved Searches List -->
        <div class="ghostly-card" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 25px;">
            <h2 class="ghostly-header" style="color: #ffffff; margin: 0 0 25px 0; display: flex; align-items: center; gap: 12px;">
                <span class="dashicons dashicons-list-view" style="color: #3ab24a; font-size: 1.3em;"></span>
                Manage Saved Searches
            </h2>
            
            <?php if (!empty($saved_searches)): ?>
                <div style="display: grid; gap: 20px;">
                    <?php foreach ($saved_searches as $search_id => $search_data): ?>
                        <?php
                        $is_active = $search_data['active'] ?? true;
                        $created_date = isset($search_data['created']) ? date('M j, Y', strtotime($search_data['created'])) : 'Unknown';
                        ?>
                        <div class="search-item" style="background: rgba(255, 255, 255, 0.05); border-left: 4px solid <?php echo $is_active ? '#3ab24a' : '#ffb900'; ?>; border-radius: 10px; padding: 20px; transition: all 0.3s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <div style="flex: 1;">
                                    <h3 style="color: #ffffff; margin: 0 0 8px 0; font-size: 1.2em;">
                                        <?php echo esc_html($search_id); ?>
                                    </h3>
                                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                                        <span style="background: <?php echo $is_active ? '#3ab24a' : '#ffb900'; ?>; color: white; padding: 4px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 500;">
                                            <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <span style="color: #ffffff; opacity: 0.7; font-size: 0.9em;">
                                            Created: <?php echo $created_date; ?>
                                        </span>
                                    </div>
                                    <p style="color: #ffffff; opacity: 0.8; margin: 0; line-height: 1.5;">
                                        <?php echo esc_html($search_data['criteria'] ?? 'No criteria specified'); ?>
                                    </p>
                                </div>
                                
                                <div style="display: flex; gap: 10px; margin-left: 20px;">
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('ufub_saved_searches_action', 'nonce'); ?>
                                        <input type="hidden" name="action" value="toggle_search">
                                        <input type="hidden" name="search_id" value="<?php echo esc_attr($search_id); ?>">
                                        <button type="submit" 
                                                class="ghostly-button" 
                                                style="background: <?php echo $is_active ? 'rgba(255, 185, 0, 0.8)' : 'rgba(67, 233, 123, 0.8)'; ?>; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.9em; cursor: pointer;">
                                            <?php echo $is_active ? '⏸️ Pause' : '▶️ Activate'; ?>
                                        </button>
                                    </form>
                                    
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this saved search?');">
                                        <?php wp_nonce_field('ufub_saved_searches_action', 'nonce'); ?>
                                        <input type="hidden" name="action" value="delete_search">
                                        <input type="hidden" name="search_id" value="<?php echo esc_attr($search_id); ?>">
                                        <button type="submit" 
                                                class="ghostly-button" 
                                                style="background: rgba(220, 50, 50, 0.8); color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.9em; cursor: pointer;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #ffffff; opacity: 0.7;">
                    <div style="font-size: 4em; margin-bottom: 20px;">🔍</div>
                    <h3 style="color: #ffffff; margin: 0 0 15px 0;">No Saved Searches Yet</h3>
                    <p style="margin: 0 0 25px 0; font-size: 1.1em;">Create your first saved search to start automating your lead generation process.</p>
                    <button onclick="document.getElementById('search_name').focus();" 
                            class="ghostly-button" 
                            style="background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%); color: white; padding: 15px 30px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                        <span style="margin-right: 8px;">➕</span>
                        Create First Search
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Component Health Status -->
        <?php if ($component_available && !empty($component_health)): ?>
        <div class="ghostly-card" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 25px;">
            <h2 class="ghostly-header" style="color: #ffffff; margin: 0 0 20px 0; display: flex; align-items: center; gap: 12px;">
                <span class="dashicons dashicons-admin-tools" style="color: #3ab24a; font-size: 1.3em;"></span>
                System Health Status
            </h2>
            
            <div style="background: rgba(<?php echo $health_status === 'healthy' ? '67, 233, 123' : ($health_status === 'warning' ? '255, 185, 0' : '220, 50, 50'); ?>, 0.1); border: 1px solid rgba(<?php echo $health_status === 'healthy' ? '67, 233, 123' : ($health_status === 'warning' ? '255, 185, 0' : '220, 50, 50'); ?>, 0.3); border-radius: 10px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 2em;">
                        <?php echo $health_status === 'healthy' ? '✅' : ($health_status === 'warning' ? '⚠️' : '❌'); ?>
                    </span>
                    <div>
                        <h4 style="color: <?php echo $health_status === 'healthy' ? '#43e97b' : ($health_status === 'warning' ? '#ffb900' : '#dc3232'); ?>; margin: 0 0 5px 0;">
                            Saved Searches Component: <?php echo strtoupper($health_status); ?>
                        </h4>
                        <p style="color: #ffffff; margin: 0; opacity: 0.9;">
                            <?php 
                            if (isset($component_health['message'])) {
                                echo esc_html($component_health['message']);
                            } else {
                                echo $health_status === 'healthy' ? 'All systems operational' : 'System experiencing issues';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="ghostly-card" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 25px;">
            <h2 class="ghostly-header" style="color: #ffffff; margin: 0 0 20px 0; display: flex; align-items: center; gap: 12px;">
                <span class="dashicons dashicons-admin-generic" style="color: #3ab24a; font-size: 1.3em;"></span>
                Quick Actions
            </h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <a href="<?php echo admin_url('admin.php?page=ufub-dashboard'); ?>" 
                   class="quick-action-card" 
                   style="display: flex; flex-direction: column; align-items: center; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; text-align: center;">
                    <span style="font-size: 2.5em; margin-bottom: 12px;">📊</span>
                    <strong style="font-size: 1.1em; margin-bottom: 6px;">Dashboard</strong>
                    <small style="opacity: 0.9;">System overview</small>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=ufub-settings'); ?>" 
                   class="quick-action-card"
                   style="display: flex; flex-direction: column; align-items: center; padding: 25px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; text-align: center;">
                    <span style="font-size: 2.5em; margin-bottom: 12px;">⚙️</span>
                    <strong style="font-size: 1.1em; margin-bottom: 6px;">Settings</strong>
                    <small style="opacity: 0.9;">Configuration</small>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=ufub-property-matching'); ?>" 
                   class="quick-action-card"
                   style="display: flex; flex-direction: column; align-items: center; padding: 25px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; text-align: center;">
                    <span style="font-size: 2.5em; margin-bottom: 12px;">🏠</span>
                    <strong style="font-size: 1.1em; margin-bottom: 6px;">Property Matching</strong>
                    <small style="opacity: 0.9;">Configure matching</small>
                </a>
                
                <?php if (defined('UFUB_DEBUG') && UFUB_DEBUG): ?>
                <a href="<?php echo admin_url('admin.php?page=ufub-debug'); ?>" 
                   class="quick-action-card"
                   style="display: flex; flex-direction: column; align-items: center; padding: 25px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; text-align: center;">
                    <span style="font-size: 2.5em; margin-bottom: 12px;">🔧</span>
                    <strong style="font-size: 1.1em; margin-bottom: 6px;">Debug Panel</strong>
                    <small style="opacity: 0.9;">System diagnostics</small>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// WordPress admin variables for AJAX
window.ufub_admin = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('ufub_admin_nonce'); ?>',
    debug: <?php echo UFUB_DEBUG ? 'true' : 'false'; ?>
};

// Enhanced JavaScript for Saved Searches interface
document.addEventListener('DOMContentLoaded', function() {
    // Load the Ghostly Labs Saved Searches JavaScript
    if (typeof jQuery !== 'undefined') {
        // jQuery is available, load our enhanced script
        const scriptUrl = '<?php echo plugins_url("assets/js/saved-searches.js", dirname(__DIR__)); ?>';
        
        // Load the script dynamically
        const script = document.createElement('script');
        script.src = scriptUrl;
        script.onload = function() {
            console.log('✅ Ghostly Labs Saved Searches Manager loaded successfully');
        };
        script.onerror = function() {
            console.warn('⚠️ Could not load enhanced saved searches script, using fallback');
            initializeFallbackInterface();
        };
        document.head.appendChild(script);
    } else {
        console.warn('⚠️ jQuery not available, using fallback interface');
        initializeFallbackInterface();
    }
    
    // Fallback interface for basic functionality
    function initializeFallbackInterface() {
        // Stat card hover effects
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
                this.style.filter = 'brightness(1.1)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.filter = 'brightness(1)';
            });
        });
        
        // Search item hover effects
        const searchItems = document.querySelectorAll('.search-item');
        searchItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.background = 'rgba(255, 255, 255, 0.08)';
                this.style.transform = 'translateX(5px)';
            });
            item.addEventListener('mouseleave', function() {
                this.style.background = 'rgba(255, 255, 255, 0.05)';
                this.style.transform = 'translateX(0)';
            });
        });
        
        // Quick action card hover effects
        const actionCards = document.querySelectorAll('.quick-action-card');
        actionCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.05)';
                this.style.boxShadow = '0 12px 35px rgba(0, 0, 0, 0.3)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '';
            });
        });
        
        // Form validation
        const form = document.querySelector('form[method="post"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                const searchName = document.getElementById('search_name');
                const searchCriteria = document.getElementById('search_criteria');
                
                if (searchName && searchName.value.trim().length < 3) {
                    e.preventDefault();
                    alert('Search name must be at least 3 characters long.');
                    searchName.focus();
                    return false;
                }
                
                if (searchCriteria && searchCriteria.value.trim().length < 10) {
                    e.preventDefault();
                    alert('Search criteria must be at least 10 characters long.');
                    searchCriteria.focus();
                    return false;
                }
            });
        }
        
        console.log('✅ Ghostly Labs Saved Searches Fallback Interface Loaded');
    }
});
</script>

<style>
/* Ghostly Labs Premium Styling for Saved Searches */
.ghostly-container {
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2d2d2d 100%);
    min-height: 100vh;
    padding: 0;
}

.ghostly-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    transition: all 0.3s ease;
}

.ghostly-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(58, 178, 74, 0.3);
    box-shadow: 0 8px 32px rgba(58, 178, 74, 0.1);
}

.ghostly-header {
    color: #ffffff;
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.ghostly-button {
    background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%);
    border: none;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.ghostly-button:hover {
    background: linear-gradient(135deg, #2ea040 0%, #2d8f3a 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(58, 178, 74, 0.4);
    color: white;
    text-decoration: none;
}

.ghostly-success {
    background: rgba(67, 233, 123, 0.1);
    border: 1px solid rgba(67, 233, 123, 0.3);
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    color: #43e97b;
    font-weight: 500;
}

.ghostly-error {
    background: rgba(220, 50, 50, 0.1);
    border: 1px solid rgba(220, 50, 50, 0.3);
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    color: #dc3232;
    font-weight: 500;
}

/* Premium animations */
@keyframes ghostly-glow {
    0%, 100% { 
        text-shadow: 0 0 5px #3ab24a, 0 0 10px #3ab24a, 0 0 15px #3ab24a;
    }
    50% { 
        text-shadow: 0 0 10px #3ab24a, 0 0 20px #3ab24a, 0 0 30px #3ab24a;
    }
}

/* Form styling */
input[type="text"], textarea, select {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: #ffffff;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

input[type="text"]:focus, textarea:focus, select:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: #3ab24a;
    box-shadow: 0 0 0 3px rgba(58, 178, 74, 0.2);
    outline: none;
}

input[type="text"]::placeholder, textarea::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

/* Responsive design */
@media (max-width: 768px) {
    .ghostly-container {
        padding: 10px;
    }
    
    .ghostly-card {
        margin-bottom: 15px;
        padding: 15px;
    }
    
    .ghostly-header-section {
        margin: -10px -10px 15px -10px !important;
        padding: 20px !important;
    }
    
    .ghostly-header h1 {
        font-size: 1.5em !important;
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .ghostly-status-indicator {
        margin-left: 0 !important;
        margin-top: 10px;
    }
}

/* Custom scrollbars */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #2ea040 0%, #2d8f3a 100%);
}
</style>

<!-- Footer -->
<div class="ghostly-footer" style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid rgba(255, 255, 255, 0.1); margin-top: 40px; padding: 25px; text-align: center;">
    <p style="color: #ffffff; opacity: 0.6; margin: 0; font-size: 0.9em;">
        <span style="font-size: 1.2em;">GL</span>
        <strong style="color: #3ab24a;">Ghostly Labs</strong> 
        Ultimate Follow Up Boss Integration v<?php echo esc_html(defined('UFUB_VERSION') ? UFUB_VERSION : '2.1.2'); ?>
        • Saved Searches Management
    </p>
</div>

<?php
// Clean up variables
unset($component_health, $error_messages, $saved_searches, $search_stats);

// Log page access
if (function_exists('ufub_log_info')) {
    ufub_log_info('Saved Searches page accessed', array(
        'user_id' => get_current_user_id(),
        'component_available' => $component_available,
        'health_status' => $health_status,
        'total_searches' => count($saved_searches ?? array())
    ));
}
?>