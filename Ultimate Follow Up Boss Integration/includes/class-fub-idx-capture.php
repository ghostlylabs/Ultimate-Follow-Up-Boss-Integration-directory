<?php
/**
 * IDX Search Capture - RealtyNA Integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage IDX_Capture
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_IDX_Capture {
    
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
     * Initialize IDX capture
     */
    private function init() {
        // Hook into common IDX search forms
        add_action('wp_footer', array($this, 'inject_capture_script'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handler for search capture
        add_action('wp_ajax_ufub_capture_idx_search', array($this, 'ajax_capture_search'));
        add_action('wp_ajax_nopriv_ufub_capture_idx_search', array($this, 'ajax_capture_search'));
    }
    
    /**
     * Enqueue capture scripts
     */
    public function enqueue_scripts() {
        if (!get_option('ufub_tracking_enabled', true)) {
            return;
        }
        
        wp_enqueue_script(
            'ufub-idx-capture',
            UFUB_PLUGIN_URL . 'assets/js/fub-tracking.js',
            array('jquery'),
            UFUB_VERSION,
            true
        );
        
        wp_localize_script('ufub-idx-capture', 'ufub_idx', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ufub_idx_nonce')
        ));
    }
    
    /**
     * Inject capture script for various IDX providers
     */
    public function inject_capture_script() {
        if (!get_option('ufub_tracking_enabled', true)) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // RealtyNA form selectors
            var realtynaSelectors = [
                '.dsSearchAgent-form',
                '.realtyNA-search-form',
                'form[action*="realtyNA"]',
                'form[action*="search"]'
            ];
            
            // General IDX form selectors
            var idxSelectors = [
                'form[class*="search"]',
                'form[id*="search"]',
                'form[class*="idx"]',
                'form[id*="idx"]',
                '.property-search-form',
                '.listing-search-form'
            ];
            
            var allSelectors = realtynaSelectors.concat(idxSelectors);
            
            // Monitor form submissions
            allSelectors.forEach(function(selector) {
                $(document).on('submit', selector, function(e) {
                    ufub_captureIDXSearch($(this));
                });
            });
            
            // Monitor individual field changes for real-time capture
            $(document).on('change', 'input[name*="price"], select[name*="beds"], input[name*="location"], select[name*="property_type"]', function() {
                var form = $(this).closest('form');
                if (form.length) {
                    ufub_capturePartialSearch(form);
                }
            });
        });
        
        function ufub_captureIDXSearch(form) {
            var searchData = {
                action: 'ufub_capture_idx_search',
                nonce: ufub_idx.nonce,
                search_type: 'full_search',
                form_data: {}
            };
            
            // Extract search criteria
            searchData.form_data = ufub_extractSearchCriteria(form);
            
            // Send to server
            $.post(ufub_idx.ajax_url, searchData, function(response) {
                if (response.success && response.data.popup_trigger) {
                    ufub_showSmartPopup(response.data.popup_data);
                }
            });
        }
        
        function ufub_capturePartialSearch(form) {
            var searchData = {
                action: 'ufub_capture_idx_search',
                nonce: ufub_idx.nonce,
                search_type: 'partial_search',
                form_data: ufub_extractSearchCriteria(form)
            };
            
            $.post(ufub_idx.ajax_url, searchData);
        }
        
        function ufub_extractSearchCriteria(form) {
            var criteria = {};
            
            // Price range
            var minPrice = form.find('input[name*="min_price"], input[name*="price_min"], select[name*="min_price"] option:selected').val();
            var maxPrice = form.find('input[name*="max_price"], input[name*="price_max"], select[name*="max_price"] option:selected').val();
            
            if (minPrice) criteria.min_price = minPrice;
            if (maxPrice) criteria.max_price = maxPrice;
            
            // Bedrooms
            var beds = form.find('select[name*="beds"] option:selected, input[name*="beds"]').val();
            if (beds) criteria.beds = beds;
            
            // Bathrooms
            var baths = form.find('select[name*="baths"] option:selected, input[name*="baths"]').val();
            if (baths) criteria.baths = baths;
            
            // Square footage
            var sqft = form.find('input[name*="sqft"], select[name*="sqft"] option:selected').val();
            if (sqft) criteria.sqft = sqft;
            
            // Location
            var location = form.find('input[name*="location"], input[name*="city"], input[name*="zip"], select[name*="area"] option:selected').val();
            if (location) criteria.location = location;
            
            // Property type
            var propType = form.find('select[name*="type"] option:selected, input[name*="type"]:checked').val();
            if (propType) criteria.property_type = propType;
            
            return criteria;
        }
        </script>
        <?php
    }
    
    /**
     * AJAX: Capture IDX search
     */
    public function ajax_capture_search() {
        check_ajax_referer('ufub_idx_nonce', 'nonce');
        
        $search_type = sanitize_text_field($_POST['search_type'] ?? 'full_search');
        $form_data = wp_unslash($_POST['form_data'] ?? array());
        
        // Store search data
        $search_id = $this->store_search_data($form_data, $search_type);
        
        $response_data = array('search_id' => $search_id);
        
        // Check if we should trigger smart popup
        if ($search_type === 'full_search') {
            $popup_check = $this->check_popup_trigger($form_data);
            if ($popup_check['should_trigger']) {
                $response_data['popup_trigger'] = true;
                $response_data['popup_data'] = $popup_check['popup_data'];
            }
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Store search data in database
     */
    private function store_search_data($criteria, $search_type) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        // Generate session ID if not exists
        $session_id = $this->get_or_create_session_id();
        
        $search_data = array(
            'user_email' => '', // Will be filled when contact is captured
            'search_criteria' => wp_json_encode($criteria),
            'status' => 'anonymous',
            'search_type' => $search_type,
            'session_id' => $session_id,
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $table,
            $search_data,
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Check if smart popup should be triggered
     */
    private function check_popup_trigger($criteria) {
        // Get behavioral tracker instance
        $behavioral_tracker = FUB_Behavioral_Tracker::get_instance();
        $ai_recommender = FUB_AI_Recommender::get_instance();
        
        // Check if user has enough behavioral data
        $session_id = $this->get_or_create_session_id();
        $behavior_count = $behavioral_tracker->get_session_behavior_count($session_id);
        
        $properties_before_popup = get_option('ufub_ai_properties_before_popup', 5);
        
        if ($behavior_count >= $properties_before_popup) {
            // Get AI recommendation
            $recommendation = $ai_recommender->generate_recommendation($session_id);
            
            if ($recommendation && $recommendation['confidence_score'] >= get_option('ufub_ai_confidence_required', 70)) {
                return array(
                    'should_trigger' => true,
                    'popup_data' => array(
                        'recommendation' => $recommendation,
                        'search_criteria' => $criteria
                    )
                );
            }
        }
        
        return array('should_trigger' => false);
    }
    
    /**
     * Capture contact information and sync to FUB
     */
    public function capture_contact($contact_data, $search_criteria) {
        // Create contact in Follow Up Boss
        $fub_response = $this->api->create_contact($contact_data);
        
        if (is_wp_error($fub_response)) {
            return $fub_response;
        }
        
        $contact_id = $fub_response['id'] ?? null;
        
        if ($contact_id) {
            // Create saved search in FUB
            $search_response = $this->api->create_saved_search($contact_id, $search_criteria);
            
            // Update local database
            $this->update_search_with_contact($contact_data['email'], $contact_id, $search_response['id'] ?? null);
        }
        
        return array(
            'success' => true,
            'contact_id' => $contact_id,
            'message' => 'Contact and search saved successfully'
        );
    }
    
    /**
     * Update search record with contact information
     */
    private function update_search_with_contact($email, $fub_contact_id, $fub_search_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        $session_id = $this->get_or_create_session_id();
        
        $wpdb->update(
            $table,
            array(
                'user_email' => $email,
                'fub_contact_id' => $fub_contact_id,
                'fub_search_id' => $fub_search_id,
                'status' => 'active',
                'updated_at' => current_time('mysql')
            ),
            array('session_id' => $session_id, 'status' => 'anonymous'),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%s', '%s')
        );
    }
    
    /**
     * Get or create session ID
     */
    private function get_or_create_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['ufub_session_id'])) {
            $_SESSION['ufub_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['ufub_session_id'];
    }
}
