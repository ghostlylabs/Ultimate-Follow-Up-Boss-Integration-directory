<?php
/**
 * File: includes/class-fub-performance-config.php
 * Performance and Timeout Configuration
 * Centralized configuration for all performance-related settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class FUB_Performance_Config {
    
    // Timeout configurations (in seconds)
    const WEBHOOK_TIMEOUT = 30;
    const API_REQUEST_TIMEOUT = 25;
    const DATABASE_QUERY_TIMEOUT = 15;
    const FILE_OPERATION_TIMEOUT = 10;
    const CACHE_OPERATION_TIMEOUT = 5;
    
    // Memory configurations
    const MIN_MEMORY_LIMIT = '256M';
    const RECOMMENDED_MEMORY_LIMIT = '512M';
    const MAX_MEMORY_LIMIT = '1024M';
    
    // Rate limiting configurations
    const API_RATE_LIMIT = 100; // requests per window
    const API_RATE_WINDOW = 3600; // 1 hour in seconds
    const WEBHOOK_RATE_LIMIT = 200; // webhooks per window
    const WEBHOOK_RATE_WINDOW = 3600; // 1 hour in seconds
    
    // Cache configurations
    const CACHE_DEFAULT_TTL = 3600; // 1 hour
    const CACHE_SHORT_TTL = 300; // 5 minutes
    const CACHE_LONG_TTL = 86400; // 24 hours
    const USER_CACHE_TTL = 3600; // 1 hour
    
    // Database configurations
    const MAX_BATCH_SIZE = 100;
    const MAX_SEARCH_RESULTS = 1000;
    const LOG_RETENTION_DAYS = 30;
    
    // Performance monitoring thresholds
    const SLOW_QUERY_THRESHOLD = 2.0; // seconds
    const MEMORY_WARNING_THRESHOLD = 0.8; // 80% of limit
    const CPU_WARNING_THRESHOLD = 0.9; // 90% usage
    
    private static $instance = null;
    private $current_config = array();
    private $performance_metrics = array();
    
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
        $this->init_config();
        $this->init_monitoring();
    }
    
    /**
     * Initialize configuration
     */
    private function init_config() {
        $this->current_config = array(
            'timeouts' => array(
                'webhook' => self::WEBHOOK_TIMEOUT,
                'api_request' => self::API_REQUEST_TIMEOUT,
                'database_query' => self::DATABASE_QUERY_TIMEOUT,
                'file_operation' => self::FILE_OPERATION_TIMEOUT,
                'cache_operation' => self::CACHE_OPERATION_TIMEOUT
            ),
            'memory' => array(
                'min_limit' => self::MIN_MEMORY_LIMIT,
                'recommended_limit' => self::RECOMMENDED_MEMORY_LIMIT,
                'max_limit' => self::MAX_MEMORY_LIMIT
            ),
            'rate_limits' => array(
                'api_requests' => self::API_RATE_LIMIT,
                'api_window' => self::API_RATE_WINDOW,
                'webhook_requests' => self::WEBHOOK_RATE_LIMIT,
                'webhook_window' => self::WEBHOOK_RATE_WINDOW
            ),
            'cache' => array(
                'default_ttl' => self::CACHE_DEFAULT_TTL,
                'short_ttl' => self::CACHE_SHORT_TTL,
                'long_ttl' => self::CACHE_LONG_TTL,
                'user_cache_ttl' => self::USER_CACHE_TTL
            ),
            'database' => array(
                'max_batch_size' => self::MAX_BATCH_SIZE,
                'max_search_results' => self::MAX_SEARCH_RESULTS,
                'log_retention_days' => self::LOG_RETENTION_DAYS
            ),
            'monitoring' => array(
                'slow_query_threshold' => self::SLOW_QUERY_THRESHOLD,
                'memory_warning_threshold' => self::MEMORY_WARNING_THRESHOLD,
                'cpu_warning_threshold' => self::CPU_WARNING_THRESHOLD
            )
        );
        
        // Allow configuration override via WordPress options
        $this->load_custom_config();
    }
    
    /**
     * Load custom configuration from WordPress options
     */
    private function load_custom_config() {
        $custom_config = get_option('fub_performance_config', array());
        
        if (!empty($custom_config)) {
            $this->current_config = array_merge_recursive($this->current_config, $custom_config);
        }
    }
    
    /**
     * Get configuration value
     */
    public function get($section, $key = null) {
        if ($key === null) {
            return isset($this->current_config[$section]) ? $this->current_config[$section] : array();
        }
        
        return isset($this->current_config[$section][$key]) ? $this->current_config[$section][$key] : null;
    }
    
    /**
     * Set configuration value
     */
    public function set($section, $key, $value) {
        if (!isset($this->current_config[$section])) {
            $this->current_config[$section] = array();
        }
        
        $this->current_config[$section][$key] = $value;
        
        // Save to WordPress options
        $custom_config = get_option('fub_performance_config', array());
        if (!isset($custom_config[$section])) {
            $custom_config[$section] = array();
        }
        $custom_config[$section][$key] = $value;
        
        update_option('fub_performance_config', $custom_config);
    }
    
    /**
     * Initialize performance monitoring
     */
    private function init_monitoring() {
        $this->performance_metrics = array(
            'requests' => array(),
            'memory_usage' => array(),
            'query_times' => array(),
            'cache_hits' => 0,
            'cache_misses' => 0
        );
        
        // Hook into WordPress for monitoring
        add_action('init', array($this, 'start_performance_monitoring'));
        add_action('shutdown', array($this, 'end_performance_monitoring'));
    }
    
    /**
     * Start performance monitoring for the current request
     */
    public function start_performance_monitoring() {
        $this->performance_metrics['request_start'] = microtime(true);
        $this->performance_metrics['memory_start'] = memory_get_usage(true);
        
        // Set memory limit if needed
        $this->ensure_memory_limit();
        
        // Set timeout for webhook requests
        if ($this->is_webhook_request()) {
            $this->set_webhook_timeouts();
        }
    }
    
    /**
     * End performance monitoring and log metrics
     */
    public function end_performance_monitoring() {
        if (!isset($this->performance_metrics['request_start'])) {
            return;
        }
        
        $execution_time = microtime(true) - $this->performance_metrics['request_start'];
        $memory_peak = memory_get_peak_usage(true);
        $memory_used = $memory_peak - $this->performance_metrics['memory_start'];
        
        // Log performance if thresholds exceeded
        if ($execution_time > $this->get('monitoring', 'slow_query_threshold')) {
            error_log("FUB Performance Warning: Slow request detected - {$execution_time}s execution time");
        }
        
        $memory_limit_bytes = $this->convert_to_bytes(ini_get('memory_limit'));
        $memory_usage_ratio = $memory_peak / $memory_limit_bytes;
        
        if ($memory_usage_ratio > $this->get('monitoring', 'memory_warning_threshold')) {
            error_log("FUB Performance Warning: High memory usage - " . round($memory_usage_ratio * 100, 2) . "% of limit");
        }
        
        // Store metrics for reporting
        $this->store_performance_metrics($execution_time, $memory_peak, $memory_used);
    }
    
    /**
     * Store performance metrics for reporting
     */
    private function store_performance_metrics($execution_time, $memory_peak, $memory_used) {
        $metrics = get_transient('fub_performance_metrics') ?: array();
        
        $metrics[] = array(
            'timestamp' => time(),
            'execution_time' => $execution_time,
            'memory_peak' => $memory_peak,
            'memory_used' => $memory_used,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'is_webhook' => $this->is_webhook_request()
        );
        
        // Keep only last 100 metrics
        $metrics = array_slice($metrics, -100);
        
        set_transient('fub_performance_metrics', $metrics, HOUR_IN_SECONDS);
    }
    
    /**
     * Check if current request is a webhook request
     */
    private function is_webhook_request() {
        return (
            isset($_SERVER['REQUEST_URI']) && 
            strpos($_SERVER['REQUEST_URI'], '/wp-json/fub/v1/webhook') !== false
        );
    }
    
    /**
     * Ensure adequate memory limit
     */
    public function ensure_memory_limit($required_limit = null) {
        if (!$required_limit) {
            $required_limit = $this->get('memory', 'recommended_limit');
        }
        
        $current_limit = ini_get('memory_limit');
        $current_bytes = $this->convert_to_bytes($current_limit);
        $required_bytes = $this->convert_to_bytes($required_limit);
        
        if ($current_bytes < $required_bytes && !ini_get('safe_mode')) {
            ini_set('memory_limit', $required_limit);
            return true;
        }
        
        return false;
    }
    
    /**
     * Set webhook-specific timeouts
     */
    public function set_webhook_timeouts() {
        $webhook_timeout = $this->get('timeouts', 'webhook');
        
        if (!ini_get('safe_mode')) {
            set_time_limit($webhook_timeout - 5); // 5 second buffer
        }
        
        // Set specific timeouts for webhook processing
        add_filter('http_request_timeout', function($timeout) {
            return $this->get('timeouts', 'api_request');
        });
        
        // Database timeout
        global $wpdb;
        if (method_exists($wpdb, 'set_timeout')) {
            $wpdb->set_timeout($this->get('timeouts', 'database_query'));
        }
    }
    
    /**
     * Convert memory limit string to bytes
     */
    public function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // fallthrough
            case 'm':
                $value *= 1024;
                // fallthrough
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Create timeout wrapper for operations
     */
    public function with_timeout($callback, $timeout_type = 'default', $args = array()) {
        $timeout = $this->get('timeouts', $timeout_type) ?: self::API_REQUEST_TIMEOUT;
        $start_time = microtime(true);
        
        try {
            // Set alarm for timeout (Unix systems only)
            if (function_exists('pcntl_alarm') && function_exists('pcntl_signal')) {
                pcntl_signal(SIGALRM, function() {
                    throw new Exception('Operation timed out');
                });
                pcntl_alarm($timeout);
            }
            
            $result = call_user_func_array($callback, $args);
            
            // Clear alarm
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Clear alarm on exception
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }
            
            $elapsed = microtime(true) - $start_time;
            if ($elapsed >= $timeout) {
                throw new Exception("Operation timed out after {$elapsed} seconds");
            }
            
            throw $e;
        }
    }
    
    /**
     * Monitor database query performance
     */
    public function monitor_query($query, $callback) {
        $start_time = microtime(true);
        
        try {
            $result = call_user_func($callback);
            $execution_time = microtime(true) - $start_time;
            
            // Log slow queries
            if ($execution_time > $this->get('monitoring', 'slow_query_threshold')) {
                error_log("FUB Slow Query ({$execution_time}s): " . substr($query, 0, 200));
            }
            
            return $result;
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            error_log("FUB Query Error ({$execution_time}s): " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Cache operation with timeout
     */
    public function cache_get($key, $default = null) {
        $start_time = microtime(true);
        
        try {
            $result = get_transient($key);
            $execution_time = microtime(true) - $start_time;
            
            if ($result !== false) {
                $this->performance_metrics['cache_hits']++;
            } else {
                $this->performance_metrics['cache_misses']++;
                $result = $default;
            }
            
            // Log slow cache operations
            if ($execution_time > $this->get('timeouts', 'cache_operation')) {
                error_log("FUB Slow Cache Get ({$execution_time}s): {$key}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("FUB Cache Get Error: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Cache set with timeout
     */
    public function cache_set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->get('cache', 'default_ttl');
        }
        
        $start_time = microtime(true);
        
        try {
            $result = set_transient($key, $value, $ttl);
            $execution_time = microtime(true) - $start_time;
            
            // Log slow cache operations
            if ($execution_time > $this->get('timeouts', 'cache_operation')) {
                error_log("FUB Slow Cache Set ({$execution_time}s): {$key}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("FUB Cache Set Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current performance metrics
     */
    public function get_performance_metrics() {
        $stored_metrics = get_transient('fub_performance_metrics') ?: array();
        
        return array(
            'current_session' => $this->performance_metrics,
            'historical' => $stored_metrics,
            'cache_hit_ratio' => $this->calculate_cache_hit_ratio(),
            'average_response_time' => $this->calculate_average_response_time($stored_metrics),
            'memory_statistics' => $this->get_memory_statistics()
        );
    }
    
    /**
     * Calculate cache hit ratio
     */
    private function calculate_cache_hit_ratio() {
        $hits = $this->performance_metrics['cache_hits'];
        $misses = $this->performance_metrics['cache_misses'];
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
    
    /**
     * Calculate average response time
     */
    private function calculate_average_response_time($metrics) {
        if (empty($metrics)) {
            return 0;
        }
        
        $total_time = array_sum(array_column($metrics, 'execution_time'));
        return round($total_time / count($metrics), 3);
    }
    
    /**
     * Get memory statistics
     */
    private function get_memory_statistics() {
        return array(
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => $this->convert_to_bytes(ini_get('memory_limit')),
            'limit_formatted' => ini_get('memory_limit')
        );
    }
    
    /**
     * Optimize configuration based on server environment
     */
    public function optimize_for_environment() {
        $optimizations = array();
        
        // Check PHP version and adjust accordingly
        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            $optimizations['timeouts']['api_request'] = self::API_REQUEST_TIMEOUT + 5;
            $optimizations['memory']['recommended_limit'] = '512M';
        }
        
        // Check if running on VPS/dedicated server
        $server_type = $this->detect_server_type();
        if ($server_type === 'dedicated') {
            $optimizations['database']['max_batch_size'] = 200;
            $optimizations['cache']['default_ttl'] = 7200; // 2 hours
        } elseif ($server_type === 'shared') {
            $optimizations['database']['max_batch_size'] = 50;
            $optimizations['timeouts']['webhook'] = 20; // Shorter for shared hosting
        }
        
        // Apply optimizations
        foreach ($optimizations as $section => $settings) {
            foreach ($settings as $key => $value) {
                $this->set($section, $key, $value);
            }
        }
        
        return $optimizations;
    }
    
    /**
     * Detect server type
     */
    private function detect_server_type() {
        // Simple heuristics to detect server type
        $memory_limit = $this->convert_to_bytes(ini_get('memory_limit'));
        $max_execution_time = ini_get('max_execution_time');
        
        if ($memory_limit >= $this->convert_to_bytes('1G') && $max_execution_time >= 300) {
            return 'dedicated';
        } elseif ($memory_limit >= $this->convert_to_bytes('512M') && $max_execution_time >= 60) {
            return 'vps';
        } else {
            return 'shared';
        }
    }
    
    /**
     * Generate performance report
     */
    public function generate_performance_report() {
        $metrics = $this->get_performance_metrics();
        $server_info = $this->get_server_info();
        
        return array(
            'timestamp' => current_time('mysql'),
            'server_info' => $server_info,
            'performance_metrics' => $metrics,
            'configuration' => $this->current_config,
            'recommendations' => $this->get_performance_recommendations($metrics, $server_info)
        );
    }
    
    /**
     * Get server information
     */
    private function get_server_info() {
        return array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'mysql_version' => $this->get_mysql_version()
        );
    }
    
    /**
     * Get MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }
    
    /**
     * Get performance recommendations
     */
    private function get_performance_recommendations($metrics, $server_info) {
        $recommendations = array();
        
        // Memory recommendations
        $memory_usage_ratio = $metrics['memory_statistics']['peak_usage'] / $metrics['memory_statistics']['limit'];
        if ($memory_usage_ratio > 0.8) {
            $recommendations[] = array(
                'type' => 'memory',
                'severity' => 'high',
                'message' => 'Memory usage is high. Consider increasing memory_limit or optimizing queries.',
                'current_limit' => $server_info['memory_limit'],
                'recommended_limit' => $this->get('memory', 'max_limit')
            );
        }
        
        // Cache recommendations
        $cache_hit_ratio = $metrics['cache_hit_ratio'];
        if ($cache_hit_ratio < 70) {
            $recommendations[] = array(
                'type' => 'cache',
                'severity' => 'medium',
                'message' => 'Cache hit ratio is low. Consider adjusting cache TTL values.',
                'current_ratio' => $cache_hit_ratio,
                'target_ratio' => 80
            );
        }
        
        // Response time recommendations
        $avg_response_time = $metrics['average_response_time'];
        if ($avg_response_time > 2.0) {
            $recommendations[] = array(
                'type' => 'performance',
                'severity' => 'high',
                'message' => 'Average response time is high. Consider database optimization.',
                'current_time' => $avg_response_time,
                'target_time' => 1.0
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Reset performance metrics
     */
    public function reset_metrics() {
        $this->performance_metrics = array(
            'requests' => array(),
            'memory_usage' => array(),
            'query_times' => array(),
            'cache_hits' => 0,
            'cache_misses' => 0
        );
        
        delete_transient('fub_performance_metrics');
    }
    
    /**
     * Export configuration
     */
    public function export_config() {
        return array(
            'version' => UFUB_VERSION,
            'config' => $this->current_config,
            'export_date' => current_time('mysql')
        );
    }
    
    /**
     * Import configuration
     */
    public function import_config($config_data) {
        if (!isset($config_data['config']) || !is_array($config_data['config'])) {
            return new WP_Error('invalid_config', 'Invalid configuration data');
        }
        
        $this->current_config = $config_data['config'];
        update_option('fub_performance_config', $config_data['config']);
        
        return true;
    }
}