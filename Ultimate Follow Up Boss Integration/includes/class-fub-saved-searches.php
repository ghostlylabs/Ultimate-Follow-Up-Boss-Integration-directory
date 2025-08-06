<?php
/**
 * FUB Saved Searches Handler
 * 
 * Intelligent saved search detection and management for Follow Up Boss integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Saved_Searches
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Saved_Searches {
    
    private static $instance = null;
    private $api;
    
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
    }
    
    /**
     * Initialize saved searches
     */
    private function init() {
        // Get API instance
        if (class_exists('FUB_API')) {
            $this->api = FUB_API::get_instance();
        }
        
        // AJAX handlers
        add_action('wp_ajax_ufub_create_saved_search', array($this, 'handle_create_saved_search'));
        add_action('wp_ajax_nopriv_ufub_create_saved_search', array($this, 'handle_create_saved_search'));
        
        add_action('wp_ajax_ufub_get_saved_searches', array($this, 'ajax_get_saved_searches'));
        add_action('wp_ajax_ufub_delete_saved_search', array($this, 'ajax_delete_saved_search'));
        add_action('wp_ajax_ufub_update_saved_search', array($this, 'ajax_update_saved_search'));
        
        // Scheduled cleanup
        add_action('ufub_cleanup_saved_searches', array($this, 'cleanup_old_searches'));
        
        if (!wp_next_scheduled('ufub_cleanup_saved_searches')) {
            wp_schedule_event(time(), 'weekly', 'ufub_cleanup_saved_searches');
        }
        
        if (function_exists('ufub_log_info')) {
            ufub_log_info('FUB Saved Searches handler initialized');
        }
    }
    
    /**
     * Handle create saved search request
     */
    public function handle_create_saved_search() {
        check_ajax_referer('ufub_tracking_nonce', 'nonce');
        
        $request_data = $this->get_request_data();
        $patterns = $request_data['patterns'] ?? array();
        $ideal_profile = $request_data['ideal_profile'] ?? array();
        $search_history = $request_data['search_history'] ?? array();
        
        if (empty($patterns) || empty($ideal_profile)) {
            wp_send_json_error('Invalid saved search data');
        }
        
        // Get person information from session
        $person_data = $this->get_person_from_session();
        
        if (!$person_data) {
            // Create anonymous person for tracking
            $person_data = array(
                'email' => $this->generate_anonymous_email(),
                'first_name' => 'Anonymous',
                'last_name' => 'Visitor'
            );
        }
        
        // Create saved search
        $saved_search_id = $this->create_saved_search($person_data, $ideal_profile, $patterns, $search_history);
        
        if ($saved_search_id) {
            // Send to FUB as a "Saved Property" event
            $this->send_saved_search_to_fub($person_data, $ideal_profile, $saved_search_id);
            
            // Generate agent note
            $this->create_agent_note($person_data, $ideal_profile, $patterns);
            
            wp_send_json_success(array(
                'message' => 'Saved search created successfully',
                'saved_search_id' => $saved_search_id,
                'ideal_profile' => $ideal_profile
            ));
        } else {
            wp_send_json_error('Failed to create saved search');
        }
    }
    
    /**
     * Create saved search in database
     */
    private function create_saved_search($person_data, $ideal_profile, $patterns, $search_history) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_saved_searches';
        $this->create_saved_searches_table_if_needed();
        
        $search_data = array(
            'title' => $this->generate_search_title($ideal_profile),
            'criteria' => wp_json_encode($ideal_profile['criteria']),
            'patterns' => wp_json_encode($patterns),
            'search_history' => wp_json_encode($search_history),
            'confidence_score' => $patterns['similarity'] ?? 0,
            'person_email' => $person_data['email'],
            'person_name' => trim(($person_data['first_name'] ?? '') . ' ' . ($person_data['last_name'] ?? '')),
            'status' => 'active',
            'frequency' => $this->determine_search_frequency($search_history),
            'price_range' => $this->extract_price_range($ideal_profile['criteria']),
            'location' => $this->extract_location($ideal_profile['criteria']),
            'property_type' => $ideal_profile['criteria']['property_type'] ?? '',
            'bedrooms' => $ideal_profile['criteria']['bedrooms'] ?? '',
            'bathrooms' => $ideal_profile['criteria']['bathrooms'] ?? '',
            'created_date' => current_time('mysql'),
            'last_updated' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $search_data, array(
            '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ));
        
        if ($result !== false) {
            $saved_search_id = $wpdb->insert_id;
            
            if (function_exists('ufub_log_info')) {
                ufub_log_info('Saved search created', array(
                    'saved_search_id' => $saved_search_id,
                    'person_email' => $person_data['email'],
                    'confidence_score' => $patterns['similarity'] ?? 0
                ));
            }
            
            return $saved_search_id;
        }
        
        return false;
    }
    
    /**
     * Send saved search to FUB
     */
    private function send_saved_search_to_fub($person_data, $ideal_profile, $saved_search_id) {
        if (!$this->api) {
            return false;
        }
        
        // Create a property object representing the ideal home
        $ideal_property = $this->create_ideal_property_from_profile($ideal_profile);
        
        // Prepare event data for FUB
        $event_data = array(
            'system' => get_option('ufub_system_key', 'ufub_wordpress_integration'),
            'source' => $this->get_source_domain(),
            'type' => 'Saved Property',
            'person' => $this->format_person_for_fub($person_data),
            'property' => $ideal_property,
            'occurred' => current_time('c'),
            'sourceUrl' => home_url() . "?saved_search_id={$saved_search_id}",
            'notes' => "Auto-generated saved search based on user behavior patterns (Confidence: " . round(($ideal_profile['confidence'] ?? 0) * 100) . "%)"
        );
        
        $result = $this->api->make_request('POST', '/events', $event_data);
        
        if ($result && !isset($result['error'])) {
            if (function_exists('ufub_log_info')) {
                ufub_log_info('Saved search sent to FUB', array(
                    'saved_search_id' => $saved_search_id,
                    'fub_event_id' => $result['id'] ?? 'unknown',
                    'person_email' => $person_data['email']
                ));
            }
            
            // Update saved search with FUB event ID
            global $wpdb;
            $table = $wpdb->prefix . 'ufub_saved_searches';
            $wpdb->update(
                $table,
                array('fub_event_id' => $result['id'] ?? ''),
                array('id' => $saved_search_id),
                array('%s'),
                array('%d')
            );
            
            return true;
        }
        
        if (function_exists('ufub_log_error')) {
            ufub_log_error('Failed to send saved search to FUB', array(
                'saved_search_id' => $saved_search_id,
                'error' => $result['error'] ?? 'Unknown error'
            ));
        }
        
        return false;
    }
    
    /**
     * Create agent note with ideal home profile
     */
    private function create_agent_note($person_data, $ideal_profile, $patterns) {
        if (!$this->api) {
            return;
        }
        
        // Generate comprehensive note content
        $note_content = $this->generate_agent_note_content($ideal_profile, $patterns);
        
        // Get or create person in FUB first
        $fub_result = $this->api->create_or_update_person($person_data, 'Website Activity');
        
        if ($fub_result['success'] && isset($fub_result['person_id'])) {
            // Create note in FUB
            $note_result = $this->api->create_note(
                $fub_result['person_id'],
                $note_content,
                'Ideal Home Search Profile'
            );
            
            if ($note_result && !isset($note_result['error'])) {
                if (function_exists('ufub_log_info')) {
                    ufub_log_info('Agent note created for saved search', array(
                        'person_id' => $fub_result['person_id'],
                        'note_id' => $note_result['id'] ?? 'unknown',
                        'person_email' => $person_data['email']
                    ));
                }
            }
        }
    }
    
    /**
     * Generate agent note content
     */
    private function generate_agent_note_content($ideal_profile, $patterns) {
        $content = "🏠 **IDEAL HOME SEARCH PROFILE** (Auto-Generated)\n\n";
        $content .= "**Confidence Level:** " . round(($patterns['similarity'] ?? 0) * 100) . "%\n";
        $content .= "**Generated:** " . current_time('M j, Y g:i A') . "\n\n";
        
        $content .= "**PREFERRED CRITERIA:**\n";
        
        $criteria = $ideal_profile['criteria'] ?? array();
        
        if (!empty($criteria['location'])) {
            $content .= "📍 **Location:** " . $criteria['location'] . "\n";
        }
        
        if (!empty($criteria['price_range'])) {
            $content .= "💰 **Price Range:** " . $this->format_price_range($criteria['price_range']) . "\n";
        }
        
        if (!empty($criteria['bedrooms'])) {
            $content .= "🛏️ **Bedrooms:** " . $criteria['bedrooms'] . "\n";
        }
        
        if (!empty($criteria['bathrooms'])) {
            $content .= "🚿 **Bathrooms:** " . $criteria['bathrooms'] . "\n";
        }
        
        if (!empty($criteria['property_type'])) {
            $content .= "🏡 **Property Type:** " . ucfirst($criteria['property_type']) . "\n";
        }
        
        $content .= "\n**SEARCH BEHAVIOR ANALYSIS:**\n";
        
        // Analyze search patterns
        $most_searched_location = $this->get_most_common_value($patterns, 'location');
        if ($most_searched_location) {
            $content .= "• Most searched location: " . $most_searched_location . "\n";
        }
        
        $most_searched_price = $this->get_most_common_value($patterns, 'price_range');
        if ($most_searched_price) {
            $content .= "• Consistent price range: " . $most_searched_price . "\n";
        }
        
        $content .= "• Search consistency: " . round(($patterns['similarity'] ?? 0) * 100) . "% similar criteria\n";
        
        $content .= "\n**RECOMMENDED ACTIONS:**\n";
        $content .= "• Set up automated property alerts for this profile\n";
        $content .= "• Schedule follow-up call to discuss specific needs\n";
        $content .= "• Send curated property listings matching criteria\n";
        
        if (($patterns['similarity'] ?? 0) > 0.8) {
            $content .= "• HIGH CONFIDENCE: Client has very specific requirements\n";
        }
        
        $content .= "\n---\n";
        $content .= "*This profile was automatically generated based on website behavior analysis*";
        
        return $content;
    }
    
    /**
     * Get most common value from patterns
     */
    private function get_most_common_value($patterns, $key) {
        if (!isset($patterns[$key]) || empty($patterns[$key])) {
            return null;
        }
        
        $values = $patterns[$key];
        
        // Find the value with the highest count
        $max_count = 0;
        $most_common = null;
        
        foreach ($values as $value => $count) {
            if ($count > $max_count) {
                $max_count = $count;
                $most_common = $value;
            }
        }
        
        return $most_common;
    }
    
    /**
     * Format price range for display (FIXED SYNTAX ERROR)
     */
    private function format_price_range($price_range) {
        if (strpos($price_range, '-') !== false) {
            list($min, $max) = explode('-', $price_range);
            return '$' . number_format((int)$min) . ' - $' . number_format((int)$max);
        }
        
        return $price_range;
    }
    
    /**
     * Create ideal property from profile
     */
    private function create_ideal_property_from_profile($ideal_profile) {
        $criteria = $ideal_profile['criteria'] ?? array();
        
        $property = array(
            'street' => 'Ideal Home Criteria',
            'city' => $criteria['location'] ?? 'Any',
            'propertyType' => $criteria['property_type'] ?? 'Any'
        );
        
        if (!empty($criteria['price_range'])) {
            if (strpos($criteria['price_range'], '-') !== false) {
                list($min_price, $max_price) = explode('-', $criteria['price_range']);
                $property['price'] = (int)$max_price; // Use max price for the property
            }
        }
        
        if (!empty($criteria['bedrooms'])) {
            $property['bedrooms'] = (int)$criteria['bedrooms'];
        }
        
        if (!empty($criteria['bathrooms'])) {
            $property['bathrooms'] = (float)$criteria['bathrooms'];
        }
        
        return $property;
    }
    
    /**
     * Generate search title
     */
    private function generate_search_title($ideal_profile) {
        $criteria = $ideal_profile['criteria'] ?? array();
        $parts = array();
        
        if (!empty($criteria['bedrooms'])) {
            $parts[] = $criteria['bedrooms'] . ' bed';
        }
        
        if (!empty($criteria['bathrooms'])) {
            $parts[] = $criteria['bathrooms'] . ' bath';
        }
        
        if (!empty($criteria['property_type'])) {
            $parts[] = $criteria['property_type'];
        }
        
        if (!empty($criteria['location'])) {
            $parts[] = 'in ' . $criteria['location'];
        }
        
        if (!empty($parts)) {
            return 'Auto-Search: ' . implode(', ', $parts);
        }
        
        return 'Auto-Generated Property Search';
    }
    
    /**
     * Determine search frequency
     */
    private function determine_search_frequency($search_history) {
        $count = count($search_history);
        
        if ($count >= 5) {
            return 'high';
        } elseif ($count >= 3) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Extract price range from criteria
     */
    private function extract_price_range($criteria) {
        if (!empty($criteria['price_range'])) {
            return $criteria['price_range'];
        }
        
        $min = $criteria['min_price'] ?? '';
        $max = $criteria['max_price'] ?? '';
        
        if ($min || $max) {
            return ($min ?: '0') . '-' . ($max ?: '999999999');
        }
        
        return '';
    }
    
    /**
     * Extract location from criteria
     */
    private function extract_location($criteria) {
        return $criteria['location'] ?? $criteria['city'] ?? $criteria['neighborhood'] ?? '';
    }
    
    /**
     * Get person from session
     */
    private function get_person_from_session() {
        // Try to get from WordPress user
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return array(
                'email' => $user->user_email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            );
        }
        
        // Try to get from recent tracking data
        global $wpdb;
        $table = $wpdb->prefix . 'ufub_tracking_data';
        
        $result = $wpdb->get_row(
            "SELECT tracking_data FROM {$table} 
             WHERE event_type IN ('registration', 'inquiry') 
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY timestamp DESC LIMIT 1"
        );
        
        if ($result) {
            $data = json_decode($result->tracking_data, true);
            return $data['person_data'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Generate anonymous email
     */
    private function generate_anonymous_email() {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        return 'saved_search_' . time() . '_' . wp_rand(1000, 9999) . '@' . $domain;
    }
    
    /**
     * Format person for FUB
     */
    private function format_person_for_fub($person_data) {
        $formatted = array();
        
        if (!empty($person_data['email'])) {
            $formatted['emails'] = array(array('value' => $person_data['email']));
        }
        
        if (!empty($person_data['first_name'])) {
            $formatted['firstName'] = $person_data['first_name'];
        }
        
        if (!empty($person_data['last_name'])) {
            $formatted['lastName'] = $person_data['last_name'];
        }
        
        return $formatted;
    }
    
    /**
     * Get source domain
     */
    private function get_source_domain() {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        return str_replace('www.', '', $domain);
    }
    
    /**
     * Create saved searches table if needed
     */
    private function create_saved_searches_table_if_needed() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_saved_searches';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                criteria longtext,
                patterns longtext,
                search_history longtext,
                confidence_score decimal(3,2),
                person_email varchar(255),
                person_name varchar(255),
                fub_contact_id varchar(100),
                fub_event_id varchar(100),
                status varchar(20) DEFAULT 'active',
                frequency varchar(20),
                price_range varchar(100),
                location varchar(255),
                property_type varchar(100),
                bedrooms varchar(10),
                bathrooms varchar(10),
                last_match_count int(11) DEFAULT 0,
                last_match_date datetime,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY person_email (person_email),
                KEY status (status),
                KEY location (location),
                KEY price_range (price_range),
                KEY created_date (created_date)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            if (function_exists('ufub_log_info')) {
                ufub_log_info('Saved searches table created');
            }
        }
    }
    
    /**
     * Get all saved searches
     */
    public function get_saved_searches($limit = 50, $offset = 0, $filters = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_saved_searches';
        $this->create_saved_searches_table_if_needed();
        
        $where = array('1=1');
        $where_values = array();
        
        // Apply filters
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['person_email'])) {
            $where[] = 'person_email = %s';
            $where_values[] = $filters['person_email'];
        }
        
        if (!empty($filters['location'])) {
            $where[] = 'location LIKE %s';
            $where_values[] = '%' . $filters['location'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_date >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_date <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_date DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Update saved search
     */
    public function update_saved_search($search_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_saved_searches';
        
        $update_data = array();
        $format = array();
        
        $allowed_fields = array(
            'title' => '%s',
            'status' => '%s',
            'frequency' => '%s',
            'criteria' => '%s',
            'last_match_count' => '%d',
            'last_match_date' => '%s'
        );
        
        foreach ($data as $key => $value) {
            if (isset($allowed_fields[$key])) {
                $update_data[$key] = $value;
                $format[] = $allowed_fields[$key];
            }
        }
        
        if (!empty($update_data)) {
            $update_data['last_updated'] = current_time('mysql');
            $format[] = '%s';
            
            $result = $wpdb->update(
                $table,
                $update_data,
                array('id' => $search_id),
                $format,
                array('%d')
            );
            
            return $result !== false;
        }
        
        return false;
    }
    
    /**
     * Delete saved search
     */
    public function delete_saved_search($search_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_saved_searches';
        
        $result = $wpdb->delete(
            $table,
            array('id' => $search_id),
            array('%d')
        );
        
        if ($result !== false && function_exists('ufub_log_info')) {
            ufub_log_info('Saved search deleted', array('search_id' => $search_id));
        }
        
        return $result !== false;
    }
    
    /**
     * Cleanup old searches
     */
    public function cleanup_old_searches() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_saved_searches';
        
        // Delete inactive searches older than 6 months
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE status = 'inactive' AND created_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
        ));
        
        if ($result > 0 && function_exists('ufub_log_info')) {
            ufub_log_info('Cleaned up old saved searches', array('deleted_count' => $result));
        }
        
        // Mark searches as inactive if no activity for 3 months
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'inactive' 
             WHERE status = 'active' 
             AND (last_match_date IS NULL OR last_match_date < DATE_SUB(NOW(), INTERVAL 3 MONTH))
             AND created_date < DATE_SUB(NOW(), INTERVAL 3 MONTH)"
        ));
    }
    
    /**
     * Get request data from AJAX
     */
    private function get_request_data() {
        $data = $_POST['data'] ?? '';
        
        if (empty($data)) {
            return array();
        }
        
        return json_decode(stripslashes($data), true) ?: array();
    }
    
    /**
     * AJAX: Get saved searches
     */
    public function ajax_get_saved_searches() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $limit = (int) ($_POST['limit'] ?? 50);
        $offset = (int) ($_POST['offset'] ?? 0);
        $filters = $_POST['filters'] ?? array();
        
        $searches = $this->get_saved_searches($limit, $offset, $filters);
        
        // Get total count
        global $wpdb;
        $table = $wpdb->prefix . 'ufub_saved_searches';
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        wp_send_json_success(array(
            'searches' => $searches,
            'total' => (int) $total,
            'limit' => $limit,
            'offset' => $offset
        ));
    }
    
    /**
     * AJAX: Delete saved search
     */
    public function ajax_delete_saved_search() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $search_id = (int) ($_POST['search_id'] ?? 0);
        
        if ($search_id <= 0) {
            wp_send_json_error('Invalid search ID');
        }
        
        if ($this->delete_saved_search($search_id)) {
            wp_send_json_success('Saved search deleted successfully');
        } else {
            wp_send_json_error('Failed to delete saved search');
        }
    }
    
    /**
     * AJAX: Update saved search
     */
    public function ajax_update_saved_search() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $search_id = (int) ($_POST['search_id'] ?? 0);
        $update_data = $_POST['data'] ?? array();
        
        if ($search_id <= 0) {
            wp_send_json_error('Invalid search ID');
        }
        
        if ($this->update_saved_search($search_id, $update_data)) {
            wp_send_json_success('Saved search updated successfully');
        } else {
            wp_send_json_error('Failed to update saved search');
        }
    }
    
    /**
     * Get saved search statistics
     */
    public function get_saved_search_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_saved_searches';
        $this->create_saved_searches_table_if_needed();
        
        $stats = array();
        
        // Total searches
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        // Active searches
        $stats['active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'active'");
        
        // Today's searches
        $stats['today'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE DATE(created_date) = CURDATE()");
        
        // This week's searches
        $stats['this_week'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        // High confidence searches (>80%)
        $stats['high_confidence'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE confidence_score > 0.8");
        
        // Most popular locations
        $stats['popular_locations'] = $wpdb->get_results(
            "SELECT location, COUNT(*) as count 
             FROM {$table} 
             WHERE location != '' 
             GROUP BY location 
             ORDER BY count DESC 
             LIMIT 5",
            ARRAY_A
        );
        
        // Average confidence score
        $stats['avg_confidence'] = (float) $wpdb->get_var("SELECT AVG(confidence_score) FROM {$table}");
        
        return $stats;
    }
}