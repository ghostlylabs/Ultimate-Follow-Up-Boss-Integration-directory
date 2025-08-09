<?php
/**
 * FUB Webhooks Handler - CRITICAL for Bidirectional Sync
 * 
 * Receives webhooks FROM Follow Up Boss when things happen
 * Enables FUB to notify us about contact updates, property matches, etc.
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
        $this->init();
    }
    
    /**
     * Initialize webhooks
     */
    private function init() {
        // Register webhook endpoint
        add_action('init', array($this, 'register_webhook_endpoint'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Process webhook data
        add_action('wp_ajax_nopriv_ufub_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_ufub_webhook', array($this, 'handle_webhook'));
    }
    
    /**
     * Register webhook endpoint for FUB callbacks
     */
    public function register_webhook_endpoint() {
        // Add rewrite rule for webhook URL
        add_rewrite_rule(
            '^ufub-webhook/?$',
            'index.php?ufub_webhook=1',
            'top'
        );
        
        // Add query var
        add_filter('query_vars', function($vars) {
            $vars[] = 'ufub_webhook';
            return $vars;
        });
        
        // Handle webhook request
        add_action('template_redirect', array($this, 'handle_webhook_request'));
    }
    
    /**
     * Register REST API routes for webhooks
     */
    public function register_rest_routes() {
        register_rest_route('ufub/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_rest_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature')
        ));
    }
    
    /**
     * Handle webhook request via template redirect
     */
    public function handle_webhook_request() {
        if (get_query_var('ufub_webhook')) {
            $this->process_webhook();
            exit;
        }
    }
    
    /**
     * Handle REST API webhook
     */
    public function handle_rest_webhook($request) {
        $data = $request->get_json_params();
        return $this->process_webhook_data($data);
    }
    
    /**
     * Process incoming webhook
     */
    private function process_webhook() {
        // Get raw input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(array('error' => 'Invalid JSON'));
            return;
        }
        
        $result = $this->process_webhook_data($data);
        
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode($result);
    }
    
    /**
     * Process webhook data from FUB
     */
    private function process_webhook_data($data) {
        // Log incoming webhook
        $this->log_webhook($data);
        
        // Verify webhook signature if provided
        if (!$this->verify_webhook_data($data)) {
            return array('success' => false, 'error' => 'Invalid webhook signature');
        }
        
        // Process different webhook types
        $webhook_type = $data['type'] ?? 'unknown';
        
        switch ($webhook_type) {
            case 'contact.created':
                return $this->handle_contact_created($data);
                
            case 'contact.updated':
                return $this->handle_contact_updated($data);
                
            case 'event.created':
                return $this->handle_event_created($data);
                
            case 'property.match':
                return $this->handle_property_match($data);
                
            default:
                return $this->handle_generic_webhook($data);
        }
    }
    
    /**
     * Handle contact created webhook
     */
    private function handle_contact_created($data) {
        global $wpdb;
        
        $contact = $data['data']['contact'] ?? array();
        
        if (empty($contact['id'])) {
            return array('success' => false, 'error' => 'Missing contact ID');
        }
        
        // Store FUB contact ID for future reference
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        // Update any matching saved searches with FUB contact ID
        if (!empty($contact['email'])) {
            $wpdb->update(
                $table,
                array('fub_contact_id' => $contact['id']),
                array('user_email' => $contact['email']),
                array('%s'),
                array('%s')
            );
        }
        
        return array('success' => true, 'message' => 'Contact created processed');
    }
    
    /**
     * Handle contact updated webhook
     */
    private function handle_contact_updated($data) {
        $contact = $data['data']['contact'] ?? array();
        
        // Update local contact information if needed
        // This could sync contact preferences, tags, etc.
        
        return array('success' => true, 'message' => 'Contact updated processed');
    }
    
    /**
     * Handle event created webhook
     */
    private function handle_event_created($data) {
        $event = $data['data']['event'] ?? array();
        
        // Process events from FUB (emails clicked, responses, etc.)
        // Update behavioral tracking based on FUB activity
        
        if (!empty($event['contact_id']) && !empty($event['type'])) {
            // Log event for behavioral analysis
            $this->log_fub_event($event);
        }
        
        return array('success' => true, 'message' => 'Event processed');
    }
    
    /**
     * Handle property match webhook
     */
    private function handle_property_match($data) {
        // FUB might send us property match notifications
        // This could trigger additional actions on our side
        
        return array('success' => true, 'message' => 'Property match processed');
    }
    
    /**
     * Handle generic webhook
     */
    private function handle_generic_webhook($data) {
        // Store unknown webhook types for analysis
        return array('success' => true, 'message' => 'Generic webhook processed');
    }
    
    /**
     * Verify webhook signature/authenticity
     */
    private function verify_webhook_data($data) {
        // Implement webhook signature verification
        // FUB should provide a signature header to verify authenticity
        
        $signature = $_SERVER['HTTP_X_FUB_SIGNATURE'] ?? '';
        
        if (empty($signature)) {
            // For now, allow webhooks without signatures (development)
            return true;
        }
        
        // Verify signature against webhook secret
        $webhook_secret = get_option('ufub_webhook_secret', '');
        if (empty($webhook_secret)) {
            return true; // No secret configured
        }
        
        $expected_signature = hash_hmac('sha256', json_encode($data), $webhook_secret);
        
        return hash_equals($signature, $expected_signature);
    }
    
    /**
     * Verify webhook signature for REST API
     */
    public function verify_webhook_signature($request) {
        // Basic permission check - can be enhanced with signature verification
        return true;
    }
    
    /**
     * Log webhook for debugging
     */
    private function log_webhook($data) {
        global $wpdb;
        
        // Store webhook data for analysis
        $table = $wpdb->prefix . 'fub_api_logs';
        
        $wpdb->insert(
            $table,
            array(
                'event_type' => 'webhook_received',
                'request_data' => json_encode($data),
                'response_data' => '',
                'status' => 'received',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Log FUB event for behavioral tracking
     */
    private function log_fub_event($event) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_behavioral_data';
        
        $wpdb->insert(
            $table,
            array(
                'user_identifier' => $event['contact_id'],
                'event_type' => 'fub_activity',
                'event_data' => json_encode($event),
                'confidence_score' => 80, // FUB events are high confidence
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Get webhook URL for FUB configuration
     */
    public function get_webhook_url() {
        return home_url('/ufub-webhook/');
    }
    
    /**
     * Get REST webhook URL
     */
    public function get_rest_webhook_url() {
        return rest_url('ufub/v1/webhook');
    }
    
    /**
     * Setup webhook in FUB (if API supports it)
     */
    public function setup_fub_webhook() {
        // This would configure the webhook URL in FUB
        // Implementation depends on FUB's webhook API
        
        $webhook_url = $this->get_webhook_url();
        
        // Make API call to FUB to register our webhook
        // This is pseudocode - depends on FUB's actual API
        /*
        $response = $this->api->register_webhook(array(
            'url' => $webhook_url,
            'events' => array('contact.created', 'contact.updated', 'event.created')
        ));
        */
        
        return $webhook_url;
    }
}
?>
