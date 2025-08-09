<?php
/**
 * FUB Security Handler - CRITICAL for AJAX Protection
 * 
 * Protects AJAX endpoints from attacks, validates nonces, checks permissions
 * This is HOW we prevent unauthorized access to our plugin functions
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Security
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Security {
    
    private static $instance = null;
    private $nonce_actions = array();
    
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
        $this->init();
        $this->register_nonce_actions();
    }
    
    /**
     * Initialize security
     */
    private function init() {
        // Add security headers
        add_action('wp_head', array($this, 'add_security_headers'), 1);
        add_action('admin_head', array($this, 'add_security_headers'), 1);
        
        // Validate AJAX requests
        add_action('wp_ajax_nopriv_ufub_save_search', array($this, 'validate_ajax_request'), 1);
        add_action('wp_ajax_ufub_save_search', array($this, 'validate_ajax_request'), 1);
        add_action('wp_ajax_ufub_delete_saved_search', array($this, 'validate_ajax_request'), 1);
        add_action('wp_ajax_ufub_test_api', array($this, 'validate_ajax_request'), 1);
        
        // Rate limiting
        add_action('init', array($this, 'check_rate_limits'));
        
        // Input sanitization filters
        add_filter('ufub_sanitize_input', array($this, 'sanitize_input_data'), 10, 2);
    }
    
    /**
     * Register nonce actions for validation
     */
    private function register_nonce_actions() {
        $this->nonce_actions = array(
            'ufub_save_search' => 'Save Search',
            'ufub_delete_search' => 'Delete Search', 
            'ufub_test_api' => 'Test API',
            'ufub_admin_settings' => 'Admin Settings',
            'ufub_property_match' => 'Property Match',
            'ufub_behavioral_track' => 'Behavioral Tracking'
        );
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add to our plugin pages
        if (!$this->is_plugin_page()) {
            return;
        }
        
        // Prevent XSS
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Check if current page is related to our plugin
     */
    private function is_plugin_page() {
        global $pagenow;
        
        // Admin pages
        if (is_admin()) {
            $page = $_GET['page'] ?? '';
            return strpos($page, 'ufub') !== false || strpos($page, 'fub') !== false;
        }
        
        // Frontend pages with AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = $_REQUEST['action'] ?? '';
            return strpos($action, 'ufub') !== false || strpos($action, 'fub') !== false;
        }
        
        return false;
    }
    
    /**
     * Validate AJAX requests
     */
    public function validate_ajax_request() {
        // Get current action
        $action = $_REQUEST['action'] ?? '';
        
        if (!$this->is_plugin_action($action)) {
            return; // Not our action, let it proceed
        }
        
        // Check nonce for actions that require it
        $nonce_action = $this->get_nonce_action_for_ajax($action);
        
        if ($nonce_action) {
            $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_wpnonce'] ?? '';
            
            if (!wp_verify_nonce($nonce, $nonce_action)) {
                wp_die('Security check failed', 'Security Error', array('response' => 403));
            }
        }
        
        // Check rate limits
        if (!$this->check_action_rate_limit($action)) {
            wp_die('Rate limit exceeded', 'Too Many Requests', array('response' => 429));
        }
        
        // Log security event
        $this->log_security_event('ajax_validated', array(
            'action' => $action,
            'user_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
    }
    
    /**
     * Check if action belongs to our plugin
     */
    private function is_plugin_action($action) {
        return strpos($action, 'ufub_') === 0 || strpos($action, 'fub_') === 0;
    }
    
    /**
     * Get nonce action for AJAX request
     */
    private function get_nonce_action_for_ajax($ajax_action) {
        $nonce_map = array(
            'ufub_save_search' => 'ufub_save_search',
            'ufub_delete_saved_search' => 'ufub_delete_search',
            'ufub_test_api' => 'ufub_test_api',
            'ufub_update_settings' => 'ufub_admin_settings'
        );
        
        return $nonce_map[$ajax_action] ?? null;
    }
    
    /**
     * Check rate limits for actions
     */
    public function check_rate_limits() {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return true;
        }
        
        $action = $_REQUEST['action'] ?? '';
        
        if (!$this->is_plugin_action($action)) {
            return true;
        }
        
        return $this->check_action_rate_limit($action);
    }
    
    /**
     * Check rate limit for specific action
     */
    private function check_action_rate_limit($action) {
        $ip = $this->get_client_ip();
        $transient_key = 'ufub_rate_limit_' . md5($ip . $action);
        
        // Rate limits per action (requests per hour)
        $limits = array(
            'ufub_save_search' => 20,      // 20 searches per hour
            'ufub_delete_saved_search' => 10,  // 10 deletions per hour
            'ufub_test_api' => 5,          // 5 API tests per hour
            'default' => 60                // 60 general requests per hour
        );
        
        $limit = $limits[$action] ?? $limits['default'];
        $current_count = (int) get_transient($transient_key);
        
        if ($current_count >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Increment counter
        set_transient($transient_key, $current_count + 1, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Sanitize input data
     */
    public function sanitize_input_data($data, $type = 'text') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input_data($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($data);
                
            case 'url':
                return esc_url_raw($data);
                
            case 'int':
                return (int) $data;
                
            case 'float':
                return (float) $data;
                
            case 'textarea':
                return sanitize_textarea_field($data);
                
            case 'key':
                return sanitize_key($data);
                
            case 'html':
                return wp_kses_post($data);
                
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Validate user permissions for action
     */
    public function validate_user_permissions($action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Define permission requirements
        $permissions = array(
            'ufub_admin_settings' => 'manage_options',
            'ufub_test_api' => 'manage_options', 
            'ufub_view_logs' => 'manage_options',
            'ufub_delete_search' => 'edit_posts', // Subscribers can delete their own searches
            'ufub_save_search' => '', // Anyone can save searches
        );
        
        $required_cap = $permissions[$action] ?? 'read';
        
        if (empty($required_cap)) {
            return true; // No permission required
        }
        
        return user_can($user_id, $required_cap);
    }
    
    /**
     * Generate secure nonce
     */
    public function generate_nonce($action) {
        if (!array_key_exists($action, $this->nonce_actions)) {
            return false;
        }
        
        return wp_create_nonce($action);
    }
    
    /**
     * Verify nonce
     */
    public function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Log security events
     */
    private function log_security_event($event_type, $data = array()) {
        global $wpdb;
        
        // Don't log if logging is disabled
        if (!get_option('ufub_security_logging', true)) {
            return;
        }
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        $log_data = array(
            'event_type' => 'security_' . $event_type,
            'request_data' => json_encode($data),
            'response_data' => '',
            'status' => 'logged',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($table, $log_data, array('%s', '%s', '%s', '%s', '%s'));
    }
    
    /**
     * Check for suspicious activity
     */
    public function check_suspicious_activity($ip = null) {
        if (!$ip) {
            $ip = $this->get_client_ip();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'fub_api_logs';
        
        // Check for multiple failed attempts in last hour
        $failed_attempts = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table 
            WHERE event_type LIKE 'security_%' 
            AND request_data LIKE %s
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ", '%' . $ip . '%'));
        
        return $failed_attempts > 10; // Suspicious if more than 10 security events per hour
    }
    
    /**
     * Block suspicious IP
     */
    public function block_ip($ip, $duration = 3600) {
        $blocked_ips = get_option('ufub_blocked_ips', array());
        
        $blocked_ips[$ip] = array(
            'blocked_at' => time(),
            'expires_at' => time() + $duration,
            'reason' => 'Suspicious activity'
        );
        
        update_option('ufub_blocked_ips', $blocked_ips);
        
        $this->log_security_event('ip_blocked', array(
            'ip' => $ip,
            'duration' => $duration
        ));
    }
    
    /**
     * Check if IP is blocked
     */
    public function is_ip_blocked($ip = null) {
        if (!$ip) {
            $ip = $this->get_client_ip();
        }
        
        $blocked_ips = get_option('ufub_blocked_ips', array());
        
        if (!isset($blocked_ips[$ip])) {
            return false;
        }
        
        $block_info = $blocked_ips[$ip];
        
        // Check if block has expired
        if (time() > $block_info['expires_at']) {
            unset($blocked_ips[$ip]);
            update_option('ufub_blocked_ips', $blocked_ips);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get security dashboard data
     */
    public function get_security_dashboard() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        // Get security events from last 24 hours
        $events = $wpdb->get_results("
            SELECT event_type, COUNT(*) as count, MAX(created_at) as last_occurrence
            FROM $table 
            WHERE event_type LIKE 'security_%' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY event_type
            ORDER BY count DESC
        ");
        
        // Get blocked IPs
        $blocked_ips = get_option('ufub_blocked_ips', array());
        $active_blocks = array_filter($blocked_ips, function($block) {
            return time() < $block['expires_at'];
        });
        
        return array(
            'events_24h' => $events,
            'blocked_ips' => count($active_blocks),
            'rate_limits_active' => $this->count_active_rate_limits()
        );
    }
    
    /**
     * Count active rate limits
     */
    private function count_active_rate_limits() {
        global $wpdb;
        
        // This is a simplified count - in practice you'd query the transients
        $count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_ufub_rate_limit_%'
        ");
        
        return (int) $count;
    }
}
?>
