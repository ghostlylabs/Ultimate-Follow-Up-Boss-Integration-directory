<?php
/**
 * File: includes/class-fub-rest-security.php
 * Enhanced REST API Security Layer
 * Provides comprehensive security for webhook endpoints and API access
 */

if (!defined('ABSPATH')) {
    exit;
}

class FUB_REST_Security {
    
    private $security;
    private $debug;
    private $performance;
    private $rate_limiters = array();
    private $security_headers = array();
    
    public function __construct() {
        $this->security = new FUB_Security();
        $this->debug = new FUB_Debug();
        $this->performance = FUB_Performance_Config::get_instance();
        
        $this->init_security_headers();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // REST API security hooks
        add_action('rest_api_init', array($this, 'register_security_middleware'), 1);
        add_filter('rest_pre_dispatch', array($this, 'security_pre_dispatch'), 10, 3);
        add_filter('rest_post_dispatch', array($this, 'security_post_dispatch'), 10, 3);
        
        // Authentication hooks
        add_filter('determine_current_user', array($this, 'determine_webhook_user'), 20);
        add_filter('rest_authentication_errors', array($this, 'check_webhook_authentication'));
        
        // Security headers
        add_action('rest_api_init', array($this, 'add_security_headers'));
        add_action('send_headers', array($this, 'add_security_headers'));
    }
    
    /**
     * Initialize security headers
     */
    private function init_security_headers() {
        $this->security_headers = array(
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
            'X-FUB-API-Version' => UFUB_VERSION,
            'X-Robots-Tag' => 'noindex, nofollow'
        );
    }
    
    /**
     * Add security headers to responses
     */
    public function add_security_headers() {
        if (!$this->is_fub_api_request()) {
            return;
        }
        
        foreach ($this->security_headers as $header => $value) {
            if (!headers_sent()) {
                header("{$header}: {$value}");
            }
        }
    }
    
    /**
     * Register security middleware
     */
    public function register_security_middleware() {
        // Register authentication method for webhooks
        add_filter('rest_authentication_errors', array($this, 'authenticate_webhook_request'));
    }
    
    /**
     * Pre-dispatch security checks
     */
    public function security_pre_dispatch($response, $handler, $request) {
        // Only apply to FUB endpoints
        if (!$this->is_fub_api_request($request)) {
            return $response;
        }
        
        $start_time = microtime(true);
        
        try {
            // Rate limiting
            $rate_limit_result = $this->check_rate_limits($request);
            if (is_wp_error($rate_limit_result)) {
                return $rate_limit_result;
            }
            
            // IP whitelist/blacklist check
            $ip_check_result = $this->check_ip_restrictions($request);
            if (is_wp_error($ip_check_result)) {
                return $ip_check_result;
            }
            
            // Request validation
            $validation_result = $this->validate_request_format($request);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }
            
            // Webhook-specific security
            if ($this->is_webhook_request($request)) {
                $webhook_security_result = $this->validate_webhook_security($request);
                if (is_wp_error($webhook_security_result)) {
                    return $webhook_security_result;
                }
            }
            
            // Log successful security check
            $elapsed = microtime(true) - $start_time;
            $this->debug->log("Security check passed in {$elapsed}s for " . $request->get_route(), 'debug');
            
            return $response;
            
        } catch (Exception $e) {
            $this->debug->log("Security check failed: " . $e->getMessage(), 'error');
            return new WP_Error('security_check_failed', 'Security validation failed', array('status' => 403));
        }
    }
    
    /**
     * Post-dispatch security processing
     */
    public function security_post_dispatch($response, $server, $request) {
        if (!$this->is_fub_api_request($request)) {
            return $response;
        }
        
        // Add security headers to response
        if (is_a($response, 'WP_REST_Response')) {
            foreach ($this->security_headers as $header => $value) {
                $response->header($header, $value);
            }
            
            // Add rate limit headers
            $rate_limit_info = $this->get_rate_limit_info($request);
            if ($rate_limit_info) {
                $response->header('X-RateLimit-Limit', $rate_limit_info['limit']);
                $response->header('X-RateLimit-Remaining', $rate_limit_info['remaining']);
                $response->header('X-RateLimit-Reset', $rate_limit_info['reset']);
            }
        }
        
        return $response;
    }
    
    /**
     * Check if request is to FUB API endpoint
     */
    private function is_fub_api_request($request = null) {
        if (!$request) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        } else {
            $request_uri = $request->get_route();
        }
        
        return strpos($request_uri, '/wp-json/fub/') !== false;
    }
    
    /**
     * Check if request is a webhook request
     */
    private function is_webhook_request($request) {
        $route = $request->get_route();
        return strpos($route, '/webhook/') !== false;
    }
    
    /**
     * Rate limiting check
     */
    private function check_rate_limits($request) {
        $client_ip = $this->get_client_ip();
        $route = $request->get_route();
        $method = $request->get_method();
        
        // Different rate limits for different endpoints
        if ($this->is_webhook_request($request)) {
            $limit = $this->performance->get('rate_limits', 'webhook_requests');
            $window = $this->performance->get('rate_limits', 'webhook_window');
            $key = "webhook_rate_limit_{$client_ip}";
        } else {
            $limit = $this->performance->get('rate_limits', 'api_requests');
            $window = $this->performance->get('rate_limits', 'api_window');
            $key = "api_rate_limit_{$client_ip}";
        }
        
        // Check rate limit
        if (!$this->security->check_rate_limit($key, $limit, $window)) {
            $this->security->log_security_event('rate_limit_exceeded', 'warning', array(
                'ip' => $client_ip,
                'route' => $route,
                'method' => $method
            ));
            
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array(
                'status' => 429,
                'headers' => array(
                    'Retry-After' => $window
                )
            ));
        }
        
        return true;
    }
    
    /**
     * Get rate limit information for headers
     */
    private function get_rate_limit_info($request) {
        $client_ip = $this->get_client_ip();
        
        if ($this->is_webhook_request($request)) {
            $limit = $this->performance->get('rate_limits', 'webhook_requests');
            $window = $this->performance->get('rate_limits', 'webhook_window');
            $key = "webhook_rate_limit_{$client_ip}";
        } else {
            $limit = $this->performance->get('rate_limits', 'api_requests');
            $window = $this->performance->get('rate_limits', 'api_window');
            $key = "api_rate_limit_{$client_ip}";
        }
        
        $current_count = get_transient($key) ?: 0;
        $remaining = max(0, $limit - $current_count);
        $reset_time = time() + $window;
        
        return array(
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset_time
        );
    }
    
    /**
     * IP restrictions check
     */
    private function check_ip_restrictions($request) {
        $client_ip = $this->get_client_ip();
        
        // Check IP blacklist
        $blacklisted_ips = get_option('fub_blacklisted_ips', array());
        if (in_array($client_ip, $blacklisted_ips)) {
            $this->security->log_security_event('blacklisted_ip_access', 'error', array(
                'ip' => $client_ip,
                'route' => $request->get_route()
            ));
            
            return new WP_Error('ip_blacklisted', 'Access denied', array('status' => 403));
        }
        
        // Check IP whitelist for webhook endpoints
        if ($this->is_webhook_request($request)) {
            $whitelisted_ips = get_option('fub_webhook_whitelist_ips', array());
            if (!empty($whitelisted_ips) && !in_array($client_ip, $whitelisted_ips)) {
                $this->security->log_security_event('webhook_ip_not_whitelisted', 'warning', array(
                    'ip' => $client_ip,
                    'route' => $request->get_route()
                ));
                
                return new WP_Error('ip_not_whitelisted', 'IP not whitelisted for webhooks', array('status' => 403));
            }
        }
        
        return true;
    }
    
    /**
     * Validate request format
     */
    private function validate_request_format($request) {
        $method = $request->get_method();
        $content_type = $request->get_header('content-type');
        
        // Validate content type for POST/PUT requests
        if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
            if (empty($content_type)) {
                return new WP_Error('missing_content_type', 'Content-Type header required', array('status' => 400));
            }
            
            // For webhooks, require JSON
            if ($this->is_webhook_request($request) && strpos($content_type, 'application/json') === false) {
                return new WP_Error('invalid_content_type', 'Content-Type must be application/json for webhooks', array('status' => 400));
            }
        }
        
        // Validate request size
        $content_length = $request->get_header('content-length');
        if ($content_length && (int)$content_length > 1048576) { // 1MB limit
            return new WP_Error('request_too_large', 'Request body too large', array('status' => 413));
        }
        
        return true;
    }
    
    /**
     * Validate webhook-specific security
     */
    private function validate_webhook_security($request) {
        // Check for required headers
        $signature = $request->get_header('X-FUB-Signature');
        if (empty($signature)) {
            return new WP_Error('missing_signature', 'Webhook signature required', array('status' => 401));
        }
        
        // Verify timestamp to prevent replay attacks
        $timestamp = $request->get_header('X-FUB-Timestamp');
        if (!empty($timestamp)) {
            $request_time = (int)$timestamp;
            $current_time = time();
            $max_age = 300; // 5 minutes
            
            if (abs($current_time - $request_time) > $max_age) {
                return new WP_Error('request_too_old', 'Request timestamp too old', array('status' => 401));
            }
        }
        
        // Verify signature
        $body = $request->get_body();
        if (!$this->verify_webhook_signature($body, $signature)) {
            $this->security->log_security_event('invalid_webhook_signature', 'error', array(
                'ip' => $this->get_client_ip(),
                'route' => $request->get_route(),
                'signature' => substr($signature, 0, 20) . '...'
            ));
            
            return new WP_Error('invalid_signature', 'Invalid webhook signature', array('status' => 401));
        }
        
        return true;
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($payload, $signature) {
        $webhook_secret = get_option('fub_webhook_secret');
        if (empty($webhook_secret)) {
            return false;
        }
        
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
        return hash_equals($expected, $signature);
    }
    
    /**
     * Determine user for webhook requests
     */
    public function determine_webhook_user($user_id) {
        if (!$this->is_fub_api_request()) {
            return $user_id;
        }
        
        // For webhook requests, use a special system user
        if ($this->is_webhook_request()) {
            return $this->get_webhook_user_id();
        }
        
        return $user_id;
    }
    
    /**
     * Get or create webhook system user
     */
    private function get_webhook_user_id() {
        $webhook_user_id = get_option('fub_webhook_user_id');
        
        if (!$webhook_user_id || !get_user_by('id', $webhook_user_id)) {
            // Create a system user for webhook operations
            $webhook_user_id = wp_create_user(
                'fub_webhook_system',
                wp_generate_password(32),
                'webhook@' . parse_url(home_url(), PHP_URL_HOST)
            );
            
            if (!is_wp_error($webhook_user_id)) {
                update_option('fub_webhook_user_id', $webhook_user_id);
                
                // Set user meta to identify as system user
                update_user_meta($webhook_user_id, 'fub_system_user', true);
                update_user_meta($webhook_user_id, 'fub_user_type', 'webhook_system');
            }
        }
        
        return $webhook_user_id;
    }
    
    /**
     * Check webhook authentication
     */
    public function check_webhook_authentication($errors) {
        if (!$this->is_fub_api_request()) {
            return $errors;
        }
        
        // Skip authentication for webhook health check
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/webhook/health') !== false) {
            return $errors;
        }
        
        return $errors;
    }
    
    /**
     * Authenticate webhook request
     */
    public function authenticate_webhook_request($errors) {
        if (!$this->is_fub_api_request()) {
            return $errors;
        }
        
        global $wp;
        $route = $wp->query_vars['rest_route'] ?? '';
        
        // Only apply to webhook endpoints
        if (strpos($route, '/fub/v1/webhook/') === false) {
            return $errors;
        }
        
        // Health check endpoint is public
        if (strpos($route, '/webhook/health') !== false) {
            return $errors;
        }
        
        // Verify webhook signature
        $signature = $_SERVER['HTTP_X_FUB_SIGNATURE'] ?? '';
        if (empty($signature)) {
            return new WP_Error('webhook_auth_failed', 'Webhook signature required', array('status' => 401));
        }
        
        $body = file_get_contents('php://input');
        if (!$this->verify_webhook_signature($body, $signature)) {
            return new WP_Error('webhook_auth_failed', 'Invalid webhook signature', array('status' => 401));
        }
        
        // Set current user to webhook system user
        wp_set_current_user($this->get_webhook_user_id());
        
        return $errors;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Add IP to blacklist
     */
    public function blacklist_ip($ip, $reason = '') {
        $blacklisted_ips = get_option('fub_blacklisted_ips', array());
        
        if (!in_array($ip, $blacklisted_ips)) {
            $blacklisted_ips[] = $ip;
            update_option('fub_blacklisted_ips', $blacklisted_ips);
            
            $this->security->log_security_event('ip_blacklisted', 'info', array(
                'ip' => $ip,
                'reason' => $reason
            ));
        }
    }
    
    /**
     * Remove IP from blacklist
     */
    public function remove_ip_from_blacklist($ip) {
        $blacklisted_ips = get_option('fub_blacklisted_ips', array());
        
        $key = array_search($ip, $blacklisted_ips);
        if ($key !== false) {
            unset($blacklisted_ips[$key]);
            update_option('fub_blacklisted_ips', array_values($blacklisted_ips));
            
            $this->security->log_security_event('ip_removed_from_blacklist', 'info', array(
                'ip' => $ip
            ));
        }
    }
    
    /**
     * Add IP to webhook whitelist
     */
    public function whitelist_webhook_ip($ip) {
        $whitelisted_ips = get_option('fub_webhook_whitelist_ips', array());
        
        if (!in_array($ip, $whitelisted_ips)) {
            $whitelisted_ips[] = $ip;
            update_option('fub_webhook_whitelist_ips', $whitelisted_ips);
        }
    }
    
    /**
     * Get security statistics
     */
    public function get_security_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_security_logs';
        
        // Get stats for last 24 hours
        $stats = array(
            'total_requests' => 0,
            'blocked_requests' => 0,
            'rate_limited' => 0,
            'invalid_signatures' => 0,
            'blacklisted_attempts' => 0,
            'top_blocked_ips' => array(),
            'request_methods' => array(),
            'hourly_distribution' => array()
        );
        
        // Get blocked requests
        $blocked_events = $wpdb->get_results($wpdb->prepare("
            SELECT event_type, ip_address, COUNT(*) as count
            FROM {$table}
            WHERE created_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND severity IN ('warning', 'error')
            GROUP BY event_type, ip_address
            ORDER BY count DESC
            LIMIT 10
        "));
        
        foreach ($blocked_events as $event) {
            $stats['blocked_requests'] += $event->count;
            
            switch ($event->event_type) {
                case 'rate_limit_exceeded':
                    $stats['rate_limited'] += $event->count;
                    break;
                case 'invalid_webhook_signature':
                    $stats['invalid_signatures'] += $event->count;
                    break;
                case 'blacklisted_ip_access':
                    $stats['blacklisted_attempts'] += $event->count;
                    break;
            }
            
            if (!isset($stats['top_blocked_ips'][$event->ip_address])) {
                $stats['top_blocked_ips'][$event->ip_address] = 0;
            }
            $stats['top_blocked_ips'][$event->ip_address] += $event->count;
        }
        
        // Sort top blocked IPs
        arsort($stats['top_blocked_ips']);
        $stats['top_blocked_ips'] = array_slice($stats['top_blocked_ips'], 0, 5, true);
        
        return $stats;
    }
    
    /**
     * Generate security report
     */
    public function generate_security_report($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_security_logs';
        
        $report = array(
            'period' => "{$days} days",
            'generated_at' => current_time('mysql'),
            'summary' => array(),
            'events' => array(),
            'recommendations' => array()
        );
        
        // Get event summary
        $summary = $wpdb->get_results($wpdb->prepare("
            SELECT 
                event_type,
                severity,
                COUNT(*) as count,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM {$table}
            WHERE created_date > DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY event_type, severity
            ORDER BY count DESC
        ", $days));
        
        $report['summary'] = $summary;
        
        // Get detailed events
        $events = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table}
            WHERE created_date > DATE_SUB(NOW(), INTERVAL %d DAY)
            AND severity IN ('warning', 'error')
            ORDER BY created_date DESC
            LIMIT 100
        ", $days));
        
        $report['events'] = $events;
        
        // Generate recommendations
        $report['recommendations'] = $this->generate_security_recommendations($summary);
        
        return $report;
    }
    
    /**
     * Generate security recommendations
     */
    private function generate_security_recommendations($summary) {
        $recommendations = array();
        
        foreach ($summary as $event) {
            if ($event->count > 100 && $event->severity === 'error') {
                $recommendations[] = array(
                    'type' => 'high_frequency_errors',
                    'severity' => 'high',
                    'message' => "High frequency of {$event->event_type} events detected ({$event->count} occurrences)",
                    'action' => 'Consider investigating and potentially blocking problematic IPs'
                );
            }
            
            if ($event->event_type === 'rate_limit_exceeded' && $event->count > 50) {
                $recommendations[] = array(
                    'type' => 'rate_limiting',
                    'severity' => 'medium',
                    'message' => "Frequent rate limiting ({$event->count} times)",
                    'action' => 'Consider adjusting rate limits or implementing IP-based restrictions'
                );
            }
        }
        
        return $recommendations;
    }
}