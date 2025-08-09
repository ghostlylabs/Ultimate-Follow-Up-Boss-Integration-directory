<?php
/**
 * Behavioral Tracker - Property Viewing Analytics
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Behavioral_Tracker
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Behavioral_Tracker {
    
    private static $instance = null;
    
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
     * Initialize behavioral tracking
     */
    private function init() {
        // Hook into page views
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_scripts'));
        
        // AJAX handlers for tracking
        add_action('wp_ajax_ufub_track_property_view', array($this, 'ajax_track_property_view'));
        add_action('wp_ajax_nopriv_ufub_track_property_view', array($this, 'ajax_track_property_view'));
        
        add_action('wp_ajax_ufub_track_scroll_depth', array($this, 'ajax_track_scroll_depth'));
        add_action('wp_ajax_nopriv_ufub_track_scroll_depth', array($this, 'ajax_track_scroll_depth'));
        
        add_action('wp_ajax_ufub_track_time_spent', array($this, 'ajax_track_time_spent'));
        add_action('wp_ajax_nopriv_ufub_track_time_spent', array($this, 'ajax_track_time_spent'));
    }
    
    /**
     * Enqueue tracking scripts
     */
    public function enqueue_tracking_scripts() {
        if (!get_option('ufub_tracking_enabled', true)) {
            return;
        }
        
        // Only track on property/listing pages
        if (!$this->is_property_page()) {
            return;
        }
        
        wp_enqueue_script(
            'ufub-behavioral-tracker',
            UFUB_PLUGIN_URL . 'assets/js/fub-tracking.js',
            array('jquery'),
            UFUB_VERSION,
            true
        );
        
        wp_localize_script('ufub-behavioral-tracker', 'ufub_tracker', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ufub_tracking_nonce'),
            'property_id' => $this->extract_property_id(),
            'tracking_interval' => get_option('ufub_tracking_interval', 5) // seconds
        ));
    }
    
    /**
     * Check if current page is a property listing
     */
    private function is_property_page() {
        global $post;
        
        // Check for property post types
        $property_post_types = array('property', 'listing', 'real-estate', 'homes');
        
        if (is_single() && in_array(get_post_type(), $property_post_types)) {
            return true;
        }
        
        // Check for RealtyNA or IDX property pages
        $url = $_SERVER['REQUEST_URI'] ?? '';
        
        $property_patterns = array(
            '/property/',
            '/listing/',
            '/home/',
            '/mls/',
            '/realtyNA/',
            'property_id=',
            'listing_id=',
            'mls_id='
        );
        
        foreach ($property_patterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract property ID from URL or post
     */
    private function extract_property_id() {
        global $post;
        
        // Try to get from URL parameters
        $property_id = $_GET['property_id'] ?? $_GET['listing_id'] ?? $_GET['mls_id'] ?? null;
        
        if ($property_id) {
            return sanitize_text_field($property_id);
        }
        
        // Try to get from post
        if ($post) {
            // Check for custom field property ID
            $meta_property_id = get_post_meta($post->ID, 'property_id', true) ?: 
                               get_post_meta($post->ID, 'listing_id', true) ?: 
                               get_post_meta($post->ID, 'mls_id', true);
            
            if ($meta_property_id) {
                return $meta_property_id;
            }
            
            return $post->ID;
        }
        
        // Extract from URL patterns
        $url = $_SERVER['REQUEST_URI'] ?? '';
        
        if (preg_match('/property[\/\-_](\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/listing[\/\-_](\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    /**
     * AJAX: Track property view
     */
    public function ajax_track_property_view() {
        check_ajax_referer('ufub_tracking_nonce', 'nonce');
        
        $property_id = sanitize_text_field($_POST['property_id'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $referrer = sanitize_text_field($_POST['referrer'] ?? '');
        $user_agent = sanitize_text_field($_POST['user_agent'] ?? '');
        
        $view_id = $this->record_property_view($property_id, $session_id, $referrer, $user_agent);
        
        wp_send_json_success(array('view_id' => $view_id));
    }
    
    /**
     * AJAX: Track scroll depth
     */
    public function ajax_track_scroll_depth() {
        check_ajax_referer('ufub_tracking_nonce', 'nonce');
        
        $view_id = intval($_POST['view_id'] ?? 0);
        $scroll_depth = intval($_POST['scroll_depth'] ?? 0);
        $max_scroll = intval($_POST['max_scroll'] ?? 0);
        
        $this->update_scroll_depth($view_id, $scroll_depth, $max_scroll);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Track time spent
     */
    public function ajax_track_time_spent() {
        check_ajax_referer('ufub_tracking_nonce', 'nonce');
        
        $view_id = intval($_POST['view_id'] ?? 0);
        $time_spent = intval($_POST['time_spent'] ?? 0); // seconds
        $is_active = (bool) ($_POST['is_active'] ?? true);
        
        $this->update_time_spent($view_id, $time_spent, $is_active);
        
        wp_send_json_success();
    }
    
    /**
     * Record a property view
     */
    private function record_property_view($property_id, $session_id, $referrer = '', $user_agent = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_behavioral_data';
        
        // Get property details
        $property_details = $this->get_property_details($property_id);
        
        $view_data = array(
            'session_id' => $session_id,
            'property_id' => $property_id,
            'property_details' => wp_json_encode($property_details),
            'view_time' => current_time('mysql'),
            'time_spent' => 0,
            'scroll_depth' => 0,
            'max_scroll' => 0,
            'referrer' => $referrer,
            'user_agent' => $user_agent,
            'is_active' => 1
        );
        
        $wpdb->insert(
            $table,
            $view_data,
            array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update scroll depth for a view
     */
    private function update_scroll_depth($view_id, $scroll_depth, $max_scroll) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_behavioral_data';
        
        $wpdb->update(
            $table,
            array(
                'scroll_depth' => $scroll_depth,
                'max_scroll' => max($max_scroll, $scroll_depth)
            ),
            array('id' => $view_id),
            array('%d', '%d'),
            array('%d')
        );
    }
    
    /**
     * Update time spent for a view
     */
    private function update_time_spent($view_id, $time_spent, $is_active) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_behavioral_data';
        
        $wpdb->update(
            $table,
            array(
                'time_spent' => $time_spent,
                'is_active' => $is_active ? 1 : 0,
                'last_activity' => current_time('mysql')
            ),
            array('id' => $view_id),
            array('%d', '%d', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get property details for tracking
     */
    private function get_property_details($property_id) {
        global $post;
        
        $details = array();
        
        // If it's a WordPress post
        if (is_numeric($property_id) && $property_id > 0) {
            $property_post = get_post($property_id);
            
            if ($property_post) {
                $details['title'] = get_the_title($property_id);
                $details['price'] = get_post_meta($property_id, 'price', true) ?: 
                                  get_post_meta($property_id, 'listing_price', true);
                $details['beds'] = get_post_meta($property_id, 'beds', true) ?: 
                                  get_post_meta($property_id, 'bedrooms', true);
                $details['baths'] = get_post_meta($property_id, 'baths', true) ?: 
                                   get_post_meta($property_id, 'bathrooms', true);
                $details['sqft'] = get_post_meta($property_id, 'sqft', true) ?: 
                                  get_post_meta($property_id, 'square_feet', true);
                $details['property_type'] = get_post_meta($property_id, 'property_type', true);
                $details['location'] = get_post_meta($property_id, 'location', true) ?: 
                                      get_post_meta($property_id, 'address', true);
            }
        }
        
        // Fallback to current post
        if (empty($details) && $post) {
            $details['title'] = get_the_title();
            $details['url'] = get_permalink();
            $details['post_type'] = get_post_type();
        }
        
        // Extract from page content if needed
        if (empty($details['price']) || empty($details['beds'])) {
            $page_content = get_the_content();
            
            // Try to extract price from content
            if (preg_match('/\$[\d,]+/', $page_content, $price_matches)) {
                $details['price'] = $price_matches[0];
            }
            
            // Try to extract beds/baths
            if (preg_match('/(\d+)\s*bed/i', $page_content, $bed_matches)) {
                $details['beds'] = $bed_matches[1];
            }
            
            if (preg_match('/(\d+)\s*bath/i', $page_content, $bath_matches)) {
                $details['baths'] = $bath_matches[1];
            }
        }
        
        return $details;
    }
    
    /**
     * Get session behavior count
     */
    public function get_session_behavior_count($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_behavioral_data';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE session_id = %s",
            $session_id
        ));
    }
    
    /**
     * Get session behavioral data
     */
    public function get_session_data($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_behavioral_data';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s ORDER BY view_time DESC",
            $session_id
        ));
    }
    
    /**
     * Get engagement score for a session
     */
    public function calculate_engagement_score($session_id) {
        $data = $this->get_session_data($session_id);
        
        if (empty($data)) {
            return 0;
        }
        
        $total_score = 0;
        $view_count = count($data);
        
        foreach ($data as $view) {
            $score = 0;
            
            // Time spent score (max 40 points)
            $time_score = min(40, ($view->time_spent / 60) * 10); // 10 points per minute, max 4 minutes
            
            // Scroll depth score (max 30 points)
            $scroll_score = ($view->max_scroll / 100) * 30;
            
            // Multiple views bonus (max 30 points)
            $view_bonus = min(30, $view_count * 5);
            
            $total_score += $time_score + $scroll_score + $view_bonus;
        }
        
        return min(100, $total_score / $view_count);
    }
}
