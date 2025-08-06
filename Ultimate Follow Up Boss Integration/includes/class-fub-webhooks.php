<?php
/**
 * FUB Webhooks Handler
 * 
 * Handles incoming webhooks from Follow Up Boss for real-time synchronization
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Webhooks
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Webhooks {
    
    private static $instance = null;
    private $api;
    private $webhook_secret;
    
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
        $this->webhook_secret = get_option('ufub_webhook_secret', '');
        $this->init();
    }
    
    /**
     * Initialize webhooks
     */
    private function init() {
        // Get API instance
        if (class_exists('FUB_API')) {
            $this->api = FUB_API::get_instance();
        }
        
        // Register webhook endpoints
        add_action('init', array($this, 'register_webhook_endpoints'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'maybe_setup_webhooks'));
        
        // AJAX handlers
        add_action('wp_ajax_ufub_setup_webhooks', array($this, 'ajax_setup_webhooks'));
        add_action('wp_ajax_ufub_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_ufub_delete_webhook', array($this, 'ajax_delete_webhook'));
        
        ufub_log_info('FUB Webhooks handler initialized');
    }
    
    /**
     * Register webhook endpoints
     */
    public function register_webhook_endpoints() {
        // Main webhook endpoint
        add_rewrite_rule(
            '^ufub-webhook/([^/]+)/?$',
            'index.php?ufub_webhook=1&webhook_type=$matches[1]',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'ufub_webhook';
            $vars[] = 'webhook_type';
            return $vars;
        });
    }
    
    /**
     * Handle webhook requests
     */
    public function handle_webhook($webhook_type) {
        // Verify request
        if (!$this->verify_webhook_request()) {
            http_response_code(401);
            exit('Unauthorized');
        }
        
        // Get webhook payload
        $payload = $this->get_webhook_payload();
        
        if (!$payload) {
            http_response_code(400);
            exit('Invalid payload');
        }
        
        // Process webhook based on type
        try {
            switch ($webhook_type) {
                case 'person-created':
                    $this->handle_person_created($payload);
                    break;
                    
                case 'person-updated':
                    $this->handle_person_updated($payload);
                    break;
                    
                case 'person-deleted':
                    $this->handle_person_deleted($payload);
                    break;
                    
                case 'event-created':
                    $this->handle_event_created($payload);
                    break;
                    
                case 'note-created':
                    $this->handle_note_created($payload);
                    break;
                    
                default:
                    ufub_log('WARNING', 'Unknown webhook type received', array(
                        'webhook_type' => $webhook_type,
                        'payload' => $payload
                    ));
                    http_response_code(400);
                    exit('Unknown webhook type');
            }
            
            // Success response
            http_response_code(200);
            echo json_encode(array('status' => 'success', 'message' => 'Webhook processed'));
            
        } catch (Exception $e) {
            ufub_log_error('Webhook processing error', array(
                'webhook_type' => $webhook_type,
                'error' => $e->getMessage(),
                'payload' => $payload
            ));
            
            http_response_code(500);
            exit('Internal server error');
        }
        
        exit;
    }
    
    /**
     * Handle person created webhook
     */
    private function handle_person_created($payload) {
        $person_data = $payload['person'] ?? array();
        
        if (empty($person_data['id'])) {
            throw new Exception('Person ID missing from webhook payload');
        }
        
        ufub_log_info('Person created webhook received', array(
            'person_id' => $person_data['id'],
            'email' => $person_data['emails'][0]['value'] ?? 'unknown'
        ));
        
        // Create WordPress user if enabled
        if (get_option('ufub_sync_create_users', true)) {
            $this->create_wp_user_from_fub($person_data);
        }
        
        // Store person mapping
        $this->store_person_mapping($person_data);
        
        // Trigger action for other plugins/themes
        do_action('ufub_person_created', $person_data);
    }
    
    /**
     * Handle person updated webhook
     */
    private function handle_person_updated($payload) {
        $person_data = $payload['person'] ?? array();
        
        if (empty($person_data['id'])) {
            throw new Exception('Person ID missing from webhook payload');
        }
        
        ufub_log_info('Person updated webhook received', array(
            'person_id' => $person_data['id'],
            'email' => $person_data['emails'][0]['value'] ?? 'unknown'
        ));
        
        // Update WordPress user if exists
        $this->update_wp_user_from_fub($person_data);
        
        // Update person mapping
        $this->store_person_mapping($person_data);
        
        // Trigger action
        do_action('ufub_person_updated', $person_data);
    }
    
    /**
     * Handle person deleted webhook
     */
    private function handle_person_deleted($payload) {
        $person_id = $payload['personId'] ?? '';
        
        if (empty($person_id)) {
            throw new Exception('Person ID missing from webhook payload');
        }
        
        ufub_log_info('Person deleted webhook received', array(
            'person_id' => $person_id
        ));
        
        // Handle WordPress user deletion based on settings
        $delete_wp_users = get_option('ufub_sync_delete_users', false);
        
        if ($delete_wp_users) {
            $this->delete_wp_user_by_fub_id($person_id);
        } else {
            // Just remove the mapping
            $this->remove_person_mapping($person_id);
        }
        
        // Trigger action
        do_action('ufub_person_deleted', $person_id);
    }
    
    /**
     * Handle event created webhook
     */
    private function handle_event_created($payload) {
        $event_data = $payload['event'] ?? array();
        
        ufub_log_info('Event created webhook received', array(
            'event_id' => $event_data['id'] ?? 'unknown',
            'event_type' => $event_data['type'] ?? 'unknown',
            'person_id' => $event_data['personId'] ?? 'unknown'
        ));
        
        // Store event data for analytics
        $this->store_event_data($event_data);
        
        // Trigger action
        do_action('ufub_event_created', $event_data);
    }
    
    /**
     * Handle note created webhook
     */
    private function handle_note_created($payload) {
        $note_data = $payload['note'] ?? array();
        
        ufub_log_info('Note created webhook received', array(
            'note_id' => $note_data['id'] ?? 'unknown',
            'person_id' => $note_data['personId'] ?? 'unknown'
        ));
        
        // Trigger action
        do_action('ufub_note_created', $note_data);
    }
    
    /**
     * Create WordPress user from FUB person data
     */
    private function create_wp_user_from_fub($person_data) {
        $email = $person_data['emails'][0]['value'] ?? '';
        
        if (empty($email) || email_exists($email)) {
            return false;
        }
        
        $user_data = array(
            'user_login' => sanitize_user($email),
            'user_email' => $email,
            'first_name' => $person_data['firstName'] ?? '',
            'last_name' => $person_data['lastName'] ?? '',
            'display_name' => trim(($person_data['firstName'] ?? '') . ' ' . ($person_data['lastName'] ?? '')),
            'user_pass' => wp_generate_password(),
            'role' => get_option('ufub_default_user_role', 'subscriber')
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (!is_wp_error($user_id)) {
            // Store FUB person ID
            update_user_meta($user_id, 'fub_person_id', $person_data['id']);
            
            // Store additional FUB data
            if (!empty($person_data['phones'][0]['value'])) {
                update_user_meta($user_id, 'phone', $person_data['phones'][0]['value']);
            }
            
            if (!empty($person_data['addresses'][0])) {
                $address = $person_data['addresses'][0];
                update_user_meta($user_id, 'address', $address['street'] ?? '');
                update_user_meta($user_id, 'city', $address['city'] ?? '');
                update_user_meta($user_id, 'state', $address['state'] ?? '');
                update_user_meta($user_id, 'zip', $address['code'] ?? '');
            }
            
            // Store custom fields
            if (!empty($person_data['customFields'])) {
                foreach ($person_data['customFields'] as $field) {
                    update_user_meta($user_id, 'fub_' . $field['name'], $field['value']);
                }
            }
            
            ufub_log_info('WordPress user created from FUB person', array(
                'user_id' => $user_id,
                'fub_person_id' => $person_data['id'],
                'email' => $email
            ));
            
            return $user_id;
        }
        
        return false;
    }
    
    /**
     * Update WordPress user from FUB person data
     */
    private function update_wp_user_from_fub($person_data) {
        // Find user by FUB person ID
        $users = get_users(array(
            'meta_key' => 'fub_person_id',
            'meta_value' => $person_data['id'],
            'number' => 1
        ));
        
        if (empty($users)) {
            // Try to find by email
            $email = $person_data['emails'][0]['value'] ?? '';
            if ($email) {
                $user = get_user_by('email', $email);
                if ($user) {
                    update_user_meta($user->ID, 'fub_person_id', $person_data['id']);
                    $users = array($user);
                }
            }
        }
        
        if (empty($users)) {
            return false;
        }
        
        $user = $users[0];
        
        // Update user data
        $user_data = array(
            'ID' => $user->ID,
            'first_name' => $person_data['firstName'] ?? '',
            'last_name' => $person_data['lastName'] ?? '',
            'display_name' => trim(($person_data['firstName'] ?? '') . ' ' . ($person_data['lastName'] ?? ''))
        );
        
        wp_update_user($user_data);
        
        // Update meta fields
        if (!empty($person_data['phones'][0]['value'])) {
            update_user_meta($user->ID, 'phone', $person_data['phones'][0]['value']);
        }
        
        ufub_log_info('WordPress user updated from FUB person', array(
            'user_id' => $user->ID,
            'fub_person_id' => $person_data['id']
        ));
        
        return $user->ID;
    }
    
    /**
     * Delete WordPress user by FUB ID
     */
    private function delete_wp_user_by_fub_id($fub_person_id) {
        $users = get_users(array(
            'meta_key' => 'fub_person_id',
            'meta_value' => $fub_person_id,
            'number' => 1
        ));
        
        if (!empty($users)) {
            $user_id = $users[0]->ID;
            
            if (wp_delete_user($user_id)) {
                ufub_log_info('WordPress user deleted via FUB webhook', array(
                    'user_id' => $user_id,
                    'fub_person_id' => $fub_person_id
                ));
            }
        }
    }
    
    /**
     * Store person mapping
     */
    private function store_person_mapping($person_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_person_mapping';
        
        // Create table if it doesn't exist
        $this->create_mapping_table_if_needed();
        
        $email = $person_data['emails'][0]['value'] ?? '';
        
        $wpdb->replace(
            $table,
            array(
                'fub_person_id' => $person_data['id'],
                'email' => $email,
                'first_name' => $person_data['firstName'] ?? '',
                'last_name' => $person_data['lastName'] ?? '',
                'phone' => $person_data['phones'][0]['value'] ?? '',
                'last_updated' => current_time('mysql'),
                'person_data' => wp_json_encode($person_data)
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Remove person mapping
     */
    private function remove_person_mapping($fub_person_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_person_mapping';
        
        $wpdb->delete(
            $table,
            array('fub_person_id' => $fub_person_id),
            array('%s')
        );
    }
    
    /**
     * Store event data
     */
    private function store_event_data($event_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_tracking_data';
        
        $wpdb->insert(
            $table,
            array(
                'session_id' => 'webhook_' . $event_data['id'],
                'fub_contact_id' => $event_data['personId'] ?? '',
                'event_type' => 'fub_' . strtolower(str_replace(' ', '_', $event_data['type'] ?? 'unknown')),
                'tracking_data' => wp_json_encode($event_data),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Verify webhook request
     */
    private function verify_webhook_request() {
        // If no secret is set, we can't verify
        if (empty($this->webhook_secret)) {
            ufub_log('WARNING', 'Webhook secret not configured - cannot verify request');
            return true; // Allow for now, but log warning
        }
        
        // Get signature from headers
        $signature = $_SERVER['HTTP_X_FUB_SIGNATURE'] ?? '';
        
        if (empty($signature)) {
            ufub_log_error('Webhook signature missing from request');
            return false;
        }
        
        // Get raw payload
        $payload = file_get_contents('php://input');
        
        // Calculate expected signature
        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhook_secret);
        
        // Verify signature
        if (!hash_equals($expected_signature, $signature)) {
            ufub_log_error('Webhook signature verification failed', array(
                'expected' => $expected_signature,
                'received' => $signature
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Get webhook payload
     */
    private function get_webhook_payload() {
        $payload = file_get_contents('php://input');
        
        if (empty($payload)) {
            return null;
        }
        
        $decoded = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            ufub_log_error('Invalid JSON in webhook payload', array(
                'json_error' => json_last_error_msg(),
                'payload' => substr($payload, 0, 500)
            ));
            return null;
        }
        
        return $decoded;
    }
    
    /**
     * Setup webhooks in FUB
     */
    public function setup_webhooks() {
        if (!$this->api) {
            return array('success' => false, 'error' => 'API not available');
        }
        
        $webhook_url = home_url('ufub-webhook');
        
        // Define webhook events to subscribe to
        $events = array(
            'personCreated',
            'personUpdated', 
            'personDeleted',
            'eventCreated',
            'noteCreated'
        );
        
        $results = array();
        
        foreach ($events as $event) {
            $webhook_data = array(
                'url' => $webhook_url . '/' . $this->convert_event_name($event),
                'events' => array($event),
                'active' => true
            );
            
            $result = $this->api->create_webhook($webhook_data['url'], $webhook_data['events']);
            
            if ($result && !isset($result['error'])) {
                $results[$event] = array(
                    'success' => true,
                    'webhook_id' => $result['id'],
                    'url' => $webhook_data['url']
                );
                
                // Store webhook ID for future reference
                update_option("ufub_webhook_{$event}_id", $result['id']);
                
            } else {
                $results[$event] = array(
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error'
                );
            }
        }
        
        ufub_log_info('Webhooks setup completed', $results);
        
        return $results;
    }
    
    /**
     * Convert event name for URL
     */
    private function convert_event_name($event) {
        $mapping = array(
            'personCreated' => 'person-created',
            'personUpdated' => 'person-updated',
            'personDeleted' => 'person-deleted',
            'eventCreated' => 'event-created',
            'noteCreated' => 'note-created'
        );
        
        return $mapping[$event] ?? strtolower($event);
    }
    
    /**
     * Maybe setup webhooks automatically
     */
    public function maybe_setup_webhooks() {
        // Only run this once per day
        $last_check = get_option('ufub_webhook_last_check', 0);
        if (time() - $last_check < DAY_IN_SECONDS) {
            return;
        }
        
        update_option('ufub_webhook_last_check', time());
        
        // Check if webhooks are already set up
        if (get_option('ufub_webhooks_configured', false)) {
            return;
        }
        
        // Auto-setup if API is configured
        if (get_option('ufub_api_key') && get_option('ufub_auto_setup_webhooks', true)) {
            $this->setup_webhooks();
            update_option('ufub_webhooks_configured', true);
        }
    }
    
    /**
     * Create mapping table if needed
     */
    private function create_mapping_table_if_needed() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ufub_person_mapping';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                fub_person_id varchar(100) NOT NULL,
                wp_user_id bigint(20) DEFAULT NULL,
                email varchar(255) NOT NULL,
                first_name varchar(100),
                last_name varchar(100),
                phone varchar(50),
                last_updated datetime DEFAULT CURRENT_TIMESTAMP,
                person_data longtext,
                PRIMARY KEY (id),
                UNIQUE KEY fub_person_id (fub_person_id),
                KEY email (email),
                KEY wp_user_id (wp_user_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * AJAX: Setup webhooks
     */
    public function ajax_setup_webhooks() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $results = $this->setup_webhooks();
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Send a test payload to our webhook endpoint
        $test_payload = array(
            'person' => array(
                'id' => 'test_' . time(),
                'firstName' => 'Test',
                'lastName' => 'User',
                'emails' => array(array('value' => 'test@example.com'))
            )
        );
        
        $webhook_url = home_url('ufub-webhook/person-created');
        
        $response = wp_remote_post($webhook_url, array(
            'body' => wp_json_encode($test_payload),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-FUB-Signature' => 'sha256=' . hash_hmac('sha256', wp_json_encode($test_payload), $this->webhook_secret)
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Webhook test failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            wp_send_json_success('Webhook test successful');
        } else {
            wp_send_json_error('Webhook test failed with response code: ' . $response_code);
        }
    }
    
    /**
     * AJAX: Delete webhook
     */
    public function ajax_delete_webhook() {
        check_ajax_referer('ufub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $webhook_id = sanitize_text_field($_POST['webhook_id']);
        
        if ($this->api) {
            $result = $this->api->delete_webhook($webhook_id);
            
            if ($result && !isset($result['error'])) {
                wp_send_json_success('Webhook deleted successfully');
            } else {
                wp_send_json_error('Failed to delete webhook: ' . ($result['error'] ?? 'Unknown error'));
            }
        } else {
            wp_send_json_error('API not available');
        }
    }
}