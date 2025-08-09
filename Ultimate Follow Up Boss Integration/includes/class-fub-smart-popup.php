<?php
/**
 * Smart Popup - AI-Triggered Contact Capture
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Smart_Popup
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Smart_Popup {
    
    private static $instance = null;
    private $ai_recommender;
    private $behavioral_tracker;
    
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
        $this->ai_recommender = FUB_AI_Recommender::get_instance();
        $this->behavioral_tracker = FUB_Behavioral_Tracker::get_instance();
        $this->init();
    }
    
    /**
     * Initialize smart popup
     */
    private function init() {
        // Enqueue popup scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_popup_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_ufub_show_smart_popup', array($this, 'ajax_show_popup'));
        add_action('wp_ajax_nopriv_ufub_show_smart_popup', array($this, 'ajax_show_popup'));
        
        add_action('wp_ajax_ufub_submit_popup_form', array($this, 'ajax_submit_form'));
        add_action('wp_ajax_nopriv_ufub_submit_popup_form', array($this, 'ajax_submit_form'));
        
        add_action('wp_ajax_ufub_dismiss_popup', array($this, 'ajax_dismiss_popup'));
        add_action('wp_ajax_nopriv_ufub_dismiss_popup', array($this, 'ajax_dismiss_popup'));
        
        // Add popup HTML to footer
        add_action('wp_footer', array($this, 'render_popup_html'));
    }
    
    /**
     * Enqueue popup scripts and styles
     */
    public function enqueue_popup_scripts() {
        if (!get_option('ufub_popup_enabled', true)) {
            return;
        }
        
        wp_enqueue_script(
            'ufub-smart-popup',
            UFUB_PLUGIN_URL . 'assets/js/smart-popup.js',
            array('jquery'),
            UFUB_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ufub-smart-popup',
            UFUB_PLUGIN_URL . 'assets/css/smart-popup.css',
            array(),
            UFUB_VERSION
        );
        
        wp_localize_script('ufub-smart-popup', 'ufub_popup', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ufub_popup_nonce'),
            'settings' => array(
                'trigger_delay' => get_option('ufub_popup_delay', 5),
                'max_shows_per_session' => get_option('ufub_popup_max_shows', 1),
                'respect_dismissal_days' => get_option('ufub_popup_dismissal_days', 7)
            )
        ));
    }
    
    /**
     * AJAX: Show smart popup
     */
    public function ajax_show_popup() {
        check_ajax_referer('ufub_popup_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        // Check if popup should be shown
        if (!$this->should_show_popup($session_id)) {
            wp_send_json_error('Popup criteria not met');
        }
        
        // Get AI recommendation
        $recommendation = $this->ai_recommender->generate_recommendation($session_id);
        
        if (!$recommendation || $recommendation['confidence_score'] < get_option('ufub_popup_min_confidence', 60)) {
            wp_send_json_error('Insufficient confidence for popup');
        }
        
        // Generate popup content
        $popup_content = $this->generate_popup_content($recommendation);
        
        // Track popup show
        $this->track_popup_event($session_id, 'shown', $recommendation['confidence_score']);
        
        wp_send_json_success(array(
            'content' => $popup_content,
            'recommendation' => $recommendation
        ));
    }
    
    /**
     * AJAX: Submit popup form
     */
    public function ajax_submit_form() {
        check_ajax_referer('ufub_popup_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $form_data = wp_unslash($_POST['form_data'] ?? array());
        
        // Validate form data
        $validation = $this->validate_form_data($form_data);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }
        
        // Process contact capture
        $contact_result = $this->process_contact_capture($session_id, $form_data);
        
        if (is_wp_error($contact_result)) {
            wp_send_json_error($contact_result->get_error_message());
        }
        
        // Track successful conversion
        $this->track_popup_event($session_id, 'converted', null, $form_data);
        
        wp_send_json_success(array(
            'message' => 'Thank you! I\'ll be in touch with personalized recommendations.',
            'contact_id' => $contact_result['contact_id']
        ));
    }
    
    /**
     * AJAX: Dismiss popup
     */
    public function ajax_dismiss_popup() {
        check_ajax_referer('ufub_popup_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $reason = sanitize_text_field($_POST['reason'] ?? 'user_dismissed');
        
        $this->track_popup_event($session_id, 'dismissed', null, array('reason' => $reason));
        
        // Set dismissal cookie
        $dismissal_days = get_option('ufub_popup_dismissal_days', 7);
        setcookie('ufub_popup_dismissed', time(), time() + ($dismissal_days * 24 * 60 * 60), '/');
        
        wp_send_json_success();
    }
    
    /**
     * Check if popup should be shown
     */
    private function should_show_popup($session_id) {
        // Check if popups are enabled
        if (!get_option('ufub_popup_enabled', true)) {
            return false;
        }
        
        // Check if user has dismissed popup recently
        if (isset($_COOKIE['ufub_popup_dismissed'])) {
            $dismissed_time = intval($_COOKIE['ufub_popup_dismissed']);
            $dismissal_days = get_option('ufub_popup_dismissal_days', 7);
            
            if ((time() - $dismissed_time) < ($dismissal_days * 24 * 60 * 60)) {
                return false;
            }
        }
        
        // Check session behavior count
        $behavior_count = $this->behavioral_tracker->get_session_behavior_count($session_id);
        $min_views = get_option('ufub_popup_min_views', 3);
        
        if ($behavior_count < $min_views) {
            return false;
        }
        
        // Check engagement score
        $engagement_score = $this->behavioral_tracker->calculate_engagement_score($session_id);
        $min_engagement = get_option('ufub_popup_min_engagement', 30);
        
        if ($engagement_score < $min_engagement) {
            return false;
        }
        
        // Check if popup was already shown in this session
        $popup_shows = $this->get_session_popup_shows($session_id);
        $max_shows = get_option('ufub_popup_max_shows', 1);
        
        if ($popup_shows >= $max_shows) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate popup content based on AI recommendation
     */
    private function generate_popup_content($recommendation) {
        $preferences = $recommendation['preferences'];
        $analysis = $recommendation['analysis'];
        $recommendations = $recommendation['recommendations'];
        
        // Select popup template based on urgency
        $template = $this->get_popup_template($preferences['urgency_level']);
        
        // Customize content
        $content = array(
            'headline' => $this->generate_headline($preferences, $analysis),
            'message' => $recommendations['message'],
            'offer' => $this->generate_offer($preferences),
            'form_fields' => $this->get_form_fields($preferences),
            'urgency_indicators' => $recommendations['urgency_indicators'],
            'template' => $template
        );
        
        return $content;
    }
    
    /**
     * Generate personalized headline
     */
    private function generate_headline($preferences, $analysis) {
        $headlines = array(
            'high' => array(
                'I Found Your Perfect Home!',
                'Exclusive Match for Your Search',
                'Your Dream Home Awaits',
                'Perfect Properties Just Listed'
            ),
            'medium' => array(
                'Properties Matching Your Interests',
                'Handpicked Homes for You',
                'See What\'s New in Your Area',
                'Don\'t Miss These Great Options'
            ),
            'low' => array(
                'Let Me Help Find Your Home',
                'Personalized Property Recommendations',
                'Get Insider Access to Listings',
                'Stay Updated on New Properties'
            )
        );
        
        $urgency = $preferences['urgency_level'];
        $options = $headlines[$urgency];
        
        return $options[array_rand($options)];
    }
    
    /**
     * Generate personalized offer
     */
    private function generate_offer($preferences) {
        $offers = array(
            'high' => array(
                'Free market analysis of your preferred area',
                'Private showing of exclusive listings',
                'First access to new properties',
                'Personalized property alerts'
            ),
            'medium' => array(
                'Weekly curated property list',
                'Neighborhood market reports',
                'Free consultation call',
                'Custom search setup'
            ),
            'low' => array(
                'Monthly market updates',
                'Free buyer\'s guide',
                'Property value estimates',
                'Educational resources'
            )
        );
        
        $urgency = $preferences['urgency_level'];
        $options = $offers[$urgency];
        
        return $options[array_rand($options)];
    }
    
    /**
     * Get form fields based on preferences
     */
    private function get_form_fields($preferences) {
        $fields = array(
            array(
                'name' => 'first_name',
                'type' => 'text',
                'label' => 'First Name',
                'required' => true,
                'placeholder' => 'Enter your first name'
            ),
            array(
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email Address',
                'required' => true,
                'placeholder' => 'your@email.com'
            ),
            array(
                'name' => 'phone',
                'type' => 'tel',
                'label' => 'Phone Number',
                'required' => false,
                'placeholder' => '(555) 123-4567'
            )
        );
        
        // Add preference-specific fields
        if ($preferences['urgency_level'] === 'high') {
            $fields[] = array(
                'name' => 'best_time',
                'type' => 'select',
                'label' => 'Best Time to Call',
                'required' => false,
                'options' => array(
                    'morning' => 'Morning (9am-12pm)',
                    'afternoon' => 'Afternoon (12pm-5pm)',
                    'evening' => 'Evening (5pm-8pm)'
                )
            );
        }
        
        return $fields;
    }
    
    /**
     * Get popup template
     */
    private function get_popup_template($urgency_level) {
        $templates = array(
            'high' => 'urgent',
            'medium' => 'standard',
            'low' => 'casual'
        );
        
        return $templates[$urgency_level] ?? 'standard';
    }
    
    /**
     * Validate form data
     */
    private function validate_form_data($form_data) {
        // Required fields
        if (empty($form_data['first_name'])) {
            return array('valid' => false, 'message' => 'First name is required');
        }
        
        if (empty($form_data['email']) || !is_email($form_data['email'])) {
            return array('valid' => false, 'message' => 'Valid email address is required');
        }
        
        // Optional phone validation
        if (!empty($form_data['phone'])) {
            $phone = preg_replace('/[^\d]/', '', $form_data['phone']);
            if (strlen($phone) < 10) {
                return array('valid' => false, 'message' => 'Please enter a valid phone number');
            }
        }
        
        return array('valid' => true);
    }
    
    /**
     * Process contact capture
     */
    private function process_contact_capture($session_id, $form_data) {
        // Get IDX capture instance
        $idx_capture = FUB_IDX_Capture::get_instance();
        
        // Get session's search criteria
        $search_criteria = $this->get_session_search_criteria($session_id);
        
        // Prepare contact data for FUB
        $contact_data = array(
            'firstName' => sanitize_text_field($form_data['first_name']),
            'emails' => array(
                array('value' => sanitize_email($form_data['email']))
            ),
            'source' => 'Smart Popup - AI Triggered',
            'tags' => array('AI Qualified Lead')
        );
        
        if (!empty($form_data['phone'])) {
            $contact_data['phones'] = array(
                array('value' => sanitize_text_field($form_data['phone']))
            );
        }
        
        // Add behavioral insights as notes
        $behavioral_summary = $this->generate_behavioral_summary($session_id);
        $contact_data['notes'] = $behavioral_summary;
        
        // Create contact and saved search
        return $idx_capture->capture_contact($contact_data, $search_criteria);
    }
    
    /**
     * Get session search criteria
     */
    private function get_session_search_criteria($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        $latest_search = $wpdb->get_row($wpdb->prepare(
            "SELECT search_criteria FROM {$table} WHERE session_id = %s ORDER BY created_at DESC LIMIT 1",
            $session_id
        ));
        
        if ($latest_search) {
            return json_decode($latest_search->search_criteria, true);
        }
        
        return array();
    }
    
    /**
     * Generate behavioral summary for contact notes
     */
    private function generate_behavioral_summary($session_id) {
        $behavioral_data = $this->behavioral_tracker->get_session_data($session_id);
        $engagement_score = $this->behavioral_tracker->calculate_engagement_score($session_id);
        
        $summary = "AI-Qualified Lead - Engagement Score: {$engagement_score}/100\n\n";
        $summary .= "Properties Viewed: " . count($behavioral_data) . "\n";
        
        if (!empty($behavioral_data)) {
            $total_time = array_sum(array_column($behavioral_data, 'time_spent'));
            $summary .= "Total Time Spent: " . gmdate('H:i:s', $total_time) . "\n";
            
            $avg_scroll = array_sum(array_column($behavioral_data, 'max_scroll')) / count($behavioral_data);
            $summary .= "Average Scroll Depth: " . round($avg_scroll) . "%\n\n";
            
            $summary .= "Recent Properties:\n";
            foreach (array_slice($behavioral_data, 0, 3) as $view) {
                $details = json_decode($view->property_details, true);
                $summary .= "- " . ($details['title'] ?? 'Property ' . $view->property_id) . " ({$view->time_spent}s)\n";
            }
        }
        
        return $summary;
    }
    
    /**
     * Track popup events
     */
    private function track_popup_event($session_id, $event_type, $confidence_score = null, $additional_data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        $log_data = array(
            'endpoint' => 'smart_popup',
            'method' => $event_type,
            'request_data' => wp_json_encode(array(
                'session_id' => $session_id,
                'confidence_score' => $confidence_score,
                'additional_data' => $additional_data
            )),
            'response_code' => 200,
            'response_data' => wp_json_encode(array('success' => true)),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $table,
            $log_data,
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get session popup shows count
     */
    private function get_session_popup_shows($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_api_logs';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE endpoint = 'smart_popup' AND method = 'shown' AND request_data LIKE %s",
            '%"session_id":"' . $session_id . '"%'
        ));
    }
    
    /**
     * Render popup HTML in footer
     */
    public function render_popup_html() {
        if (!get_option('ufub_popup_enabled', true)) {
            return;
        }
        ?>
        <div id="ufub-smart-popup" class="ufub-popup-overlay" style="display: none;">
            <div class="ufub-popup-container">
                <div class="ufub-popup-header">
                    <button class="ufub-popup-close">&times;</button>
                </div>
                <div class="ufub-popup-content">
                    <!-- Content will be dynamically loaded -->
                </div>
            </div>
        </div>
        <?php
    }
}
