<?php
/**
 * FUB Property Matcher - Enhanced v2.1.2
 * 
 * Intelligent property matching system for Follow Up Boss saved searches
 * with health monitoring and analytics integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Property_Matcher
 * @version 2.1.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Property_Matcher {
    
    private static $instance = null;
    private $api;
    private $health_status = array();
    private $analytics_data = array();
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
        $this->init_analytics();
        $this->init();
    }
    
    /**
     * Initialize analytics tracking
     */
    private function init_analytics() {
        $this->analytics_data = array(
            'matches_today' => 0,
            'total_matches' => 0,
            'avg_match_score' => 0,
            'successful_matches' => 0,
            'failed_matches' => 0,
            'avg_processing_time' => 0,
            'last_match_time' => null,
            'match_quality_distribution' => array(),
            'popular_search_criteria' => array()
        );
        
        $this->performance_metrics = array(
            'query_times' => array(),
            'memory_usage' => array(),
            'database_queries' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'slowest_match' => 0,
            'fastest_match' => PHP_INT_MAX
        );
        
        // Load existing analytics
        $stored_analytics = get_option('ufub_property_matcher_analytics', array());
        if (!empty($stored_analytics)) {
            $this->analytics_data = array_merge($this->analytics_data, $stored_analytics);
        }
        
        $stored_performance = get_option('ufub_property_matcher_performance', array());
        if (!empty($stored_performance)) {
            $this->performance_metrics = array_merge($this->performance_metrics, $stored_performance);
        }
    }
    
    /**
     * Initialize property matcher
     */
    private function init() {
        // Get API instance
        if (class_exists('FUB_API')) {
            $this->api = FUB_API::get_instance();
        }
        
        // Schedule property matching checks
        add_action('ufub_daily_property_matching', array($this, 'run_daily_matching'));
        
        if (!wp_next_scheduled('ufub_daily_property_matching')) {
            wp_schedule_event(time(), 'daily', 'ufub_daily_property_matching');
        }
        
        // AJAX handlers
        add_action('wp_ajax_ufub_run_property_matching', array($this, 'ajax_run_property_matching'));
        add_action('wp_ajax_ufub_get_matching_results', array($this, 'ajax_get_matching_results'));
        
        // Hook into new property additions
        add_action('publish_wpl_property', array($this, 'check_new_property_matches'), 10, 2);
        
        if (function_exists('ufub_log_info')) {
            ufub_log_info('FUB Property Matcher initialized with enhanced monitoring');
        }
    }
    
    /**
     * PHASE 1A INTEGRATION: Component Health Check
     * 
     * @return array Detailed health status for orchestration layer
     */
    public function health_check() {
        $start_time = microtime(true);
        
        // Property matcher health checks
        $database_connectivity = $this->check_database_connectivity();
        $search_functionality = $this->check_search_functionality();
        $matching_algorithm = $this->check_matching_algorithm();
        $api_integration = $this->check_api_integration();
        
        $health_checks = array(
            'database_connectivity' => $database_connectivity['status'],
            'search_functionality' => $search_functionality['status'],
            'matching_algorithm' => $matching_algorithm['status'],
            'api_integration' => $api_integration['status']
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
        
        $recommendations = $this->generate_matcher_recommendations($health_checks);
        
        $this->health_status = array(
            // ENHANCED FORMAT (Phase 3 Dashboard)
            'status' => $status,
            'score' => $health_score,
            'checks' => $health_checks,
            'details' => array(
                'database_connectivity' => $database_connectivity,
                'search_functionality' => $search_functionality,
                'matching_algorithm' => $matching_algorithm,
                'api_integration' => $api_integration
            ),
            'metrics' => array(
                'matches_today' => $this->analytics_data['matches_today'],
                'avg_match_score' => $this->analytics_data['avg_match_score'],
                'successful_matches' => $this->analytics_data['successful_matches'],
                'avg_processing_time' => $this->analytics_data['avg_processing_time']
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
     * BACKWARDS COMPATIBILITY: Simple error message for Phase 1A
     */
    private function get_simple_error_message($health_checks, $recommendations) {
        $failed_checks = array();
        foreach ($health_checks as $check => $status) {
            if ($status !== 'pass') {
                $failed_checks[] = str_replace('_', ' ', $check);
            }
        }
        
        $message = 'Property Matcher issues: ' . implode(', ', $failed_checks);
        
        if (!empty($recommendations)) {
            $message .= '. Recommendations: ' . implode('; ', array_slice($recommendations, 0, 2));
        }
        
        return $message;
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Diagnostics Information
     */
    public function get_diagnostics() {
        return array(
            'component_info' => array(
                'class' => get_class($this),
                'version' => '2.1.2',
                'memory_usage' => memory_get_usage(true),
                'active_searches' => $this->count_active_searches()
            ),
            'database_info' => array(
                'saved_searches_table' => $this->check_saved_searches_table(),
                'property_count' => $this->get_property_count(),
                'search_index_status' => $this->check_search_indexes()
            ),
            'performance_data' => $this->performance_metrics,
            'matching_statistics' => array(
                'total_matches' => $this->analytics_data['total_matches'],
                'average_score' => $this->analytics_data['avg_match_score'],
                'quality_distribution' => $this->analytics_data['match_quality_distribution']
            ),
            'recent_matches' => $this->get_recent_matches(5),
            'algorithm_info' => array(
                'scoring_method' => 'weighted_criteria',
                'threshold' => 70,
                'max_results' => 50
            )
        );
    }
    
    /**
     * PHASE 3 INTEGRATION: Get Analytics Data
     */
    public function get_analytics() {
        $this->update_analytics_calculations();
        
        return array(
            'overview' => array(
                'matches_today' => $this->analytics_data['matches_today'],
                'total_matches' => $this->analytics_data['total_matches'],
                'avg_match_score' => $this->analytics_data['avg_match_score'],
                'success_rate' => $this->calculate_success_rate()
            ),
            'performance_metrics' => array(
                'avg_processing_time' => $this->analytics_data['avg_processing_time'],
                'slowest_match' => $this->performance_metrics['slowest_match'],
                'fastest_match' => $this->performance_metrics['fastest_match'] === PHP_INT_MAX ? 0 : $this->performance_metrics['fastest_match'],
                'cache_hit_ratio' => $this->calculate_cache_hit_ratio()
            ),
            'match_quality' => array(
                'distribution' => $this->analytics_data['match_quality_distribution'],
                'high_quality_matches' => $this->count_high_quality_matches(),
                'average_confidence' => $this->analytics_data['avg_match_score']
            ),
            'search_trends' => array(
                'popular_criteria' => $this->analytics_data['popular_search_criteria'],
                'daily_volume' => $this->get_daily_match_volume()
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
            'diagnostic_info' => array(
                'database_connected' => $this->check_database_connectivity()['status'] === 'pass',
                'last_successful_match' => $this->analytics_data['last_match_time'],
                'active_searches' => $this->count_active_searches()
            ),
            'timestamp' => current_time('mysql')
        );
        
        // Track error in analytics
        $this->analytics_data['failed_matches']++;
        
        if (is_wp_error($error)) {
            $error_code = $error->get_error_code();
            $error_message = $error->get_error_message();
        } else {
            $error_code = 'property_matcher_error';
            $error_message = is_string($error) ? $error : 'Property matching error';
        }
        
        // Generate recommendations
        $error_data['recommendations'] = $this->generate_error_recommendations($error_code, $context);
        
        // Log error
        if (function_exists('ufub_log_error')) {
            ufub_log_error($error_message, $error_data);
        }
        
        // Save analytics
        $this->save_analytics_data();
        
        return new WP_Error($error_code, $error_message, $error_data);
    }
    
    /**
     * Check database connectivity
     */
    private function check_database_connectivity() {
        global $wpdb;
        
        try {
            // Test basic database connection
            $result = $wpdb->get_var("SELECT 1");
            
            if ($result !== '1') {
                return array(
                    'status' => 'fail',
                    'message' => 'Database connection failed',
                    'details' => 'Unable to execute basic query'
                );
            }
            
            // Check if property tables exist
            $property_table = $wpdb->prefix . 'wpl_properties';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$property_table'") === $property_table;
            
            if (!$table_exists) {
                return array(
                    'status' => 'warning',
                    'message' => 'Property table not found',
                    'details' => 'WPL properties table does not exist'
                );
            }
            
            return array(
                'status' => 'pass',
                'message' => 'Database connectivity healthy',
                'details' => 'All required tables accessible'
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'fail',
                'message' => 'Database connection exception',
                'details' => $e->getMessage()
            );
        }
    }
    
    /**
     * Check search functionality
     */
    private function check_search_functionality() {
        try {
            // Test a simple property search
            $test_criteria = array(
                'property_type' => 'residential',
                'min_price' => 100000,
                'max_price' => 500000
            );
            
            $query_args = $this->build_property_query_args($test_criteria);
            
            if (empty($query_args)) {
                return array(
                    'status' => 'fail',
                    'message' => 'Query building failed',
                    'details' => 'Unable to construct search query from criteria'
                );
            }
            
            // Test query execution
            $test_query = new WP_Query(array_merge($query_args, array('posts_per_page' => 1)));
            
            return array(
                'status' => 'pass',
                'message' => 'Search functionality operational',
                'details' => "Test query returned {$test_query->found_posts} properties"
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'fail',
                'message' => 'Search functionality exception',
                'details' => $e->getMessage()
            );
        }
    }
    
    /**
     * Check matching algorithm
     */
    private function check_matching_algorithm() {
        try {
            // Test matching algorithm with sample data
            $test_property = array(
                'id' => 'test',
                'price' => 250000,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'property_type' => 'residential',
                'location_text' => 'Test City'
            );
            
            $test_criteria = array(
                'min_price' => 200000,
                'max_price' => 300000,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'property_type' => 'residential'
            );
            
            $match_score = $this->calculate_match_score($test_property, $test_criteria);
            
            if ($match_score === 0) {
                return array(
                    'status' => 'fail',
                    'message' => 'Matching algorithm failed',
                    'details' => 'Algorithm returned zero score for valid match'
                );
            }
            
            if ($match_score < 70) {
                return array(
                    'status' => 'warning',
                    'message' => 'Matching algorithm suboptimal',
                    'details' => "Perfect match only scored {$match_score}%"
                );
            }
            
            return array(
                'status' => 'pass',
                'message' => 'Matching algorithm operational',
                'details' => "Test match scored {$match_score}%"
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'fail',
                'message' => 'Matching algorithm exception',
                'details' => $e->getMessage()
            );
        }
    }
    
    /**
     * Check API integration
     */
    private function check_api_integration() {
        if (!$this->api) {
            return array(
                'status' => 'fail',
                'message' => 'API instance not available',
                'details' => 'FUB_API class not loaded'
            );
        }
        
        // Check if API can send property events
        if (!method_exists($this->api, 'send_property_event')) {
            return array(
                'status' => 'fail',
                'message' => 'API missing required methods',
                'details' => 'send_property_event method not available'
            );
        }
        
        return array(
            'status' => 'pass',
            'message' => 'API integration healthy',
            'details' => 'All required API methods available'
        );
    }
    
    /**
     * Generate matcher-specific recommendations
     */
    private function generate_matcher_recommendations($health_checks) {
        $recommendations = array();
        
        if ($health_checks['database_connectivity'] === 'fail') {
            $recommendations[] = 'Check database connection settings';
            $recommendations[] = 'Verify WPL plugin is installed and activated';
            $recommendations[] = 'Contact hosting provider if database issues persist';
        }
        
        if ($health_checks['search_functionality'] === 'fail') {
            $recommendations[] = 'Review property search configuration';
            $recommendations[] = 'Check WPL plugin compatibility';
            $recommendations[] = 'Verify property database structure';
        }
        
        if ($health_checks['matching_algorithm'] === 'fail') {
            $recommendations[] = 'Review matching criteria configuration';
            $recommendations[] = 'Check property data quality and completeness';
            $recommendations[] = 'Consider adjusting matching thresholds';
        }
        
        if ($health_checks['api_integration'] === 'fail') {
            $recommendations[] = 'Verify FUB API configuration';
            $recommendations[] = 'Check plugin initialization order';
            $recommendations[] = 'Review API class dependencies';
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Generate error-specific recommendations
     */
    private function generate_error_recommendations($error_code, $context) {
        $recommendations = array();
        
        switch ($error_code) {
            case 'database_query_failed':
                $recommendations[] = 'Check database connectivity and permissions';
                $recommendations[] = 'Verify property table structure is intact';
                $recommendations[] = 'Review database error logs for more details';
                break;
                
            case 'no_properties_found':
                $recommendations[] = 'Verify properties exist in the database';
                $recommendations[] = 'Check if WPL plugin is properly configured';
                $recommendations[] = 'Review property import and sync settings';
                break;
                
            case 'matching_algorithm_error':
                $recommendations[] = 'Review matching criteria and weights';
                $recommendations[] = 'Check property data completeness';
                $recommendations[] = 'Consider adjusting matching thresholds';
                break;
                
            case 'api_communication_failed':
                $recommendations[] = 'Verify Follow Up Boss API credentials';
                $recommendations[] = 'Check network connectivity and firewall settings';
                $recommendations[] = 'Review API rate limiting and quotas';
                break;
                
            default:
                $recommendations[] = 'Check system logs for additional error details';
                $recommendations[] = 'Review property matcher configuration';
                $recommendations[] = 'Contact support if issues persist';
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * MISSING METHOD IMPLEMENTATIONS
     */
    
    /**
     * Count active saved searches
     */
    private function count_active_searches() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'ufub_saved_search_%' 
            AND option_value != ''
        ");
        
        return intval($count);
    }
    
    /**
     * Check saved searches table status
     */
    private function check_saved_searches_table() {
        global $wpdb;
        
        $saved_searches = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'ufub_saved_search_%'
        ");
        
        return array(
            'total_searches' => intval($saved_searches),
            'table_status' => 'using_wp_options',
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Get total property count
     */
    private function get_property_count() {
        global $wpdb;
        
        $property_table = $wpdb->prefix . 'wpl_properties';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$property_table'") !== $property_table) {
            return 0;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $property_table WHERE finalized = 1");
        
        return intval($count);
    }
    
    /**
     * Check search indexes status
     */
    private function check_search_indexes() {
        global $wpdb;
        
        $property_table = $wpdb->prefix . 'wpl_properties';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$property_table'") !== $property_table) {
            return array('status' => 'table_not_found');
        }
        
        // Check for common indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM $property_table");
        
        return array(
            'status' => 'available',
            'index_count' => count($indexes),
            'indexed_columns' => array_unique(wp_list_pluck($indexes, 'Column_name'))
        );
    }
    
    /**
     * Get recent matches
     */
    private function get_recent_matches($limit = 5) {
        $recent_matches = get_option('ufub_recent_property_matches', array());
        
        return array_slice($recent_matches, 0, $limit);
    }
    
    /**
     * Update analytics calculations
     */
    private function update_analytics_calculations() {
        // Update today's matches count
        $today = date('Y-m-d');
        $today_matches = get_option('ufub_property_matches_' . $today, 0);
        $this->analytics_data['matches_today'] = intval($today_matches);
        
        // Calculate average match score
        $match_scores = get_option('ufub_property_match_scores', array());
        if (!empty($match_scores)) {
            $this->analytics_data['avg_match_score'] = array_sum($match_scores) / count($match_scores);
        }
        
        // Update processing time
        if (!empty($this->performance_metrics['query_times'])) {
            $this->analytics_data['avg_processing_time'] = array_sum($this->performance_metrics['query_times']) / count($this->performance_metrics['query_times']);
        }
    }
    
    /**
     * Calculate success rate
     */
    private function calculate_success_rate() {
        $total_attempts = $this->analytics_data['successful_matches'] + $this->analytics_data['failed_matches'];
        
        if ($total_attempts === 0) {
            return 0;
        }
        
        return round(($this->analytics_data['successful_matches'] / $total_attempts) * 100, 2);
    }
    
    /**
     * Calculate cache hit ratio
     */
    private function calculate_cache_hit_ratio() {
        $total_requests = $this->performance_metrics['cache_hits'] + $this->performance_metrics['cache_misses'];
        
        if ($total_requests === 0) {
            return 0;
        }
        
        return round(($this->performance_metrics['cache_hits'] / $total_requests) * 100, 2);
    }
    
    /**
     * Count high quality matches
     */
    private function count_high_quality_matches() {
        $distribution = $this->analytics_data['match_quality_distribution'];
        
        return intval($distribution['excellent'] ?? 0) + intval($distribution['good'] ?? 0);
    }
    
    /**
     * Get daily match volume
     */
    private function get_daily_match_volume() {
        $daily_volume = array();
        
        // Get last 7 days of match data
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $matches = get_option('ufub_property_matches_' . $date, 0);
            
            $daily_volume[] = array(
                'date' => $date,
                'matches' => intval($matches)
            );
        }
        
        return $daily_volume;
    }
    
    /**
     * Save analytics data
     */
    private function save_analytics_data() {
        update_option('ufub_property_matcher_analytics', $this->analytics_data);
        update_option('ufub_property_matcher_performance', $this->performance_metrics);
    }
    
    /**
     * Build property query arguments
     */
    private function build_property_query_args($criteria) {
        $args = array(
            'post_type' => 'wpl_property',
            'post_status' => 'publish',
            'meta_query' => array('relation' => 'AND')
        );
        
        // Price range
        if (isset($criteria['min_price']) && $criteria['min_price'] > 0) {
            $args['meta_query'][] = array(
                'key' => 'price',
                'value' => floatval($criteria['min_price']),
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        if (isset($criteria['max_price']) && $criteria['max_price'] > 0) {
            $args['meta_query'][] = array(
                'key' => 'price',
                'value' => floatval($criteria['max_price']),
                'compare' => '<=',
                'type' => 'NUMERIC'
            );
        }
        
        // Bedrooms
        if (isset($criteria['bedrooms']) && $criteria['bedrooms'] > 0) {
            $args['meta_query'][] = array(
                'key' => 'bedrooms',
                'value' => intval($criteria['bedrooms']),
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        // Bathrooms
        if (isset($criteria['bathrooms']) && $criteria['bathrooms'] > 0) {
            $args['meta_query'][] = array(
                'key' => 'bathrooms',
                'value' => floatval($criteria['bathrooms']),
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        // Property type
        if (isset($criteria['property_type']) && !empty($criteria['property_type'])) {
            $args['meta_query'][] = array(
                'key' => 'property_type',
                'value' => sanitize_text_field($criteria['property_type']),
                'compare' => '='
            );
        }
        
        return $args;
    }
    
    /**
     * Calculate match score between property and criteria
     */
    private function calculate_match_score($property, $criteria) {
        $score = 0;
        $max_score = 0;
        
        // Price matching (weight: 30%)
        $price_weight = 30;
        $max_score += $price_weight;
        
        if (isset($property['price']) && isset($criteria['min_price']) && isset($criteria['max_price'])) {
            $price = floatval($property['price']);
            $min_price = floatval($criteria['min_price']);
            $max_price = floatval($criteria['max_price']);
            
            if ($price >= $min_price && $price <= $max_price) {
                // Perfect match if within range
                $score += $price_weight;
            } elseif ($price < $min_price) {
                // Partial score if below minimum
                $score += max(0, $price_weight * (1 - (($min_price - $price) / $min_price)));
            } else {
                // Partial score if above maximum
                $score += max(0, $price_weight * (1 - (($price - $max_price) / $max_price)));
            }
        }
        
        // Bedrooms matching (weight: 20%)
        $bedroom_weight = 20;
        $max_score += $bedroom_weight;
        
        if (isset($property['bedrooms']) && isset($criteria['bedrooms'])) {
            $prop_bedrooms = intval($property['bedrooms']);
            $criteria_bedrooms = intval($criteria['bedrooms']);
            
            if ($prop_bedrooms >= $criteria_bedrooms) {
                $score += $bedroom_weight;
            } else {
                $score += $bedroom_weight * ($prop_bedrooms / $criteria_bedrooms);
            }
        }
        
        // Bathrooms matching (weight: 15%)
        $bathroom_weight = 15;
        $max_score += $bathroom_weight;
        
        if (isset($property['bathrooms']) && isset($criteria['bathrooms'])) {
            $prop_bathrooms = floatval($property['bathrooms']);
            $criteria_bathrooms = floatval($criteria['bathrooms']);
            
            if ($prop_bathrooms >= $criteria_bathrooms) {
                $score += $bathroom_weight;
            } else {
                $score += $bathroom_weight * ($prop_bathrooms / $criteria_bathrooms);
            }
        }
        
        // Property type matching (weight: 25%)
        $type_weight = 25;
        $max_score += $type_weight;
        
        if (isset($property['property_type']) && isset($criteria['property_type'])) {
            if (strtolower($property['property_type']) === strtolower($criteria['property_type'])) {
                $score += $type_weight;
            }
        }
        
        // Location matching (weight: 10%) - basic text matching
        $location_weight = 10;
        $max_score += $location_weight;
        
        if (isset($property['location_text']) && isset($criteria['location'])) {
            $property_location = strtolower($property['location_text']);
            $criteria_location = strtolower($criteria['location']);
            
            if (strpos($property_location, $criteria_location) !== false || 
                strpos($criteria_location, $property_location) !== false) {
                $score += $location_weight;
            }
        }
        
        // Calculate final percentage
        return $max_score > 0 ? round(($score / $max_score) * 100, 2) : 0;
    }
    
    /**
     * AJAX handler for running property matching
     */
    public function ajax_run_property_matching() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ufub_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $results = $this->run_property_matching();
        
        wp_send_json_success(array(
            'message' => 'Property matching completed',
            'results' => $results
        ));
    }
    
    /**
     * AJAX handler for getting matching results
     */
    public function ajax_get_matching_results() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ufub_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $analytics = $this->get_analytics();
        
        wp_send_json_success($analytics);
    }
    
    /**
     * Run property matching process
     */
    public function run_property_matching() {
        $start_time = microtime(true);
        
        try {
            // Get all saved searches
            $saved_searches = $this->get_saved_searches();
            
            if (empty($saved_searches)) {
                return array(
                    'matches_found' => 0,
                    'message' => 'No saved searches configured'
                );
            }
            
            $total_matches = 0;
            
            foreach ($saved_searches as $search) {
                $matches = $this->find_matching_properties($search);
                $total_matches += count($matches);
                
                // Send matches to Follow Up Boss
                foreach ($matches as $match) {
                    $this->send_property_match($search, $match);
                }
            }
            
            // Update analytics
            $this->analytics_data['total_matches'] += $total_matches;
            $this->analytics_data['last_match_time'] = current_time('mysql');
            
            $processing_time = (microtime(true) - $start_time) * 1000;
            $this->performance_metrics['query_times'][] = $processing_time;
            
            // Update fastest/slowest
            $this->performance_metrics['slowest_match'] = max($this->performance_metrics['slowest_match'], $processing_time);
            $this->performance_metrics['fastest_match'] = min($this->performance_metrics['fastest_match'], $processing_time);
            
            $this->save_analytics_data();
            
            return array(
                'matches_found' => $total_matches,
                'processing_time' => round($processing_time, 2) . 'ms',
                'searches_processed' => count($saved_searches)
            );
            
        } catch (Exception $e) {
            return $this->handle_error($e, array('method' => 'run_property_matching'));
        }
    }
    
    /**
     * Daily matching cron job
     */
    public function run_daily_matching() {
        if (function_exists('ufub_log_info')) {
            ufub_log_info('Running daily property matching');
        }
        
        $results = $this->run_property_matching();
        
        if (function_exists('ufub_log_info')) {
            ufub_log_info('Daily property matching completed', $results);
        }
    }
    
    /**
     * Check new property for matches
     */
    public function check_new_property_matches($post_id, $post) {
        // Run matching for new property
        $property_data = $this->get_property_data($post_id);
        
        if (empty($property_data)) {
            return;
        }
        
        $saved_searches = $this->get_saved_searches();
        
        foreach ($saved_searches as $search) {
            $match_score = $this->calculate_match_score($property_data, $search['criteria']);
            
            if ($match_score >= 70) { // Threshold for immediate notification
                $this->send_property_match($search, array(
                    'property' => $property_data,
                    'score' => $match_score,
                    'match_type' => 'immediate'
                ));
            }
        }
    }
    
    /**
     * Helper methods for core functionality
     */
    
    private function get_saved_searches() {
        // This would integrate with the saved searches component
        // For now, return mock data structure
        return array(
            array(
                'id' => 1,
                'name' => 'Family Homes Under 400K',
                'criteria' => array(
                    'min_price' => 200000,
                    'max_price' => 400000,
                    'bedrooms' => 3,
                    'bathrooms' => 2,
                    'property_type' => 'residential'
                ),
                'contact_id' => 'fub_contact_123'
            )
        );
    }
    
    private function find_matching_properties($search) {
        $query_args = $this->build_property_query_args($search['criteria']);
        $query_args['posts_per_page'] = 50; // Limit results
        
        $properties_query = new WP_Query($query_args);
        $matches = array();
        
        while ($properties_query->have_posts()) {
            $properties_query->the_post();
            
            $property_data = $this->get_property_data(get_the_ID());
            $match_score = $this->calculate_match_score($property_data, $search['criteria']);
            
            if ($match_score >= 70) { // Minimum threshold
                $matches[] = array(
                    'property' => $property_data,
                    'score' => $match_score,
                    'match_type' => 'scheduled'
                );
            }
        }
        
        wp_reset_postdata();
        
        return $matches;
    }
    
    private function get_property_data($post_id) {
        // Get property data from WPL or other property plugin
        $property = array(
            'id' => $post_id,
            'price' => get_post_meta($post_id, 'price', true),
            'bedrooms' => get_post_meta($post_id, 'bedrooms', true),
            'bathrooms' => get_post_meta($post_id, 'bathrooms', true),
            'property_type' => get_post_meta($post_id, 'property_type', true),
            'location_text' => get_post_meta($post_id, 'location_text', true),
            'title' => get_the_title($post_id),
            'url' => get_permalink($post_id)
        );
        
        return $property;
    }
    
    private function send_property_match($search, $match) {
        if (!$this->api) {
            return false;
        }
        
        // Send property match to Follow Up Boss
        $event_data = array(
            'contact_id' => $search['contact_id'],
            'event_type' => 'property_match',
            'property_data' => $match['property'],
            'match_score' => $match['score'],
            'search_criteria' => $search['criteria']
        );
        
        return $this->api->send_property_event($event_data);
    }
}

// Initialize the Property Matcher
if (class_exists('FUB_Property_Matcher')) {
    FUB_Property_Matcher::get_instance();
}