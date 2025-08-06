<?php
/**
 * Follow Up Boss Events Handler - Complete Enhanced v2.1.2
 * 
 * Complete events management with health monitoring, analytics, and diagnostics
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Events
 * @version 2.1.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Events {
    
    private static $instance = null;
    private $api;
    private $analytics_data = array();
    private $health_status = array();
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
        $this->api = FUB_API::get_instance();
        $this->init_analytics();
        
        // Hook into WordPress events
        add_action('wp_footer', array($this, 'track_page_view'));
        add_action('comment_post', array($this, 'track_comment_event'), 10, 2);
        add_action('user_register', array($this, 'track_registration_event'));
        
        if (UFUB_DEBUG) {
            ufub_log_info('FUB Events handler initialized');
        }
    }
    
    /**
     * Initialize analytics tracking
     */
    private function init_analytics() {
        $this->analytics_data = array(
            'total_events' => 0,
            'successful_events' => 0,
            'failed_events' => 0,
            'events_today' => 0,
            'last_event_time' => null,
            'event_types' => array(),
            'daily_events' => array()
        );
        
        $this->performance_metrics = array(
            'avg_processing_time' => 0,
            'slowest_event' => 0,
            'fastest_event' => PHP_INT_MAX,
            'queue_size' => 0,
            'processing_errors' => 0
        );
        
        // Load existing analytics
        $this->load_analytics_data();
    }
    
    /**
     * PHASE 1A INTEGRATION: Component Health Check
     */
    public function health_check() {
        $start_time = microtime(true);
        
        // Check event processing health
        $event_processing = $this->check_event_processing_health();
        $api_connectivity = $this->check_api_connectivity();
        $queue_health = $this->check_queue_health();
        $performance = $this->check_performance_health();
        
        $health_checks = array(
            'event_processing' => $event_processing['status'],
            'api_connectivity' => $api_connectivity['status'],
            'queue_health' => $queue_health['status'],
            'performance' => $performance['status']
        );
        
        $passed_checks = count(array_filter($health_checks, function($status) {
            return $status === 'pass';
        }));
        
        $health_score = round(($passed_checks / count($health_checks)) * 100);
        
        if ($health_score >= 90) {
            $status = 'healthy';
        } elseif ($health_score >= 70) {
            $status = 'warning';
        } else {
            $status = 'critical';
        }
        
        $this->health_status = array(
            'status' => $status,
            'score' => $health_score,
            'checks' => $health_checks,
            'details' => array(
                'event_processing' => $event_processing,
                'api_connectivity' => $api_connectivity,
                'queue_health' => $queue_health,
                'performance' => $performance
            ),
            'metrics' => array(
                'events_today' => $this->analytics_data['events_today'],
                'success_rate' => $this->calculate_success_rate(),
                'avg_processing_time' => $this->performance_metrics['avg_processing_time'],
                'queue_size' => $this->performance_metrics['queue_size']
            ),
            'last_check' => current_time('mysql'),
            'check_duration' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
        );
        
        return $this->health_status;
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Diagnostics
     */
    public function get_diagnostics() {
        return array(
            'component_info' => array(
                'class' => get_class($this),
                'version' => '2.1.2',
                'memory_usage' => memory_get_usage(true)
            ),
            'event_statistics' => array(
                'total_events' => $this->analytics_data['total_events'],
                'events_today' => $this->analytics_data['events_today'],
                'success_rate' => $this->calculate_success_rate(),
                'event_types_tracked' => array_keys($this->analytics_data['event_types'])
            ),
            'performance_data' => array(
                'avg_processing_time' => $this->performance_metrics['avg_processing_time'],
                'slowest_event' => $this->performance_metrics['slowest_event'],
                'fastest_event' => $this->performance_metrics['fastest_event'] === PHP_INT_MAX ? 0 : $this->performance_metrics['fastest_event'],
                'processing_errors' => $this->performance_metrics['processing_errors']
            ),
            'queue_status' => array(
                'current_size' => $this->performance_metrics['queue_size'],
                'pending_events' => $this->get_pending_events_count()
            ),
            'recent_events' => $this->get_recent_events(10)
        );
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Analytics
     */
    public function get_analytics() {
        return array(
            'overview' => array(
                'total_events' => $this->analytics_data['total_events'],
                'events_today' => $this->analytics_data['events_today'],
                'success_rate' => $this->calculate_success_rate(),
                'avg_processing_time' => $this->performance_metrics['avg_processing_time']
            ),
            'event_breakdown' => $this->analytics_data['event_types'],
            'daily_trends' => $this->get_daily_trends(),
            'performance_metrics' => array(
                'processing_speed' => $this->calculate_processing_speed(),
                'error_rate' => $this->calculate_error_rate(),
                'queue_efficiency' => $this->calculate_queue_efficiency()
            ),
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Enhanced Error Handling
     */
    public function handle_error($error, $context = array()) {
        $this->analytics_data['failed_events']++;
        $this->performance_metrics['processing_errors']++;
        
        $error_data = array(
            'component' => get_class($this),
            'method' => $context['method'] ?? 'unknown',
            'event_type' => $context['event_type'] ?? 'unknown',
            'context' => $context,
            'timestamp' => current_time('mysql')
        );
        
        if (function_exists('ufub_log_error')) {
            ufub_log_error('Events processing error: ' . (is_string($error) ? $error : $error->get_error_message()), $error_data);
        }
        
        $this->save_analytics_data();
        
        return new WP_Error('events_error', is_string($error) ? $error : $error->get_error_message(), $error_data);
    }
    
    /**
     * CRITICAL: Track Event - Main event processing method
     * 
     * @param string $event_type Event type
     * @param array $event_data Event data
     * @param array $person_data Person data
     * @return array|WP_Error Result or error
     */
    public function track_event($event_type, $event_data = array(), $person_data = array()) {
        $start_time = microtime(true);
        
        try {
            // Validate inputs
            if (empty($event_type)) {
                return $this->handle_error('Event type is required', array(
                    'method' => 'track_event',
                    'event_type' => $event_type
                ));
            }
            
            // Prepare event data
            $processed_event = array(
                'type' => sanitize_text_field($event_type),
                'happenedAt' => current_time('mysql'),
                'message' => $event_data['message'] ?? "User {$event_type} event",
                'source' => home_url(),
                'systemKey' => get_option('ufub_system_key', 'ufub_wordpress_integration')
            );
            
            // Add person data if provided
            if (!empty($person_data)) {
                $processed_event['person'] = $this->prepare_person_data($person_data);
                if (is_wp_error($processed_event['person'])) {
                    return $this->handle_error($processed_event['person'], array(
                        'method' => 'track_event',
                        'event_type' => $event_type
                    ));
                }
            }
            
            // Add custom fields
            if (!empty($event_data['customFields'])) {
                $processed_event['customFields'] = $event_data['customFields'];
            }
            
            // Add property data if it's a property event
            if (!empty($event_data['property'])) {
                $processed_event['property'] = $event_data['property'];
            }
            
            // Send to Follow Up Boss
            $api_result = $this->api->create_event($processed_event);
            
            $processing_time = microtime(true) - $start_time;
            
            if (is_wp_error($api_result) || !$api_result['success']) {
                $this->analytics_data['failed_events']++;
                
                return $this->handle_error($api_result, array(
                    'method' => 'track_event',
                    'event_type' => $event_type,
                    'event_data' => $processed_event,
                    'processing_time' => $processing_time
                ));
            }
            
            // Update analytics on success
            $this->analytics_data['total_events']++;
            $this->analytics_data['events_today']++;
            $this->analytics_data['successful_events']++;
            $this->analytics_data['last_event_time'] = current_time('mysql');
            
            // Track event type
            if (!isset($this->analytics_data['event_types'][$event_type])) {
                $this->analytics_data['event_types'][$event_type] = 0;
            }
            $this->analytics_data['event_types'][$event_type]++;
            
            // Update performance metrics
            $this->update_performance_metrics($processing_time);
            
            // Save analytics
            $this->save_analytics_data();
            
            if (UFUB_DEBUG) {
                ufub_log_info('Event tracked successfully', array(
                    'event_type' => $event_type,
                    'event_id' => $api_result['data']['id'] ?? 'unknown',
                    'processing_time' => round($processing_time * 1000, 2) . 'ms'
                ));
            }
            
            return array(
                'success' => true,
                'event_id' => $api_result['data']['id'] ?? null,
                'event_type' => $event_type,
                'processing_time' => round($processing_time * 1000, 2),
                'analytics' => array(
                    'events_today' => $this->analytics_data['events_today'],
                    'success_rate' => $this->calculate_success_rate()
                )
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e->getMessage(), array(
                'method' => 'track_event',
                'event_type' => $event_type,
                'exception' => array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * Track page view event
     */
    public function track_page_view() {
        // Only track for logged-in users or if tracking is enabled
        if (!is_user_logged_in() && !get_option('ufub_track_anonymous', false)) {
            return;
        }
        
        // Skip admin pages
        if (is_admin()) {
            return;
        }
        
        $person_data = array();
        
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $person_data = array(
                'firstName' => $user->first_name ?: $user->display_name,
                'lastName' => $user->last_name ?: 'User',
                'email' => $user->user_email
            );
        }
        
        $event_data = array(
            'message' => 'Page viewed: ' . get_the_title(),
            'customFields' => array(
                'page_url' => get_permalink(),
                'page_title' => get_the_title(),
                'referrer' => wp_get_referer()
            )
        );
        
        $this->track_event('PageView', $event_data, $person_data);
    }
    
    /**
     * Track comment event
     */
    public function track_comment_event($comment_id, $comment_approved) {
        if ($comment_approved !== 1) {
            return; // Only track approved comments
        }
        
        $comment = get_comment($comment_id);
        if (!$comment) {
            return;
        }
        
        $person_data = array(
            'firstName' => $comment->comment_author,
            'lastName' => 'Commenter',
            'email' => $comment->comment_author_email
        );
        
        $event_data = array(
            'message' => 'Comment posted on: ' . get_the_title($comment->comment_post_ID),
            'customFields' => array(
                'comment_content' => substr($comment->comment_content, 0, 200),
                'post_title' => get_the_title($comment->comment_post_ID),
                'post_url' => get_permalink($comment->comment_post_ID)
            )
        );
        
        $this->track_event('Comment', $event_data, $person_data);
    }
    
    /**
     * Track user registration event
     */
    public function track_registration_event($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $person_data = array(
            'firstName' => $user->first_name ?: $user->display_name,
            'lastName' => $user->last_name ?: 'User',
            'email' => $user->user_email
        );
        
        $event_data = array(
            'message' => 'User registered on website',
            'customFields' => array(
                'registration_date' => $user->user_registered,
                'user_role' => implode(', ', $user->roles)
            )
        );
        
        $this->track_event('Registration', $event_data, $person_data);
    }
    
    /**
     * Prepare person data for events
     */
    private function prepare_person_data($person_data) {
        $person = array();
        
        // Ensure first name
        if (!empty($person_data['firstName'])) {
            $person['firstName'] = sanitize_text_field($person_data['firstName']);
        } elseif (!empty($person_data['first_name'])) {
            $person['firstName'] = sanitize_text_field($person_data['first_name']);
        } else {
            $person['firstName'] = 'Website';
        }
        
        // Ensure last name
        if (!empty($person_data['lastName'])) {
            $person['lastName'] = sanitize_text_field($person_data['lastName']);
        } elseif (!empty($person_data['last_name'])) {
            $person['lastName'] = sanitize_text_field($person_data['last_name']);
        } else {
            $person['lastName'] = 'Visitor';
        }
        
        // Email
        if (!empty($person_data['email']) && is_email($person_data['email'])) {
            $person['emails'] = array(array('value' => sanitize_email($person_data['email'])));
        }
        
        // Phone
        if (!empty($person_data['phone'])) {
            $person['phones'] = array(array('value' => sanitize_text_field($person_data['phone'])));
        }
        
        return $person;
    }
    
    // Health check methods
    
    private function check_event_processing_health() {
        $success_rate = $this->calculate_success_rate();
        
        if ($success_rate < 80) {
            return array(
                'status' => 'fail',
                'message' => 'Low event processing success rate',
                'details' => "Success rate: {$success_rate}%"
            );
        } elseif ($success_rate < 95) {
            return array(
                'status' => 'warning',
                'message' => 'Event processing success rate needs attention',
                'details' => "Success rate: {$success_rate}%"
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'Event processing healthy',
            'details' => "Success rate: {$success_rate}%"
        );
    }
    
    private function check_api_connectivity() {
        if (!$this->api) {
            return array(
                'status' => 'fail',
                'message' => 'API instance not available',
                'details' => 'FUB_API instance not initialized'
            );
        }
        
        // Check API health
        $api_health = $this->api->health_check();
        
        if ($api_health['score'] < 70) {
            return array(
                'status' => 'fail',
                'message' => 'API connectivity issues',
                'details' => "API health score: {$api_health['score']}%"
            );
        } elseif ($api_health['score'] < 90) {
            return array(
                'status' => 'warning',
                'message' => 'API connectivity concerns',
                'details' => "API health score: {$api_health['score']}%"
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'API connectivity healthy',
            'details' => "API health score: {$api_health['score']}%"
        );
    }
    
    private function check_queue_health() {
        $queue_size = $this->performance_metrics['queue_size'];
        
        if ($queue_size > 100) {
            return array(
                'status' => 'fail',
                'message' => 'Event queue backlog critical',
                'details' => "Queue size: {$queue_size} events"
            );
        } elseif ($queue_size > 50) {
            return array(
                'status' => 'warning',
                'message' => 'Event queue backlog growing',
                'details' => "Queue size: {$queue_size} events"
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'Event queue healthy',
            'details' => "Queue size: {$queue_size} events"
        );
    }
    
    private function check_performance_health() {
        $avg_time = $this->performance_metrics['avg_processing_time'];
        
        if ($avg_time > 2000) { // 2 seconds
            return array(
                'status' => 'fail',
                'message' => 'Event processing too slow',
                'details' => "Average time: {$avg_time}ms"
            );
        } elseif ($avg_time > 1000) { // 1 second
            return array(
                'status' => 'warning',
                'message' => 'Event processing slower than optimal',
                'details' => "Average time: {$avg_time}ms"
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'Event processing performance good',
            'details' => "Average time: {$avg_time}ms"
        );
    }
    
    // Analytics and utility methods
    
    private function calculate_success_rate() {
        $total = $this->analytics_data['total_events'];
        $successful = $this->analytics_data['successful_events'];
        
        return $total > 0 ? round(($successful / $total) * 100, 2) : 100.0;
    }
    
    private function calculate_processing_speed() {
        return $this->performance_metrics['avg_processing_time'] > 0 
            ? round(1000 / $this->performance_metrics['avg_processing_time'], 2) 
            : 0;
    }
    
    private function calculate_error_rate() {
        $total = $this->analytics_data['total_events'];
        $failed = $this->analytics_data['failed_events'];
        
        return $total > 0 ? round(($failed / $total) * 100, 2) : 0;
    }
    
    private function calculate_queue_efficiency() {
        // Simple efficiency calculation based on queue size and processing speed
        $queue_size = $this->performance_metrics['queue_size'];
        $processing_speed = $this->calculate_processing_speed();
        
        if ($queue_size == 0) {
            return 100;
        }
        
        return max(0, min(100, round((100 - ($queue_size / 10)) * ($processing_speed / 10), 2)));
    }
    
    private function update_performance_metrics($processing_time) {
        $time_ms = round($processing_time * 1000, 2);
        
        // Update average (simple moving average)
        if ($this->performance_metrics['avg_processing_time'] == 0) {
            $this->performance_metrics['avg_processing_time'] = $time_ms;
        } else {
            $this->performance_metrics['avg_processing_time'] = round(
                ($this->performance_metrics['avg_processing_time'] + $time_ms) / 2,
                2
            );
        }
        
        // Update min/max
        $this->performance_metrics['slowest_event'] = max($this->performance_metrics['slowest_event'], $time_ms);
        $this->performance_metrics['fastest_event'] = min($this->performance_metrics['fastest_event'], $time_ms);
    }
    
    private function get_daily_trends() {
        return $this->analytics_data['daily_events'] ?? array();
    }
    
    private function get_pending_events_count() {
        // In a real implementation, this would check a queue table
        return $this->performance_metrics['queue_size'];
    }
    
    private function get_recent_events($limit = 10) {
        // In a real implementation, this would query recent events
        return array();
    }
    
    private function load_analytics_data() {
        $stored_analytics = get_option('ufub_events_analytics', array());
        $stored_performance = get_option('ufub_events_performance', array());
        
        if (!empty($stored_analytics)) {
            $this->analytics_data = array_merge($this->analytics_data, $stored_analytics);
        }
        
        if (!empty($stored_performance)) {
            $this->performance_metrics = array_merge($this->performance_metrics, $stored_performance);
        }
    }
    
    private function save_analytics_data() {
        update_option('ufub_events_analytics', $this->analytics_data);
        update_option('ufub_events_performance', $this->performance_metrics);
    }
}