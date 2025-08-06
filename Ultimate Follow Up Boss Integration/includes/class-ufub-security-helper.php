<?php
/**
 * Security Helper Class for Ultimate Follow Up Boss Integration
 *
 * Handles all security operations including AJAX verification,
 * input sanitization, and capability checking.
 *
 * @package Ultimate_FUB_Integration
 * @subpackage Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UFUB Security Helper Class
 *
 * Centralized security operations for the plugin
 */
class UFUB_Security_Helper {

    /**
     * Security nonce action names
     */
    const NONCE_AJAX = 'ufub_ajax_nonce';
    const NONCE_SETTINGS = 'ufub_settings_nonce';
    const NONCE_DEBUG = 'ufub_debug_nonce';
    const NONCE_WEBHOOK = 'ufub_webhook_nonce';

    /**
     * Rate limiting storage
     */
    private static $rate_limits = array();

    /**
     * Initialization flag
     */
    private static $initialized = false;

    /**
     * Initialize security helper
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }

        // Add security headers early
        add_action('send_headers', array(__CLASS__, 'add_security_headers'));
        
        // Admin-specific initialization
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_security_scripts'));
        }
        
        // Clean up expired rate limits
        add_action('wp_scheduled_delete', array(__CLASS__, 'cleanup_rate_limits'));

        self::$initialized = true;
    }

    /**
     * Enqueue security scripts and nonces
     */
    public static function enqueue_security_scripts($hook) {
        // Only on plugin pages
        if (!self::is_plugin_page($hook)) {
            return;
        }
        
        wp_localize_script('jquery', 'ufub_security', array(
            'ajax_nonce' => wp_create_nonce(self::NONCE_AJAX),
            'settings_nonce' => wp_create_nonce(self::NONCE_SETTINGS),
            'debug_nonce' => wp_create_nonce(self::NONCE_DEBUG),
            'webhook_nonce' => wp_create_nonce(self::NONCE_WEBHOOK),
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    /**
     * Add security headers
     */
    public static function add_security_headers() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    /**
     * Verify AJAX request security
     *
     * @param string $action The action being performed
     * @param string $nonce_type The type of nonce to verify
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function verify_ajax_request($action = '', $nonce_type = self::NONCE_AJAX) {
        // Check if AJAX request
        if (!wp_doing_ajax()) {
            return new WP_Error('not_ajax', 'Not an AJAX request');
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            return new WP_Error('insufficient_capability', 'Insufficient user capability');
        }

        // Verify nonce
        $nonce = sanitize_text_field($_POST['nonce'] ?? $_POST['_wpnonce'] ?? '');
        if (!wp_verify_nonce($nonce, $nonce_type)) {
            return new WP_Error('invalid_nonce', 'Security verification failed');
        }

        // Rate limiting check
        if (!self::check_rate_limit($action)) {
            return new WP_Error('rate_limited', 'Too many requests. Please try again later.');
        }

        return true;
    }

    /**
     * Verify settings form submission
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function verify_settings_form() {
        // Check user capability
        if (!current_user_can('manage_options')) {
            return new WP_Error('insufficient_capability', 'Insufficient user capability');
        }

        // Verify nonce
        $nonce = sanitize_text_field($_POST['_wpnonce'] ?? '');
        if (!wp_verify_nonce($nonce, self::NONCE_SETTINGS)) {
            return new WP_Error('invalid_nonce', 'Security verification failed');
        }

        return true;
    }

    /**
     * Sanitize input data based on type
     *
     * @param mixed $data The data to sanitize
     * @param string $type The type of sanitization to apply
     * @return mixed Sanitized data
     */
    public static function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            
            case 'url':
                return esc_url_raw($data);
            
            case 'int':
                return absint($data);
            
            case 'float':
                return floatval($data);
            
            case 'boolean':
                return (bool) $data;
            
            case 'array':
                if (is_array($data)) {
                    return array_map(array(__CLASS__, 'sanitize_recursive'), $data);
                }
                return array();
            
            case 'html':
                return wp_kses_post($data);
            
            case 'textarea':
                return sanitize_textarea_field($data);
            
            case 'key':
                return sanitize_key($data);
            
            case 'slug':
                return sanitize_title($data);
            
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Recursive sanitization for arrays
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private static function sanitize_recursive($data) {
        if (is_array($data)) {
            return array_map(array(__CLASS__, 'sanitize_recursive'), $data);
        }
        return sanitize_text_field($data);
    }

    /**
     * Validate API key format
     *
     * @param string $api_key The API key to validate
     * @return bool True if valid format
     */
    public static function validate_api_key($api_key) {
        // Follow Up Boss API keys are typically 40+ characters
        return strlen($api_key) >= 20 && preg_match('/^[A-Za-z0-9_-]+$/', $api_key);
    }

    /**
     * Validate webhook URL
     *
     * @param string $url The webhook URL to validate
     * @return bool True if valid
     */
    public static function validate_webhook_url($url) {
        // Must be HTTPS and valid URL
        return filter_var($url, FILTER_VALIDATE_URL) && 
               strpos($url, 'https://') === 0;
    }

    /**
     * Check rate limiting for actions
     *
     * @param string $action The action being performed
     * @param int $limit Number of requests allowed per minute
     * @return bool True if within limits
     */
    public static function check_rate_limit($action, $limit = 60) {
        $user_id = get_current_user_id();
        $key = $action . '_' . $user_id;
        $current_time = time();
        
        // Initialize if not exists
        if (!isset(self::$rate_limits[$key])) {
            self::$rate_limits[$key] = array(
                'count' => 0,
                'reset_time' => $current_time + 60
            );
        }
        
        $rate_data = &self::$rate_limits[$key];
        
        // Reset if time window expired
        if ($current_time > $rate_data['reset_time']) {
            $rate_data['count'] = 0;
            $rate_data['reset_time'] = $current_time + 60;
        }
        
        // Check if over limit
        if ($rate_data['count'] >= $limit) {
            return false;
        }
        
        // Increment counter
        $rate_data['count']++;
        
        return true;
    }

    /**
     * Log security events
     *
     * @param string $event The security event
     * @param array $data Additional data to log
     */
    public static function log_security_event($event, $data = array()) {
        if (function_exists('ufub_log_warning')) {
            $log_data = array_merge(array(
                'event' => $event,
                'user_id' => get_current_user_id(),
                'ip_address' => self::get_client_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'),
                'timestamp' => current_time('mysql')
            ), $data);
            
            ufub_log_warning('Security Event: ' . $event, $log_data);
        }
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    public static function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
    }

    /**
     * Check if current page is a plugin page
     *
     * @param string $hook Current admin page hook
     * @return bool True if on plugin page
     */
    public static function is_plugin_page($hook = '') {
        // If hook provided, check it
        if (!empty($hook)) {
            return strpos($hook, 'ufub') !== false || strpos($hook, 'fub-integration') !== false;
        }
        
        // Fallback: check $_GET parameters
        if (isset($_GET['page'])) {
            $page = sanitize_text_field($_GET['page']);
            return strpos($page, 'ufub') !== false || strpos($page, 'fub-integration') !== false;
        }
        
        return false;
    }

    /**
     * Safe execution wrapper with error handling
     *
     * @param callable $callback The function to execute
     * @param array $args Arguments to pass to function
     * @param mixed $default Default value to return on error
     * @return mixed Function result or default value
     */
    public static function safe_execute($callback, $args = array(), $default = null) {
        try {
            if (is_callable($callback)) {
                return call_user_func_array($callback, $args);
            }
        } catch (Exception $e) {
            self::log_security_event('safe_execute_error', array(
                'callback' => is_string($callback) ? $callback : 'closure',
                'error' => $e->getMessage()
            ));
        } catch (Error $e) {
            self::log_security_event('safe_execute_fatal', array(
                'callback' => is_string($callback) ? $callback : 'closure',
                'error' => $e->getMessage()
            ));
        }
        
        return $default;
    }

    /**
     * Validate template path for secure loading
     *
     * @param string $template_path Path to template file
     * @return bool True if path is safe
     */
    public static function validate_template_path($template_path) {
        // Get plugin directory path
        $plugin_dir = UFUB_PLUGIN_DIR;
        $real_plugin_dir = realpath($plugin_dir);
        $real_template_path = realpath($template_path);
        
        // Check if template is within plugin directory
        if (!$real_template_path || !$real_plugin_dir) {
            return false;
        }
        
        // Ensure template is within plugin directory (prevent directory traversal)
        return strpos($real_template_path, $real_plugin_dir) === 0;
    }

    /**
     * Create secure nonce for specific context
     *
     * @param string $context The context for the nonce
     * @return string Generated nonce
     */
    public static function create_nonce($context) {
        return wp_create_nonce('ufub_' . $context);
    }

    /**
     * Verify nonce for specific context
     *
     * @param string $nonce The nonce to verify
     * @param string $context The context of the nonce
     * @return bool True if valid
     */
    public static function verify_nonce($nonce, $context) {
        return wp_verify_nonce($nonce, 'ufub_' . $context);
    }

    /**
     * Clean up expired rate limits
     */
    public static function cleanup_rate_limits() {
        $current_time = time();
        foreach (self::$rate_limits as $key => $data) {
            if ($current_time > $data['reset_time']) {
                unset(self::$rate_limits[$key]);
            }
        }
    }

    /**
     * Get security status for dashboard
     *
     * @return array Security status information
     */
    public static function get_security_status() {
        return array(
            'ssl_enabled' => is_ssl(),
            'wp_version_current' => version_compare(get_bloginfo('version'), '6.0', '>='),
            'php_version_current' => version_compare(PHP_VERSION, '7.4', '>='),
            'security_headers_enabled' => true,
            'rate_limiting_active' => !empty(self::$rate_limits),
            'debug_mode' => defined('UFUB_DEBUG') && UFUB_DEBUG,
            'plugin_version' => defined('UFUB_VERSION') ? UFUB_VERSION : '2.1.1'
        );
    }

    /**
     * Validate user permissions for action
     *
     * @param string $action The action being performed
     * @param int $user_id User ID (default: current user)
     * @return bool True if user has permission
     */
    public static function validate_user_permissions($action, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Basic capability check
        if (!user_can($user_id, 'manage_options')) {
            return false;
        }
        
        // Action-specific checks
        switch ($action) {
            case 'manage_api':
            case 'manage_settings':
                return user_can($user_id, 'manage_options');
                
            case 'view_debug':
                return user_can($user_id, 'manage_options') && UFUB_DEBUG;
                
            case 'manage_webhooks':
                return user_can($user_id, 'manage_options');
                
            default:
                return user_can($user_id, 'manage_options');
        }
    }

    /**
     * Sanitize and validate settings array
     *
     * @param array $settings Raw settings array
     * @return array Sanitized settings
     */
    public static function sanitize_settings($settings) {
        $sanitized = array();
        
        $allowed_settings = array(
            'ufub_api_key' => 'text',
            'ufub_tracking_enabled' => 'boolean',
            'ufub_debug_enabled' => 'boolean',
            'ufub_security_enabled' => 'boolean',
            'ufub_webhook_enabled' => 'boolean',
            'ufub_auto_sync' => 'boolean',
            'ufub_saved_search_threshold' => 'int',
            'ufub_similarity_threshold' => 'int',
            'ufub_popup_enabled' => 'boolean',
            'ufub_popup_threshold' => 'int'
        );
        
        foreach ($allowed_settings as $setting => $type) {
            if (isset($settings[$setting])) {
                $sanitized[$setting] = self::sanitize_input($settings[$setting], $type);
                
                // Additional validation for specific settings
                switch ($setting) {
                    case 'ufub_saved_search_threshold':
                        $sanitized[$setting] = max(2, min(10, $sanitized[$setting]));
                        break;
                        
                    case 'ufub_similarity_threshold':
                        $sanitized[$setting] = max(50, min(95, $sanitized[$setting]));
                        break;
                        
                    case 'ufub_popup_threshold':
                        $sanitized[$setting] = max(3, min(20, $sanitized[$setting]));
                        break;
                }
            }
        }
        
        return $sanitized;
    }
}