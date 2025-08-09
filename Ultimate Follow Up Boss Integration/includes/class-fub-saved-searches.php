<?php
/**
 * FUB Saved Searches Handler - CRITICAL for Managing User Searches
 * 
 * Manages saved searches from users - stores criteria, retrieves for matching
 * This is HOW we know what users are looking for to match against new properties
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
        $this->api = FUB_API::get_instance();
        $this->init();
    }
    
    /**
     * Initialize saved searches
     */
    private function init() {
        // AJAX handlers for saved searches
        add_action('wp_ajax_ufub_save_search', array($this, 'ajax_save_search'));
        add_action('wp_ajax_nopriv_ufub_save_search', array($this, 'ajax_save_search'));
        
        add_action('wp_ajax_ufub_get_saved_searches', array($this, 'ajax_get_saved_searches'));
        add_action('wp_ajax_ufub_delete_saved_search', array($this, 'ajax_delete_saved_search'));
        
        // Capture IDX search forms
        add_action('wp_footer', array($this, 'inject_search_capture_js'));
    }
    
    /**
     * Save a search from user
     */
    public function save_search($search_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        // Validate required fields
        if (empty($search_data['user_email'])) {
            return new WP_Error('missing_email', 'Email is required for saved searches');
        }
        
        // Prepare search criteria
        $criteria = array(
            'location' => sanitize_text_field($search_data['location'] ?? ''),
            'min_price' => (int) ($search_data['min_price'] ?? 0),
            'max_price' => (int) ($search_data['max_price'] ?? 0),
            'beds' => (int) ($search_data['beds'] ?? 0),
            'baths' => (float) ($search_data['baths'] ?? 0),
            'property_type' => sanitize_text_field($search_data['property_type'] ?? ''),
            'keywords' => sanitize_text_field($search_data['keywords'] ?? '')
        );
        
        // Remove empty criteria
        $criteria = array_filter($criteria);
        
        $search_record = array(
            'user_email' => sanitize_email($search_data['user_email']),
            'user_first_name' => sanitize_text_field($search_data['first_name'] ?? ''),
            'user_last_name' => sanitize_text_field($search_data['last_name'] ?? ''),
            'user_phone' => sanitize_text_field($search_data['phone'] ?? ''),
            'search_criteria' => json_encode($criteria),
            'search_frequency' => sanitize_text_field($search_data['frequency'] ?? 'daily'),
            'is_active' => 1,
            'created_at' => current_time('mysql'),
            'last_checked' => current_time('mysql')
        );
        
        // Check if search already exists for this user
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE user_email = %s AND search_criteria = %s",
            $search_record['user_email'],
            $search_record['search_criteria']
        ));
        
        if ($existing) {
            // Update existing search
            $result = $wpdb->update(
                $table,
                array(
                    'search_frequency' => $search_record['search_frequency'],
                    'is_active' => 1,
                    'last_checked' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%d', '%s'),
                array('%d')
            );
            
            $search_id = $existing->id;
        } else {
            // Insert new search
            $result = $wpdb->insert($table, $search_record, array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
            ));
            
            $search_id = $wpdb->insert_id;
        }
        
        if ($result === false) {
            return new WP_Error('save_failed', 'Failed to save search');
        }
        
        // Send search to FUB as saved search
        $this->send_search_to_fub($search_record, $search_id);
        
        return array(
            'success' => true,
            'search_id' => $search_id,
            'message' => 'Search saved successfully'
        );
    }
    
    /**
     * Send saved search to Follow Up Boss
     */
    private function send_search_to_fub($search_data, $search_id) {
        // Create or update contact in FUB with saved search
        $contact_data = array(
            'first_name' => $search_data['user_first_name'],
            'last_name' => $search_data['user_last_name'],
            'email' => $search_data['user_email'],
            'phone' => $search_data['user_phone'],
            'tags' => array('Website Lead', 'Saved Search'),
            'saved_search_criteria' => $search_data['search_criteria'],
            'search_frequency' => $search_data['search_frequency']
        );
        
        $result = $this->api->send_event('Property Inquiry', $contact_data);
        
        if (!is_wp_error($result) && $result['success']) {
            // Update local record with FUB contact ID if available
            if (!empty($result['contact_id'])) {
                global $wpdb;
                $table = $wpdb->prefix . 'fub_saved_searches';
                
                $wpdb->update(
                    $table,
                    array('fub_contact_id' => $result['contact_id']),
                    array('id' => $search_id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Get all active saved searches
     */
    public function get_active_searches() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        $searches = $wpdb->get_results("
            SELECT * FROM $table 
            WHERE is_active = 1 
            ORDER BY created_at DESC
        ");
        
        // Parse search criteria JSON
        foreach ($searches as &$search) {
            $search->criteria = json_decode($search->search_criteria, true);
        }
        
        return $searches;
    }
    
    /**
     * Get saved searches for specific user
     */
    public function get_user_searches($user_email) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        $searches = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE user_email = %s AND is_active = 1 
            ORDER BY created_at DESC
        ", $user_email));
        
        // Parse search criteria JSON
        foreach ($searches as &$search) {
            $search->criteria = json_decode($search->search_criteria, true);
        }
        
        return $searches;
    }
    
    /**
     * Update last checked time for search
     */
    public function update_last_checked($search_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        return $wpdb->update(
            $table,
            array('last_checked' => current_time('mysql')),
            array('id' => $search_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Deactivate saved search
     */
    public function deactivate_search($search_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        
        return $wpdb->update(
            $table,
            array('is_active' => 0),
            array('id' => $search_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * AJAX handler to save search
     */
    public function ajax_save_search() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ufub_save_search')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $search_data = array(
            'user_email' => sanitize_email($_POST['email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'location' => sanitize_text_field($_POST['location'] ?? ''),
            'min_price' => (int) ($_POST['min_price'] ?? 0),
            'max_price' => (int) ($_POST['max_price'] ?? 0),
            'beds' => (int) ($_POST['beds'] ?? 0),
            'baths' => (float) ($_POST['baths'] ?? 0),
            'property_type' => sanitize_text_field($_POST['property_type'] ?? ''),
            'keywords' => sanitize_text_field($_POST['keywords'] ?? ''),
            'frequency' => sanitize_text_field($_POST['frequency'] ?? 'daily')
        );
        
        $result = $this->save_search($search_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX handler to get saved searches
     */
    public function ajax_get_saved_searches() {
        // Check nonce for admin requests
        check_ajax_referer('ufub_admin_nonce', 'nonce');
        
        // Check permissions for admin access
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // If email provided, get user-specific searches
        $user_email = sanitize_email($_POST['email'] ?? '');
        
        if (!empty($user_email)) {
            $searches = $this->get_user_searches($user_email);
        } else {
            // Get all saved searches for admin dashboard
            $searches = $this->get_active_searches();
        }
        
        wp_send_json_success($searches);
    }
    
    /**
     * AJAX handler to delete saved search
     */
    public function ajax_delete_saved_search() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ufub_delete_search')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $search_id = (int) ($_POST['search_id'] ?? 0);
        
        if (!$search_id) {
            wp_send_json_error('Invalid search ID');
            return;
        }
        
        $result = $this->deactivate_search($search_id);
        
        if ($result !== false) {
            wp_send_json_success('Search deleted');
        } else {
            wp_send_json_error('Failed to delete search');
        }
    }
    
    /**
     * Inject JavaScript to capture IDX searches
     */
    public function inject_search_capture_js() {
        if (!is_page() && !is_single()) {
            return; // Only on pages that might have IDX forms
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Capture IDX search forms
            $('form[action*="search"], form.property-search, .idx-search-form').on('submit', function(e) {
                var form = $(this);
                var searchData = {
                    location: form.find('input[name*="location"], input[name*="city"], input[name*="area"]').val() || '',
                    min_price: form.find('input[name*="min_price"], select[name*="min_price"]').val() || '',
                    max_price: form.find('input[name*="max_price"], select[name*="max_price"]').val() || '',
                    beds: form.find('select[name*="bed"], input[name*="bed"]').val() || '',
                    baths: form.find('select[name*="bath"], input[name*="bath"]').val() || '',
                    property_type: form.find('select[name*="type"], input[name*="type"]:checked').val() || ''
                };
                
                // Check if this looks like a property search
                if (searchData.location || searchData.min_price || searchData.max_price || searchData.beds) {
                    // Store search data for later capture
                    sessionStorage.setItem('ufub_last_search', JSON.stringify(searchData));
                    
                    // Show save search popup after a delay
                    setTimeout(function() {
                        ufub_show_save_search_popup(searchData);
                    }, 3000);
                }
            });
            
            // Function to show save search popup
            window.ufub_show_save_search_popup = function(searchData) {
                if ($('#ufub-save-search-popup').length > 0) {
                    return; // Already shown
                }
                
                var popup = $('<div id="ufub-save-search-popup" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:9999;max-width:400px;width:90%;">')
                    .html(`
                        <h3>Save Your Search</h3>
                        <p>Get notified when new properties match your criteria!</p>
                        <form id="ufub-save-search-form">
                            <input type="email" name="email" placeholder="Your Email" required style="width:100%;margin:5px 0;padding:8px;">
                            <input type="text" name="first_name" placeholder="First Name" style="width:48%;margin:5px 1%;padding:8px;">
                            <input type="text" name="last_name" placeholder="Last Name" style="width:48%;margin:5px 1%;padding:8px;">
                            <input type="tel" name="phone" placeholder="Phone (optional)" style="width:100%;margin:5px 0;padding:8px;">
                            <select name="frequency" style="width:100%;margin:5px 0;padding:8px;">
                                <option value="daily">Daily updates</option>
                                <option value="weekly">Weekly updates</option>
                                <option value="monthly">Monthly updates</option>
                            </select>
                            <div style="margin:10px 0;">
                                <button type="submit" style="background:#007cba;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;">Save Search</button>
                                <button type="button" onclick="$('#ufub-save-search-popup').remove()" style="background:#ccc;color:#333;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin-left:10px;">No Thanks</button>
                            </div>
                        </form>
                    `);
                
                $('body').append(popup);
                
                // Handle form submission
                $('#ufub-save-search-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = {
                        action: 'ufub_save_search',
                        nonce: '<?php echo wp_create_nonce('ufub_save_search'); ?>',
                        email: $(this).find('[name="email"]').val(),
                        first_name: $(this).find('[name="first_name"]').val(),
                        last_name: $(this).find('[name="last_name"]').val(),
                        phone: $(this).find('[name="phone"]').val(),
                        frequency: $(this).find('[name="frequency"]').val(),
                        location: searchData.location,
                        min_price: searchData.min_price,
                        max_price: searchData.max_price,
                        beds: searchData.beds,
                        baths: searchData.baths,
                        property_type: searchData.property_type
                    };
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                popup.html('<div style="text-align:center;"><h3>Search Saved!</h3><p>You\'ll receive email alerts when new properties match your criteria.</p><button onclick="$(this).closest(\'#ufub-save-search-popup\').remove()" style="background:#007cba;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;">Close</button></div>');
                                setTimeout(function() { popup.remove(); }, 3000);
                            } else {
                                alert('Error saving search: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error saving search. Please try again.');
                        }
                    });
                });
            };
        });
        </script>
        <?php
    }
}
?>
