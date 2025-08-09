<?php
/**
 * FUB Favorites Handler - Track Property Favorites/Saves
 * 
 * High-intent behavior tracking - when users save/favorite specific properties
 * This is premium intent signal for agents - better than just viewing
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Favorites
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Favorites {
    
    private static $instance = null;
    private $api;
    private $events;
    
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
        $this->events = FUB_Events::get_instance();
        $this->init();
    }
    
    /**
     * Initialize favorites tracking
     */
    private function init() {
        // AJAX handlers for favorites
        add_action('wp_ajax_ufub_favorite_property', array($this, 'ajax_favorite_property'));
        add_action('wp_ajax_nopriv_ufub_favorite_property', array($this, 'ajax_favorite_property'));
        
        add_action('wp_ajax_ufub_unfavorite_property', array($this, 'ajax_unfavorite_property'));
        add_action('wp_ajax_nopriv_ufub_unfavorite_property', array($this, 'ajax_unfavorite_property'));
        
        add_action('wp_ajax_ufub_get_favorites', array($this, 'ajax_get_favorites'));
        add_action('wp_ajax_nopriv_ufub_get_favorites', array($this, 'ajax_get_favorites'));
        
        // Enqueue favorites JavaScript
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue JavaScript for favorites functionality
     */
    public function enqueue_scripts() {
        if (!is_admin()) {
            wp_add_inline_script('ufub-tracking', $this->get_favorites_javascript());
        }
    }
    
    /**
     * AJAX handler for favoriting a property
     */
    public function ajax_favorite_property() {
        $property_id = intval($_POST['property_id'] ?? 0);
        $property_data = $_POST['property_data'] ?? array();
        
        if (!$property_id) {
            wp_send_json_error('Invalid property ID');
        }
        
        // Get or create user session
        $user_id = get_current_user_id();
        $session_id = $this->events->get_session_id();
        
        // Store favorite locally
        $favorite_id = $this->save_favorite($user_id, $session_id, $property_id, $property_data);
        
        if ($favorite_id) {
            // Send HIGH PRIORITY event to FUB - this is premium intent!
            $this->send_favorite_to_fub($user_id, $session_id, $property_id, $property_data);
            
            wp_send_json_success(array(
                'message' => 'Property favorited',
                'favorite_id' => $favorite_id
            ));
        } else {
            wp_send_json_error('Failed to save favorite');
        }
    }
    
    /**
     * AJAX handler for unfavoriting a property
     */
    public function ajax_unfavorite_property() {
        $property_id = intval($_POST['property_id'] ?? 0);
        
        if (!$property_id) {
            wp_send_json_error('Invalid property ID');
        }
        
        $user_id = get_current_user_id();
        $session_id = $this->events->get_session_id();
        
        $result = $this->remove_favorite($user_id, $session_id, $property_id);
        
        if ($result) {
            wp_send_json_success('Property unfavorited');
        } else {
            wp_send_json_error('Failed to remove favorite');
        }
    }
    
    /**
     * Save favorite to database
     */
    private function save_favorite($user_id, $session_id, $property_id, $property_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_favorites';
        
        // Check if already favorited
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE property_id = %d AND (user_id = %d OR session_id = %s)",
            $property_id, $user_id, $session_id
        ));
        
        if ($existing) {
            return $existing; // Already favorited
        }
        
        // Insert new favorite
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id ?: null,
                'session_id' => $session_id,
                'property_id' => $property_id,
                'property_data' => wp_json_encode($property_data),
                'favorited_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Send favorite event to Follow Up Boss - HIGH PRIORITY!
     */
    private function send_favorite_to_fub($user_id, $session_id, $property_id, $property_data) {
        // This is HIGH INTENT behavior - prioritize it!
        $event_data = array(
            'event_type' => 'property_favorited',
            'priority' => 'HIGH', // Mark as high priority
            'property_id' => $property_id,
            'property_details' => $property_data,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'timestamp' => current_time('c'),
            'intent_score' => 85, // High intent score for favoriting
            'description' => 'User favorited a property - HIGH INTENT behavior!'
        );
        
        // Add property details for context
        if (!empty($property_data)) {
            $event_data['property_address'] = $property_data['address'] ?? '';
            $event_data['property_price'] = $property_data['price'] ?? '';
            $event_data['property_beds'] = $property_data['beds'] ?? '';
            $event_data['property_baths'] = $property_data['baths'] ?? '';
        }
        
        // Get user info if available
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $event_data['user_email'] = $user->user_email;
                $event_data['user_name'] = $user->display_name;
            }
        }
        
        // Send to FUB as high-priority event
        $result = $this->api->send_event('Property Favorited', $event_data);
        
        if ($result && !is_wp_error($result)) {
            error_log('[FUB FAVORITES] Sent high-priority favorite event to FUB for property ' . $property_id);
        } else {
            error_log('[FUB FAVORITES] Failed to send favorite event to FUB: ' . ($result ? $result->get_error_message() : 'Unknown error'));
        }
        
        return $result;
    }
    
    /**
     * Remove favorite from database
     */
    private function remove_favorite($user_id, $session_id, $property_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_favorites';
        
        $result = $wpdb->delete(
            $table,
            array(
                'property_id' => $property_id,
                'user_id' => $user_id ?: 0,
                'session_id' => $session_id
            ),
            array('%d', '%d', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get user's favorites
     */
    public function ajax_get_favorites() {
        $user_id = get_current_user_id();
        $session_id = $this->events->get_session_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'fub_favorites';
        
        $favorites = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE (user_id = %d OR session_id = %s) ORDER BY favorited_at DESC",
            $user_id, $session_id
        ));
        
        $formatted_favorites = array();
        foreach ($favorites as $favorite) {
            $property_data = json_decode($favorite->property_data, true) ?: array();
            $formatted_favorites[] = array(
                'id' => $favorite->id,
                'property_id' => $favorite->property_id,
                'property_data' => $property_data,
                'favorited_at' => $favorite->favorited_at
            );
        }
        
        wp_send_json_success($formatted_favorites);
    }
    
    /**
     * Get favorites JavaScript for frontend
     */
    private function get_favorites_javascript() {
        return '
        // Property Favorites Functionality
        window.ufub_favorites = {
            favorite: function(propertyId, propertyData) {
                fetch(ufub_ajax_url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        action: "ufub_favorite_property",
                        property_id: propertyId,
                        property_data: JSON.stringify(propertyData || {}),
                        nonce: ufub_nonce
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log("Property favorited:", data.data);
                        // Update UI to show favorited state
                        this.updateFavoriteButton(propertyId, true);
                    } else {
                        console.error("Favorite failed:", data.data);
                    }
                })
                .catch(error => console.error("Favorite error:", error));
            },
            
            unfavorite: function(propertyId) {
                fetch(ufub_ajax_url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        action: "ufub_unfavorite_property",
                        property_id: propertyId,
                        nonce: ufub_nonce
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log("Property unfavorited");
                        // Update UI to show unfavorited state
                        this.updateFavoriteButton(propertyId, false);
                    } else {
                        console.error("Unfavorite failed:", data.data);
                    }
                })
                .catch(error => console.error("Unfavorite error:", error));
            },
            
            updateFavoriteButton: function(propertyId, isFavorited) {
                const buttons = document.querySelectorAll(`[data-property-id="${propertyId}"]`);
                buttons.forEach(button => {
                    if (isFavorited) {
                        button.classList.add("favorited");
                        button.innerHTML = "❤️ Favorited";
                    } else {
                        button.classList.remove("favorited");
                        button.innerHTML = "🤍 Save";
                    }
                });
            }
        };
        
        // Auto-attach to favorite buttons
        document.addEventListener("DOMContentLoaded", function() {
            document.addEventListener("click", function(e) {
                if (e.target.matches(".ufub-favorite-btn")) {
                    e.preventDefault();
                    const propertyId = e.target.dataset.propertyId;
                    const isFavorited = e.target.classList.contains("favorited");
                    
                    if (isFavorited) {
                        ufub_favorites.unfavorite(propertyId);
                    } else {
                        // Extract property data from button or page
                        const propertyData = {
                            address: e.target.dataset.address || "",
                            price: e.target.dataset.price || "",
                            beds: e.target.dataset.beds || "",
                            baths: e.target.dataset.baths || ""
                        };
                        ufub_favorites.favorite(propertyId, propertyData);
                    }
                }
            });
        });
        ';
    }
    
    /**
     * Create favorites table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fub_favorites';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(100) NOT NULL,
            property_id bigint(20) NOT NULL,
            property_data longtext,
            favorited_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY property_id (property_id),
            KEY favorited_at (favorited_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize favorites tracking
if (class_exists('FUB_Favorites')) {
    FUB_Favorites::get_instance();
}
?>
