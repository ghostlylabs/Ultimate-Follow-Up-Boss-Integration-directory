# Ultimate Follow Up Boss Integration - Installation & Update Guide

## Critical Security and Performance Updates - Version 2.1.0

This update addresses several critical security and performance issues identified in the audit:

### ✅ Fixed Issues
1. **Session Management Anti-Pattern** - Replaced with transient-based caching
2. **Database Table Creation Logic** - Fixed version checking and error handling  
3. **Missing Error Handler Restoration** - Proper error handler management
4. **Unsafe Dynamic Class Loading** - Added safe loading with error handling
5. **REST API Security Gap** - Enhanced webhook authentication and rate limiting
6. **Webhook Timeout Issues** - Comprehensive timeout management

## Installation Instructions

### Prerequisites
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Follow Up Boss API key
- SSL certificate (required for webhooks)

### 1. Upload Plugin Files

Upload all plugin files to `/wp-content/plugins/ultimate-fub-integration/`:

```
ultimate-fub-integration/
├── ultimate-fub-integration.php          # Main plugin file (UPDATED)
├── readme.md
├── includes/
│   ├── class-fub-api.php
│   ├── class-fub-debug.php
│   ├── class-fub-events.php
│   ├── class-fub-property-matcher.php
│   ├── class-fub-saved-searches.php
│   ├── class-fub-security.php
│   ├── class-fub-webhooks.php             # UPDATED - Enhanced security & timeouts
│   ├── class-fub-performance-config.php  # NEW - Performance management
│   ├── class-fub-rest-security.php       # NEW - Enhanced API security
│   └── class-ufub-security-helper.php
├── admin/
│   ├── dashboard.php
│   ├── settings.php
│   └── debug-panel.php
└── assets/
    ├── css/
    │   ├── fub-admin.css
    │   └── fub-debug.css
    └── js/
        └── fub-admin.js
```

### 2. Plugin Activation

1. Go to WordPress Admin → Plugins
2. Find "Ultimate Follow Up Boss Integration"
3. Click "Activate"

The plugin will automatically:
- Create/update database tables with proper versioning
- Set default configuration options
- Create webhook system user
- Initialize security settings

### 3. Initial Configuration

#### Basic Settings
1. Navigate to **Follow Up Boss → Settings**
2. Enter your Follow Up Boss API key
3. Verify API URL: `https://api.followupboss.com/v1/`
4. Test connection using the "Test Connection" button

#### Security Configuration
1. Go to **Follow Up Boss → Settings → Security**
2. Configure webhook security:
   ```php
   // Webhook secret is auto-generated
   // IP whitelist (optional - recommended for production)
   fub_webhook_whitelist_ips = array(
       '192.168.1.100',    // Your server IP
       '10.0.0.50'         // Follow Up Boss webhook IPs
   );
   ```

#### Performance Optimization
1. Navigate to **Follow Up Boss → Debug Panel → Performance**
2. Run environment optimization:
   ```php
   $performance = FUB_Performance_Config::get_instance();
   $optimizations = $performance->optimize_for_environment();
   ```

### 4. Webhook Setup

#### Register Webhooks
```php
// In WordPress admin or via API
$webhook_events = array(
    'person.created',
    'person.updated', 
    'person.deleted',
    'event.created',
    'property.updated'
);

foreach ($webhook_events as $event) {
    $webhooks->register_webhook($event);
}
```

#### Webhook URLs
Your webhook endpoints will be:
- `https://yoursite.com/wp-json/fub/v1/webhook/person.created`
- `https://yoursite.com/wp-json/fub/v1/webhook/person.updated`
- `https://yoursite.com/wp-json/fub/v1/webhook/event.created`
- etc.

#### Health Check
Test webhook functionality:
- `GET https://yoursite.com/wp-json/fub/v1/webhook/health`

### 5. Database Tables

The plugin creates these tables with proper indexing:

```sql
-- Contacts table
wp_fub_contacts
├── id (PRIMARY KEY)
├── fub_id (UNIQUE INDEX)
├── email (INDEX)
├── stage (INDEX)
├── created_date (INDEX)
└── modified_date (INDEX)

-- Events table  
wp_fub_events
├── id (PRIMARY KEY)
├── fub_id (INDEX)
├── person_id (INDEX)
├── type (INDEX)
└── created_date (INDEX)

-- Security logs table
wp_fub_security_logs
├── id (PRIMARY KEY)
├── event_type (INDEX)
├── severity (INDEX)
├── ip_address (INDEX)
└── created_date (INDEX)
```

## Update Instructions

### From Version 2.0.x to 2.1.0

#### Automatic Update
1. Backup your database and files
2. Upload new plugin files
3. The plugin will automatically:
   - Update database schema
   - Migrate session data to transients
   - Apply security patches
   - Update performance configurations

#### Manual Update Steps
If automatic update fails:

```php
// 1. Clear old session data
if (session_id()) {
    session_destroy();
}

// 2. Clear problematic transients
delete_transient('fub_*');

// 3. Force database update
delete_option('fub_db_version');
$plugin = Ultimate_FUB_Integration::get_instance();
$plugin->activate_plugin();

// 4. Reset webhook secret
delete_option('fub_webhook_secret');
update_option('fub_webhook_secret', wp_generate_password(32, false));
```

### Configuration Migration

#### Session to Transient Migration
Old session-based storage is automatically migrated:

```php
// Old (problematic)
$_SESSION['fub_data'] = $data;

// New (cache-friendly)
$user_id = get_current_user_id();
set_transient("fub_user_cache_{$user_id}", $data, HOUR_IN_SECONDS);
```

#### Performance Settings
New performance configuration options:

```php
$performance_config = array(
    'timeouts' => array(
        'webhook' => 30,
        'api_request' => 25,
        'database_query' => 15
    ),
    'rate_limits' => array(
        'api_requests' => 100,
        'webhook_requests' => 200
    ),
    'cache' => array(
        'default_ttl' => 3600,
        'user_cache_ttl' => 3600
    )
);
```

## Security Configuration

### Enhanced Webhook Security

#### Signature Verification
```php
// Webhook requests must include:
headers: {
    'X-FUB-Signature': 'sha256=',
    'X-FUB-Timestamp': '',
    'Content-Type': 'application/json'
}
```

#### Rate Limiting
- API requests: 100 per hour per IP
- Webhook requests: 200 per hour per IP
- Automatic IP blocking for repeated violations

#### IP Restrictions
```php
// Whitelist webhook IPs (recommended)
update_option('fub_webhook_whitelist_ips', array(
    '192.168.1.100',
    '10.0.0.50'
));

// Blacklist problematic IPs
update_option('fub_blacklisted_ips', array(
    '192.168.1.999'
));
```

### Security Headers
All API responses include:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `X-RateLimit-*` headers

## Performance Monitoring

### Performance Metrics
Access via **Follow Up Boss → Debug Panel → Performance**:

- Response times
- Memory usage
- Cache hit ratios  
- Database query performance
- Webhook processing times

### Automatic Optimization
The plugin automatically:
- Detects server environment (shared/VPS/dedicated)
- Optimizes timeouts and limits accordingly
- Monitors for performance issues
- Logs slow queries and high memory usage

### Performance Alerts
Configure alerts for:
- Slow queries (>2 seconds)
- High memory usage (>80% of limit)
- Frequent timeouts
- Poor cache performance

## Troubleshooting

### Common Issues

#### 1. Webhook Timeouts
```php
// Check current timeout settings
$performance = FUB_Performance_Config::get_instance();
echo $performance->get('timeouts', 'webhook'); // Should be 30

// Increase if needed
$performance->set('timeouts', 'webhook', 45);
```

#### 2. Memory Issues
```php
// Check memory usage
$stats = $performance->get_memory_statistics();
if ($stats['peak_usage'] / $stats['limit'] > 0.8) {
    // Increase memory limit
    ini_set('memory_limit', '512M');
}
```

#### 3. Rate Limiting
```php
// Check rate limit status
$client_ip = '192.168.1.100';
$rate_limit_key = "api_rate_limit_{$client_ip}";
$current_count = get_transient($rate_limit_key);
echo "Current requests: {$current_count}/100";

// Reset if needed
delete_transient($rate_limit_key);
```

#### 4. Database Connection Issues
```php
// Test database connection
global $wpdb;
$result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fub_contacts");
if ($wpdb->last_error) {
    echo "Database error: " . $wpdb->last_error;
}
```

### Debug Mode
Enable detailed logging:

```php
update_option('fub_debug_mode', true);

// Check logs in wp-content/debug.log or
// Follow Up Boss → Debug Panel → Logs
```

### Performance Report
Generate comprehensive performance report:

```php
$performance = FUB_Performance_Config::get_instance();
$report = $performance->generate_performance_report();

// View recommendations
foreach ($report['recommendations'] as $rec) {
    echo "Issue: {$rec['message']}\n";
    echo "Action: {$rec['action']}\n";
}
```

## Production Checklist

### Before Going Live
- [ ] SSL certificate installed and working
- [ ] API key configured and tested
- [ ] Webhook endpoints registered with Follow Up Boss
- [ ] Security settings configured (IP whitelist, rate limits)
- [ ] Performance optimization run
- [ ] Debug mode disabled
- [ ] Backup procedures in place

### Monitoring Setup
- [ ] Performance monitoring enabled
- [ ] Security logging active
- [ ] Alert thresholds configured
- [ ] Regular backup schedule
- [ ] Update notifications enabled

### Security Hardening
- [ ] Webhook IP whitelist configured
- [ ] Rate limiting enabled
- [ ] Security headers verified
- [ ] Failed authentication monitoring
- [ ] Regular security audits scheduled

## Support and Maintenance

### Regular Maintenance
- Monitor performance metrics weekly
- Review security logs monthly
- Update plugin when new versions available
- Test webhook functionality quarterly
- Backup database before major updates

### Performance Optimization
- Run environment optimization after server changes
- Monitor cache hit ratios
- Optimize database queries if needed
- Adjust timeout settings based on usage patterns

### Security Best Practices
- Regularly review IP whitelist/blacklist
- Monitor for suspicious activity
- Keep webhook secrets secure
- Use HTTPS for all API communications
- Implement proper backup and recovery procedures