<?php
/**
 * FUB Magic Moments - VIP Return Visitor Alerts
 * 
 * Detects when important contacts return to the website
 * Sends high-priority alerts to agents: "🔥 HOT LEAD: Josh Shampo is back!"
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Magic_Moments
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_Magic_Moments {
    
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
     * Initialize magic moments
     */
    private function init() {
        // Hook into WordPress login to detect returning users
        add_action('wp_login', array($this, 'check_returning_user'), 10, 2);
        
        // Hook into behavioral tracking to detect returning visitors
        add_action('ufub_track_behavior', array($this, 'check_returning_visitor'), 10, 2);
        
        // Daily cleanup of old visitor data
        add_action('ufub_daily_cleanup', array($this, 'cleanup_old_visitor_data'));
        
        if (!wp_next_scheduled('ufub_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ufub_daily_cleanup');
        }
    }
    
    /**
     * Check if returning user is a VIP contact
     */
    public function check_returning_user($user_login, $user) {
        $this->process_returning_visitor($user->user_email, array(
            'user_id' => $user->ID,
            'display_name' => $user->display_name,
            'login_method' => 'wordpress_login'
        ));
    }
    
    /**
     * Check if returning visitor is a known contact
     */
    public function check_returning_visitor($event_data, $user_id) {
        // Only check for page views (not every single event)
        if (($event_data['event_type'] ?? '') !== 'page_view') {
            return;
        }
        
        // Get visitor email from various sources
        $email = $this->get_visitor_email($user_id, $event_data);
        
        if ($email) {
            $this->process_returning_visitor($email, array(
                'user_id' => $user_id,
                'session_id' => $event_data['session_id'] ?? '',
                'page_url' => $event_data['page_url'] ?? '',
                'referrer' => $event_data['referrer'] ?? ''
            ));
        }
    }
    
    /**
     * Process returning visitor and check if they're a VIP
     */
    private function process_returning_visitor($email, $context = array()) {
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        
        // Check if we've already alerted for this visitor today
        if ($this->already_alerted_today($email)) {
            return;
        }
        
        // Check if this is a known FUB contact
        $contact_info = $this->check_fub_contact($email);
        
        if ($contact_info && $this->is_vip_contact($contact_info)) {
            $this->send_magic_moment_alert($email, $contact_info, $context);
            $this->record_alert($email);
        }
    }
    
    /**
     * Get visitor email from various sources
     */
    private function get_visitor_email($user_id, $event_data) {
        // Try WordPress user first
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            if ($user && $user->user_email) {
                return $user->user_email;
            }
        }
        
        // Try session data
        $session_id = $event_data['session_id'] ?? '';
        if ($session_id) {
            $email = $this->get_email_from_session($session_id);
            if ($email) {
                return $email;
            }
        }
        
        // Try saved search data
        $email = $this->get_email_from_saved_searches($user_id, $session_id);
        if ($email) {
            return $email;
        }
        
        return null;
    }
    
    /**
     * Get email from session data
     */
    private function get_email_from_session($session_id) {
        global $wpdb;
        
        // Check behavioral data for email captures
        $table = $wpdb->prefix . 'fub_behavioral_data';
        $email = $wpdb->get_var($wpdb->prepare(
            "SELECT user_email FROM $table WHERE session_id = %s AND user_email IS NOT NULL ORDER BY view_time DESC LIMIT 1",
            $session_id
        ));
        
        return $email;
    }
    
    /**
     * Get email from saved searches
     */
    private function get_email_from_saved_searches($user_id, $session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_saved_searches';
        $email = $wpdb->get_var($wpdb->prepare(
            "SELECT user_email FROM $table WHERE (user_id = %d OR session_id = %s) AND user_email IS NOT NULL ORDER BY created_at DESC LIMIT 1",
            $user_id ?: 0, $session_id
        ));
        
        return $email;
    }
    
    /**
     * Check if visitor is a known FUB contact
     */
    private function check_fub_contact($email) {
        // Query FUB API to check if this is a known contact
        $result = $this->api->get_contact_by_email($email);
        
        if ($result && !is_wp_error($result) && !empty($result['data'])) {
            return $result['data'];
        }
        
        return false;
    }
    
    /**
     * Determine if contact is VIP (high value)
     */
    private function is_vip_contact($contact_info) {
        // VIP criteria - customize based on your needs
        $vip_indicators = array(
            'has_transactions' => !empty($contact_info['transactions']),
            'high_property_value' => ($contact_info['max_budget'] ?? 0) > 500000,
            'recent_activity' => $this->has_recent_activity($contact_info),
            'hot_lead_score' => ($contact_info['lead_score'] ?? 0) > 80,
            'vip_tag' => $this->has_vip_tags($contact_info),
            'repeat_visitor' => $this->is_repeat_visitor($contact_info['email'])
        );
        
        // Contact is VIP if they meet any of these criteria
        return array_filter($vip_indicators);
    }
    
    /**
     * Check if contact has recent activity in FUB
     */
    private function has_recent_activity($contact_info) {
        $last_activity = $contact_info['last_activity'] ?? '';
        if (!$last_activity) {
            return false;
        }
        
        $last_activity_time = strtotime($last_activity);
        $thirty_days_ago = strtotime('-30 days');
        
        return $last_activity_time > $thirty_days_ago;
    }
    
    /**
     * Check if contact has VIP tags
     */
    private function has_vip_tags($contact_info) {
        $tags = $contact_info['tags'] ?? array();
        $vip_tags = array('VIP', 'Hot Lead', 'High Value', 'Priority', 'Investor', 'Repeat Client');
        
        foreach ($vip_tags as $vip_tag) {
            if (in_array($vip_tag, $tags)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if this is a repeat visitor (multiple sessions)
     */
    private function is_repeat_visitor($email) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_behavioral_data';
        $session_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $table WHERE user_email = %s",
            $email
        ));
        
        return $session_count > 1;
    }
    
    /**
     * Send magic moment alert to agent
     */
    private function send_magic_moment_alert($email, $contact_info, $context) {
        $contact_name = $contact_info['name'] ?? $email;
        $agent_email = $contact_info['assigned_agent'] ?? '';
        
        // Create high-priority alert
        $alert_data = array(
            'event_type' => 'vip_return_visit',
            'priority' => 'URGENT',
            'contact_email' => $email,
            'contact_name' => $contact_name,
            'contact_id' => $contact_info['id'] ?? '',
            'timestamp' => current_time('c'),
            'context' => $context,
            'vip_reasons' => $this->is_vip_contact($contact_info),
            'alert_title' => "🔥 HOT LEAD: {$contact_name} is back on the website!",
            'alert_message' => $this->build_alert_message($contact_name, $contact_info, $context)
        );
        
        // Send to FUB as urgent event
        $result = $this->api->send_event('VIP Return Visit', $alert_data);
        
        // Also send direct notification if agent email is available
        if ($agent_email) {
            $this->send_agent_notification($agent_email, $alert_data);
        }
        
        error_log('[MAGIC MOMENTS] 🔥 VIP ALERT: ' . $contact_name . ' (' . $email . ') has returned to the website!');
        
        return $result;
    }
    
    /**
     * Build detailed alert message
     */
    private function build_alert_message($contact_name, $contact_info, $context) {
        $message = "🔥 **URGENT: VIP Contact on Website**\n\n";
        $message .= "**Contact:** {$contact_name}\n";
        $message .= "**Email:** " . ($contact_info['email'] ?? '') . "\n";
        $message .= "**Phone:** " . ($contact_info['phone'] ?? 'N/A') . "\n\n";
        
        // Add VIP reasons
        $vip_reasons = $this->is_vip_contact($contact_info);
        if ($vip_reasons) {
            $message .= "**Why this is important:**\n";
            foreach ($vip_reasons as $reason => $value) {
                if ($value) {
                    $message .= "• " . ucwords(str_replace('_', ' ', $reason)) . "\n";
                }
            }
            $message .= "\n";
        }
        
        // Add current activity
        if (!empty($context['page_url'])) {
            $message .= "**Current Activity:**\n";
            $message .= "• Viewing: " . $context['page_url'] . "\n";
            if (!empty($context['referrer'])) {
                $message .= "• Came from: " . $context['referrer'] . "\n";
            }
            $message .= "\n";
        }
        
        $message .= "**Action Required:** Contact this lead immediately while they're hot! 🔥";
        
        return $message;
    }
    
    /**
     * Send direct notification to agent
     */
    private function send_agent_notification($agent_email, $alert_data) {
        // This could send email, SMS, or other notifications
        // For now, we'll send via FUB's notification system
        
        $notification_data = array(
            'recipient' => $agent_email,
            'type' => 'urgent_alert',
            'title' => $alert_data['alert_title'],
            'message' => $alert_data['alert_message'],
            'contact_id' => $alert_data['contact_id']
        );
        
        return $this->api->send_notification($notification_data);
    }
    
    /**
     * Check if we've already alerted for this visitor today
     */
    private function already_alerted_today($email) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_magic_moments';
        $today = date('Y-m-d');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE contact_email = %s AND DATE(alerted_at) = %s",
            $email, $today
        ));
        
        return $count > 0;
    }
    
    /**
     * Record that we've sent an alert
     */
    private function record_alert($email) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_magic_moments';
        
        $wpdb->insert(
            $table,
            array(
                'contact_email' => $email,
                'alerted_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Cleanup old visitor data (run daily)
     */
    public function cleanup_old_visitor_data() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_magic_moments';
        
        // Remove alerts older than 30 days
        $wpdb->query("DELETE FROM $table WHERE alerted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        error_log('[MAGIC MOMENTS] Cleaned up old visitor alert data');
    }
    
    /**
     * Create magic moments table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fub_magic_moments';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_email varchar(255) NOT NULL,
            alerted_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY contact_email (contact_email),
            KEY alerted_at (alerted_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize magic moments
if (class_exists('FUB_Magic_Moments')) {
    FUB_Magic_Moments::get_instance();
}
?>
