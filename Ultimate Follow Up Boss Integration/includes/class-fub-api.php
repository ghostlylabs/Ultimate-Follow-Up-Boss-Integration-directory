<?php
/**
 * Follow Up Boss API Wrapper - Complete Enhanced v2.1.2
 * 
 * Complete API integration for Follow Up Boss CRM with enhanced health monitoring,
 * analytics, diagnostics, and Phase 1A/3 integration support
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage API
 * @version 2.1.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_API {
    
    private static $instance = null;
    private $api_key;
    private $api_url;
    private $system_key;
    private $source_domain;
    private $rate_limits = array();
    private $last_request_time = 0;
    
    // Enhanced monitoring properties
    private $health_status = array();
    private $analytics_data = array();
    private $performance_metrics = array();
    private $connection_quality = array();
    
    /**
     * Singleton instance
     */
    public static function get_instance($api_key = null) {
        if (null === self::$instance) {
            self::$instance = new self($api_key);
        } elseif ($api_key) {
            self::$instance->api_key = $api_key;
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('ufub_api_key', '');
        $this->api_url = UFUB_API_URL;
        $this->system_key = get_option('ufub_system_key', 'ufub_wordpress_integration');
        $this->source_domain = $this->get_source_domain();
        
        // Initialize enhanced monitoring
        $this->init_analytics();
        $this->init_rate_limits();
        $this->init_connection_quality();
        
        // Schedule health monitoring
        if (!wp_next_scheduled('ufub_api_health_check')) {
            wp_schedule_event(time(), 'hourly', 'ufub_api_health_check');
        }
        add_action('ufub_api_health_check', array($this, 'scheduled_health_check'));
        
        // Debug logging
        if (UFUB_DEBUG) {
            ufub_log_info('FUB API initialized with enhanced monitoring', array(
                'api_url' => $this->api_url,
                'system_key' => $this->system_key,
                'source_domain' => $this->source_domain,
                'has_api_key' => !empty($this->api_key)
            ));
        }
    }
    
    /**
     * Initialize analytics tracking
     */
    private function init_analytics() {
        $this->analytics_data = array(
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'requests_today' => 0,
            'avg_response_time' => 0,
            'uptime_percentage' => 100.0,
            'last_successful_request' => null,
            'last_failed_request' => null,
            'endpoint_stats' => array(),
            'daily_usage' => array(),
            'error_patterns' => array()
        );
        
        $this->performance_metrics = array(
            'response_times' => array(),
            'memory_usage' => array(),
            'peak_memory' => 0,
            'slowest_request' => 0,
            'fastest_request' => PHP_INT_MAX,
            'timeout_count' => 0,
            'retry_count' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0
        );
        
        // Load existing analytics
        $this->load_analytics_data();
    }
    
    /**
     * Initialize rate limiting
     */
    private function init_rate_limits() {
        // Initialize rate limits from FUB documentation with enhanced tracking
        $this->rate_limits = array(
            'events_post' => array('limit' => 'unlimited', 'window' => 10, 'used' => 0, 'reset_time' => time() + 10),
            'events_get' => array('limit' => 20, 'window' => 10, 'used' => 0, 'reset_time' => time() + 10),
            'global' => array('limit' => 250, 'window' => 10, 'used' => 0, 'reset_time' => time() + 10),
            'people_put' => array('limit' => 25, 'window' => 10, 'used' => 0, 'reset_time' => time() + 10),
            'notes' => array('limit' => 10, 'window' => 10, 'used' => 0, 'reset_time' => time() + 10)
        );
    }
    
    /**
     * Initialize connection quality monitoring
     */
    private function init_connection_quality() {
        $this->connection_quality = array(
            'latency' => array(),
            'stability_score' => 100,
            'connection_drops' => 0,
            'ssl_health' => 'unknown',
            'dns_resolution_time' => 0,
            'tcp_connect_time' => 0,
            'last_quality_check' => null,
            'quality_history' => array()
        );
        
        // Load existing quality data
        $stored_quality = get_option('ufub_api_connection_quality', array());
        if (!empty($stored_quality)) {
            $this->connection_quality = array_merge($this->connection_quality, $stored_quality);
        }
    }
    
    /**
     * PHASE 1A INTEGRATION: Component Health Check
     * 
     * @return array Detailed health status for orchestration layer
     */
    public function health_check() {
        $start_time = microtime(true);
        
        // Comprehensive health checks
        $api_connectivity = $this->check_api_connectivity_detailed();
        $authentication = $this->check_authentication_status();
        $rate_limiting = $this->check_rate_limit_status();
        $performance = $this->check_performance_status();
        $ssl_security = $this->check_ssl_security();
        
        // Calculate overall health score
        $health_checks = array(
            'api_connectivity' => $api_connectivity['status'],
            'authentication' => $authentication['status'],
            'rate_limiting' => $rate_limiting['status'],
            'performance' => $performance['status'],
            'ssl_security' => $ssl_security['status']
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
        
        // Generate actionable recommendations
        $recommendations = $this->generate_health_recommendations($health_checks, array(
            'api_connectivity' => $api_connectivity,
            'authentication' => $authentication,
            'rate_limiting' => $rate_limiting,
            'performance' => $performance,
            'ssl_security' => $ssl_security
        ));
        
        $this->health_status = array(
            'status' => $status,
            'score' => $health_score,
            'checks' => $health_checks,
            'details' => array(
                'api_connectivity' => $api_connectivity,
                'authentication' => $authentication,
                'rate_limiting' => $rate_limiting,
                'performance' => $performance,
                'ssl_security' => $ssl_security
            ),
            'metrics' => array(
                'requests_today' => $this->analytics_data['requests_today'],
                'success_rate' => $this->calculate_success_rate(),
                'avg_response_time' => $this->analytics_data['avg_response_time'],
                'uptime_percentage' => $this->analytics_data['uptime_percentage'],
                'connection_quality' => $this->connection_quality['stability_score']
            ),
            'recommendations' => $recommendations,
            'last_check' => current_time('mysql'),
            'check_duration' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
        );
        
        // Store health check history
        $this->store_health_check_history($this->health_status);
        
        return $this->health_status;
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Diagnostics Information
     * 
     * @return array Detailed diagnostic data for debug panel
     */
    public function get_diagnostics() {
        return array(
            'component_info' => array(
                'class' => get_class($this),
                'version' => '2.1.2',
                'api_version' => '1.0',
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => $this->performance_metrics['peak_memory']
            ),
            'configuration' => array(
                'api_key_configured' => !empty($this->api_key),
                'api_key_length' => strlen($this->api_key),
                'api_url' => $this->api_url,
                'system_key' => $this->system_key,
                'source_domain' => $this->source_domain,
                'ssl_verify' => true,
                'timeout' => 30
            ),
            'connection_details' => array(
                'last_successful_connection' => $this->analytics_data['last_successful_request'],
                'last_failed_connection' => $this->analytics_data['last_failed_request'],
                'dns_resolution_time' => $this->connection_quality['dns_resolution_time'],
                'tcp_connect_time' => $this->connection_quality['tcp_connect_time'],
                'ssl_handshake_time' => $this->connection_quality['ssl_health'],
                'connection_drops' => $this->connection_quality['connection_drops']
            ),
            'rate_limiting' => array(
                'current_limits' => $this->rate_limits,
                'usage_today' => $this->analytics_data['requests_today'],
                'remaining_quota' => $this->calculate_remaining_quota(),
                'next_reset' => $this->get_next_rate_limit_reset()
            ),
            'performance_data' => array(
                'total_requests' => $this->performance_metrics['total_requests'] ?? $this->analytics_data['total_requests'],
                'avg_response_time' => $this->analytics_data['avg_response_time'],
                'slowest_request' => $this->performance_metrics['slowest_request'],
                'fastest_request' => $this->performance_metrics['fastest_request'] === PHP_INT_MAX ? 0 : $this->performance_metrics['fastest_request'],
                'timeout_count' => $this->performance_metrics['timeout_count'],
                'retry_count' => $this->performance_metrics['retry_count']
            ),
            'endpoint_statistics' => $this->analytics_data['endpoint_stats'],
            'recent_errors' => $this->get_recent_api_errors(),
            'caching_stats' => array(
                'cache_hits' => $this->performance_metrics['cache_hits'],
                'cache_misses' => $this->performance_metrics['cache_misses'],
                'cache_hit_ratio' => $this->calculate_cache_hit_ratio()
            )
        );
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Analytics Data
     * 
     * @return array Analytics data for dashboard display
     */
    public function get_analytics() {
        $this->update_analytics_calculations();
        
        return array(
            'overview' => array(
                'total_requests' => $this->analytics_data['total_requests'],
                'requests_today' => $this->analytics_data['requests_today'],
                'success_rate' => $this->calculate_success_rate(),
                'avg_response_time' => $this->analytics_data['avg_response_time'],
                'uptime_percentage' => $this->analytics_data['uptime_percentage']
            ),
            'performance_trends' => array(
                'daily_usage' => $this->get_daily_usage_trends(),
                'response_time_trend' => $this->get_response_time_trends(),
                'error_rate_trend' => $this->get_error_rate_trends(),
                'endpoint_popularity' => $this->get_endpoint_popularity()
            ),
            'connection_quality' => array(
                'stability_score' => $this->connection_quality['stability_score'],
                'avg_latency' => $this->calculate_average_latency(),
                'connection_drops' => $this->connection_quality['connection_drops'],
                'ssl_health' => $this->connection_quality['ssl_health']
            ),
            'rate_limiting' => array(
                'quota_usage' => $this->calculate_quota_usage_percentage(),
                'rate_limit_hits' => $this->count_rate_limit_hits(),
                'busiest_endpoint' => $this->get_busiest_endpoint()
            ),
            'health_score' => $this->health_status['score'] ?? 0,
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Enhanced Error Handling
     * 
     * @param mixed $error Error object or message
     * @param array $context Additional context
     * @return WP_Error Standardized error response
     */
    public function handle_error($error, $context = array()) {
        $error_data = array(
            'component' => get_class($this),
            'method' => $context['method'] ?? 'unknown',
            'endpoint' => $context['endpoint'] ?? 'unknown',
            'request_data' => $context['request_data'] ?? null,
            'context' => $context,
            'diagnostic_info' => array(
                'api_configured' => !empty($this->api_key),
                'last_successful_request' => $this->analytics_data['last_successful_request'],
                'connection_quality' => $this->connection_quality['stability_score']
            ),
            'recommendations' => array(),
            'timestamp' => current_time('mysql')
        );
        
        // Track error in analytics
        $this->analytics_data['failed_requests']++;
        $this->analytics_data['last_failed_request'] = current_time('mysql');
        
        // Analyze error pattern
        $error_code = 'general_error';
        $error_message = 'Unknown error occurred';
        
        if (is_wp_error($error)) {
            $error_code = $error->get_error_code();
            $error_message = $error->get_error_message();
        } elseif (is_string($error)) {
            $error_message = $error;
        }
        
        // Update error patterns
        if (!isset($this->analytics_data['error_patterns'][$error_code])) {
            $this->analytics_data['error_patterns'][$error_code] = 0;
        }
        $this->analytics_data['error_patterns'][$error_code]++;
        
        // Generate specific recommendations
        $error_data['recommendations'] = $this->generate_error_recommendations($error_code, $context);
        
        // Update connection quality if network error
        if (in_array($error_code, array('api_connection_failed', 'timeout', 'ssl_error'))) {
            $this->connection_quality['connection_drops']++;
            $this->update_connection_stability();
        }
        
        // Log error for debugging
        if (function_exists('ufub_log_error')) {
            ufub_log_error($error_message, $error_data);
        }
        
        // Save updated analytics
        $this->save_analytics_data();
        
        return new WP_Error($error_code, $error_message, $error_data);
    }
    
    /**
     * Enhanced test connection with detailed diagnostics
     */
    public function test_connection() {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            // Detailed connection testing
            $connection_details = array();
            
            // DNS resolution test
            $dns_start = microtime(true);
            $host = parse_url($this->api_url, PHP_URL_HOST);
            $ip = gethostbyname($host);
            $connection_details['dns_resolution_time'] = round((microtime(true) - $dns_start) * 1000, 2);
            $connection_details['resolved_ip'] = $ip;
            
            // API identity test
            $response = $this->make_request('GET', '/identity');
            
            $total_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage() - $memory_start;
            
            if ($response && !isset($response['error'])) {
                // Update connection quality metrics
                $this->connection_quality['dns_resolution_time'] = $connection_details['dns_resolution_time'];
                $this->connection_quality['last_quality_check'] = current_time('mysql');
                $this->connection_quality['latency'][] = round($total_time * 1000, 2);
                
                // Keep only last 50 latency measurements
                if (count($this->connection_quality['latency']) > 50) {
                    array_shift($this->connection_quality['latency']);
                }
                
                // Update analytics
                $this->analytics_data['successful_requests']++;
                $this->analytics_data['last_successful_request'] = current_time('mysql');
                
                $result = array(
                    'success' => true,
                    'message' => 'API connection successful',
                    'data' => $response,
                    'connection_details' => array_merge($connection_details, array(
                        'total_time' => round($total_time * 1000, 2) . 'ms',
                        'memory_used' => size_format($memory_used),
                        'ssl_verified' => is_ssl(),
                        'http_version' => '1.1'
                    )),
                    'quality_score' => $this->calculate_connection_quality_score()
                );
                
                if (UFUB_DEBUG) {
                    ufub_log_info('API connection test successful', array_merge($result, array(
                        'response_time' => $total_time,
                        'memory_usage' => $memory_used
                    )));
                }
            } else {
                $this->connection_quality['connection_drops']++;
                $this->update_connection_stability();
                
                $result = array(
                    'success' => false,
                    'message' => 'API connection failed: ' . ($response['error'] ?? 'Unknown error'),
                    'data' => $response,
                    'connection_details' => $connection_details,
                    'quality_score' => $this->connection_quality['stability_score']
                );
                
                if (UFUB_DEBUG) {
                    ufub_log_error('API connection test failed', array_merge($result, array(
                        'response_time' => $total_time
                    )));
                }
            }
            
        } catch (Exception $e) {
            $this->connection_quality['connection_drops']++;
            $this->update_connection_stability();
            
            $result = array(
                'success' => false,
                'message' => 'API connection error: ' . $e->getMessage(),
                'data' => null,
                'connection_details' => array(
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ),
                'quality_score' => $this->connection_quality['stability_score']
            );
            
            if (UFUB_DEBUG) {
                ufub_log_error('API connection exception', array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'response_time' => microtime(true) - $start_time
                ));
            }
        }
        
        // Track performance metrics
        if (UFUB_DEBUG && class_exists('FUB_Debug')) {
            FUB_Debug::track_performance('API Connection Test', $start_time, $memory_start);
        }
        
        // Save analytics
        $this->save_analytics_data();
        
        return $result;
    }
    
    /**
     * ENHANCED: Create or update person in Follow Up Boss
     * With complete monitoring and "No Name" contact fix
     * 
     * @param array $person_data Person data to create/update
     * @return array|WP_Error API response or error
     */
    public function create_or_update_person($person_data) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            // Validate and enhance person data
            $enhanced_data = $this->prepare_person_data_enhanced($person_data);
            
            if (is_wp_error($enhanced_data)) {
                return $this->handle_error($enhanced_data, array(
                    'method' => 'create_or_update_person',
                    'endpoint' => '/people',
                    'request_data' => $person_data
                ));
            }
            
            // Make API request with enhanced monitoring
            $response = $this->make_request('PUT', '/people', $enhanced_data);
            
            // Track performance
            if (UFUB_DEBUG && class_exists('FUB_Debug')) {
                FUB_Debug::track_performance('Create/Update Person', $start_time, $memory_start);
            }
            
            if (isset($response['error'])) {
                return $this->handle_error($response['error'], array(
                    'method' => 'create_or_update_person',
                    'endpoint' => '/people',
                    'request_data' => $enhanced_data,
                    'api_response' => $response
                ));
            }
            
            // Log successful operation
            if (UFUB_DEBUG) {
                ufub_log_info('Person created/updated successfully', array(
                    'person_id' => $response['id'] ?? 'unknown',
                    'email' => $enhanced_data['emails'][0]['value'] ?? 'no email',
                    'name' => ($enhanced_data['firstName'] ?? '') . ' ' . ($enhanced_data['lastName'] ?? ''),
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                ));
            }
            
            // Update analytics
            $this->analytics_data['total_requests']++;
            $this->analytics_data['requests_today']++;
            $this->analytics_data['successful_requests']++;
            $this->save_analytics_data();
            
            return array(
                'success' => true,
                'data' => $response,
                'analytics' => array(
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                    'memory_used' => memory_get_usage() - $memory_start,
                    'api_calls_today' => $this->analytics_data['requests_today'],
                    'success_rate' => $this->calculate_success_rate()
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'create_or_update_person',
                'endpoint' => '/people',
                'request_data' => $person_data,
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * CRITICAL FIX: Enhanced person data preparation
     * Prevents "No Name" contacts in Follow Up Boss
     * 
     * @param array $data Raw person data
     * @return array|WP_Error Enhanced person data or error
     */
    private function prepare_person_data_enhanced($data) {
        $person = array();
        
        try {
            // CRITICAL: Ensure names are always present
            $person['firstName'] = $this->ensure_first_name($data);
            $person['lastName'] = $this->ensure_last_name($data);
            
            // Email handling with validation
            if (!empty($data['email'])) {
                if (!is_email($data['email'])) {
                    return new WP_Error('invalid_email', 'Invalid email address provided: ' . $data['email']);
                }
                $person['emails'] = array(array('value' => sanitize_email($data['email'])));
            } elseif (!empty($data['emails']) && is_array($data['emails'])) {
                $person['emails'] = array();
                foreach ($data['emails'] as $email_data) {
                    if (is_array($email_data) && !empty($email_data['value'])) {
                        if (is_email($email_data['value'])) {
                            $person['emails'][] = array('value' => sanitize_email($email_data['value']));
                        }
                    } elseif (is_string($email_data) && is_email($email_data)) {
                        $person['emails'][] = array('value' => sanitize_email($email_data));
                    }
                }
            }
            
            // Phone number handling
            if (!empty($data['phone'])) {
                $person['phones'] = array(array('value' => sanitize_text_field($data['phone'])));
            } elseif (!empty($data['phones']) && is_array($data['phones'])) {
                $person['phones'] = array();
                foreach ($data['phones'] as $phone_data) {
                    if (is_array($phone_data) && !empty($phone_data['value'])) {
                        $person['phones'][] = array('value' => sanitize_text_field($phone_data['value']));
                    } elseif (is_string($phone_data)) {
                        $person['phones'][] = array('value' => sanitize_text_field($phone_data));
                    }
                }
            }
            
            // Address handling
            if (!empty($data['address'])) {
                $person['addresses'] = array($this->prepare_address_data($data['address']));
            } elseif (!empty($data['addresses']) && is_array($data['addresses'])) {
                $person['addresses'] = array();
                foreach ($data['addresses'] as $address_data) {
                    $formatted_address = $this->prepare_address_data($address_data);
                    if (!empty($formatted_address)) {
                        $person['addresses'][] = $formatted_address;
                    }
                }
            }
            
            // Custom fields and tags
            if (!empty($data['tags']) && is_array($data['tags'])) {
                $person['tags'] = array_map('sanitize_text_field', $data['tags']);
            }
            
            if (!empty($data['customFields']) && is_array($data['customFields'])) {
                $person['customFields'] = $data['customFields'];
            }
            
            // Source tracking
            $person['source'] = $data['source'] ?? $this->source_domain;
            $person['systemKey'] = $this->system_key;
            
            // Validation
            if (empty($person['emails']) && empty($person['phones'])) {
                return new WP_Error('missing_contact_info', 'Either email or phone number is required');
            }
            
            if (UFUB_DEBUG) {
                ufub_log_info('Person data prepared', array(
                    'firstName' => $person['firstName'],
                    'lastName' => $person['lastName'],
                    'has_email' => !empty($person['emails']),
                    'has_phone' => !empty($person['phones']),
                    'source' => $person['source']
                ));
            }
            
            return $person;
            
        } catch (Exception $e) {
            return new WP_Error('data_preparation_error', 'Failed to prepare person data: ' . $e->getMessage());
        }
    }
    
    /**
     * CRITICAL: Ensure first name is always present
     * 
     * @param array $data Person data
     * @return string First name
     */
    private function ensure_first_name($data) {
        // Try explicit firstName
        if (!empty($data['firstName'])) {
            return sanitize_text_field($data['firstName']);
        }
        
        // Try first_name (underscore variant)
        if (!empty($data['first_name'])) {
            return sanitize_text_field($data['first_name']);
        }
        
        // Try to extract from full name
        if (!empty($data['name']) || !empty($data['full_name'])) {
            $full_name = $data['name'] ?? $data['full_name'];
            $name_parts = explode(' ', trim($full_name));
            if (!empty($name_parts[0])) {
                return sanitize_text_field($name_parts[0]);
            }
        }
        
        // Try to extract from email
        if (!empty($data['email'])) {
            $email_parts = explode('@', $data['email']);
            if (!empty($email_parts[0])) {
                // Extract name part before any numbers or special chars
                $name_part = preg_replace('/[^a-zA-Z].*/', '', $email_parts[0]);
                if (!empty($name_part) && strlen($name_part) > 1) {
                    return ucfirst(strtolower($name_part));
                }
            }
        }
        
        // Fallback to "Website" for lead tracking
        return 'Website';
    }
    
    /**
     * CRITICAL: Ensure last name is always present
     * 
     * @param array $data Person data
     * @return string Last name
     */
    private function ensure_last_name($data) {
        // Try explicit lastName
        if (!empty($data['lastName'])) {
            return sanitize_text_field($data['lastName']);
        }
        
        // Try last_name (underscore variant)
        if (!empty($data['last_name'])) {
            return sanitize_text_field($data['last_name']);
        }
        
        // Try to extract from full name
        if (!empty($data['name']) || !empty($data['full_name'])) {
            $full_name = $data['name'] ?? $data['full_name'];
            $name_parts = explode(' ', trim($full_name));
            if (count($name_parts) > 1) {
                return sanitize_text_field(end($name_parts));
            }
        }
        
        // Fallback to "Visitor" for lead tracking
        return 'Visitor';
    }
    
    /**
     * ENHANCED: Send property event to Follow Up Boss
     * With monitoring and analytics integration
     * 
     * @param array $event_data Event data
     * @return array|WP_Error API response or error
     */
    public function send_property_event($event_data) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            // Prepare and validate event data
            $enhanced_event = $this->prepare_property_event_data($event_data);
            
            if (is_wp_error($enhanced_event)) {
                return $this->handle_error($enhanced_event, array(
                    'method' => 'send_property_event',
                    'endpoint' => '/events',
                    'request_data' => $event_data
                ));
            }
            
            // Make API request
            $response = $this->make_request('POST', '/events', $enhanced_event);
            
            // Track performance
            if (UFUB_DEBUG && class_exists('FUB_Debug')) {
                FUB_Debug::track_performance('Send Property Event', $start_time, $memory_start);
            }
            
            if (isset($response['error'])) {
                return $this->handle_error($response['error'], array(
                    'method' => 'send_property_event',
                    'endpoint' => '/events',
                    'request_data' => $enhanced_event,
                    'api_response' => $response
                ));
            }
            
            // Log successful operation
            if (UFUB_DEBUG) {
                ufub_log_info('Property event sent successfully', array(
                    'event_id' => $response['id'] ?? 'unknown',
                    'event_type' => $enhanced_event['type'] ?? 'unknown',
                    'property_address' => $enhanced_event['property']['address'] ?? 'unknown',
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                ));
            }
            
            // Update analytics
            $this->analytics_data['total_requests']++;
            $this->analytics_data['requests_today']++;
            $this->analytics_data['successful_requests']++;
            $this->save_analytics_data();
            
            return array(
                'success' => true,
                'data' => $response,
                'analytics' => array(
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                    'memory_used' => memory_get_usage() - $memory_start,
                    'api_calls_today' => $this->analytics_data['requests_today']
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'send_property_event',
                'endpoint' => '/events',
                'request_data' => $event_data,
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * ENHANCED: Get person from Follow Up Boss
     * With caching and monitoring
     * 
     * @param string $person_id Person ID or email
     * @return array|WP_Error Person data or error
     */
    public function get_person($person_id) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            // Check cache first
            $cache_key = 'ufub_person_' . md5($person_id);
            $cached_data = get_transient($cache_key);
            
            if ($cached_data !== false && !UFUB_DEBUG) {
                $this->performance_metrics['cache_hits']++;
                
                if (UFUB_DEBUG) {
                    ufub_log_info('Person data retrieved from cache', array(
                        'person_id' => $person_id,
                        'cache_key' => $cache_key
                    ));
                }
                
                return array(
                    'success' => true,
                    'data' => $cached_data,
                    'cached' => true,
                    'analytics' => array(
                        'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                        'cache_hit' => true
                    )
                );
            }
            
            $this->performance_metrics['cache_misses']++;
            
            // Determine search method
            if (is_email($person_id)) {
                $endpoint = '/people';
                $params = array('email' => $person_id);
            } else {
                $endpoint = '/people/' . urlencode($person_id);
                $params = null;
            }
            
            // Make API request
            $response = $this->make_request('GET', $endpoint, null, $params);
            
            // Track performance
            if (UFUB_DEBUG && class_exists('FUB_Debug')) {
                FUB_Debug::track_performance('Get Person', $start_time, $memory_start);
            }
            
            if (isset($response['error'])) {
                return $this->handle_error($response['error'], array(
                    'method' => 'get_person',
                    'endpoint' => $endpoint,
                    'person_id' => $person_id,
                    'api_response' => $response
                ));
            }
            
            // Cache successful response for 15 minutes
            if (!empty($response)) {
                set_transient($cache_key, $response, 15 * MINUTE_IN_SECONDS);
            }
            
            // Log successful operation
            if (UFUB_DEBUG) {
                ufub_log_info('Person retrieved successfully', array(
                    'person_id' => $person_id,
                    'found' => !empty($response),
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                ));
            }
            
            // Update analytics
            $this->analytics_data['total_requests']++;
            $this->analytics_data['requests_today']++;
            $this->analytics_data['successful_requests']++;
            $this->save_analytics_data();
            
            return array(
                'success' => true,
                'data' => $response,
                'cached' => false,
                'analytics' => array(
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                    'cache_hit' => false,
                    'api_calls_today' => $this->analytics_data['requests_today']
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'get_person',
                'person_id' => $person_id,
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * ENHANCED: Search people in Follow Up Boss
     * With pagination and monitoring
     * 
     * @param array $search_params Search parameters
     * @return array|WP_Error Search results or error
     */
    public function search_people($search_params) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            // Validate search parameters
            if (empty($search_params)) {
                return new WP_Error('empty_search_params', 'Search parameters are required');
            }
            
            // Prepare search query
            $query_params = array();
            
            if (!empty($search_params['email'])) {
                $query_params['email'] = sanitize_email($search_params['email']);
            }
            
            if (!empty($search_params['phone'])) {
                $query_params['phone'] = sanitize_text_field($search_params['phone']);
            }
            
            if (!empty($search_params['name'])) {
                $query_params['name'] = sanitize_text_field($search_params['name']);
            }
            
            // Pagination
            if (!empty($search_params['limit'])) {
                $query_params['limit'] = absint($search_params['limit']);
            }
            
            if (!empty($search_params['offset'])) {
                $query_params['offset'] = absint($search_params['offset']);
            }
            
            // Make API request
            $response = $this->make_request('GET', '/people', null, $query_params);
            
            // Track performance
            if (UFUB_DEBUG && class_exists('FUB_Debug')) {
                FUB_Debug::track_performance('Search People', $start_time, $memory_start);
            }
            
            if (isset($response['error'])) {
                return $this->handle_error($response['error'], array(
                    'method' => 'search_people',
                    'endpoint' => '/people',
                    'search_params' => $search_params,
                    'api_response' => $response
                ));
            }
            
            // Log successful operation
            if (UFUB_DEBUG) {
                $result_count = is_array($response) ? count($response) : 0;
                ufub_log_info('People search completed', array(
                    'search_params' => $query_params,
                    'results_found' => $result_count,
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                ));
            }
            
            // Update analytics
            $this->analytics_data['total_requests']++;
            $this->analytics_data['requests_today']++;
            $this->analytics_data['successful_requests']++;
            $this->save_analytics_data();
            
            return array(
                'success' => true,
                'data' => $response,
                'search_params' => $query_params,
                'analytics' => array(
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                    'results_count' => is_array($response) ? count($response) : 0,
                    'api_calls_today' => $this->analytics_data['requests_today']
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'search_people',
                'search_params' => $search_params,
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * ENHANCED: Create event in Follow Up Boss
     * With proper categorization and monitoring
     * 
     * @param array $event_data Event data
     * @return array|WP_Error API response or error
     */
    public function create_event($event_data) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            // Prepare and validate event data
            $enhanced_event = $this->prepare_general_event_data($event_data);
            
            if (is_wp_error($enhanced_event)) {
                return $this->handle_error($enhanced_event, array(
                    'method' => 'create_event',
                    'endpoint' => '/events',
                    'request_data' => $event_data
                ));
            }
            
            // Make API request
            $response = $this->make_request('POST', '/events', $enhanced_event);
            
            // Track performance
            if (UFUB_DEBUG && class_exists('FUB_Debug')) {
                FUB_Debug::track_performance('Create Event', $start_time, $memory_start);
            }
            
            if (isset($response['error'])) {
                return $this->handle_error($response['error'], array(
                    'method' => 'create_event',
                    'endpoint' => '/events',
                    'request_data' => $enhanced_event,
                    'api_response' => $response
                ));
            }
            
            // Log successful operation
            if (UFUB_DEBUG) {
                ufub_log_info('Event created successfully', array(
                    'event_id' => $response['id'] ?? 'unknown',
                    'event_type' => $enhanced_event['type'] ?? 'unknown',
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                ));
            }
            
            // Update analytics
            $this->analytics_data['total_requests']++;
            $this->analytics_data['requests_today']++;
            $this->analytics_data['successful_requests']++;
            $this->save_analytics_data();
            
            return array(
                'success' => true,
                'data' => $response,
                'analytics' => array(
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                    'memory_used' => memory_get_usage() - $memory_start,
                    'api_calls_today' => $this->analytics_data['requests_today']
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'create_event',
                'endpoint' => '/events',
                'request_data' => $event_data,
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * ENHANCED: Add note to person
     * With monitoring and error handling
     * 
     * @param string $person_id Person ID
     * @param string $note_content Note content
     * @param array $note_data Additional note data
     * @return array|WP_Error API response or error
     */
    public function add_note($person_id, $note_content, $note_data = array()) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            if (empty($person_id) || empty($note_content)) {
                return new WP_Error('missing_required_data', 'Person ID and note content are required');
            }
            
            // Prepare note data
            $note = array(
                'personId' => $person_id,
                'body' => sanitize_textarea_field($note_content),
                'systemKey' => $this->system_key,
                'source' => $note_data['source'] ?? $this->source_domain
            );
            
            // Add optional fields
            if (!empty($note_data['subject'])) {
                $note['subject'] = sanitize_text_field($note_data['subject']);
            }
            
            if (!empty($note_data['type'])) {
                $note['type'] = sanitize_text_field($note_data['type']);
            }
            
            // Make API request
            $response = $this->make_request('POST', '/notes', $note);
            
            // Track performance
            if (UFUB_DEBUG && class_exists('FUB_Debug')) {
                FUB_Debug::track_performance('Add Note', $start_time, $memory_start);
            }
            
            if (isset($response['error'])) {
                return $this->handle_error($response['error'], array(
                    'method' => 'add_note',
                    'endpoint' => '/notes',
                    'person_id' => $person_id,
                    'request_data' => $note,
                    'api_response' => $response
                ));
            }
            
            // Log successful operation
            if (UFUB_DEBUG) {
                ufub_log_info('Note added successfully', array(
                    'note_id' => $response['id'] ?? 'unknown',
                    'person_id' => $person_id,
                    'note_length' => strlen($note_content),
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                ));
            }
            
            // Update analytics
            $this->analytics_data['total_requests']++;
            $this->analytics_data['requests_today']++;
            $this->analytics_data['successful_requests']++;
            $this->save_analytics_data();
            
            return array(
                'success' => true,
                'data' => $response,
                'analytics' => array(
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                    'api_calls_today' => $this->analytics_data['requests_today']
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'add_note',
                'person_id' => $person_id,
                'note_content' => $note_content,
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * ENHANCED: Get lists from Follow Up Boss
     * With caching and monitoring
     * 
     * @return array|WP_Error Lists data or error
     */
    public function get_lists() {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        try {
            // Check cache first
            $cache_key = 'ufub_lists_data';
            $cached_lists = get_transient($cache_key);
            
            if ($cached_lists !== false && !UFUB_DEBUG) {
                $this->performance_metrics['cache_hits']++;
                
                if (UFUB_DEBUG) {
                    ufub_log_info('Lists data retrieved from cache');
                }
                
                return array(
                    'success' => true,
                    'data' => $cached_lists,
                    'cached' => true,
                    'analytics' => array(
                        'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                        'cache_hit' => true
                    )
                );
            }
            
            $this->performance_metrics['cache_misses']++;
            
            // Make API request
            $response = $this->make_request('GET', '/lists');
            
            // Track performance
            if (UFUB_DEBUG && class_exists('FUB_Debug')) {
                FUB_Debug::track_performance('Get Lists', $start_time, $memory_start);
            }
            
            if (isset($response['error'])) {
                return $this->handle_error($response['error'], array(
                    'method' => 'get_lists',
                    'endpoint' => '/lists',
                    'api_response' => $response
                ));
            }
            
            // Cache successful response for 1 hour
            if (!empty($response)) {
                set_transient($cache_key, $response, HOUR_IN_SECONDS);
            }
            
            // Log successful operation
            if (UFUB_DEBUG) {
                $list_count = is_array($response) ? count($response) : 0;
                ufub_log_info('Lists retrieved successfully', array(
                    'list_count' => $list_count,
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                ));
            }
            
            // Update analytics
            $this->analytics_data['total_requests']++;
            $this->analytics_data['requests_today']++;
            $this->analytics_data['successful_requests']++;
            $this->save_analytics_data();
            
            return array(
                'success' => true,
                'data' => $response,
                'cached' => false,
                'analytics' => array(
                    'response_time' => round((microtime(true) - $start_time) * 1000, 2),
                    'cache_hit' => false,
                    'api_calls_today' => $this->analytics_data['requests_today']
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'get_lists',
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * Enhanced API request with comprehensive monitoring
     */
    private function make_request($method, $endpoint, $data = null, $params = null) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        // Check rate limits with enhanced tracking
        $rate_limit_result = $this->check_rate_limit_enhanced($endpoint, $method);
        if (is_wp_error($rate_limit_result)) {
            return array('error' => $rate_limit_result->get_error_message());
        }
        
        // Build URL
        $url = $this->api_url . $endpoint;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        
        // Prepare request with enhanced headers
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'X-System-Key' => $this->system_key,
                'User-Agent' => 'Ultimate-FUB-Integration/' . UFUB_VERSION,
                'X-Request-ID' => $this->generate_request_id(),
                'X-Source-Domain' => $this->source_domain
            ),
            'timeout' => 30,
            'sslverify' => true,
            'httpversion' => '1.1'
        );
        
        // Add body data for POST/PUT requests
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }
        
        // Enhanced debug logging
        if (UFUB_DEBUG) {
            ufub_log_info('FUB API Request', array(
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'has_data' => !empty($data),
                'request_id' => $args['headers']['X-Request-ID']
            ));
        }
        
        // Make request with retry logic
        $response = $this->make_request_with_retry($url, $args, $start_time);
        
        // Track endpoint statistics
        $this->track_endpoint_stats($endpoint, $method, microtime(true) - $start_time, !isset($response['error']));
        
        return $response;
    }
    
    /**
     * Make request with intelligent retry logic
     */
    private function make_request_with_retry($url, $args, $start_time, $retry_count = 0) {
        $max_retries = 3;
        $response = wp_remote_request($url, $args);
        $response_time = microtime(true) - $start_time;
        
        // Handle WordPress errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            // Retry on timeout or connection errors
            if ($retry_count < $max_retries && 
                (strpos($error_message, 'timeout') !== false || 
                 strpos($error_message, 'connection') !== false)) {
                
                $this->performance_metrics['retry_count']++;
                sleep(pow(2, $retry_count)); // Exponential backoff
                
                return $this->make_request_with_retry($url, $args, $start_time, $retry_count + 1);
            }
            
            if (UFUB_DEBUG) {
                ufub_log_error('WordPress HTTP error', array(
                    'message' => $error_message,
                    'url' => $url,
                    'retry_count' => $retry_count
                ));
            }
            
            return array('error' => "HTTP Error: {$error_message}");
        }
        
        // Process response
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        // Track response time
        $this->performance_metrics['response_times'][] = $response_time;
        if (count($this->performance_metrics['response_times']) > 100) {
            array_shift($this->performance_metrics['response_times']);
        }
        
        // Update min/max response times
        $this->performance_metrics['slowest_request'] = max($this->performance_metrics['slowest_request'], $response_time);
        $this->performance_metrics['fastest_request'] = min($this->performance_metrics['fastest_request'], $response_time);
        
        // Parse rate limit headers with enhanced tracking
        $this->parse_rate_limit_headers_enhanced($response_headers);
        
        // Debug API call tracking
        if (UFUB_DEBUG && class_exists('FUB_Debug')) {
            FUB_Debug::track_api_call(parse_url($url, PHP_URL_PATH), $args['method'], $response_code, $response_time, $args['body'] ?? null);
        }
        
        // Handle different response codes
        if ($response_code >= 200 && $response_code < 300) {
            // Success
            $decoded_response = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (UFUB_DEBUG) {
                    ufub_log_error('JSON decode error', array(
                        'json_error' => json_last_error_msg(),
                        'response_body' => substr($response_body, 0, 500),
                        'response_code' => $response_code
                    ));
                }
                
                return array('error' => 'Invalid JSON response from API');
            }
            
            // Update success metrics
            $this->analytics_data['successful_requests']++;
            $this->analytics_data['last_successful_request'] = current_time('mysql');
            
            return $decoded_response;
            
        } elseif ($response_code === 429) {
            // Rate limit exceeded - retry with backoff
            if ($retry_count < $max_retries) {
                $retry_after = $response_headers['retry-after'] ?? pow(2, $retry_count + 1);
                
                if (UFUB_DEBUG) {
                    ufub_log_error('Rate limit exceeded, retrying', array(
                        'url' => $url,
                        'retry_after' => $retry_after,
                        'retry_count' => $retry_count
                    ));
                }
                
                $this->performance_metrics['retry_count']++;
                sleep($retry_after);
                
                return $this->make_request_with_retry($url, $args, $start_time, $retry_count + 1);
            }
            
            return array('error' => "Rate limit exceeded after {$max_retries} retries");
            
        } elseif ($response_code >= 400) {
            // Client/Server error
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['message'] ?? "HTTP {$response_code} error";
            
            if (UFUB_DEBUG) {
                ufub_log_error('FUB API error', array(
                    'response_code' => $response_code,
                    'error_message' => $error_message,
                    'url' => $url,
                    'error_data' => $error_data
                ));
            }
            
            // Update failure metrics
            $this->analytics_data['failed_requests']++;
            $this->analytics_data['last_failed_request'] = current_time('mysql');
            
            return array(
                'error' => $error_message,
                'code' => $response_code,
                'data' => $error_data
            );
        }
        
        // Unexpected response
        if (UFUB_DEBUG) {
            ufub_log_error('Unexpected API response', array(
                'response_code' => $response_code,
                'response_body' => substr($response_body, 0, 500),
                'url' => $url
            ));
        }
        
        return array('error' => "Unexpected response code: {$response_code}");
    }
    
    // ========== HELPER AND UTILITY METHODS ==========
    
    /**
     * Prepare property event data
     */
    private function prepare_property_event_data($event_data) {
        try {
            $event = array();
            
            // Event type and metadata
            $event['type'] = $event_data['type'] ?? 'PropertyViewing';
            $event['message'] = $event_data['message'] ?? 'Property interest recorded';
            $event['systemKey'] = $this->system_key;
            $event['source'] = $event_data['source'] ?? $this->source_domain;
            
            // Person data (required)
            if (!empty($event_data['person'])) {
                $event['person'] = $this->prepare_person_data_enhanced($event_data['person']);
                if (is_wp_error($event['person'])) {
                    return $event['person'];
                }
            } else {
                return new WP_Error('missing_person_data', 'Person data is required for property events');
            }
            
            // Property data (required)
            if (!empty($event_data['property'])) {
                $event['property'] = $this->prepare_property_data($event_data['property']);
            } else {
                return new WP_Error('missing_property_data', 'Property data is required for property events');
            }
            
            // Additional event metadata
            if (!empty($event_data['customFields'])) {
                $event['customFields'] = $event_data['customFields'];
            }
            
            if (!empty($event_data['tags'])) {
                $event['tags'] = array_map('sanitize_text_field', $event_data['tags']);
            }
            
            return $event;
            
        } catch (Exception $e) {
            return new WP_Error('event_preparation_error', 'Failed to prepare property event data: ' . $e->getMessage());
        }
    }
    
    /**
     * Prepare general event data
     */
    private function prepare_general_event_data($event_data) {
        try {
            $event = array();
            
            // Event type and metadata
            $event['type'] = $event_data['type'] ?? 'Registration';
            $event['message'] = $event_data['message'] ?? 'User event recorded';
            $event['systemKey'] = $this->system_key;
            $event['source'] = $event_data['source'] ?? $this->source_domain;
            
            // Person data (required)
            if (!empty($event_data['person'])) {
                $event['person'] = $this->prepare_person_data_enhanced($event_data['person']);
                if (is_wp_error($event['person'])) {
                    return $event['person'];
                }
            } else {
                return new WP_Error('missing_person_data', 'Person data is required for events');
            }
            
            // Additional event metadata
            if (!empty($event_data['customFields'])) {
                $event['customFields'] = $event_data['customFields'];
            }
            
            if (!empty($event_data['tags'])) {
                $event['tags'] = array_map('sanitize_text_field', $event_data['tags']);
            }
            
            // Event timestamp
            if (!empty($event_data['happenedAt'])) {
                $event['happenedAt'] = $event_data['happenedAt'];
            } else {
                $event['happenedAt'] = current_time('mysql');
            }
            
            return $event;
            
        } catch (Exception $e) {
            return new WP_Error('event_preparation_error', 'Failed to prepare event data: ' . $e->getMessage());
        }
    }
    
    /**
     * Prepare property data
     */
    private function prepare_property_data($property_data) {
        $property = array();
        
        // Address (required)
        if (!empty($property_data['address'])) {
            if (is_string($property_data['address'])) {
                $property['address'] = sanitize_text_field($property_data['address']);
            } elseif (is_array($property_data['address'])) {
                $property['address'] = $this->format_address_string($property_data['address']);
            }
        }
        
        // Property details
        if (!empty($property_data['listPrice'])) {
            $property['listPrice'] = floatval($property_data['listPrice']);
        }
        
        if (!empty($property_data['beds'])) {
            $property['beds'] = intval($property_data['beds']);
        }
        
        if (!empty($property_data['baths'])) {
            $property['baths'] = floatval($property_data['baths']);
        }
        
        if (!empty($property_data['sqft'])) {
            $property['sqft'] = intval($property_data['sqft']);
        }
        
        if (!empty($property_data['mlsNumber'])) {
            $property['mlsNumber'] = sanitize_text_field($property_data['mlsNumber']);
        }
        
        if (!empty($property_data['propertyType'])) {
            $property['propertyType'] = sanitize_text_field($property_data['propertyType']);
        }
        
        // Location data
        if (!empty($property_data['latitude'])) {
            $property['latitude'] = floatval($property_data['latitude']);
        }
        
        if (!empty($property_data['longitude'])) {
            $property['longitude'] = floatval($property_data['longitude']);
        }
        
        return $property;
    }
    
    /**
     * Prepare address data
     */
    private function prepare_address_data($address_data) {
        if (is_string($address_data)) {
            return array('street' => sanitize_text_field($address_data));
        }
        
        if (!is_array($address_data)) {
            return array();
        }
        
        $address = array();
        
        if (!empty($address_data['street'])) {
            $address['street'] = sanitize_text_field($address_data['street']);
        }
        
        if (!empty($address_data['city'])) {
            $address['city'] = sanitize_text_field($address_data['city']);
        }
        
        if (!empty($address_data['state'])) {
            $address['state'] = sanitize_text_field($address_data['state']);
        }
        
        if (!empty($address_data['zip'])) {
            $address['zip'] = sanitize_text_field($address_data['zip']);
        }
        
        if (!empty($address_data['country'])) {
            $address['country'] = sanitize_text_field($address_data['country']);
        }
        
        return $address;
    }
    
    /**
     * Format address as string
     */
    private function format_address_string($address_data) {
        $parts = array();
        
        if (!empty($address_data['street'])) {
            $parts[] = $address_data['street'];
        }
        
        if (!empty($address_data['city'])) {
            $parts[] = $address_data['city'];
        }
        
        if (!empty($address_data['state'])) {
            $parts[] = $address_data['state'];
        }
        
        if (!empty($address_data['zip'])) {
            $parts[] = $address_data['zip'];
        }
        
        return implode(', ', array_filter($parts));
    }
    
    /**
     * Enhanced rate limit checking with detailed tracking
     */
    private function check_rate_limit_enhanced($endpoint, $method) {
        $current_time = time();
        
        // Determine rate limit context
        $context = 'global';
        if (strpos($endpoint, '/events') !== false) {
            $context = ($method === 'POST') ? 'events_post' : 'events_get';
        } elseif (strpos($endpoint, '/people') !== false && $method === 'PUT') {
            $context = 'people_put';
        } elseif (strpos($endpoint, '/notes') !== false) {
            $context = 'notes';
        }
        
        $rate_limit = &$this->rate_limits[$context];
        
        // Reset if window expired
        if ($current_time >= $rate_limit['reset_time']) {
            $rate_limit['used'] = 0;
            $rate_limit['reset_time'] = $current_time + $rate_limit['window'];
        }
        
        // Check if we're over the limit
        if ($rate_limit['limit'] !== 'unlimited' && $rate_limit['used'] >= $rate_limit['limit']) {
            $wait_time = $rate_limit['reset_time'] - $current_time;
            
            if (UFUB_DEBUG) {
                ufub_log_error('Rate limit exceeded', array(
                    'context' => $context,
                    'used' => $rate_limit['used'],
                    'limit' => $rate_limit['limit'],
                    'wait_time' => $wait_time
                ));
            }
            
            return new WP_Error('rate_limit_exceeded', "Rate limit exceeded for {$context}. Try again in {$wait_time} seconds.");
        }
        
        // Increment usage
        $rate_limit['used']++;
        $this->last_request_time = $current_time;
        
        return true;
    }
    
    /**
     * Enhanced rate limit header parsing
     */
    private function parse_rate_limit_headers_enhanced($headers) {
        if (isset($headers['x-ratelimit-limit'])) {
            $limit = (int) $headers['x-ratelimit-limit'];
            $remaining = (int) ($headers['x-ratelimit-remaining'] ?? 0);
            $window = (int) ($headers['x-ratelimit-window'] ?? 10);
            $context = $headers['x-ratelimit-context'] ?? 'global';
            
            // Update our rate limit tracking
            if (isset($this->rate_limits[$context])) {
                $this->rate_limits[$context]['limit'] = $limit;
                $this->rate_limits[$context]['used'] = $limit - $remaining;
                $this->rate_limits[$context]['reset_time'] = time() + $window;
            }
            
            if (UFUB_DEBUG) {
                ufub_log_info('Rate limit info updated', array(
                    'context' => $context,
                    'limit' => $limit,
                    'remaining' => $remaining,
                    'window' => $window,
                    'usage_percentage' => round((($limit - $remaining) / $limit) * 100, 1)
                ));
            }
            
            // Alert if approaching limits
            $usage_percentage = ($limit - $remaining) / $limit;
            if ($usage_percentage > 0.8 && $limit > 10) {
                if (function_exists('ufub_log')) {
                    ufub_log('WARNING', 'Approaching rate limit', array(
                        'context' => $context,
                        'remaining' => $remaining,
                        'limit' => $limit,
                        'usage_percentage' => round($usage_percentage * 100, 1) . '%'
                    ));
                }
            }
        }
    }
    
    // ========== HEALTH CHECK METHODS ==========
    
    /**
     * Detailed API connectivity check
     */
    private function check_api_connectivity_detailed() {
        if (empty($this->api_key)) {
            return array(
                'status' => 'fail',
                'message' => 'API key not configured',
                'details' => 'No API key found in plugin settings'
            );
        }
        
        try {
            $test_result = $this->test_connection();
            
            if ($test_result['success']) {
                return array(
                    'status' => 'pass',
                    'message' => 'API connectivity excellent',
                    'details' => array(
                        'response_time' => $test_result['connection_details']['total_time'] ?? 'unknown',
                        'quality_score' => $test_result['quality_score'] ?? 100,
                        'ssl_verified' => $test_result['connection_details']['ssl_verified'] ?? false
                    )
                );
            } else {
                return array(
                    'status' => 'fail',
                    'message' => 'API connectivity failed',
                    'details' => $test_result['message'] ?? 'Unknown connection error'
                );
            }
        } catch (Exception $e) {
            return array(
                'status' => 'fail',
                'message' => 'API connectivity exception',
                'details' => $e->getMessage()
            );
        }
    }
    
    /**
     * Check authentication status
     */
    private function check_authentication_status() {
        if (empty($this->api_key)) {
            return array(
                'status' => 'fail',
                'message' => 'No API key configured',
                'details' => 'API key is required for authentication'
            );
        }
        
        if (strlen($this->api_key) < 20) {
            return array(
                'status' => 'fail',
                'message' => 'Invalid API key format',
                'details' => 'API key appears to be too short'
            );
        }
        
        // Check if last request was successful (indicates valid auth)
        if (!empty($this->analytics_data['last_successful_request'])) {
            $last_success = strtotime($this->analytics_data['last_successful_request']);
            $time_since = time() - $last_success;
            
            if ($time_since < 3600) { // Within last hour
                return array(
                    'status' => 'pass',
                    'message' => 'Authentication verified',
                    'details' => "Last successful request: " . human_time_diff($last_success) . " ago"
                );
            }
        }
        
        return array(
            'status' => 'warning',
            'message' => 'Authentication status unknown',
            'details' => 'No recent successful requests to verify authentication'
        );
    }
    
    /**
     * Check rate limiting status
     */
    private function check_rate_limit_status() {
        $total_usage = 0;
        $total_limits = 0;
        $critical_contexts = array();
        
        foreach ($this->rate_limits as $context => $limit_data) {
            if ($limit_data['limit'] === 'unlimited') {
                continue;
            }
            
            $usage_percentage = ($limit_data['used'] / $limit_data['limit']) * 100;
            $total_usage += $limit_data['used'];
            $total_limits += $limit_data['limit'];
            
            if ($usage_percentage > 90) {
                $critical_contexts[] = $context;
            }
        }
        
        if (!empty($critical_contexts)) {
            return array(
                'status' => 'fail',
                'message' => 'Rate limits critically high',
                'details' => 'Critical contexts: ' . implode(', ', $critical_contexts)
            );
        }
        
        $overall_usage = $total_limits > 0 ? ($total_usage / $total_limits) * 100 : 0;
        
        if ($overall_usage > 80) {
            return array(
                'status' => 'warning',
                'message' => 'High rate limit usage',
                'details' => round($overall_usage, 1) . '% of rate limits used'
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'Rate limiting healthy',
            'details' => round($overall_usage, 1) . '% of rate limits used'
        );
    }
    
    /**
     * Check performance status
     */
    private function check_performance_status() {
        $avg_response_time = $this->analytics_data['avg_response_time'];
        $success_rate = $this->calculate_success_rate();
        
        if ($avg_response_time > 5000) { // 5 seconds
            return array(
                'status' => 'fail',
                'message' => 'Poor performance',
                'details' => "Average response time: {$avg_response_time}ms"
            );
        }
        
        if ($success_rate < 90) {
            return array(
                'status' => 'fail',
                'message' => 'Low success rate',
                'details' => "Success rate: {$success_rate}%"
            );
        }
        
        if ($avg_response_time > 2000 || $success_rate < 95) {
            return array(
                'status' => 'warning',
                'message' => 'Performance concerns',
                'details' => "Response time: {$avg_response_time}ms, Success rate: {$success_rate}%"
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'Performance excellent',
            'details' => "Response time: {$avg_response_time}ms, Success rate: {$success_rate}%"
        );
    }
    
    /**
     * Check SSL security status
     */
    private function check_ssl_security() {
        if (!is_ssl() && strpos($this->api_url, 'https://') === 0) {
            return array(
                'status' => 'warning',
                'message' => 'SSL not enforced on site',
                'details' => 'Site not using HTTPS but API requires it'
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'SSL security verified',
            'details' => 'SSL certificate validation enabled'
        );
    }
    
    /**
     * Generate health recommendations
     */
    private function generate_health_recommendations($checks, $details) {
        $recommendations = array();
        
        if ($checks['api_connectivity'] === 'fail') {
            $recommendations[] = 'Verify API key is correctly configured in plugin settings';
            $recommendations[] = 'Check internet connectivity and firewall rules';
            $recommendations[] = 'Test connection manually using the diagnostic tools';
        }
        
        if ($checks['authentication'] === 'fail') {
            $recommendations[] = 'Regenerate API key in Follow Up Boss dashboard';
            $recommendations[] = 'Ensure API key has sufficient permissions';
            $recommendations[] = 'Contact Follow Up Boss support if authentication continues to fail';
        }
        
        if ($checks['rate_limiting'] !== 'pass') {
            $recommendations[] = 'Reduce frequency of API requests during peak hours';
            $recommendations[] = 'Implement request queuing to smooth out traffic spikes';
            $recommendations[] = 'Contact Follow Up Boss to discuss rate limit increases';
        }
        
        if ($checks['performance'] !== 'pass') {
            $recommendations[] = 'Optimize server resources and increase memory limits';
            $recommendations[] = 'Consider implementing response caching for frequently accessed data';
            $recommendations[] = 'Review and optimize API request payload sizes';
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Generate error-specific recommendations
     */
    private function generate_error_recommendations($error_code, $context) {
        $recommendations = array();
        
        switch ($error_code) {
            case 'api_connection_failed':
                $recommendations[] = 'Check API key configuration in settings panel';
                $recommendations[] = 'Verify internet connectivity and DNS resolution';
                $recommendations[] = 'Test API connection in diagnostic tools';
                break;
                
            case 'timeout':
                $recommendations[] = 'Increase request timeout in performance settings';
                $recommendations[] = 'Check server performance and memory availability';
                $recommendations[] = 'Consider implementing request queuing for large payloads';
                break;
                
            case 'rate_limit_exceeded':
                $recommendations[] = 'Reduce API request frequency';
                $recommendations[] = 'Implement exponential backoff retry logic';
                $recommendations[] = 'Contact Follow Up Boss for rate limit review';
                break;
                
            case 'invalid_payload':
                $recommendations[] = 'Review request payload structure in debug logs';
                $recommendations[] = 'Validate all required fields are present and properly formatted';
                $recommendations[] = 'Check Follow Up Boss API documentation for payload requirements';
                break;
                
            case 'ssl_error':
                $recommendations[] = 'Verify SSL certificate is valid and current';
                $recommendations[] = 'Check server SSL/TLS configuration';
                $recommendations[] = 'Contact hosting provider if SSL issues persist';
                break;
                
            default:
                $recommendations[] = 'Review detailed error logs in debug panel';
                $recommendations[] = 'Check plugin documentation for troubleshooting steps';
                $recommendations[] = 'Contact plugin support with error details if issue persists';
        }
        
        return $recommendations;
    }
    
    // ========== ANALYTICS AND UTILITY METHODS ==========
    
    /**
     * Load analytics data from storage
     */
    private function load_analytics_data() {
        $stored_analytics = get_option('ufub_api_analytics', array());
        $stored_performance = get_option('ufub_api_performance', array());
        
        if (!empty($stored_analytics)) {
            $this->analytics_data = array_merge($this->analytics_data, $stored_analytics);
        }
        
        if (!empty($stored_performance)) {
            $this->performance_metrics = array_merge($this->performance_metrics, $stored_performance);
        }
        
        // Reset daily counters if needed
        $last_reset = get_option('ufub_api_last_reset', date('Y-m-d'));
        if ($last_reset !== date('Y-m-d')) {
            $this->reset_daily_counters();
        }
    }
    
    /**
     * Save analytics data to storage
     */
    private function save_analytics_data() {
        update_option('ufub_api_analytics', $this->analytics_data);
        update_option('ufub_api_performance', $this->performance_metrics);
        update_option('ufub_api_connection_quality', $this->connection_quality);
        update_option('ufub_api_last_updated', current_time('mysql'));
    }
    
    /**
     * Calculate success rate percentage
     */
    private function calculate_success_rate() {
        $total = $this->analytics_data['total_requests'];
        $successful = $this->analytics_data['successful_requests'];
        
        return $total > 0 ? round(($successful / $total) * 100, 2) : 100.0;
    }
    
    /**
     * Update analytics calculations
     */
    private function update_analytics_calculations() {
        // Update average response time
        if (!empty($this->performance_metrics['response_times'])) {
            $this->analytics_data['avg_response_time'] = round(
                array_sum($this->performance_metrics['response_times']) / count($this->performance_metrics['response_times']) * 1000,
                2
            );
        }
        
        // Update uptime percentage
        $total_requests = $this->analytics_data['total_requests'];
        $successful_requests = $this->analytics_data['successful_requests'];
        
        if ($total_requests > 0) {
            $this->analytics_data['uptime_percentage'] = round(($successful_requests / $total_requests) * 100, 2);
        }
        
        // Update connection stability score
        $this->update_connection_stability();
    }
    
    /**
     * Update connection stability score
     */
    private function update_connection_stability() {
        $base_score = 100;
        $drops = $this->connection_quality['connection_drops'];
        $requests = $this->analytics_data['total_requests'];
        
        if ($requests > 0) {
            $drop_rate = ($drops / $requests) * 100;
            $this->connection_quality['stability_score'] = max(0, $base_score - ($drop_rate * 2));
        }
    }
    
    /**
     * Calculate connection quality score
     */
    private function calculate_connection_quality_score() {
        $score = 100;
        
        // Factor in latency
        if (!empty($this->connection_quality['latency'])) {
            $avg_latency = array_sum($this->connection_quality['latency']) / count($this->connection_quality['latency']);
            if ($avg_latency > 2000) {
                $score -= 30; // Very slow
            } elseif ($avg_latency > 1000) {
                $score -= 15; // Slow
            } elseif ($avg_latency > 500) {
                $score -= 5; // Acceptable
            }
        }
        
        // Factor in connection drops
        $drops = $this->connection_quality['connection_drops'];
        $requests = $this->analytics_data['total_requests'];
        
        if ($requests > 0) {
            $drop_rate = ($drops / $requests) * 100;
            $score -= min(40, $drop_rate * 5); // Max 40 point penalty
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Generate unique request ID
     */
    private function generate_request_id() {
        return 'ufub_' . time() . '_' . wp_rand(1000, 9999);
    }
    
    /**
     * Track endpoint statistics
     */
    private function track_endpoint_stats($endpoint, $method, $response_time, $success) {
        $key = $method . ' ' . $endpoint;
        
        if (!isset($this->analytics_data['endpoint_stats'][$key])) {
            $this->analytics_data['endpoint_stats'][$key] = array(
                'calls' => 0,
                'successes' => 0,
                'failures' => 0,
                'avg_response_time' => 0,
                'total_response_time' => 0
            );
        }
        
        $stats = &$this->analytics_data['endpoint_stats'][$key];
        $stats['calls']++;
        $stats['total_response_time'] += $response_time;
        $stats['avg_response_time'] = $stats['total_response_time'] / $stats['calls'];
        
        if ($success) {
            $stats['successes']++;
        } else {
            $stats['failures']++;
        }
    }
    
    /**
     * Get various analytics calculations
     */
    private function calculate_average_latency() {
        if (empty($this->connection_quality['latency'])) {
            return 0;
        }
        
        return round(array_sum($this->connection_quality['latency']) / count($this->connection_quality['latency']), 2);
    }
    
    private function calculate_cache_hit_ratio() {
        $hits = $this->performance_metrics['cache_hits'];
        $misses = $this->performance_metrics['cache_misses'];
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
    
    private function calculate_remaining_quota() {
        $used = 0;
        $total = 0;
        
        foreach ($this->rate_limits as $limit_data) {
            if ($limit_data['limit'] !== 'unlimited') {
                $used += $limit_data['used'];
                $total += $limit_data['limit'];
            }
        }
        
        return max(0, $total - $used);
    }
    
    private function calculate_quota_usage_percentage() {
        $used = 0;
        $total = 0;
        
        foreach ($this->rate_limits as $limit_data) {
            if ($limit_data['limit'] !== 'unlimited') {
                $used += $limit_data['used'];
                $total += $limit_data['limit'];
            }
        }
        
        return $total > 0 ? round(($used / $total) * 100, 2) : 0;
    }
    
    private function get_next_rate_limit_reset() {
        $next_reset = PHP_INT_MAX;
        
        foreach ($this->rate_limits as $limit_data) {
            if ($limit_data['reset_time'] < $next_reset) {
                $next_reset = $limit_data['reset_time'];
            }
        }
        
        return $next_reset === PHP_INT_MAX ? time() : $next_reset;
    }
    
    private function count_rate_limit_hits() {
        return $this->analytics_data['error_patterns']['rate_limit_exceeded'] ?? 0;
    }
    
    private function get_busiest_endpoint() {
        if (empty($this->analytics_data['endpoint_stats'])) {
            return 'None';
        }
        
        $busiest = '';
        $max_calls = 0;
        
        foreach ($this->analytics_data['endpoint_stats'] as $endpoint => $stats) {
            if ($stats['calls'] > $max_calls) {
                $max_calls = $stats['calls'];
                $busiest = $endpoint;
            }
        }
        
        return $busiest ?: 'None';
    }
    
    private function get_daily_usage_trends() {
        return $this->analytics_data['daily_usage'] ?? array();
    }
    
    private function get_response_time_trends() {
        // Return last 24 hours of response time data
        return array_slice($this->performance_metrics['response_times'], -24);
    }
    
    private function get_error_rate_trends() {
        // Calculate error rate over time
        $trends = array();
        $total = $this->analytics_data['total_requests'];
        $failed = $this->analytics_data['failed_requests'];
        
        if ($total > 0) {
            $trends['current_error_rate'] = round(($failed / $total) * 100, 2);
        }
        
        return $trends;
    }
    
    private function get_endpoint_popularity() {
        $popularity = array();
        
        foreach ($this->analytics_data['endpoint_stats'] as $endpoint => $stats) {
            $popularity[$endpoint] = $stats['calls'];
        }
        
        arsort($popularity);
        return array_slice($popularity, 0, 5, true);
    }
    
    private function get_recent_api_errors() {
        // Return recent errors from log
        if (function_exists('ufub_log_info') && class_exists('FUB_Debug')) {
            return FUB_Debug::get_recent_logs(10);
        }
        
        return array();
    }
    
    private function store_health_check_history($health_status) {
        $history = get_option('ufub_api_health_history', array());
        $history[time()] = $health_status;
        
        // Keep only last 24 hours
        $cutoff = time() - (24 * 3600);
        foreach ($history as $timestamp => $check) {
            if ($timestamp < $cutoff) {
                unset($history[$timestamp]);
            }
        }
        
        update_option('ufub_api_health_history', $history);
    }
    
    private function reset_daily_counters() {
        $this->analytics_data['requests_today'] = 0;
        update_option('ufub_api_last_reset', date('Y-m-d'));
        $this->save_analytics_data();
    }
    
    /**
     * Scheduled health check
     */
    public function scheduled_health_check() {
        $health = $this->health_check();
        
        // Alert if health score drops below threshold
        if ($health['score'] < 70) {
            $this->send_health_alert($health);
        }
        
        // Store daily stats
        $today = date('Y-m-d');
        $this->analytics_data['daily_usage'][$today] = array(
            'requests' => $this->analytics_data['requests_today'],
            'success_rate' => $this->calculate_success_rate(),
            'avg_response_time' => $this->analytics_data['avg_response_time'],
            'health_score' => $health['score']
        );
        
        // Clean up old daily stats (keep 30 days)
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($this->analytics_data['daily_usage'] as $date => $stats) {
            if ($date < $cutoff_date) {
                unset($this->analytics_data['daily_usage'][$date]);
            }
        }
        
        $this->save_analytics_data();
    }
    
    /**
     * Send health alert email
     */
    private function send_health_alert($health) {
        $admin_email = get_option('admin_email');
        $subject = '[FUB Integration Alert] API Health Score Low: ' . $health['score'] . '%';
        
        $message = "The Follow Up Boss API integration health score has dropped to " . $health['score'] . "%.\n\n";
        $message .= "Issues detected:\n";
        
        foreach ($health['checks'] as $check => $status) {
            if ($status !== 'pass') {
                $message .= "- " . ucwords(str_replace('_', ' ', $check)) . ": " . strtoupper($status) . "\n";
            }
        }
        
        $message .= "\nRecommendations:\n";
        foreach ($health['recommendations'] as $recommendation) {
            $message .= "- " . $recommendation . "\n";
        }
        
        $message .= "\nPlease check the plugin's debug panel for more details.";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get source domain
     */
    private function get_source_domain() {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        return str_replace('www.', '', $domain);
    }
    
}