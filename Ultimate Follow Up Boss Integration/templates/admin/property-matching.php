<?php
/**
 * Property Matching Preferences
 * Ultimate Follow Up Boss Integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Templates
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// DEBUG: Verify this template is loading
echo '<!-- PROPERTY MATCHING PHP TEMPLATE IS LOADING -->';

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Handle form submission
$message = '';
if (isset($_POST['save_settings']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'property_matching_settings')) {
    // Save settings to WordPress options
    update_option('ufub_price_importance', sanitize_text_field($_POST['ufub_price_importance']));
    update_option('ufub_location_importance', sanitize_text_field($_POST['ufub_location_importance']));
    update_option('ufub_smart_matching', !empty($_POST['ufub_smart_matching']));
    update_option('ufub_bidirectional_sync', !empty($_POST['ufub_bidirectional_sync']));
    
    $message = '<div class="notice notice-success"><p><strong>Settings saved successfully!</strong></p></div>';
}

// Get current settings
$price_importance = get_option('ufub_price_importance', '3');
$location_importance = get_option('ufub_location_importance', '5');
$smart_matching = get_option('ufub_smart_matching', true);
$bidirectional_sync = get_option('ufub_bidirectional_sync', true);
?>

<div class="wrap ghostly-admin-wrap">
    <style>
        /* Ensure dark theme applies */
        .ghostly-admin-wrap {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%) !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding: 20px;
        }
        
        .ghostly-admin-wrap h1 {
            color: #ffffff !important;
            font-size: 2em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .ghostly-admin-wrap h1:before {
            content: "🎯";
            font-size: 1.2em;
        }
        
        .ghostly-admin-wrap p {
            color: rgba(255, 255, 255, 0.8) !important;
            font-size: 1.1em;
            margin-bottom: 25px;
        }
        
        .ghostly-admin-wrap .form-table th {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            font-weight: 600;
        }
        
        .ghostly-admin-wrap .form-table td {
            background: rgba(255, 255, 255, 0.03);
            padding: 15px;
            border-left: 4px solid #667eea;
        }
        
        .ghostly-admin-wrap select,
        .ghostly-admin-wrap input[type="checkbox"] {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
        }
        
        .ghostly-admin-wrap .button-primary {
            background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%) !important;
            border: none !important;
            color: white !important;
            padding: 12px 25px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 1em !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }
        
        .ghostly-admin-wrap .button-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 5px 15px rgba(58, 178, 74, 0.3) !important;
        }
        
        .ghostly-admin-wrap .notice {
            background: rgba(58, 178, 74, 0.1) !important;
            border-left: 4px solid #3ab24a !important;
            color: #3ab24a !important;
            padding: 15px !important;
            border-radius: 6px !important;
            margin: 20px 0 !important;
        }
        
        .ghostly-admin-wrap .form-table {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .ghostly-admin-wrap td p.description {
            color: rgba(255, 255, 255, 0.7) !important;
            font-style: italic;
            margin-top: 8px;
        }
    </style>
    
    <h1>Property Matching Settings</h1>
    
    <?php echo $message; ?>
    
    <p>Configure matching preferences for property alerts sent to Follow Up Boss leads.</p>
    
    <form method="post" action="">
        <?php wp_nonce_field('property_matching_settings', '_wpnonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">Price Match Importance</th>
                <td>
                    <select name="ufub_price_importance">
                        <option value="5" <?php selected($price_importance, '5'); ?>>⭐⭐⭐⭐⭐ Critical</option>
                        <option value="4" <?php selected($price_importance, '4'); ?>>⭐⭐⭐⭐ Important</option>
                        <option value="3" <?php selected($price_importance, '3'); ?>>⭐⭐⭐ Moderate</option>
                        <option value="2" <?php selected($price_importance, '2'); ?>>⭐⭐ Less Important</option>
                        <option value="1" <?php selected($price_importance, '1'); ?>>⭐ Minimal</option>
                    </select>
                    <p class="description">How important is price matching for property alerts?</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Location Match Importance</th>
                <td>
                    <select name="ufub_location_importance">
                        <option value="5" <?php selected($location_importance, '5'); ?>>⭐⭐⭐⭐⭐ Critical</option>
                        <option value="4" <?php selected($location_importance, '4'); ?>>⭐⭐⭐⭐ Important</option>
                        <option value="3" <?php selected($location_importance, '3'); ?>>⭐⭐⭐ Moderate</option>
                        <option value="2" <?php selected($location_importance, '2'); ?>>⭐⭐ Less Important</option>
                        <option value="1" <?php selected($location_importance, '1'); ?>>⭐ Minimal</option>
                    </select>
                    <p class="description">How important is location matching for property alerts?</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Smart Features</th>
                <td>
                    <label>
                        <input type="checkbox" name="ufub_smart_matching" <?php checked($smart_matching); ?>>
                        Enable smart learning from user behavior
                    </label>
                    <p class="description">Automatically learn from user clicks and improve matching over time.</p>
                    
                    <br><br>
                    
                    <label>
                        <input type="checkbox" name="ufub_bidirectional_sync" <?php checked($bidirectional_sync); ?>>
                        Enable bidirectional sync with Follow Up Boss
                    </label>
                    <p class="description">Sync property data both ways between your website and Follow Up Boss CRM.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button-primary" value="Save Settings">
        </p>
    </form>
    
    <div class="postbox">
        <div class="postbox-header">
            <h3>How Property Matching Works</h3>
        </div>
        <div class="inside">
            <p>This system automatically matches new properties to saved searches and sends alerts through Follow Up Boss.</p>
            <ul>
                <li><strong>⭐⭐⭐⭐⭐ Critical:</strong> Must match closely or property is excluded</li>
                <li><strong>⭐⭐⭐⭐ Important:</strong> Strong influence on property score</li>
                <li><strong>⭐⭐⭐ Moderate:</strong> Considered but not critical</li>
                <li><strong>⭐⭐ Less Important:</strong> Minor influence on score</li>
                <li><strong>⭐ Minimal:</strong> Barely considered in matching</li>
            </ul>
        </div>
    </div>
</div>
