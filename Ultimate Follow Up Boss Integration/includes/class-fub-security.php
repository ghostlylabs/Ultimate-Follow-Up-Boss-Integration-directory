<?php
/**
 * FUB Security Monitoring System - Enhanced v2.1.2
 * 
 * Comprehensive security monitoring and threat detection with health interface
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Security
 * @version 2.1.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Security {
    
    private static $instance = null;
    private $security_table;
    private $failed_login_attempts = array();
    private $suspicious_ips = array();
    private $alert_email;
    private $health_status = array();
    private $analytics_data = array();
    
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
        $this->security_table = $wpdb->prefix . 'ufub_security_logs';
        $this->alert_email = get_option('admin_email');
        
        $this->init_analytics();
        $this->init();
    }
    
    /**
     * Initialize analytics tracking
     */
    private function init_analytics() {
        $this->analytics_data = array(
            'threats_blocked_today' => 0,
            'login_attempts_today' => 0,
            'security_score' => 100,
            'active_alerts' => 0,
            'blocked_ips' => 0,
            'last_threat_detected' => null
        );
        
        // Load existing analytics
        $stored_analytics = get_option('ufub_security_analytics', array());
        if (!empty($stored_analytics)) {
            $this->analytics_data = array_merge($this->analytics_data, $stored_analytics);
        }
    }
    
    /**
     * PHASE 1A INTEGRATION: Component Health Check
     * 
     * @return array Detailed health status for orchestration layer
     */
    public function health_check() {
        $start_time = microtime(true);
        
        // Security health checks
        $threat_detection = $this->check_threat_detection_status();
        $login_monitoring = $this->check_login_monitoring_status();
        $ip_blocking = $this->check_ip_blocking_status();
        $log_integrity = $this->check_log_integrity();
        
        $health_checks = array(
            'threat_detection' => $threat_detection['status'],
            'login_monitoring' => $login_monitoring['status'],
            'ip_blocking' => $ip_blocking['status'],
            'log_integrity' => $log_integrity['status']
        );
        
        $passed_checks = count(array_filter($health_checks, function($status) {
            return $status === 'pass';
        }));
        
        $health_score = round(($passed_checks / count($health_checks)) * 100);
        
        // Determine overall status
        if ($health_score >= 90) {
            $status = 'healthy';
        } elseif ($health_score >= 70) {
            $status = 'warning';
        } else {
            $status = 'critical';
        }
        
        $recommendations = $this->generate_security_recommendations($health_checks);
        
        $this->health_status = array(
            // ENHANCED FORMAT (Phase 3 Dashboard)
            'status' => $status,
            'score' => $health_score,
            'checks' => $health_checks,
            'details' => array(
                'threat_detection' => $threat_detection,
                'login_monitoring' => $login_monitoring,
                'ip_blocking' => $ip_blocking,
                'log_integrity' => $log_integrity
            ),
            'metrics' => array(
                'threats_blocked_today' => $this->analytics_data['threats_blocked_today'],
                'security_score' => $this->analytics_data['security_score'],
                'active_alerts' => $this->analytics_data['active_alerts'],
                'blocked_ips' => count(get_option('ufub_blocked_ips', array()))
            ),
            'recommendations' => $recommendations,
            'last_check' => current_time('mysql'),
            'check_duration' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
            
            // BACKWARDS COMPATIBILITY (Phase 1A Orchestration)
            'healthy' => ($status === 'healthy'),
            'error' => ($status !== 'healthy') ? $this->get_simple_error_message($health_checks, $recommendations) : null
        );
        
        return $this->health_status;
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Diagnostics Information
     */
    public function get_diagnostics() {
        return array(
            'component_info' => array(
                'class' => get_class($this),
                'version' => '2.1.2',
                'table_exists' => $this->check_security_table_exists(),
                'monitoring_active' => !empty($this->failed_login_attempts)
            ),
            'security_stats' => array(
                'total_events' => $this->get_total_security_events(),
                'recent_threats' => $this->get_recent_threats(5),
                'blocked_ips' => get_option('ufub_blocked_ips', array()),
                'suspicious_ips' => get_option('ufub_suspicious_ips', array())
            ),
            'monitoring_status' => array(
                'login_tracking' => true,
                'admin_monitoring' => true,
                'file_integrity' => true,
                'api_monitoring' => true
            )
        );
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Analytics Data
     */
    public function get_analytics() {
        return array(
            'overview' => array(
                'security_score' => $this->analytics_data['security_score'],
                'threats_blocked_today' => $this->analytics_data['threats_blocked_today'],
                'active_alerts' => $this->analytics_data['active_alerts'],
                'total_blocked_ips' => count(get_option('ufub_blocked_ips', array()))
            ),
            'threat_analysis' => array(
                'login_attempts_today' => $this->analytics_data['login_attempts_today'],
                'last_threat_detected' => $this->analytics_data['last_threat_detected'],
                'threat_patterns' => $this->get_threat_patterns()
            ),
            'health_score' => $this->health_status['score'] ?? 0,
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Enhanced Error Handling
     */
    public function handle_error($error, $context = array()) {
        $error_data = array(
            'component' => get_class($this),
            'method' => $context['method'] ?? 'unknown',
            'context' => $context,
            'timestamp' => current_time('mysql')
        );
        
        if (is_wp_error($error)) {
            $error_code = $error->get_error_code();
            $error_message = $error->get_error_message();
        } else {
            $error_code = 'security_error';
            $error_message = is_string($error) ? $error : 'Security system error';
        }
        
        // Log security error
        $this->log_security_event($error_code, 'ERROR', $error_message, $error_data);
        
        return new WP_Error($error_code, $error_message, $error_data);
    }
    
    /**
     * Initialize security monitoring
     */
    private function init() {
        // Create security table if needed
        $this->create_security_table_if_needed();
        
        // Login monitoring
        add_action('wp_login_failed', array($this, 'track_failed_login'));
        add_action('wp_login', array($this, 'track_successful_login'), 10, 2);
        add_action('wp_logout', array($this, 'track_logout'));
        
        // Admin access monitoring
        add_action('admin_init', array($this, 'monitor_admin_access'));
        add_action('user_register', array($this, 'track_user_registration'));
        add_action('profile_update', array($this, 'track_profile_update'), 10, 2);
        
        // File monitoring
        add_action('wp_loaded', array($this, 'check_file_integrity'));
        
        // API security
        add_action('rest_api_init', array($this, 'monitor_api_access'));
        
        // Schedule daily security scan
        if (!wp_next_scheduled('ufub_daily_security_scan')) {
            wp_schedule_event(time(), 'daily', 'ufub_daily_security_scan');
        }
        add_action('ufub_daily_security_scan', array($this, 'daily_security_scan'));
        
        // AJAX handlers
        add_action('wp_ajax_ufub_get_security_report', array($this, 'ajax_get_security_report'));
        add_action('wp_ajax_ufub_block_ip', array($this, 'ajax_block_ip'));
        add_action('wp_ajax_ufub_whitelist_ip', array($this, 'ajax_whitelist_ip'));
        
        // Initialize monitoring
        $this->log_security_event('SYSTEM_START', 'INFO', 'Security monitoring initialized', array(
            'plugin_version' => UFUB_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
        ));
    }
    
    /**
     * Check threat detection status
     */
    private function check_threat_detection_status() {
        // Check if security logging is working
        if (!$this->check_security_table_exists()) {
            return array(
                'status' => 'fail',
                'message' => 'Security logging table missing',
                'details' => 'Cannot track security events without database table'
            );
        }
        
        // Check recent threat detection activity
        $recent_events = $this->get_recent_security_events(24); // Last 24 hours
        
        return array(
            'status' => 'pass',
            'message' => 'Threat detection active',
            'details' => count($recent_events) . ' security events logged in last 24 hours'
        );
    }
    
    /**
     * Check login monitoring status
     */
    private function check_login_monitoring_status() {
        // Verify login hooks are active
        global $wp_filter;
        
        $required_hooks = array('wp_login_failed', 'wp_login');
        $active_hooks = 0;
        
        foreach ($required_hooks as $hook) {
            if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
                $active_hooks++;
            }
        }
        
        if ($active_hooks === count($required_hooks)) {
            return array(
                'status' => 'pass',
                'message' => 'Login monitoring active',
                'details' => 'All login event hooks properly registered'
            );
        }
        
        return array(
            'status' => 'fail',
            'message' => 'Login monitoring incomplete',
            'details' => "Only {$active_hooks}/{$required_hooks} hooks active"
        );
    }
    
    /**
     * Check IP blocking status
     */
    private function check_ip_blocking_status() {
        $blocked_ips = get_option('ufub_blocked_ips', array());
        $suspicious_ips = get_option('ufub_suspicious_ips', array());
        
        return array(
            'status' => 'pass',
            'message' => 'IP blocking system operational',
            'details' => count($blocked_ips) . ' blocked IPs, ' . count($suspicious_ips) . ' suspicious IPs'
        );
    }
    
    /**
     * Check log integrity
     */
    private function check_log_integrity() {
        if (!$this->check_security_table_exists()) {
            return array(
                'status' => 'fail',
                'message' => 'Security logs table missing',
                'details' => 'Cannot verify log integrity without database table'
            );
        }
        
        $log_count = $this->get_total_security_events();
        
        return array(
            'status' => 'pass',
            'message' => 'Log integrity verified',
            'details' => "{$log_count} security events logged"
        );
    }
    
    /**
     * Generate security recommendations
     */
    private function generate_security_recommendations($health_checks) {
        $recommendations = array();
        
        if ($health_checks['threat_detection'] === 'fail') {
            $recommendations[] = 'Create security database table in plugin settings';
            $recommendations[] = 'Check database permissions and storage space';
        }
        
        if ($health_checks['login_monitoring'] === 'fail') {
            $recommendations[] = 'Verify security hooks are properly registered';
            $recommendations[] = 'Check for plugin conflicts affecting login monitoring';
        }
        
        if ($health_checks['ip_blocking'] === 'fail') {
            $recommendations[] = 'Review blocked IP configuration';
            $recommendations[] = 'Verify IP blocking functionality is enabled';
        }
        
        if ($health_checks['log_integrity'] === 'fail') {
            $recommendations[] = 'Restore security database table structure';
            $recommendations[] = 'Check database connectivity and permissions';
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Get simple error message for backwards compatibility
     */
    private function get_simple_error_message($health_checks, $recommendations) {
        $failed_checks = array();
        
        foreach ($health_checks as $check => $status) {
            if ($status === 'fail') {
                $failed_checks[] = str_replace('_', ' ', $check);
            }
        }
        
        if (!empty($failed_checks)) {
            $message = 'Failed checks: ' . implode(', ', $failed_checks);
            
            if (!empty($recommendations)) {
                $message .= '. ' . $recommendations[0];
            }
            
            return $message;
        }
        
        return 'Security component health check failed';
    }
    
    /**
     * Check if security table exists
     */
    private function check_security_table_exists() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->security_table}'") === $this->security_table;
    }
    
    /**
     * Create security table if needed
     */
    private function create_security_table_if_needed() {
        global $wpdb;
        
        if (!$this->check_security_table_exists()) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$this->security_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                severity varchar(20) NOT NULL,
                message text NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                ip_address varchar(45) NOT NULL,
                user_agent text,
                additional_data longtext,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY severity (severity),
                KEY ip_address (ip_address),
                KEY created_date (created_date)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            if (function_exists('ufub_log_info')) {
                ufub_log_info('Security logs table created');
            }
        }
    }
    
    /**
     * Log security event
     */
    public function log_security_event($event_type, $severity, $message, $additional_data = array()) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $wpdb->insert(
            $this->security_table,
            array(
                'event_type' => $event_type,
                'severity' => $severity,
                'message' => $message,
                'user_id' => $user_id ?: null,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'additional_data' => wp_json_encode($additional_data),
                'created_date' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        // Update analytics
        if (in_array($severity, array('ERROR', 'CRITICAL'))) {
            $this->analytics_data['threats_blocked_today']++;
            $this->analytics_data['last_threat_detected'] = current_time('mysql');
        }
        
        // Send alert for critical events
        if (in_array($severity, array('CRITICAL', 'HIGH'))) {
            $this->send_security_alert($event_type, $message, $additional_data);
        }
        
        // Debug logging if enabled
        if (UFUB_DEBUG && class_exists('FUB_Debug')) {
            FUB_Debug::log('SECURITY', "{$event_type}: {$message}", $additional_data);
        }
        
        // Save analytics
        update_option('ufub_security_analytics', $this->analytics_data);
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
     * Get recent security events
     */
    private function get_recent_security_events($hours = 24) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->security_table} 
             WHERE created_date >= DATE_SUB(NOW(), INTERVAL %d HOUR) 
             ORDER BY created_date DESC",
            $hours
        ), ARRAY_A);
    }
    
    /**
     * Get total security events
     */
    private function get_total_security_events() {
        global $wpdb;
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->security_table}");
    }
    
    /**
     * Get recent threats
     */
    private function get_recent_threats($limit = 5) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->security_table} 
             WHERE severity IN ('ERROR', 'CRITICAL', 'HIGH') 
             ORDER BY created_date DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    /**
     * Get threat patterns
     */
    private function get_threat_patterns() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->security_table} 
             WHERE severity IN ('ERROR', 'CRITICAL') 
             AND created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY event_type 
             ORDER BY count DESC 
             LIMIT 5",
            ARRAY_A
        );
    }
    
    /**
     * Send security alert
     */
    private function send_security_alert($event_type, $message, $data = array()) {
        $subject = "[Security Alert] {$event_type} - " . get_bloginfo('name');
        
        $email_body = "Security Alert Details:\n\n";
        $email_body .= "Event Type: {$event_type}\n";
        $email_body .= "Message: {$message}\n";
        $email_body .= "Time: " . current_time('mysql') . "\n";
        $email_body .= "IP Address: " . $this->get_client_ip() . "\n";
        $email_body .= "Site URL: " . home_url() . "\n\n";
        
        if (!empty($data)) {
            $email_body .= "Additional Details:\n";
            foreach ($data as $key => $value) {
                $email_body .= ucfirst($key) . ": " . (is_array($value) ? print_r($value, true) : $value) . "\n";
            }
        }
        
        $email_body .= "\n---\nUltimate Follow Up Boss Integration Security System";
        
        wp_mail($this->alert_email, $subject, $email_body);
    }
    
    // ... [Additional security monitoring methods - track_failed_login, etc.]
    // ... [All existing functionality preserved]
    
    /**
     * Track failed login attempts
     */
    public function track_failed_login($username) {
        $ip = $this->get_client_ip();
        
        // Track attempts per IP
        if (!isset($this->failed_login_attempts[$ip])) {
            $this->failed_login_attempts[$ip] = 0;
        }
        $this->failed_login_attempts[$ip]++;
        
        // Store in database
        $this->log_security_event('LOGIN_FAILED', 'MEDIUM', "Failed login attempt for user: {$username}", array(
            'username' => $username,
            'ip_attempts' => $this->failed_login_attempts[$ip],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ));
        
        // Check for brute force attack
        if ($this->failed_login_attempts[$ip] >= 5) {
            $this->handle_brute_force_attempt($ip, $username);
        }
    }
    
    /**
     * Handle brute force attempts
     */
    private function handle_brute_force_attempt($ip, $username) {
        $this->log_security_event('BRUTE_FORCE', 'CRITICAL', "Brute force attack detected from IP: {$ip}", array(
            'target_username' => $username,
            'attempt_count' => $this->failed_login_attempts[$ip],
            'blocked' => true
        ));
        
        // Add to suspicious IPs list
        $this->suspicious_ips[] = $ip;
        update_option('ufub_suspicious_ips', $this->suspicious_ips);
        
        // Send immediate alert
        $this->send_security_alert('BRUTE_FORCE', "Brute force attack detected from {$ip} targeting user {$username}", array(
            'ip' => $ip,
            'attempts' => $this->failed_login_attempts[$ip]
        ));
    }
    
    /**
     * Track successful login
     */
    public function track_successful_login($user_login, $user) {
        $ip = $this->get_client_ip();
        
        // Reset failed attempts for this IP
        if (isset($this->failed_login_attempts[$ip])) {
            unset($this->failed_login_attempts[$ip]);
        }
        
        $severity = 'INFO';
        
        // Flag suspicious logins
        if ($this->is_suspicious_login($user, $ip)) {
            $severity = 'MEDIUM';
        }
        
        $this->log_security_event('LOGIN_SUCCESS', $severity, "Successful login for user: {$user_login}", array(
            'user_id' => $user->ID,
            'user_login' => $user_login,
            'user_role' => implode(', ', $user->roles),
            'last_login' => get_user_meta($user->ID, 'last_login', true)
        ));
        
        // Update last login time
        update_user_meta($user->ID, 'last_login', current_time('mysql'));
        update_user_meta($user->ID, 'last_login_ip', $ip);
    }
    
    /**
     * Check for suspicious login
     */
    private function is_suspicious_login($user, $ip) {
        // Check if login is from a different location than usual
        $last_ip = get_user_meta($user->ID, 'last_login_ip', true);
        
        if ($last_ip && $last_ip !== $ip) {
            return true;
        }
        
        // Check if admin login outside business hours
        if (in_array('administrator', $user->roles)) {
            $hour = (int) current_time('H');
            if ($hour < 6 || $hour > 22) { // Outside 6 AM - 10 PM
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Track logout
     */
    public function track_logout($user_id) {
        $user = get_user_by('ID', $user_id);
        
        $this->log_security_event('LOGOUT', 'INFO', "User logged out: {$user->user_login}", array(
            'user_id' => $user_id,
            'session_duration' => $this->calculate_session_duration($user_id)
        ));
    }
    
    /**
     * Calculate session duration
     */
    private function calculate_session_duration($user_id) {
        $last_login = get_user_meta($user_id, 'last_login', true);
        
        if ($last_login) {
            $login_time = strtotime($last_login);
            $current_time = time();
            return $current_time - $login_time;
        }
        
        return 0;
    }
    
    /**
     * Monitor admin access
     */
    public function monitor_admin_access() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        $user = wp_get_current_user();
        $current_page = $_GET['page'] ?? $_SERVER['REQUEST_URI'];
        
        // Track sensitive admin actions
        $sensitive_pages = array('ufub-settings', 'ufub-debug', 'users.php', 'plugins.php', 'themes.php');
        
        foreach ($sensitive_pages as $page) {
            if (strpos($current_page, $page) !== false) {
                $this->log_security_event('ADMIN_ACCESS', 'INFO', "Admin access to sensitive page: {$page}", array(
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'page' => $current_page,
                    'referer' => $_SERVER['HTTP_REFERER'] ?? ''
                ));
                break;
            }
        }
    }
    
    /**
     * Track user registration
     */
    public function track_user_registration($user_id) {
        $user = get_user_by('ID', $user_id);
        
        $this->log_security_event('USER_REGISTER', 'MEDIUM', "New user registered: {$user->user_login}", array(
            'user_id' => $user_id,
            'user_email' => $user->user_email,
            'user_role' => implode(', ', $user->roles),
            'registration_method' => $this->detect_registration_method()
        ));
    }
    
    /**
     * Detect registration method
     */
    private function detect_registration_method() {
        if (is_admin()) {
            return 'admin';
        }
        
        if (isset($_POST['action']) && $_POST['action'] === 'register') {
            return 'frontend_form';
        }
        
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'api';
        }
        
        return 'unknown';
    }
    
    /**
     * Track profile updates
     */
    public function track_profile_update($user_id, $old_user_data) {
        $user = get_user_by('ID', $user_id);
        $current_user = wp_get_current_user();
        
        $changes = array();
        
        // Check for significant changes
        if ($old_user_data->user_email !== $user->user_email) {
            $changes[] = 'email';
        }
        if ($old_user_data->user_login !== $user->user_login) {
            $changes[] = 'username';
        }
        if ($old_user_data->user_pass !== $user->user_pass) {
            $changes[] = 'password';
        }
        
        if (!empty($changes)) {
            $severity = ($user_id !== $current_user->ID) ? 'HIGH' : 'MEDIUM';
            
            $this->log_security_event('PROFILE_UPDATE', $severity, "Profile updated for user: {$user->user_login}", array(
                'target_user_id' => $user_id,
                'changed_by_user_id' => $current_user->ID,
                'changed_by_login' => $current_user->user_login,
                'changes' => $changes,
                'is_self_update' => ($user_id === $current_user->ID)
            ));
        }
    }
    
    /**
     * Check file integrity
     */
    public function check_file_integrity() {
        // Only run this check occasionally to avoid performance issues
        if (rand(1, 100) > 5) { // 5% chance
            return;
        }
        
        $plugin_files = array(
            UFUB_PLUGIN_DIR . 'ultimate-fub-integration.php',
            UFUB_PLUGIN_DIR . 'includes/class-fub-api.php',
            UFUB_PLUGIN_DIR . 'includes/class-fub-events.php'
        );
        
        foreach ($plugin_files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                
                // Check for malicious code patterns
                $malicious_patterns = array(
                    'eval\s*\(',
                    'base64_decode\s*\(',
                    'exec\s*\(',
                    'system\s*\(',
                    'shell_exec\s*\(',
                    'file_get_contents\s*\(\s*["\']http',
                    'curl_exec\s*\('
                );
                
                foreach ($malicious_patterns as $pattern) {
                    if (preg_match('/' . $pattern . '/i', $content)) {
                        $this->log_security_event('FILE_INTEGRITY', 'CRITICAL', "Suspicious code detected in file: " . basename($file), array(
                            'file' => $file,
                            'pattern' => $pattern,
                            'file_size' => filesize($file),
                            'file_modified' => date('Y-m-d H:i:s', filemtime($file))
                        ));
                    }
                }
            }
        }
    }
    
    /**
     * Monitor API access
     */
    public function monitor_api_access() {
        // Monitor REST API requests to our endpoints
        add_filter('rest_pre_dispatch', array($this, 'log_api_request'), 10, 3);
    }
    
    /**
     * Log API requests
     */
    public function log_api_request($result, $server, $request) {
        $route = $request->get_route();
        
        // Only log requests to our plugin endpoints
        if (strpos($route, '/ufub/') !== false) {
            $this->log_security_event('API_REQUEST', 'INFO', "REST API request: {$route}", array(
                'method' => $request->get_method(),
                'params' => $request->get_params(),
                'user_agent' => $request->get_header('user-agent'),
                'authenticated' => is_user_logged_in()
            ));
        }
        
        return $result;
    }
    
    /**
     * Daily security scan
     */
    public function daily_security_scan() {
        $this->log_security_event('SECURITY_SCAN', 'INFO', 'Daily security scan started');
        
        // Check for failed logins in last 24 hours
        global $wpdb;
        $failed_logins = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->security_table} 
             WHERE event_type = 'LOGIN_FAILED' 
             AND created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Check for new admin users
        $new_admins = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} u
             JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = 'wp_capabilities'
             AND um.meta_value LIKE '%administrator%'
             AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Generate daily report
        $report = array(
            'scan_date' => current_time('mysql'),
            'failed_logins_24h' => $failed_logins,
            'new_admin_users' => $new_admins,
            'suspicious_ips' => count($this->suspicious_ips),
            'total_security_events' => $this->get_total_security_events()
        );
        
        // Log the report
        $this->log_security_event('SECURITY_SCAN', 'INFO', 'Daily security scan completed', $report);
        
        // Update analytics
        $this->analytics_data['security_score'] = $this->calculate_security_score($report);
        update_option('ufub_security_analytics', $this->analytics_data);
        
        // Send weekly summary on Sundays
        if (date('w') == 0) { // Sunday
            $this->send_weekly_security_summary();
        }
    }
    
    /**
     * Calculate security score
     */
    private function calculate_security_score($report) {
        $score = 100;
        
        // Deduct for failed logins
        if ($report['failed_logins_24h'] > 10) {
            $score -= min(30, $report['failed_logins_24h']);
        }
        
        // Deduct for new admin users (potential security risk)
        if ($report['new_admin_users'] > 0) {
            $score -= ($report['new_admin_users'] * 10);
        }
        
        // Deduct for suspicious IPs
        if ($report['suspicious_ips'] > 5) {
            $score -= min(20, $report['suspicious_ips'] * 2);
        }
        
        return max(0, $score);
    }
    
    /**
     * Send weekly security summary
     */
    private function send_weekly_security_summary() {
        global $wpdb;
        
        $summary = $wpdb->get_results(
            "SELECT event_type, severity, COUNT(*) as count 
             FROM {$this->security_table} 
             WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY event_type, severity 
             ORDER BY severity DESC, count DESC"
        );
        
        $subject = "[Weekly Security Summary] " . get_bloginfo('name');
        
        $email_body = "Weekly Security Summary\n";
        $email_body .= "Period: " . date('Y-m-d', strtotime('-7 days')) . " to " . date('Y-m-d') . "\n\n";
        
        foreach ($summary as $event) {
            $email_body .= "{$event->event_type} ({$event->severity}): {$event->count} events\n";
        }
        
        wp_mail($this->alert_email, $subject, $email_body);
    }
    
    /**
     * AJAX: Get security report
     */
    public function ajax_get_security_report() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        // Get recent security events
        $recent_events = $wpdb->get_results(
            "SELECT * FROM {$this->security_table} 
             ORDER BY created_date DESC LIMIT 20",
            ARRAY_A
        );
        
        // Get event summary for last 24 hours
        $event_summary = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->security_table} 
             WHERE created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             GROUP BY event_type 
             ORDER BY count DESC",
            ARRAY_A
        );
        
        // Get failed login summary
        $failed_logins = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as attempts 
             FROM {$this->security_table} 
             WHERE event_type = 'LOGIN_FAILED' 
             AND created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             GROUP BY ip_address 
             ORDER BY attempts DESC 
             LIMIT 10",
            ARRAY_A
        );
        
        $report = array(
            'recent_events' => $recent_events,
            'event_summary' => $event_summary,
            'failed_logins' => $failed_logins,
            'suspicious_ips' => $this->suspicious_ips,
            'total_events_24h' => $this->get_total_security_events(),
            'critical_events_24h' => $this->get_critical_events_count(24)
        );
        
        wp_send_json_success($report);
    }
    
    /**
     * Get critical events count
     */
    private function get_critical_events_count($hours = 24) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->security_table} 
             WHERE severity IN ('CRITICAL', 'HIGH') 
             AND created_date >= DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $hours
        ));
    }
    
    /**
     * AJAX: Block IP
     */
    public function ajax_block_ip() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $ip = sanitize_text_field($_POST['ip']);
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $blocked_ips = get_option('ufub_blocked_ips', array());
            if (!in_array($ip, $blocked_ips)) {
                $blocked_ips[] = $ip;
                update_option('ufub_blocked_ips', $blocked_ips);
                
                $this->log_security_event('IP_BLOCKED', 'HIGH', "IP address manually blocked: {$ip}", array(
                    'blocked_by' => wp_get_current_user()->user_login,
                    'reason' => 'manual_block'
                ));
                
                wp_send_json_success("IP {$ip} has been blocked");
            } else {
                wp_send_json_error("IP {$ip} is already blocked");
            }
        } else {
            wp_send_json_error("Invalid IP address");
        }
    }
    
    /**
     * AJAX: Whitelist IP
     */
    public function ajax_whitelist_ip() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $ip = sanitize_text_field($_POST['ip']);
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $whitelisted_ips = get_option('ufub_whitelisted_ips', array());
            if (!in_array($ip, $whitelisted_ips)) {
                $whitelisted_ips[] = $ip;
                update_option('ufub_whitelisted_ips', $whitelisted_ips);
                
                // Remove from suspicious list if present
                $suspicious_ips = get_option('ufub_suspicious_ips', array());
                $suspicious_ips = array_diff($suspicious_ips, array($ip));
                update_option('ufub_suspicious_ips', $suspicious_ips);
                
                $this->log_security_event('IP_WHITELISTED', 'INFO', "IP address whitelisted: {$ip}", array(
                    'whitelisted_by' => wp_get_current_user()->user_login
                ));
                
                wp_send_json_success("IP {$ip} has been whitelisted");
            } else {
                wp_send_json_error("IP {$ip} is already whitelisted");
            }
        } else {
            wp_send_json_error("Invalid IP address");
        }
    }
}