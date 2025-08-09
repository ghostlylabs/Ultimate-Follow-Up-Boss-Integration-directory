<?php
/**
 * AI Recommender - Behavioral Analysis & Property Recommendations
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage AI_Recommender
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FUB_AI_Recommender {
    
    private static $instance = null;
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
        $this->behavioral_tracker = FUB_Behavioral_Tracker::get_instance();
        $this->init();
    }
    
    /**
     * Initialize AI recommender
     */
    private function init() {
        // Schedule recommendation updates
        add_action('ufub_update_recommendations', array($this, 'update_all_recommendations'));
        
        if (!wp_next_scheduled('ufub_update_recommendations')) {
            wp_schedule_event(time(), 'hourly', 'ufub_update_recommendations');
        }
    }
    
    /**
     * Generate recommendation for a session
     */
    public function generate_recommendation($session_id) {
        $behavioral_data = $this->behavioral_tracker->get_session_data($session_id);
        
        if (empty($behavioral_data)) {
            return null;
        }
        
        // Analyze viewing patterns
        $analysis = $this->analyze_viewing_patterns($behavioral_data);
        
        // Calculate preferences
        $preferences = $this->calculate_preferences($analysis);
        
        // Generate confidence score
        $confidence_score = $this->calculate_confidence_score($analysis, $preferences);
        
        // Get property recommendations
        $recommendations = $this->get_property_recommendations($preferences, $session_id);
        
        $recommendation_data = array(
            'session_id' => $session_id,
            'preferences' => $preferences,
            'confidence_score' => $confidence_score,
            'analysis' => $analysis,
            'recommendations' => $recommendations,
            'generated_at' => current_time('mysql')
        );
        
        // Store recommendation
        $this->store_recommendation($recommendation_data);
        
        return $recommendation_data;
    }
    
    /**
     * Analyze viewing patterns from behavioral data
     */
    private function analyze_viewing_patterns($behavioral_data) {
        $analysis = array(
            'total_views' => count($behavioral_data),
            'total_time' => 0,
            'avg_time_per_view' => 0,
            'avg_scroll_depth' => 0,
            'high_engagement_views' => 0,
            'property_types_viewed' => array(),
            'price_ranges_viewed' => array(),
            'bedroom_counts_viewed' => array(),
            'locations_viewed' => array(),
            'engagement_pattern' => 'low'
        );
        
        $total_scroll = 0;
        $price_sum = 0;
        $price_count = 0;
        
        foreach ($behavioral_data as $view) {
            $analysis['total_time'] += $view->time_spent;
            $total_scroll += $view->max_scroll;
            
            // High engagement threshold: 30+ seconds and 50%+ scroll
            if ($view->time_spent >= 30 && $view->max_scroll >= 50) {
                $analysis['high_engagement_views']++;
            }
            
            // Parse property details
            $details = json_decode($view->property_details, true) ?: array();
            
            // Analyze property type
            if (!empty($details['property_type'])) {
                $type = $details['property_type'];
                $analysis['property_types_viewed'][$type] = ($analysis['property_types_viewed'][$type] ?? 0) + 1;
            }
            
            // Analyze price range
            if (!empty($details['price'])) {
                $price = $this->parse_price($details['price']);
                if ($price > 0) {
                    $price_sum += $price;
                    $price_count++;
                    
                    $range = $this->get_price_range($price);
                    $analysis['price_ranges_viewed'][$range] = ($analysis['price_ranges_viewed'][$range] ?? 0) + 1;
                }
            }
            
            // Analyze bedroom count
            if (!empty($details['beds'])) {
                $beds = intval($details['beds']);
                $analysis['bedroom_counts_viewed'][$beds] = ($analysis['bedroom_counts_viewed'][$beds] ?? 0) + 1;
            }
            
            // Analyze location
            if (!empty($details['location'])) {
                $location = $this->normalize_location($details['location']);
                $analysis['locations_viewed'][$location] = ($analysis['locations_viewed'][$location] ?? 0) + 1;
            }
        }
        
        // Calculate averages
        if ($analysis['total_views'] > 0) {
            $analysis['avg_time_per_view'] = $analysis['total_time'] / $analysis['total_views'];
            $analysis['avg_scroll_depth'] = $total_scroll / $analysis['total_views'];
        }
        
        if ($price_count > 0) {
            $analysis['avg_price_viewed'] = $price_sum / $price_count;
        }
        
        // Determine engagement pattern
        $engagement_ratio = $analysis['high_engagement_views'] / $analysis['total_views'];
        
        if ($engagement_ratio >= 0.7) {
            $analysis['engagement_pattern'] = 'high';
        } elseif ($engagement_ratio >= 0.4) {
            $analysis['engagement_pattern'] = 'medium';
        } else {
            $analysis['engagement_pattern'] = 'low';
        }
        
        return $analysis;
    }
    
    /**
     * Calculate user preferences from analysis
     */
    private function calculate_preferences($analysis) {
        $preferences = array(
            'preferred_property_type' => null,
            'preferred_price_range' => null,
            'preferred_bedrooms' => null,
            'preferred_location' => null,
            'budget_estimate' => null,
            'urgency_level' => 'low'
        );
        
        // Determine preferred property type
        if (!empty($analysis['property_types_viewed'])) {
            arsort($analysis['property_types_viewed']);
            $preferences['preferred_property_type'] = array_key_first($analysis['property_types_viewed']);
        }
        
        // Determine preferred price range
        if (!empty($analysis['price_ranges_viewed'])) {
            arsort($analysis['price_ranges_viewed']);
            $preferences['preferred_price_range'] = array_key_first($analysis['price_ranges_viewed']);
        }
        
        // Determine preferred bedrooms
        if (!empty($analysis['bedroom_counts_viewed'])) {
            arsort($analysis['bedroom_counts_viewed']);
            $preferences['preferred_bedrooms'] = array_key_first($analysis['bedroom_counts_viewed']);
        }
        
        // Determine preferred location
        if (!empty($analysis['locations_viewed'])) {
            arsort($analysis['locations_viewed']);
            $preferences['preferred_location'] = array_key_first($analysis['locations_viewed']);
        }
        
        // Estimate budget from viewing patterns
        if (!empty($analysis['avg_price_viewed'])) {
            $preferences['budget_estimate'] = $this->estimate_budget($analysis['avg_price_viewed']);
        }
        
        // Determine urgency from engagement pattern
        switch ($analysis['engagement_pattern']) {
            case 'high':
                $preferences['urgency_level'] = 'high';
                break;
            case 'medium':
                $preferences['urgency_level'] = 'medium';
                break;
            default:
                $preferences['urgency_level'] = 'low';
        }
        
        return $preferences;
    }
    
    /**
     * Calculate confidence score for recommendations
     */
    private function calculate_confidence_score($analysis, $preferences) {
        $score = 0;
        
        // Base score from number of views
        $view_score = min(30, $analysis['total_views'] * 5); // Max 30 points
        
        // Engagement score
        $engagement_score = 0;
        switch ($analysis['engagement_pattern']) {
            case 'high':
                $engagement_score = 25;
                break;
            case 'medium':
                $engagement_score = 15;
                break;
            default:
                $engagement_score = 5;
        }
        
        // Consistency score (how consistent are preferences)
        $consistency_score = 0;
        
        // Property type consistency
        if (!empty($analysis['property_types_viewed'])) {
            $total_views = array_sum($analysis['property_types_viewed']);
            $top_type_views = max($analysis['property_types_viewed']);
            $consistency_score += ($top_type_views / $total_views) * 15; // Max 15 points
        }
        
        // Price range consistency
        if (!empty($analysis['price_ranges_viewed'])) {
            $total_views = array_sum($analysis['price_ranges_viewed']);
            $top_range_views = max($analysis['price_ranges_viewed']);
            $consistency_score += ($top_range_views / $total_views) * 15; // Max 15 points
        }
        
        // Time spent score
        $time_score = min(15, ($analysis['avg_time_per_view'] / 60) * 10); // Max 15 points
        
        $total_score = $view_score + $engagement_score + $consistency_score + $time_score;
        
        return min(100, $total_score);
    }
    
    /**
     * Get property recommendations based on preferences
     */
    private function get_property_recommendations($preferences, $session_id) {
        // This would integrate with your property database or MLS
        // For now, we'll return a structured recommendation format
        
        $recommendations = array(
            'message' => $this->generate_personalized_message($preferences),
            'criteria' => array(
                'property_type' => $preferences['preferred_property_type'],
                'price_range' => $preferences['preferred_price_range'],
                'bedrooms' => $preferences['preferred_bedrooms'],
                'location' => $preferences['preferred_location']
            ),
            'urgency_indicators' => $this->get_urgency_indicators($preferences),
            'suggested_actions' => $this->get_suggested_actions($preferences)
        );
        
        return $recommendations;
    }
    
    /**
     * Generate personalized message based on preferences
     */
    private function generate_personalized_message($preferences) {
        $messages = array(
            'high' => array(
                "Based on your browsing, you seem very interested in {type} properties around {price}. I have some exclusive listings that match exactly what you're looking for!",
                "I notice you've been actively searching for {bedrooms}-bedroom {type} properties. Let me show you some great options in {location}!",
                "Your search pattern shows you're serious about finding the right property. I have some perfect matches for your {price} budget."
            ),
            'medium' => array(
                "I see you're interested in {type} properties in the {price} range. Would you like me to send you some handpicked options?",
                "Based on your preferences for {bedrooms}-bedroom homes, I have some great listings you might love.",
                "You've been looking at properties in {location}. Let me help you find the perfect match!"
            ),
            'low' => array(
                "Thanks for browsing our listings! I'd love to help you find exactly what you're looking for.",
                "I notice you're interested in {type} properties. Let me know how I can assist with your search!",
                "Browsing for a new home? I'm here to help you find the perfect property."
            )
        );
        
        $urgency = $preferences['urgency_level'];
        $message_templates = $messages[$urgency];
        $template = $message_templates[array_rand($message_templates)];
        
        // Replace placeholders
        $replacements = array(
            '{type}' => $preferences['preferred_property_type'] ?: 'residential',
            '{price}' => $preferences['preferred_price_range'] ?: 'your budget',
            '{bedrooms}' => $preferences['preferred_bedrooms'] ?: '3',
            '{location}' => $preferences['preferred_location'] ?: 'your preferred area'
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Get urgency indicators
     */
    private function get_urgency_indicators($preferences) {
        $indicators = array();
        
        switch ($preferences['urgency_level']) {
            case 'high':
                $indicators = array(
                    'Multiple properties viewed',
                    'High engagement time',
                    'Consistent search criteria',
                    'Likely ready to make decision'
                );
                break;
            case 'medium':
                $indicators = array(
                    'Showing clear preferences',
                    'Regular browsing activity',
                    'Interested in specific areas'
                );
                break;
            default:
                $indicators = array(
                    'Early stage browsing',
                    'Exploring options',
                    'Building preferences'
                );
        }
        
        return $indicators;
    }
    
    /**
     * Get suggested actions for follow-up
     */
    private function get_suggested_actions($preferences) {
        $actions = array();
        
        switch ($preferences['urgency_level']) {
            case 'high':
                $actions = array(
                    'Schedule immediate consultation',
                    'Send curated property list',
                    'Offer private showing',
                    'Provide market analysis'
                );
                break;
            case 'medium':
                $actions = array(
                    'Send weekly property updates',
                    'Offer area information',
                    'Schedule casual consultation',
                    'Provide buying guide'
                );
                break;
            default:
                $actions = array(
                    'Send monthly market updates',
                    'Provide educational content',
                    'Offer neighborhood guides',
                    'Schedule future follow-up'
                );
        }
        
        return $actions;
    }
    
    /**
     * Store recommendation in database
     */
    private function store_recommendation($recommendation_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fub_ai_recommendations';
        
        $wpdb->insert(
            $table,
            array(
                'session_id' => $recommendation_data['session_id'],
                'preferences' => wp_json_encode($recommendation_data['preferences']),
                'confidence_score' => $recommendation_data['confidence_score'],
                'analysis_data' => wp_json_encode($recommendation_data['analysis']),
                'recommendations' => wp_json_encode($recommendation_data['recommendations']),
                'created_at' => $recommendation_data['generated_at']
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update all recommendations (scheduled task)
     */
    public function update_all_recommendations() {
        global $wpdb;
        
        // Get sessions with recent activity but no recent recommendations
        $behavioral_table = $wpdb->prefix . 'fub_behavioral_data';
        $recommendations_table = $wpdb->prefix . 'fub_ai_recommendations';
        
        // Get dynamic thresholds from settings
        $recommendation_threshold = get_option('ufub_ai_recommendation_threshold', 5);
        $analysis_days = get_option('ufub_ai_analysis_days', 30);
        $min_views = max(2, $recommendation_threshold - 2); // Start analysis 2 views before sending
        
        $sessions = $wpdb->get_results($wpdb->prepare("
            SELECT session_id, COUNT(*) as view_count 
            FROM {$behavioral_table} 
            WHERE view_time > DATE_SUB(NOW(), INTERVAL %d DAY)
            AND session_id NOT IN (
                SELECT session_id 
                FROM {$recommendations_table} 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 6 HOURS)
            )
            GROUP BY session_id
            HAVING view_count >= %d
        ", $analysis_days, $min_views));
        
        foreach ($sessions as $session) {
            $this->generate_recommendation($session->session_id);
        }
    }
    
    /**
     * Helper: Parse price from string
     */
    private function parse_price($price_string) {
        $price = preg_replace('/[^\d]/', '', $price_string);
        return intval($price);
    }
    
    /**
     * Helper: Get price range category
     */
    private function get_price_range($price) {
        if ($price < 200000) return 'Under $200K';
        if ($price < 400000) return '$200K-$400K';
        if ($price < 600000) return '$400K-$600K';
        if ($price < 800000) return '$600K-$800K';
        if ($price < 1000000) return '$800K-$1M';
        return 'Over $1M';
    }
    
    /**
     * Helper: Normalize location string
     */
    private function normalize_location($location) {
        $location = trim(strtolower($location));
        
        // Extract city from address
        if (preg_match('/,\s*([^,]+),\s*[a-z]{2}/i', $location, $matches)) {
            return trim($matches[1]);
        }
        
        return $location;
    }
    
    /**
     * Helper: Estimate budget from average price viewed
     */
    private function estimate_budget($avg_price) {
        // Assume budget is 10% higher than average viewed price
        $estimated = $avg_price * 1.1;
        
        // Round to nearest 25K
        return round($estimated / 25000) * 25000;
    }
}
