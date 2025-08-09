<?php
/**
 * Follow Up Boss API Integration
 * Real API connection and methods for FUB integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage API
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_API {
    
    private static $instance = null;
    private $api_key;
    private $api_url = 'https://api.followupboss.com/v1';
    private $timeout = 30;
    
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
        $this->api_key = get_option('ufub_api_key', '');
    }
    
    /**
     * Test connection to Follow Up Boss API
     * 
     * @return array|WP_Error
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Follow Up Boss API key is not configured');
        }
        
        $response = wp_remote_get($this->api_url . '/people', array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log_api_call('test_connection', 'GET', array(), $response);
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log_api_call('test_connection', 'GET', array(), $response_code, $response_body);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'Successfully connected to Follow Up Boss API',
                'response_code' => $response_code
            );
        } elseif ($response_code === 401) {
            return new WP_Error('invalid_api_key', 'Invalid API key. Please check your Follow Up Boss API credentials.');
        } else {
            return new WP_Error('connection_failed', 'Failed to connect to Follow Up Boss API. Response code: ' . $response_code);
        }
    }
    
    /**
     * Create or update contact in Follow Up Boss (Updated to match official FUB API format)
     * 
     * @param string $email Contact email address
     * @param string $name Contact full name
     * @param string $phone Contact phone number (optional)
     * @param array $additional_data Additional contact data
     * @return array|WP_Error
     */
    public function create_contact($email, $name = '', $phone = '', $additional_data = array()) {
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Valid email address is required');
        }
        
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Follow Up Boss API key is not configured');
        }
        
        // Parse name into first and last
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';
        
        // Prepare contact data in FUB's exact format
        $contact_data = array(
            'emails' => array(
                array('value' => $email, 'type' => 'Work')
            )
        );
        
        if (!empty($first_name)) {
            $contact_data['firstName'] = $first_name;
        }
        
        if (!empty($last_name)) {
            $contact_data['lastName'] = $last_name;
        }
        
        if (!empty($phone)) {
            $contact_data['phones'] = array(
                array('value' => $phone, 'type' => 'Mobile')
            );
        }
        
        // Add additional data
        if (!empty($additional_data['source'])) {
            $contact_data['source'] = $additional_data['source'];
        } else {
            $contact_data['source'] = get_site_url() ?: 'Ultimate FUB Integration';
        }
        
        if (!empty($additional_data['tags']) && is_array($additional_data['tags'])) {
            $contact_data['tags'] = $additional_data['tags'];
        }
        
        if (!empty($additional_data['notes'])) {
            $contact_data['notes'] = $additional_data['notes'];
        }
        
        $response = wp_remote_post($this->api_url . '/people', array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($contact_data)
        ));
        
        if (is_wp_error($response)) {
            $this->log_api_call('create_contact', 'POST', $contact_data, $response);
            error_log('FUB API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $parsed_response = json_decode($response_body, true);
        
        $this->log_api_call('create_contact', 'POST', $contact_data, $response_code, $response_body);
        
        if ($response_code === 201) {
            error_log('FUB: New contact created');
            return array(
                'success' => true,
                'contact_id' => $parsed_response['id'] ?? null,
                'data' => $parsed_response,
                'message' => 'New contact created successfully',
                'is_new_contact' => true
            );
        } elseif ($response_code === 200) {
            error_log('FUB: Existing contact updated');
            return array(
                'success' => true,
                'contact_id' => $parsed_response['id'] ?? null,
                'data' => $parsed_response,
                'message' => 'Existing contact updated successfully',
                'is_new_contact' => false
            );
        } else {
            $error_message = 'Failed to create contact. Response code: ' . $response_code;
            if (!empty($parsed_response['error'])) {
                $error_message .= ' - ' . $parsed_response['error'];
            }
            error_log('FUB Error: Status code ' . $response_code);
            error_log('FUB Response: ' . $response_body);
            return new WP_Error('contact_creation_failed', $error_message);
        }
    }
    
    /**
     * Create saved search in Follow Up Boss
     * 
     * @param string $contact_id FUB contact ID
     * @param array $criteria Search criteria
     * @return array|WP_Error
     */
    public function create_saved_search($contact_id, $criteria) {
        if (empty($contact_id)) {
            return new WP_Error('invalid_contact_id', 'Contact ID is required');
        }
        
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Follow Up Boss API key is not configured');
        }
        
        // Prepare saved search data
        $search_data = array(
            'personId' => $contact_id,
            'name' => $this->generate_search_name($criteria),
            'criteria' => $this->format_search_criteria($criteria),
            'isActive' => true
        );
        
        $response = wp_remote_post($this->api_url . '/savedSearches', array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => wp_json_encode($search_data)
        ));
        
        if (is_wp_error($response)) {
            $this->log_api_call('create_saved_search', 'POST', $search_data, $response);
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $parsed_response = json_decode($response_body, true);
        
        $this->log_api_call('create_saved_search', 'POST', $search_data, $response_code, $response_body);
        
        if ($response_code === 200 || $response_code === 201) {
            return array(
                'success' => true,
                'search_id' => $parsed_response['id'] ?? null,
                'data' => $parsed_response,
                'message' => 'Saved search created successfully'
            );
        } else {
            $error_message = 'Failed to create saved search. Response code: ' . $response_code;
            if (!empty($parsed_response['error'])) {
                $error_message .= ' - ' . $parsed_response['error'];
            }
            return new WP_Error('saved_search_failed', $error_message);
        }
    }
    
    /**
     * Send event to Follow Up Boss (Updated to match official FUB API format)
     * 
     * @param string $type Event type (Property Inquiry, Registration, General Inquiry, Seller Inquiry)
     * @param array $data Event data
     * @return array|WP_Error
     */
    public function send_event($type, $data) {
        if (empty($type)) {
            return new WP_Error('invalid_event_type', 'Event type is required');
        }
        
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Follow Up Boss API key is not configured');
        }
        
        // Format data to match FUB's exact structure
        $event_data = array(
            'source' => get_site_url() ?: 'Ultimate FUB Integration',
            'type' => $type,
            'message' => $data['message'] ?? '',
            'person' => array(
                'firstName' => $data['first_name'] ?? '',
                'lastName' => $data['last_name'] ?? '',
                'emails' => array(
                    array('value' => $data['email'] ?? '')
                ),
                'phones' => !empty($data['phone']) ? array(
                    array('value' => $data['phone'])
                ) : array(),
                'tags' => $data['tags'] ?? array('Website Lead')
            )
        );
        
        // Add property data if this is a property inquiry
        if (!empty($data['property'])) {
            $event_data['property'] = array(
                'street' => $data['property']['street'] ?? '',
                'city' => $data['property']['city'] ?? '',
                'state' => $data['property']['state'] ?? '',
                'code' => $data['property']['zip'] ?? '',
                'mlsNumber' => $data['property']['mls'] ?? '',
                'price' => intval($data['property']['price'] ?? 0),
                'forRent' => false,
                'url' => $data['property']['url'] ?? '',
                'type' => $data['property']['type'] ?? 'Single-Family Home',
                'bedrooms' => intval($data['property']['beds'] ?? 0),
                'bathrooms' => floatval($data['property']['baths'] ?? 0)
            );
        }
        
        $response = wp_remote_post($this->api_url . '/events', array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($event_data)
        ));
        
        if (is_wp_error($response)) {
            $this->log_api_call('send_event', 'POST', $event_data, $response);
            error_log('FUB API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $parsed_response = json_decode($response_body, true);
        
        $this->log_api_call('send_event', 'POST', $event_data, $response_code, $response_body);
        
        if ($response_code === 201) {
            error_log('FUB: New contact created');
            return array(
                'success' => true,
                'event_id' => $parsed_response['id'] ?? null,
                'data' => $parsed_response,
                'message' => 'New contact created successfully',
                'is_new_contact' => true
            );
        } elseif ($response_code === 200) {
            error_log('FUB: Existing contact updated');
            return array(
                'success' => true,
                'event_id' => $parsed_response['id'] ?? null,
                'data' => $parsed_response,
                'message' => 'Existing contact updated successfully',
                'is_new_contact' => false
            );
        } else {
            $error_message = 'Failed to send event. Response code: ' . $response_code;
            if (!empty($parsed_response['error'])) {
                $error_message .= ' - ' . $parsed_response['error'];
            }
            error_log('FUB Error: Status code ' . $response_code);
            error_log('FUB Response: ' . $response_body);
            return new WP_Error('event_send_failed', $error_message);
        }
    }
    
    /**
     * Send property alert to Follow Up Boss
     * 
     * @param string $user_email Contact email address
     * @param array $property_data Property details
     * @param array $saved_search Saved search criteria that was matched
     * @return array|WP_Error
     */
    public function send_property_alert($user_email, $property_data, $saved_search = array()) {
        if (empty($user_email) || !is_email($user_email)) {
            return new WP_Error('invalid_email', 'Valid email address is required');
        }
        
        if (empty($property_data)) {
            return new WP_Error('no_property_data', 'Property data is required');
        }
        
        // Format property details for FUB
        $property_details = array(
            'address' => $property_data['address'] ?? $property_data['location'] ?? 'Address not available',
            'price' => !empty($property_data['price']) ? '$' . number_format($property_data['price']) : 'Price on request',
            'bedrooms' => $property_data['bedrooms'] ?? $property_data['beds'] ?? 'N/A',
            'bathrooms' => $property_data['bathrooms'] ?? $property_data['baths'] ?? 'N/A',
            'sqft' => !empty($property_data['sqft']) ? number_format($property_data['sqft']) . ' sqft' : 'N/A',
            'property_type' => $property_data['property_type'] ?? 'Residential',
            'mls_id' => $property_data['mls_id'] ?? $property_data['property_id'] ?? '',
            'url' => $property_data['url'] ?? $property_data['permalink'] ?? ''
        );
        
        // Create alert message
        $alert_message = "🏠 New Property Match Alert!\n\n";
        $alert_message .= "A new property matching your saved search criteria is now available:\n\n";
        $alert_message .= "📍 Address: {$property_details['address']}\n";
        $alert_message .= "💰 Price: {$property_details['price']}\n";
        $alert_message .= "🛏️ Bedrooms: {$property_details['bedrooms']}\n";
        $alert_message .= "🚿 Bathrooms: {$property_details['bathrooms']}\n";
        $alert_message .= "📏 Square Feet: {$property_details['sqft']}\n";
        $alert_message .= "🏡 Type: {$property_details['property_type']}\n";
        
        if (!empty($property_details['mls_id'])) {
            $alert_message .= "🏷️ MLS ID: {$property_details['mls_id']}\n";
        }
        
        if (!empty($property_details['url'])) {
            $alert_message .= "\n👉 View Property: {$property_details['url']}\n";
        }
        
        // Add search criteria context if provided
        if (!empty($saved_search)) {
            $alert_message .= "\n📋 Your Search Criteria:\n";
            if (!empty($saved_search['search_name'])) {
                $alert_message .= "Search: {$saved_search['search_name']}\n";
            }
            if (!empty($saved_search['search_criteria'])) {
                $criteria = is_string($saved_search['search_criteria']) ? 
                    json_decode($saved_search['search_criteria'], true) : 
                    $saved_search['search_criteria'];
                
                if (isset($criteria['min_price']) || isset($criteria['max_price'])) {
                    $price_range = '';
                    if (isset($criteria['min_price'])) $price_range .= '$' . number_format($criteria['min_price']);
                    if (isset($criteria['min_price']) && isset($criteria['max_price'])) $price_range .= ' - ';
                    if (isset($criteria['max_price'])) $price_range .= '$' . number_format($criteria['max_price']);
                    $alert_message .= "Price Range: {$price_range}\n";
                }
                if (!empty($criteria['bedrooms'])) {
                    $alert_message .= "Bedrooms: {$criteria['bedrooms']}+\n";
                }
                if (!empty($criteria['location'])) {
                    $alert_message .= "Location: {$criteria['location']}\n";
                }
            }
        }
        
        $alert_message .= "\nThis property was automatically matched based on your preferences. Contact me if you'd like to schedule a showing or need more information!";
        
        // Send as Property Inquiry event to FUB
        $event_data = array(
            'email' => $user_email,
            'first_name' => $saved_search['user_first_name'] ?? '',
            'last_name' => $saved_search['user_last_name'] ?? '',
            'message' => $alert_message,
            'property_address' => $property_details['address'],
            'property_price' => $property_details['price'],
            'property_details' => $property_details,
            'alert_type' => 'property_match',
            'tags' => array('Property Alert', 'Auto Match', 'Saved Search')
        );
        
        // Use the existing send_event method with Property Inquiry type
        $result = $this->send_event('Property Inquiry', $event_data);
        
        if (!is_wp_error($result) && $result['success']) {
            error_log("FUB Property Alert sent: {$property_details['address']} to {$user_email}");
            
            // Add alert-specific metadata to response
            $result['alert_type'] = 'property_match';
            $result['property_address'] = $property_details['address'];
            $result['property_price'] = $property_details['price'];
        }
        
        return $result;
    }
    
    /**
     * Get contact by email from Follow Up Boss
     * 
     * @param string $email Contact email address
     * @return array|WP_Error
     */
    public function get_contact($email) {
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Valid email address is required');
        }
        
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Follow Up Boss API key is not configured');
        }
        
        $response = wp_remote_get($this->api_url . '/people?email=' . urlencode($email), array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log_api_call('get_contact', 'GET', array('email' => $email), $response);
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $parsed_response = json_decode($response_body, true);
        
        $this->log_api_call('get_contact', 'GET', array('email' => $email), $response_code, $response_body);
        
        if ($response_code === 200) {
            $contacts = $parsed_response['people'] ?? array();
            
            if (!empty($contacts)) {
                return array(
                    'success' => true,
                    'contact' => $contacts[0], // Return first match
                    'total_found' => count($contacts),
                    'message' => 'Contact found successfully'
                );
            } else {
                return array(
                    'success' => false,
                    'contact' => null,
                    'message' => 'No contact found with that email'
                );
            }
        } else {
            $error_message = 'Failed to get contact. Response code: ' . $response_code;
            if (!empty($parsed_response['error'])) {
                $error_message .= ' - ' . $parsed_response['error'];
            }
            return new WP_Error('get_contact_failed', $error_message);
        }
    }
    
    /**
     * Generate search name from criteria
     * 
     * @param array $criteria Search criteria
     * @return string
     */
    private function generate_search_name($criteria) {
        $name_parts = array();
        
        if (!empty($criteria['location'])) {
            $name_parts[] = $criteria['location'];
        }
        
        if (!empty($criteria['beds'])) {
            $name_parts[] = $criteria['beds'] . '+ beds';
        }
        
        if (!empty($criteria['min_price']) || !empty($criteria['max_price'])) {
            $price_range = '';
            if (!empty($criteria['min_price'])) {
                $price_range .= '$' . number_format($criteria['min_price']);
            }
            if (!empty($criteria['max_price'])) {
                $price_range .= (!empty($price_range) ? '-' : 'Under ') . '$' . number_format($criteria['max_price']);
            }
            $name_parts[] = $price_range;
        }
        
        if (!empty($criteria['property_type'])) {
            $name_parts[] = $criteria['property_type'];
        }
        
        if (empty($name_parts)) {
            return 'Property Search - ' . current_time('M j, Y');
        }
        
        return implode(' | ', $name_parts);
    }
    
    /**
     * Format search criteria for FUB saved search
     * 
     * @param array $criteria Raw search criteria
     * @return array Formatted criteria
     */
    private function format_search_criteria($criteria) {
        $formatted = array();
        
        if (!empty($criteria['min_price'])) {
            $formatted['minPrice'] = intval($criteria['min_price']);
        }
        
        if (!empty($criteria['max_price'])) {
            $formatted['maxPrice'] = intval($criteria['max_price']);
        }
        
        if (!empty($criteria['beds'])) {
            $formatted['minBedrooms'] = intval($criteria['beds']);
        }
        
        if (!empty($criteria['baths'])) {
            $formatted['minBathrooms'] = floatval($criteria['baths']);
        }
        
        if (!empty($criteria['sqft'])) {
            $formatted['minSquareFeet'] = intval($criteria['sqft']);
        }
        
        if (!empty($criteria['property_type'])) {
            $formatted['propertyType'] = $criteria['property_type'];
        }
        
        if (!empty($criteria['location'])) {
            $formatted['location'] = $criteria['location'];
        }
        
        return $formatted;
    }
    
    /**
     * Log API call for debugging and tracking
     * 
     * @param string $endpoint API endpoint called
     * @param string $method HTTP method
     * @param array $request_data Data sent to API
     * @param mixed $response_code HTTP response code or WP_Error
     * @param string $response_data Response body
     */
    private function log_api_call($endpoint, $method, $request_data, $response_code, $response_data = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        // Handle WP_Error objects
        if (is_wp_error($response_code)) {
            $error_code = 0;
            $error_data = wp_json_encode(array(
                'error_code' => $response_code->get_error_code(),
                'error_message' => $response_code->get_error_message()
            ));
        } else {
            $error_code = intval($response_code);
            $error_data = $response_data;
        }
        
        $wpdb->insert(
            $table,
            array(
                'endpoint' => $endpoint,
                'method' => $method,
                'request_data' => wp_json_encode($request_data),
                'response_code' => $error_code,
                'response_data' => $error_data,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Test API with demo data (for development/testing)
     * 
     * @param string $test_api_key Optional test API key (defaults to FUB demo key)
     * @return array Test results
     */
    public function test_api_with_demo($test_api_key = '7a5bad2cf150b388a7ecf1dca94d5e2d14694a') {
        $original_key = $this->api_key;
        $this->api_key = $test_api_key;
        
        $results = array();
        
        // Test 1: Connection test
        $results['connection_test'] = $this->test_connection();
        
        // Test 2: Property Inquiry event
        $results['property_inquiry'] = $this->send_event('Property Inquiry', array(
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '555-555-5555',
            'message' => 'Interested in this property from Ultimate FUB Integration',
            'tags' => array('Website Lead', 'Property Inquiry'),
            'property' => array(
                'street' => '123 Main St',
                'city' => 'Oakland',
                'state' => 'CA',
                'zip' => '94610',
                'price' => 500000,
                'beds' => 3,
                'baths' => 2,
                'type' => 'Single-Family Home',
                'url' => get_site_url() . '/property/123-main-st'
            )
        ));
        
        // Test 3: General Registration event
        $results['registration'] = $this->send_event('Registration', array(
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '555-123-4567',
            'message' => 'Signed up for property alerts',
            'tags' => array('Website Lead', 'Registration')
        ));
        
        // Test 4: Seller Inquiry (CMA Request)
        $results['seller_inquiry'] = $this->send_event('Seller Inquiry', array(
            'first_name' => 'John',
            'last_name' => 'Seller',
            'email' => 'john.seller@example.com',
            'phone' => '555-987-6543',
            'message' => 'Interested in getting a CMA for my home',
            'tags' => array('Website Lead', 'Seller Lead', 'CMA Request')
        ));
        
        // Restore original API key
        $this->api_key = $original_key;
        
        return $results;
    }
    
    /**
     * Get event type examples for documentation
     * 
     * @return array Event type examples
     */
    public function get_event_type_examples() {
        return array(
            'Property Inquiry' => array(
                'description' => 'User inquires about a specific property',
                'required_fields' => array('first_name', 'email', 'property'),
                'optional_fields' => array('last_name', 'phone', 'message', 'tags'),
                'example' => array(
                    'first_name' => 'John',
                    'last_name' => 'Buyer',
                    'email' => 'john@example.com',
                    'phone' => '555-555-5555',
                    'message' => 'I\'d like more information about this property',
                    'tags' => array('Website Lead', 'Property Inquiry'),
                    'property' => array(
                        'street' => '123 Main St',
                        'city' => 'Oakland',
                        'state' => 'CA',
                        'zip' => '94610',
                        'mls' => 'MLS123456',
                        'price' => 500000,
                        'beds' => 3,
                        'baths' => 2,
                        'type' => 'Single-Family Home',
                        'url' => 'https://yoursite.com/property/123-main-st'
                    )
                )
            ),
            'Registration' => array(
                'description' => 'User registers for property alerts or creates account',
                'required_fields' => array('first_name', 'email'),
                'optional_fields' => array('last_name', 'phone', 'message', 'tags'),
                'example' => array(
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'jane@example.com',
                    'phone' => '555-123-4567',
                    'message' => 'Please send me property updates',
                    'tags' => array('Website Lead', 'Registration')
                )
            ),
            'General Inquiry' => array(
                'description' => 'General contact form submission',
                'required_fields' => array('first_name', 'email', 'message'),
                'optional_fields' => array('last_name', 'phone', 'tags'),
                'example' => array(
                    'first_name' => 'Bob',
                    'last_name' => 'Johnson',
                    'email' => 'bob@example.com',
                    'phone' => '555-999-8888',
                    'message' => 'I\'m looking to buy a home in the area',
                    'tags' => array('Website Lead', 'General Inquiry')
                )
            ),
            'Seller Inquiry' => array(
                'description' => 'User interested in selling their property',
                'required_fields' => array('first_name', 'email'),
                'optional_fields' => array('last_name', 'phone', 'message', 'tags'),
                'example' => array(
                    'first_name' => 'Sarah',
                    'last_name' => 'Seller',
                    'email' => 'sarah@example.com',
                    'phone' => '555-777-6666',
                    'message' => 'I\'d like a market analysis for my home',
                    'tags' => array('Website Lead', 'Seller Lead', 'CMA Request')
                )
            )
        );
    }
}
