<?php
/**
 * Admin Menu Registration - Clean UI Implementation
 * Ultimate Follow Up Boss Integration
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage Admin
 * @version 2.1.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin menu pages with clean UI (no phase references)
 */
function ufub_register_admin_menu() {
    // Main menu page - Dashboard
    add_menu_page(
        'Ghostly Labs Integration',              // Page title
        'FUB Integration',                       // Menu title (clean, no phase refs)
        'manage_options',                        // Capability
        'ufub-dashboard',                        // Menu slug
        'ufub_admin_dashboard_page',            // Function
        'dashicons-networking',                  // Icon
        30                                       // Position
    );
    
    // Dashboard submenu (rename main to avoid duplication)
    add_submenu_page(
        'ufub-dashboard',                       // Parent slug
        'System Dashboard',                     // Page title
        'Dashboard',                            // Menu title
        'manage_options',                       // Capability
        'ufub-dashboard',                       // Menu slug
        'ufub_admin_dashboard_page'            // Function
    );
    
    // Settings submenu
    add_submenu_page(
        'ufub-dashboard',                       // Parent slug
        'Integration Settings',                 // Page title
        'Settings',                             // Menu title
        'manage_options',                       // Capability
        'ufub-settings',                        // Menu slug
        'ufub_admin_settings_page'             // Function
    );
    
    // Saved Searches submenu
    add_submenu_page(
        'ufub-dashboard',                       // Parent slug
        'Saved Searches Management',            // Page title
        'Saved Searches',                       // Menu title
        'manage_options',                       // Capability
        'ufub-saved-searches',                  // Menu slug
        'ufub_admin_saved_searches_page'       // Function
    );
    
    // Property Matching submenu
    add_submenu_page(
        'ufub-dashboard',                       // Parent slug
        'Property Matching Rules',              // Page title
        'Property Matching',                    // Menu title
        'manage_options',                       // Capability
        'ufub-property-matching',               // Menu slug
        'ufub_admin_property_matching_page'    // Function
    );
    
    // Debug Panel (only show if debug mode enabled)
    if (defined('UFUB_DEBUG') && UFUB_DEBUG) {
        add_submenu_page(
            'ufub-dashboard',                   // Parent slug
            'System Diagnostics',               // Page title
            'Debug Panel',                      // Menu title
            'manage_options',                   // Capability
            'ufub-debug',                       // Menu slug
            'ufub_admin_debug_page'            // Function
        );
    }
}
add_action('admin_menu', 'ufub_register_admin_menu');

/**
 * Dashboard page callback with error handling
 */
function ufub_admin_dashboard_page() {
    try {
        $template_path = UFUB_PLUGIN_DIR . 'templates/admin/dashboard.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            ufub_show_missing_template_error('dashboard.php', 'System Dashboard');
        }
    } catch (Exception $e) {
        ufub_show_template_error('Dashboard', $e->getMessage());
    }
}

/**
 * Settings page callback with error handling
 */
function ufub_admin_settings_page() {
    try {
        $template_path = UFUB_PLUGIN_DIR . 'templates/admin/settings.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            ufub_show_missing_template_error('settings.php', 'Integration Settings');
        }
    } catch (Exception $e) {
        ufub_show_template_error('Settings', $e->getMessage());
    }
}

/**
 * Saved Searches page callback with error handling
 */
function ufub_admin_saved_searches_page() {
    try {
        $template_path = UFUB_PLUGIN_DIR . 'templates/admin/saved-searches.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            ufub_show_missing_template_error('saved-searches.php', 'Saved Searches Management');
        }
    } catch (Exception $e) {
        ufub_show_template_error('Saved Searches', $e->getMessage());
    }
}

/**
 * Property Matching page callback with error handling
 */
function ufub_admin_property_matching_page() {
    try {
        $template_path = UFUB_PLUGIN_DIR . 'templates/admin/property-matching.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            ufub_show_missing_template_error('property-matching.php', 'Property Matching Rules');
        }
    } catch (Exception $e) {
        ufub_show_template_error('Property Matching', $e->getMessage());
    }
}

/**
 * Debug Panel page callback with error handling
 */
function ufub_admin_debug_page() {
    // Only show if debug mode is enabled
    if (!defined('UFUB_DEBUG') || !UFUB_DEBUG) {
        wp_die(__('Debug mode is not enabled.'));
    }
    
    try {
        $template_path = UFUB_PLUGIN_DIR . 'templates/admin/debug-panel.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            ufub_show_missing_template_error('debug-panel.php', 'System Diagnostics');
        }
    } catch (Exception $e) {
        ufub_show_template_error('Debug Panel', $e->getMessage());
    }
}

/**
 * Show missing template error with Ghostly Labs styling
 */
function ufub_show_missing_template_error($template_file, $page_title) {
    ?>
    <div class="wrap ghostly-container">
        <div class="ghostly-header-section" style="background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2d2d2d 100%); padding: 30px; margin: -20px -20px 20px -20px; border-radius: 0 0 15px 15px;">
            <h1 class="ghostly-header" style="color: #ffffff; font-size: 2.2em; margin: 0; display: flex; align-items: center; gap: 15px;">
                <span class="dashicons dashicons-warning" style="color: #dc3232; font-size: 1.2em;"></span>
                <?php echo esc_html($page_title); ?>
                <span class="ghostly-status-indicator" style="background: #dc3232; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.4em; font-weight: 500; margin-left: auto;">
                    ❌ Template Missing
                </span>
            </h1>
        </div>
        
        <div class="ghostly-card" style="background: rgba(220, 50, 50, 0.1); border: 1px solid rgba(220, 50, 50, 0.3); border-radius: 12px; padding: 40px; text-align: center; max-width: 800px; margin: 0 auto;">
            <div style="font-size: 4em; margin-bottom: 20px;">⚠️</div>
            <h2 style="color: #dc3232; margin: 0 0 15px 0;">Template File Missing</h2>
            <p style="color: #ffffff; opacity: 0.9; margin: 0 0 20px 0; font-size: 1.1em;">
                The template file <code style="background: rgba(0, 0, 0, 0.3); padding: 4px 8px; border-radius: 4px;"><?php echo esc_html($template_file); ?></code> could not be found.
            </p>
            <p style="color: #ffffff; opacity: 0.8; margin: 0 0 30px 0;">
                Please ensure the plugin is properly installed and all template files are present.
            </p>
            
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo admin_url('plugins.php'); ?>" 
                   class="ghostly-button" 
                   style="background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%); color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    Check Plugins
                </a>
                <a href="<?php echo admin_url('admin.php?page=ufub-dashboard'); ?>" 
                   class="ghostly-button" 
                   style="background: rgba(255, 255, 255, 0.1); color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; border: 1px solid rgba(255, 255, 255, 0.2);">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <style>
    .ghostly-container {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2d2d2d 100%);
        min-height: 100vh;
        padding: 0;
    }
    .ghostly-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
    }
    .ghostly-header {
        color: #ffffff;
        font-weight: 600;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }
    .ghostly-button {
        transition: all 0.3s ease;
    }
    .ghostly-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        text-decoration: none;
    }
    </style>
    <?php
}

/**
 * Show template error with Ghostly Labs styling
 */
function ufub_show_template_error($page_name, $error_message) {
    ?>
    <div class="wrap ghostly-container">
        <div class="ghostly-header-section" style="background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2d2d2d 100%); padding: 30px; margin: -20px -20px 20px -20px; border-radius: 0 0 15px 15px;">
            <h1 class="ghostly-header" style="color: #ffffff; font-size: 2.2em; margin: 0; display: flex; align-items: center; gap: 15px;">
                <span class="dashicons dashicons-warning" style="color: #dc3232; font-size: 1.2em;"></span>
                <?php echo esc_html($page_name); ?> Error
                <span class="ghostly-status-indicator" style="background: #dc3232; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.4em; font-weight: 500; margin-left: auto;">
                    ❌ System Error
                </span>
            </h1>
        </div>
        
        <div class="ghostly-card" style="background: rgba(220, 50, 50, 0.1); border: 1px solid rgba(220, 50, 50, 0.3); border-radius: 12px; padding: 40px; text-align: center; max-width: 800px; margin: 0 auto;">
            <div style="font-size: 4em; margin-bottom: 20px;">💥</div>
            <h2 style="color: #dc3232; margin: 0 0 15px 0;">Template Error Occurred</h2>
            <p style="color: #ffffff; opacity: 0.9; margin: 0 0 20px 0; font-size: 1.1em;">
                An error occurred while loading the <?php echo esc_html($page_name); ?> page.
            </p>
            
            <div style="background: rgba(0, 0, 0, 0.3); border-radius: 8px; padding: 15px; margin: 20px 0; text-align: left;">
                <strong style="color: #dc3232;">Error Details:</strong><br>
                <code style="color: #ffffff; opacity: 0.8; font-size: 0.9em;"><?php echo esc_html($error_message); ?></code>
            </div>
            
            <p style="color: #ffffff; opacity: 0.8; margin: 0 0 30px 0;">
                Please check the system logs for more information or contact support if the issue persists.
            </p>
            
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo admin_url('admin.php?page=ufub-debug'); ?>" 
                   class="ghostly-button" 
                   style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    Debug Panel
                </a>
                <a href="<?php echo admin_url('admin.php?page=ufub-dashboard'); ?>" 
                   class="ghostly-button" 
                   style="background: rgba(255, 255, 255, 0.1); color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; border: 1px solid rgba(255, 255, 255, 0.2);">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <style>
    .ghostly-container {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2d2d2d 100%);
        min-height: 100vh;
        padding: 0;
    }
    .ghostly-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
    }
    .ghostly-header {
        color: #ffffff;
        font-weight: 600;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }
    .ghostly-button {
        transition: all 0.3s ease;
    }
    .ghostly-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        text-decoration: none;
    }
    </style>
    <?php
}

/**
 * Admin page header for consistent styling across all pages
 */
function ufub_admin_page_header($page_title, $page_description, $status = 'active') {
    $status_colors = array(
        'active' => '#3ab24a',
        'warning' => '#ffb900',
        'error' => '#dc3232',
        'inactive' => '#666666'
    );
    
    $status_labels = array(
        'active' => '✅ System Active',
        'warning' => '⚠️ Issues Detected',
        'error' => '❌ System Error',
        'inactive' => '⏸️ Inactive'
    );
    
    $status_color = $status_colors[$status] ?? $status_colors['active'];
    $status_label = $status_labels[$status] ?? $status_labels['active'];
    ?>
    
    <div class="ghostly-header-section" style="background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #2d2d2d 100%); padding: 30px; margin: -20px -20px 20px -20px; border-radius: 0 0 15px 15px;">
        <h1 class="ghostly-header" style="color: #ffffff; font-size: 2.2em; margin: 0; display: flex; align-items: center; gap: 15px;">
            <span class="dashicons dashicons-networking" style="color: #3ab24a; font-size: 1.2em;"></span>
            <?php echo esc_html($page_title); ?>
            <span class="ghostly-status-indicator" style="background: <?php echo $status_color; ?>; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.4em; font-weight: 500; margin-left: auto;">
                <?php echo $status_label; ?>
            </span>
        </h1>
        <p style="color: #ffffff; opacity: 0.8; margin: 8px 0 0 0; font-size: 1.1em;">
            <?php echo esc_html($page_description); ?>
        </p>
    </div>
    <?php
}

/**
 * Check if all required templates exist
 */
function ufub_check_template_files() {
    $required_templates = array(
        'dashboard.php' => 'System Dashboard',
        'settings.php' => 'Integration Settings',
        'saved-searches.php' => 'Saved Searches Management',
        'property-matching.php' => 'Property Matching Rules',
        'debug-panel.php' => 'System Diagnostics'
    );
    
    $missing_templates = array();
    $template_dir = UFUB_PLUGIN_DIR . 'templates/admin/';
    
    foreach ($required_templates as $template => $description) {
        if (!file_exists($template_dir . $template)) {
            $missing_templates[$template] = $description;
        }
    }
    
    return $missing_templates;
}

/**
 * Show admin notice for missing templates
 */
function ufub_missing_templates_admin_notice() {
    $missing_templates = ufub_check_template_files();
    
    if (!empty($missing_templates)) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>Ultimate Follow Up Boss Integration:</strong> Missing template files detected.</p>
            <p>The following templates are missing:</p>
            <ul>
                <?php foreach ($missing_templates as $template => $description): ?>
                <li><code><?php echo esc_html($template); ?></code> - <?php echo esc_html($description); ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Please reinstall the plugin or contact support.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'ufub_missing_templates_admin_notice');

/**
 * Enqueue admin styles for all plugin pages
 */
function ufub_enqueue_admin_styles($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'ufub-') === false) {
        return;
    }
    
    // Enqueue common admin styles
    wp_enqueue_style(
        'ufub-admin-styles',
        UFUB_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        UFUB_VERSION
    );
    
    // Enqueue common admin scripts
    wp_enqueue_script(
        'ufub-admin-scripts',
        UFUB_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        UFUB_VERSION,
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('ufub-admin-scripts', 'ufub_admin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ufub_admin_nonce'),
        'plugin_url' => UFUB_PLUGIN_URL
    ));
}
add_action('admin_enqueue_scripts', 'ufub_enqueue_admin_styles');

/**
 * Add plugin action links
 */
function ufub_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=ufub-dashboard') . '">Dashboard</a>',
        '<a href="' . admin_url('admin.php?page=ufub-settings') . '">Settings</a>',
    );
    
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(UFUB_PLUGIN_FILE), 'ufub_plugin_action_links');

/**
 * Add admin bar menu
 */
function ufub_admin_bar_menu($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get system status
    $status = 'active';
    try {
        // Check if API is configured
        if (!get_option('ufub_api_key')) {
            $status = 'warning';
        }
        
        // Check if core components are healthy
        if (class_exists('FUB_Person_Data_Orchestrator')) {
            $orchestrator = FUB_Person_Data_Orchestrator::get_instance();
            if (method_exists($orchestrator, 'health_check')) {
                $health = $orchestrator->health_check();
                if (isset($health['status']) && $health['status'] !== 'healthy') {
                    $status = 'warning';
                }
            }
        }
    } catch (Exception $e) {
        $status = 'error';
    }
    
    $status_icon = $status === 'error' ? '❌' : ($status === 'warning' ? '⚠️' : '✅');
    
    $wp_admin_bar->add_menu(array(
        'id' => 'ufub-menu',
        'title' => $status_icon . ' FUB Integration',
        'href' => admin_url('admin.php?page=ufub-dashboard'),
        'meta' => array(
            'title' => 'Ultimate Follow Up Boss Integration'
        )
    ));
    
    $wp_admin_bar->add_menu(array(
        'parent' => 'ufub-menu',
        'id' => 'ufub-dashboard',
        'title' => 'Dashboard',
        'href' => admin_url('admin.php?page=ufub-dashboard')
    ));
    
    $wp_admin_bar->add_menu(array(
        'parent' => 'ufub-menu',
        'id' => 'ufub-settings',
        'title' => 'Settings',
        'href' => admin_url('admin.php?page=ufub-settings')
    ));
    
    if (defined('UFUB_DEBUG') && UFUB_DEBUG) {
        $wp_admin_bar->add_menu(array(
            'parent' => 'ufub-menu',
            'id' => 'ufub-debug',
            'title' => 'Debug Panel',
            'href' => admin_url('admin.php?page=ufub-debug')
        ));
    }
}
add_action('admin_bar_menu', 'ufub_admin_bar_menu', 100);
?>