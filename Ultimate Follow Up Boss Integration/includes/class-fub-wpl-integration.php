<?php
/**
 * FUB Property Matcher Class - REAL WPL Integration
 * Handles matching and syncing properties from WPL to Follow Up Boss
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Includes
 * @version 1.0.0
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FUB WPL Property Integration - REAL DATA
 */
class FUB_WPL_Integration {
    
    /**
     * WPL Properties table name
     */
    private $properties_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->properties_table = $wpdb->prefix . 'wpl_properties';
    }
    
    /**
     * Get new properties from REAL WPL database
     * 
     * @param array $criteria Search criteria
     * @param string $last_checked Last check timestamp
     * @return array Properties matching criteria
     */
    public function get_new_properties($criteria = array(), $last_checked = null) {
        global $wpdb;
        
        // Base WHERE conditions for active WPL properties (EXACT from WPL structure)
        $where_conditions = array(
            'confirmed = 1',        // Property is confirmed
            'finalized = 1',        // Property is finalized
            'deleted = 0'           // Property is not deleted
        );
        
        // Add timestamp filter if provided
        if ($last_checked) {
            $where_conditions[] = $wpdb->prepare('add_date >= %s', $last_checked);
        }
        
        // Apply search criteria filters
        $where_conditions = array_merge($where_conditions, $this->build_criteria_filters($criteria));
        
        // Build final query
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query = "SELECT * FROM {$this->properties_table} {$where_clause} ORDER BY add_date DESC";
        
        // Execute query
        $raw_properties = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log('WPL Property Query Error: ' . $wpdb->last_error);
            return array();
        }
        
        // Format properties for FUB
        return $this->format_properties_for_fub($raw_properties);
    }
    
    /**
     * Build WHERE conditions from search criteria
     * 
     * @param array $criteria Search criteria
     * @return array WHERE conditions
     */
    private function build_criteria_filters($criteria) {
        global $wpdb;
        $conditions = array();
        
        // Price range (REAL WPL column: price)
        if (!empty($criteria['min_price'])) {
            $conditions[] = $wpdb->prepare("price >= %d", $criteria['min_price']);
        }
        
        if (!empty($criteria['max_price'])) {
            $conditions[] = $wpdb->prepare("price <= %d", $criteria['max_price']);
        }
        
        // Bedrooms (REAL WPL column: bedrooms)
        if (!empty($criteria['bedrooms'])) {
            $conditions[] = $wpdb->prepare("bedrooms >= %d", $criteria['bedrooms']);
        }
        
        // Bathrooms (REAL WPL column: bathrooms)
        if (!empty($criteria['bathrooms'])) {
            $conditions[] = $wpdb->prepare("bathrooms >= %d", $criteria['bathrooms']);
        }
        
        // Location filters (REAL WPL columns: location1_name, location2_name, etc.)
        if (!empty($criteria['city'])) {
            $conditions[] = $wpdb->prepare("(location1_name LIKE %s OR location2_name LIKE %s OR location3_name LIKE %s)", 
                '%' . $criteria['city'] . '%', 
                '%' . $criteria['city'] . '%', 
                '%' . $criteria['city'] . '%'
            );
        }
        
        // Property type
        if (!empty($criteria['property_type'])) {
            $conditions[] = $wpdb->prepare("property_type = %d", $criteria['property_type']);
        }
        
        // Listing type
        if (!empty($criteria['listing_type'])) {
            $conditions[] = $wpdb->prepare("listing = %d", $criteria['listing_type']);
        }
        
        return $conditions;
    }
    
    /**
     * Format WPL properties for Follow Up Boss (REAL DATA)
     * 
     * @param array $raw_properties Raw WPL properties
     * @return array Formatted properties
     */
    private function format_properties_for_fub($raw_properties) {
        $formatted_properties = array();
        
        foreach ($raw_properties as $property) {
            $formatted = array(
                // Core identifiers
                'id' => $property['id'],
                'mls_id' => $property['mls_id'] ?? '',
                'listing_id' => $property['id'],
                
                // Basic property info (REAL WPL columns)
                'price' => floatval($property['price'] ?? 0),
                'bedrooms' => floatval($property['bedrooms'] ?? 0),
                'bathrooms' => floatval($property['bathrooms'] ?? 0),
                'square_feet' => floatval($property['living_area'] ?? 0),
                'lot_area' => floatval($property['lot_area'] ?? 0),
                
                // Address information (REAL WPL columns)
                'street' => $property['field_42'] ?? '',  // Street field
                'location1' => $property['location1_name'] ?? '',
                'location2' => $property['location2_name'] ?? '',
                'location3' => $property['location3_name'] ?? '',
                'location4' => $property['location4_name'] ?? '',
                'post_code' => $property['post_code'] ?? '',
                'full_address' => $this->build_full_address($property),
                
                // Property details (REAL WPL fields)
                'description' => $property['field_308'] ?? '',  // Property Description
                'property_title' => $property['field_312'] ?? $property['property_title'] ?? '',
                'property_type' => $property['property_type'] ?? 0,
                'listing_type' => $property['listing'] ?? 0,
                'view_type' => $property['field_7'] ?? 0,  // Garden/Street/Sea view
                
                // Geographic data
                'latitude' => $property['googlemap_lt'] ?? '',
                'longitude' => $property['googlemap_ln'] ?? '',
                'googlemap_title' => $property['googlemap_title'] ?? '',
                
                // Metadata
                'date_added' => $property['add_date'] ?? '',
                'last_modified' => $property['last_modified_time_stamp'] ?? '',
                'status' => $this->determine_status($property),
                'build_year' => $property['build_year'] ?? 0,
                
                // Special flags
                'featured' => $property['sp_featured'] ?? 0,
                'hot_offer' => $property['sp_hot'] ?? 0,
                'open_house' => $property['sp_openhouse'] ?? 0,
                
                // Raw data for debugging
                'raw_data' => $property
            );
            
            $formatted_properties[] = $formatted;
        }
        
        return $formatted_properties;
    }
    
    /**
     * Build full address from WPL location fields
     */
    private function build_full_address($property) {
        $address_parts = array();
        
        // Add street if available
        if (!empty($property['field_42'])) {
            $address_parts[] = $property['field_42'];
        }
        
        // Add street number if available
        if (!empty($property['street_no'])) {
            $address_parts[] = $property['street_no'];
        }
        
        // Add location levels
        for ($i = 1; $i <= 7; $i++) {
            $location_field = 'location' . $i . '_name';
            if (!empty($property[$location_field])) {
                $address_parts[] = $property[$location_field];
            }
        }
        
        // Add postal code
        if (!empty($property['post_code'])) {
            $address_parts[] = $property['post_code'];
        }
        
        return implode(', ', array_filter($address_parts));
    }
    
    /**
     * Determine property status (REAL WPL logic)
     * 
     * @param array $property Raw property data
     * @return string Status
     */
    private function determine_status($property) {
        if (isset($property['deleted']) && $property['deleted'] == 1) {
            return 'deleted';
        }
        
        if (isset($property['confirmed']) && $property['confirmed'] == 1 && 
            isset($property['finalized']) && $property['finalized'] == 1) {
            return 'active';
        }
        
        return 'pending';
    }
    
    /**
     * Get total property count (REAL WPL data)
     * 
     * @return int Total properties
     */
    public function get_total_properties() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->properties_table} 
            WHERE confirmed = 1 
            AND finalized = 1 
            AND deleted = 0
        ");
        
        return intval($count);
    }
    
    /**
     * Get property by ID (REAL WPL data)
     * 
     * @param int $property_id Property ID
     * @return array|null Property data
     */
    public function get_property_by_id($property_id) {
        global $wpdb;
        
        $property = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$this->properties_table} 
            WHERE id = %d
        ", $property_id), ARRAY_A);
        
        if (!$property) {
            return null;
        }
        
        $formatted = $this->format_properties_for_fub(array($property));
        return $formatted[0] ?? null;
    }
    
    /**
     * Test database connection and table existence
     * 
     * @return array Test results
     */
    public function test_connection() {
        global $wpdb;
        
        $results = array(
            'table_exists' => false,
            'table_name' => $this->properties_table,
            'record_count' => 0,
            'active_properties' => 0,
            'sample_property' => null,
            'field_structure' => array(),
            'errors' => array()
        );
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = %s
        ", str_replace($wpdb->prefix, '', $this->properties_table)));
        
        $results['table_exists'] = ($table_exists > 0);
        
        if (!$results['table_exists']) {
            $results['errors'][] = "Table {$this->properties_table} does not exist";
            return $results;
        }
        
        // Get total record count
        $results['record_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->properties_table}");
        
        // Get active properties count
        $results['active_properties'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$this->properties_table} 
            WHERE confirmed = 1 AND finalized = 1 AND deleted = 0
        ");
        
        // Get sample property with full data
        $sample = $wpdb->get_row("
            SELECT id, mls_id, price, bedrooms, bathrooms, living_area, 
                   field_42, field_308, field_312, property_title, 
                   location1_name, location2_name, post_code, add_date,
                   confirmed, finalized, deleted
            FROM {$this->properties_table} 
            WHERE confirmed = 1 AND finalized = 1 AND deleted = 0 
            ORDER BY add_date DESC 
            LIMIT 1
        ", ARRAY_A);
        
        if ($sample) {
            $results['sample_property'] = $sample;
        }
        
        // Get column structure to verify field mappings
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->properties_table}", ARRAY_A);
        foreach ($columns as $column) {
            $results['field_structure'][] = $column['Field'];
        }
        
        return $results;
    }
    
    /**
     * Sync properties to Follow Up Boss
     * 
     * @param array $properties Properties to sync
     * @return array Sync results
     */
    public function sync_to_fub($properties) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($properties as $property) {
            try {
                // Format property for FUB API
                $fub_property = $this->format_for_fub_api($property);
                
                // Send to Follow Up Boss (implement API call here)
                $response = $this->send_to_fub($fub_property);
                
                if ($response['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Property {$property['id']}: " . $response['error'];
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Property {$property['id']}: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Format property for FUB API
     */
    private function format_for_fub_api($property) {
        return array(
            'listingId' => $property['mls_id'],
            'address' => $property['full_address'],
            'price' => $property['price'],
            'bedrooms' => $property['bedrooms'],
            'bathrooms' => $property['bathrooms'],
            'squareFootage' => $property['square_feet'],
            'description' => $property['description'],
            'listingUrl' => home_url("/property/{$property['id']}/"),
            'status' => $property['status']
        );
    }
    
    /**
     * Send property to Follow Up Boss
     */
    private function send_to_fub($property_data) {
        // Implement FUB API call here
        // For now, return success
        return array(
            'success' => true,
            'fub_id' => 'FUB_' . uniqid()
        );
    }
}

// Initialize WPL integration
add_action('init', function() {
    global $fub_wpl_integration;
    $fub_wpl_integration = new FUB_WPL_Integration();
});
?>
