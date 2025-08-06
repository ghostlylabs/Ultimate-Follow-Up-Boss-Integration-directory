<?php
/**
 * FUB Debug System
 * 
 * Comprehensive debugging and logging system for Ultimate Follow Up Boss Integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Debug
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Debug {
    
    private static $instance = null;
    private $log_table;
    private $max_logs = 1000;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'ufub_debug_logs';
        
        // Only initialize if debug mode is enabled
        if (UFUB_DEBUG) {
            $this->init();
        }
    }
    
    /**
     * Initialize debug system
     */
    private function init() {
        // Hook into PHP errors
        set_error_handler(array($this, 'handle_php_error'));
        
        // Hook into WordPress actions
        add_action('wp_footer', array($this, 'output_debug_panel'));
        add_action('admin_footer', array($this, 'output_debug_panel'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_debug_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_debug_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_ufub_clear_debug_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_ufub_export_debug_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_ufub_log_js_error', array($this, 'ajax_log_js_error'));
        
        // Log plugin initialization
        $this->log('INFO', 'Debug system initialized', array(
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ));
    }
    
    /**
     * Log a message
     */
    public static function log($level, $message, $context = array()) {
        $instance = self::get_instance();
        $instance->write_log($level, $message, $context);
    }
    
    /**
     * Write log to database
     */
    private function write_log($level, $message, $context = array()) {
        global $wpdb;
        
        // Clean old logs if we're at the limit
        $this->cleanup_old_logs();
        
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $wpdb->insert(
            $this->log_table,
            array(
                'level' => strtoupper($level),
                'message' => $message,
                'context' => wp_json_encode($context),
                'user_id' => $user_id ?: null,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        // Also log to PHP error log for critical errors
        if (in_array(strtoupper($level), array('ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'))) {
            error_log("UFUB {$level}: {$message} " . wp_json_encode($context));
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Don't log if error reporting is off
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $level = 'ERROR';
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $level = 'ERROR';
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $level = 'WARNING';
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $level = 'NOTICE';
                break;
            default:
                $level = 'INFO';
        }
        
        // Only log if it's related to our plugin
        if (strpos($errfile, 'ultimate-fub-integration') !== false || 
            strpos($errstr, 'ufub') !== false || 
            strpos($errstr, 'FUB_') !== false) {
            
            $this->write_log($level, $errstr, array(
                'file' => $errfile,
                'line' => $errline,
                'errno' => $errno,
                'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
            ));
        }
        
        // Don't prevent the default PHP error handler
        return false;
    }
    
    /**
     * Get recent logs
     */
    public static function get_recent_logs($limit = 50) {
        $instance = self::get_instance();
        return $instance->fetch_logs($limit);
    }
    
    /**
     * Fetch logs from database
     */
    private function fetch_logs($limit = 50) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->log_table} ORDER BY timestamp DESC LIMIT %d",
            $limit
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get error count
     */
    public static function get_error_count($hours = 24) {
        $instance = self::get_instance();
        return $instance->count_errors($hours);
    }
    
    /**
     * Count errors in timeframe
     */
    private function count_errors($hours = 24) {
        global $wpdb;
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->log_table} WHERE level IN ('ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY') AND timestamp >= %s",
            $since
        ));
        
        return (int) $count;
    }
    
    /**
     * Enqueue debug assets
     */
    public function enqueue_debug_assets() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_enqueue_script(
            'ufub-debug',
            UFUB_PLUGIN_URL . 'assets/js/fub-debug.js',
            array('jquery'),
            UFUB_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ufub-debug',
            UFUB_PLUGIN_URL . 'assets/css/fub-debug.css',
            array(),
            UFUB_VERSION
        );
        
        wp_localize_script('ufub-debug', 'ufub_debug', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ufub_debug_nonce'),
            'debug_enabled' => UFUB_DEBUG,
            'is_admin' => is_admin()
        ));
    }
    
    /**
     * Output debug panel
     */
    public function output_debug_panel() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $error_count = $this->count_errors(1); // Last hour
        $recent_logs = $this->fetch_logs(10);
        
        ?>
        <div id="ufub-debug-panel" class="ufub-debug-panel" style="display: none;">
            <div class="ufub-debug-header">
                <h3>🔧 FUB Debug Panel</h3>
                <div class="ufub-debug-controls">
                    <span class="ufub-error-count" data-count="<?php echo $error_count; ?>">
                        <?php echo $error_count; ?> errors (1h)
                    </span>
                    <button id="ufub-clear-logs" class="button">Clear Logs</button>
                    <button id="ufub-export-logs" class="button">Export</button>
                    <button id="ufub-toggle-debug" class="button">Toggle</button>
                </div>
            </div>
            
            <div class="ufub-debug-content">
                <div class="ufub-debug-section">
                    <h4>System Info</h4>
                    <div id="ufub-system-info">
                        <div>Memory: <span id="memory-usage"><?php echo size_format(memory_get_usage(true)); ?></span></div>
                        <div>Peak Memory: <span id="memory-peak"><?php echo size_format(memory_get_peak_usage(true)); ?></span></div>
                        <div>API Status: <span id="api-status">Checking...</span></div>
                        <div>Tracking: <span id="tracking-status">Active</span></div>
                    </div>
                </div>
                
                <div class="ufub-debug-section">
                    <h4>Recent Logs</h4>
                    <div id="ufub-recent-logs">
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="log-entry log-<?php echo strtolower($log['level']); ?>" data-timestamp="<?php echo $log['timestamp']; ?>">
                                <span class="log-level"><?php echo $log['level']; ?></span>
                                <span class="log-time"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></span>
                                <span class="log-message"><?php echo esc_html($log['message']); ?></span>
                                <?php if ($log['context']): ?>
                                    <div class="log-context" style="display: none;">
                                        <pre><?php echo esc_html($log['context']); ?></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="ufub-debug-section">
                    <h4>Live Tracking Data</h4>
                    <div id="ufub-live-tracking">
                        <div>Session ID: <span id="session-id">Loading...</span></div>
                        <div>Property Views: <span id="property-views">0</span></div>
                        <div>Search Count: <span id="search-count">0</span></div>
                        <div>Engagement Score: <span id="engagement-score">0</span></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Debug keyboard shortcut help -->
        <div id="ufub-debug-help" style="display: none;">
            <div class="ufub-debug-overlay">
                <div class="ufub-debug-modal">
                    <h3>FUB Debug Shortcuts</h3>
                    <ul>
                        <li><kbd>Ctrl+Shift+D</kbd> - Toggle debug panel</li>
                        <li><kbd>Ctrl+Shift+E</kbd> - Export logs</li>
                        <li><kbd>Ctrl+Shift+C</kbd> - Clear logs</li>
                        <li><kbd>Ctrl+Shift+T</kbd> - Test API connection</li>
                    </ul>
                    <button onclick="document.getElementById('ufub-debug-help').style.display='none'">Close</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('ufub_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->log_table}");
        
        $this->write_log('INFO', 'Debug logs cleared by user', array(
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        ));
        
        wp_send_json_success('Logs cleared successfully');
    }
    
    /**
     * AJAX: Export logs
     */
    public function ajax_export_logs() {
        check_ajax_referer('ufub_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $logs = $this->fetch_logs(500); // Last 500 logs
        
        $export_data = array(
            'exported_at' => current_time('mysql'),
            'site_url' => home_url(),
            'plugin_version' => UFUB_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'logs' => $logs
        );
        
        wp_send_json_success($export_data);
    }
    
    /**
     * AJAX: Log JavaScript error
     */
    public function ajax_log_js_error() {
        check_ajax_referer('ufub_debug_nonce', 'nonce');
        
        $error_data = wp_unslash($_POST);
        
        $this->write_log('ERROR', 'JavaScript Error: ' . ($error_data['message'] ?? 'Unknown error'), array(
            'type' => 'javascript',
            'file' => $error_data['filename'] ?? '',
            'line' => $error_data['lineno'] ?? '',
            'column' => $error_data['colno'] ?? '',
            'stack' => $error_data['stack'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => $error_data['url'] ?? ''
        ));
        
        wp_send_json_success('Error logged');
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Clean up old logs
     */
    private function cleanup_old_logs() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
        
        if ($count > $this->max_logs) {
            $delete_count = $count - $this->max_logs;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->log_table} ORDER BY timestamp ASC LIMIT %d",
                $delete_count
            ));
        }
    }
    
    /**
     * Performance monitoring
     */
    public static function track_performance($operation, $start_time, $memory_start) {
        $execution_time = microtime(true) - $start_time;
        $memory_used = memory_get_usage() - $memory_start;
        
        self::log('PERFORMANCE', "Operation: {$operation}", array(
            'execution_time' => round($execution_time, 4),
            'memory_used' => $memory_used,
            'memory_peak' => memory_get_peak_usage(true)
        ));
        
        // Alert on slow operations
        if ($execution_time > 2.0) {
            self::log('WARNING', "Slow operation detected: {$operation}", array(
                'execution_time' => $execution_time,
                'threshold' => 2.0
            ));
        }
    }
    
    /**
     * API call tracking
     */
    public static function track_api_call($endpoint, $method, $response_code, $response_time, $request_data = array()) {
        $level = 'INFO';
        
        if ($response_code >= 400) {
            $level = 'ERROR';
        } elseif ($response_code >= 300) {
            $level = 'WARNING';
        }
        
        self::log($level, "FUB API Call: {$method} {$endpoint}", array(
            'response_code' => $response_code,
            'response_time' => round($response_time, 4),
            'request_data' => $request_data
        ));
    }
}

// Helper functions for easy logging
if (!function_exists('ufub_log')) {
    function ufub_log($level, $message, $context = array()) {
        if (UFUB_DEBUG) {
            FUB_Debug::log($level, $message, $context);
        }
    }
}

if (!function_exists('ufub_log_error')) {
    function ufub_log_error($message, $context = array()) {
        ufub_log('ERROR', $message, $context);
    }
}

if (!function_exists('ufub_log_info')) {
    function ufub_log_info($message, $context = array()) {
        ufub_log('INFO', $message, $context);
    }
}