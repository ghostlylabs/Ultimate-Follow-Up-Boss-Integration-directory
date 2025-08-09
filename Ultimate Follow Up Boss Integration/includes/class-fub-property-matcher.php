<?php
/**
 * Property Matcher - MLS Integration & Email Campaigns
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Property_Matcher
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Property_Matcher {
    
    private static $instance = null;
    private $api;
    private $wpl_integration;
    
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
        // Initialize WPL integration for REAL property data
        $this->wpl_integration = new FUB_WPL_Integration();
        $this->init();
    }
    
    /**
     * Initialize property matcher
     */
    private function init() {
        // Schedule property matching
        add_action('ufub_check_property_matches', array($this, 'check_all_matches'));
        
        if (!wp_next_scheduled('ufub_check_property_matches')) {
            wp_schedule_event(time(), 'hourly', 'ufub_check_property_matches');
        }
        
        // Admin hooks
        add_action('wp_ajax_ufub_test_property_match', array($this, 'ajax_test_match'));
        add_action('wp_ajax_ufub_trigger_matching', array($this, 'ajax_trigger_matching'));
    }
    
    /**
     * Check all property matches (scheduled)
     */
    public function check_all_matches() {
        if (!get_option('ufub_property_matching_enabled', true)) {
            return;
        }
        
        // Get active saved searches
        $searches = $this->get_active_searches();
        
        foreach ($searches as $search) {
            $this->process_search_matching($search);
        }
        
        $this->log_matching_run(count($searches));
    }
    
    /**
     * Process matching for a specific search
     */
    private function process_search_matching($search) {
        $criteria = json_decode($search->search_criteria, true);
        
        if (empty($criteria)) {
            return;
        }
        
        // Get new properties from MLS/IDX feeds
        $new_properties = $this->get_new_properties($criteria, $search->last_checked);
        
        if (empty($new_properties)) {
            $this->update_search_last_checked($search->id);
            return;
        }
        
        // Filter properties that match criteria
        $matches = $this->filter_matching_properties($new_properties, $criteria);
        
        if (!empty($matches)) {
            // Send email campaign via FUB
            $this->trigger_email_campaign($search, $matches);
            
            // Store matches
            $this->store_property_matches($search->id, $matches);
        }
        
        $this->update_search_last_checked($search->id);
    }
    
    /**
     * Get active saved searches
     */
    private function get_active_searches() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        return $wpdb->get_results("
            SELECT * FROM {$table} 
            WHERE status = 'active' 
            AND user_email IS NOT NULL 
            AND user_email != ''
            AND (last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL 1 HOUR))
            ORDER BY last_checked ASC
            LIMIT 50
        ");
    }
    
    /**
     * Get new properties from REAL WPL database
     */
    private function get_new_properties($criteria, $last_checked = null) {
        // Use REAL WPL integration instead of fake data
        if (!$this->wpl_integration) {
            error_log('WPL Integration not initialized');
            return array();
        }
        
        try {
            // Get properties from real WPL database
            $properties = $this->wpl_integration->get_new_properties($criteria, $last_checked);
            
            // Log the query for debugging
            error_log('FUB Property Matcher: Retrieved ' . count($properties) . ' properties from WPL');
            
            return $properties;
            
        } catch (Exception $e) {
            error_log('FUB Property Matcher Error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Filter properties by search criteria (UPDATED for WPL data)
     */
    private function filter_matching_properties($properties, $criteria) {
        $matching_properties = array();

        foreach ($properties as $property) {
            if ($this->property_matches_criteria($property, $criteria)) {
                // Format property for Follow Up Boss
                $formatted_property = array(
                    'mls_number' => $property['mls_id'] ?? $property['id'],
                    'street_address' => $property['address'],
                    'city' => $property['city'] ?? '',
                    'state' => $property['state'] ?? '',
                    'zip_code' => $property['zip'] ?? '',
                    'price' => $property['price'],
                    'bedrooms' => $property['bedrooms'],
                    'bathrooms' => $property['bathrooms'],
                    'square_feet' => $property['living_area'] ?? 0,
                    'property_type' => $property['listing_type'] ?? 'Residential',
                    'listing_date' => $property['listing_date'] ?? current_time('mysql'),
                    'description' => $property['description'] ?? '',
                    'photos' => $property['photos'] ?? array(),
                    'url' => $property['url'] ?? ''
                );
                
                $matching_properties[] = $formatted_property;
            }
        }

        return $matching_properties;
    }    /**
     * Check if property matches search criteria (UPDATED for WPL data)
     */
    private function property_matches_criteria($property, $criteria) {
        // Price range check
        if (!empty($criteria['min_price'])) {
            $min_price = $this->parse_price($criteria['min_price']);
            if ($property['price'] < $min_price) {
                return false;
            }
        }

        if (!empty($criteria['max_price'])) {
            $max_price = $this->parse_price($criteria['max_price']);
            if ($property['price'] > $max_price) {
                return false;
            }
        }

        // Bedroom check (updated field name for WPL)
        if (!empty($criteria['beds']) || !empty($criteria['bedrooms'])) {
            $required_beds = intval($criteria['beds'] ?? $criteria['bedrooms']);
            if ($property['bedrooms'] < $required_beds) {
                return false;
            }
        }

        // Bathroom check (updated field name for WPL)
        if (!empty($criteria['baths']) || !empty($criteria['bathrooms'])) {
            $required_baths = floatval($criteria['baths'] ?? $criteria['bathrooms']);
            if ($property['bathrooms'] < $required_baths) {
                return false;
            }
        }        // Property type check
        if (!empty($criteria['property_type'])) {
            if (strtolower($property['property_type']) !== strtolower($criteria['property_type'])) {
                return false;
            }
        }
        
        // Location check (basic keyword matching)
        if (!empty($criteria['location'])) {
            $search_location = strtolower($criteria['location']);
            $property_address = strtolower($property['address']);
            
            if (strpos($property_address, $search_location) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Trigger email campaign in Follow Up Boss
     */
    private function trigger_email_campaign($search, $matches) {
        if (empty($search->user_email)) {
            return false;
        }
        
        $success_count = 0;
        
        // Send individual property alerts for each match
        foreach ($matches as $property) {
            $result = $this->api->send_property_alert(
                $search->user_email,
                $property,
                array(
                    'search_name' => $search->search_name,
                    'search_criteria' => $search->search_criteria,
                    'user_first_name' => $search->user_first_name ?? '',
                    'user_last_name' => $search->user_last_name ?? ''
                )
            );
            
            if (!is_wp_error($result) && $result['success']) {
                $success_count++;
                error_log("FUB Property Alert sent: {$property['address']} to {$search->user_email}");
            }
        }
        
        if ($success_count > 0) {
            $this->log_email_campaign($search->id, $search->fub_contact_id ?? '', $success_count);
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate email content for property matches
     */
    private function generate_email_content($matches, $search) {
        $count = count($matches);
        $criteria = json_decode($search->search_criteria, true);
        
        // Subject line
        $subject = "🏠 {$count} New Properties Match Your Search!";
        
        // Email body
        $body = "Great news! I found {$count} new " . ($count === 1 ? 'property' : 'properties') . " that match your search criteria.\n\n";
        
        // Add search criteria summary
        $body .= "Your Search Criteria:\n";
        if (!empty($criteria['price_range'])) {
            $body .= "• Price Range: {$criteria['price_range']}\n";
        }
        if (!empty($criteria['beds'])) {
            $body .= "• Bedrooms: {$criteria['beds']}+\n";
        }
        if (!empty($criteria['baths'])) {
            $body .= "• Bathrooms: {$criteria['baths']}+\n";
        }
        if (!empty($criteria['location'])) {
            $body .= "• Location: {$criteria['location']}\n";
        }
        
        $body .= "\n--- NEW MATCHES ---\n\n";
        
        // Add property details
        foreach ($matches as $index => $property) {
            $body .= ($index + 1) . ". {$property['address']}\n";
            $body .= "   Price: $" . number_format($property['price']) . "\n";
            $body .= "   Beds/Baths: {$property['beds']}/{$property['baths']}\n";
            $body .= "   Sq Ft: " . number_format($property['sqft']) . "\n";
            $body .= "   MLS#: {$property['mls_id']}\n";
            
            if (!empty($property['description'])) {
                $body .= "   " . substr($property['description'], 0, 100) . "...\n";
            }
            
            $body .= "\n";
        }
        
        $body .= "Would you like to schedule a showing or get more information about any of these properties?\n\n";
        $body .= "Reply to this email or call me directly!\n\n";
        $body .= "Best regards,\n";
        $body .= get_option('ufub_agent_name', 'Your Real Estate Agent');
        
        return array(
            'subject' => $subject,
            'body' => $body
        );
    }
    
    /**
     * Store property matches in database
     */
    private function store_property_matches($search_id, $matches) {
        global $wpdb;
        
        // We'll store matches in the API logs table for now
        $table = $wpdb->prefix . 'fub_api_logs';
        
        foreach ($matches as $property) {
            $wpdb->insert(
                $table,
                array(
                    'endpoint' => 'property_matcher',
                    'method' => 'match_found',
                    'request_data' => wp_json_encode(array(
                        'search_id' => $search_id,
                        'property' => $property
                    )),
                    'response_code' => 200,
                    'response_data' => wp_json_encode(array('status' => 'matched')),
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
    }
    
    /**
     * Update last checked timestamp for search
     */
    private function update_search_last_checked($search_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        $wpdb->update(
            $table,
            array('last_checked' => current_time('mysql')),
            array('id' => $search_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Log matching run
     */
    private function log_matching_run($searches_processed) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        $wpdb->insert(
            $table,
            array(
                'endpoint' => 'property_matcher',
                'method' => 'matching_run',
                'request_data' => wp_json_encode(array(
                    'searches_processed' => $searches_processed,
                    'timestamp' => current_time('mysql')
                )),
                'response_code' => 200,
                'response_data' => wp_json_encode(array('status' => 'completed')),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Log email campaign
     */
    private function log_email_campaign($search_id, $contact_id, $property_count) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        $wpdb->insert(
            $table,
            array(
                'endpoint' => 'property_matcher',
                'method' => 'email_sent',
                'request_data' => wp_json_encode(array(
                    'search_id' => $search_id,
                    'contact_id' => $contact_id,
                    'property_count' => $property_count
                )),
                'response_code' => 200,
                'response_data' => wp_json_encode(array('status' => 'email_triggered')),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * AJAX: Test property matching
     */
    public function ajax_test_match() {
        check_ajax_referer('ufub_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $search_id = intval($_POST['search_id'] ?? 0);
        
        if (!$search_id) {
            wp_send_json_error('Invalid search ID');
        }
        
        $search = $this->get_search_by_id($search_id);
        
        if (!$search) {
            wp_send_json_error('Search not found');
        }
        
        $this->process_search_matching($search);
        
        wp_send_json_success('Test matching completed');
    }
    
    /**
     * AJAX: Trigger matching manually
     */
    public function ajax_trigger_matching() {
        check_ajax_referer('ufub_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $this->check_all_matches();
        
        wp_send_json_success('Manual matching triggered');
    }
    
    /**
     * Get search by ID
     */
    private function get_search_by_id($search_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $search_id
        ));
    }
    
    /**
     * Helper: Parse price from string
     */
    private function parse_price($price_string) {
        return intval(preg_replace('/[^\d]/', '', $price_string));
    }
    
    /**
     * Get matching statistics
     */
    public function get_matching_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        // Get stats from last 30 days
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_runs,
                SUM(CASE WHEN method = 'match_found' THEN 1 ELSE 0 END) as total_matches,
                SUM(CASE WHEN method = 'email_sent' THEN 1 ELSE 0 END) as emails_sent
            FROM {$table} 
            WHERE endpoint = 'property_matcher' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $searches_table = $wpdb->prefix . 'fub_saved_searches';
        $active_searches = $wpdb->get_var("SELECT COUNT(*) FROM {$searches_table} WHERE status = 'active'");
        
        return array(
            'active_searches' => intval($active_searches),
            'total_runs' => intval($stats->total_runs ?? 0),
            'total_matches' => intval($stats->total_matches ?? 0),
            'emails_sent' => intval($stats->emails_sent ?? 0)
        );
    }
    
    /**
     * Get MLS integration examples (for documentation)
     */
    public function get_mls_integration_examples() {
        return array(
            'RETS' => array(
                'description' => 'Real Estate Transaction Standard',
                'example_url' => 'https://rets.example-mls.com/Login.asmx/Login',
                'auth_method' => 'Basic Auth or Digest Auth',
                'data_format' => 'XML',
                'integration_notes' => 'Most common MLS format, requires RETS client library'
            ),
            'REST_API' => array(
                'description' => 'Modern REST API integration',
                'example_url' => 'https://api.example-mls.com/v1/properties',
                'auth_method' => 'API Key or OAuth',
                'data_format' => 'JSON',
                'integration_notes' => 'Easier to implement, use wp_remote_get/post'
            ),
            'IDX_Feed' => array(
                'description' => 'IDX data feed integration',
                'example_url' => 'https://idx.example.com/feed/properties.xml',
                'auth_method' => 'API Key',
                'data_format' => 'XML or JSON',
                'integration_notes' => 'Updated periodically, cache locally for performance'
            ),
            'Third_Party' => array(
                'description' => 'Services like Spark API, Trestle, etc.',
                'example_providers' => array('Spark API', 'Trestle', 'Rapattoni', 'BRIDGE'),
                'integration_notes' => 'Each has specific SDK/API format'
            )
        );
    }
}
