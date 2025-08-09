/* Ghostly Labs Ultimate Follow Up Boss Integration - Debug Panel
 * Version: 2.1.2
 * Premium AI-Powered Real Estate Integration - Advanced Debug Interface
 * Brand: Ghostly Labs - Artificial Intelligence
 */

(function($) {
    'use strict';
    
    /**
     * Enhanced Debug Panel with comprehensive monitoring and diagnostics
     */
    var UFUBDebugPanel = {
        
        // Debug state management
        state: {
            isActive: false,
            logLevel: 'info',
            maxLogs: 1000,
            autoRefresh: true,
            refreshInterval: 5000,
            filters: {
                api: true,
                tracking: true,
                database: true,
                errors: true,
                performance: true
            }
        },
        
        // Performance monitoring
        performance: {
            startTime: Date.now(),
            memoryUsage: [],
            apiCalls: [],
            databaseQueries: [],
            jsErrors: []
        },
        
        // Log storage
        logs: [],
        
        /**
         * Initialize debug panel with Ghostly Labs styling
         */
        init: function() {
            console.log('🔧 Ghostly Labs Debug Panel: Initializing advanced diagnostics...');
            
            // Check if debug mode is enabled
            if (!this.isDebugEnabled()) {
                console.log('🔧 Debug Panel: Debug mode disabled, minimal logging active');
                return;
            }
            
            this.state.isActive = true;
            this.setupDebugInterface();
            this.bindEvents();
            this.startPerformanceMonitoring();
            this.interceptConsole();
            this.monitorAjaxCalls();
            this.trackJavaScriptErrors();
            this.initRealTimeUpdates();
            
            console.log('🔧 Debug Panel: Advanced diagnostics active');
        },
        
        /**
         * Check if debug mode is enabled
         */
        isDebugEnabled: function() {
            return typeof ufub_debug !== 'undefined' && ufub_debug.enabled === '1';
        },
        
        /**
         * Setup debug interface
         */
        setupDebugInterface: function() {
            if ($('#ufub-debug-panel').length > 0) {
                return; // Already exists
            }
            
            var debugHTML = this.buildDebugHTML();
            $('body').append(debugHTML);
            
            // Make draggable
            $('#ufub-debug-panel').draggable({
                handle: '.ufub-debug-header',
                containment: 'window'
            });
            
            // Load saved position
            this.loadPanelPosition();
        },
        
        /**
         * Build debug panel HTML
         */
        buildDebugHTML: function() {
            return `
                <div id="ufub-debug-panel" class="ufub-debug-panel">
                    <div class="ufub-debug-header">
                        <div class="ufub-debug-title">
                            <span class="ufub-debug-icon">🔧</span>
                            Ghostly Labs Debug Panel
                        </div>
                        <div class="ufub-debug-controls">
                            <button class="ufub-debug-btn ufub-debug-minimize" title="Minimize">−</button>
                            <button class="ufub-debug-btn ufub-debug-close" title="Close">×</button>
                        </div>
                    </div>
                    <div class="ufub-debug-content">
                        <div class="ufub-debug-tabs">
                            <button class="ufub-debug-tab active" data-tab="console">Console</button>
                            <button class="ufub-debug-tab" data-tab="performance">Performance</button>
                            <button class="ufub-debug-tab" data-tab="api">API Calls</button>
                            <button class="ufub-debug-tab" data-tab="database">Database</button>
                            <button class="ufub-debug-tab" data-tab="tracking">Tracking</button>
                            <button class="ufub-debug-tab" data-tab="system">System</button>
                        </div>
                        
                        <div class="ufub-debug-filters">
                            <label><input type="checkbox" checked data-filter="api"> API</label>
                            <label><input type="checkbox" checked data-filter="tracking"> Tracking</label>
                            <label><input type="checkbox" checked data-filter="database"> Database</label>
                            <label><input type="checkbox" checked data-filter="errors"> Errors</label>
                            <label><input type="checkbox" checked data-filter="performance"> Performance</label>
                            <button class="ufub-debug-clear">Clear</button>
                        </div>
                        
                        <div class="ufub-debug-panels">
                            <div class="ufub-debug-panel-content active" data-panel="console">
                                <div class="ufub-debug-logs" id="ufub-debug-logs"></div>
                            </div>
                            
                            <div class="ufub-debug-panel-content" data-panel="performance">
                                <div class="ufub-performance-stats">
                                    <div class="ufub-stat-item">
                                        <label>Memory Usage:</label>
                                        <span id="ufub-memory-usage">--</span>
                                    </div>
                                    <div class="ufub-stat-item">
                                        <label>API Response Time:</label>
                                        <span id="ufub-api-response">--</span>
                                    </div>
                                    <div class="ufub-stat-item">
                                        <label>JS Errors:</label>
                                        <span id="ufub-js-errors">0</span>
                                    </div>
                                    <div class="ufub-stat-item">
                                        <label>Total Requests:</label>
                                        <span id="ufub-total-requests">0</span>
                                    </div>
                                </div>
                                <div class="ufub-performance-chart">
                                    <canvas id="ufub-performance-canvas" width="400" height="200"></canvas>
                                </div>
                            </div>
                            
                            <div class="ufub-debug-panel-content" data-panel="api">
                                <div class="ufub-api-calls" id="ufub-api-calls"></div>
                            </div>
                            
                            <div class="ufub-debug-panel-content" data-panel="database">
                                <div class="ufub-database-queries" id="ufub-database-queries"></div>
                            </div>
                            
                            <div class="ufub-debug-panel-content" data-panel="tracking">
                                <div class="ufub-tracking-events" id="ufub-tracking-events"></div>
                            </div>
                            
                            <div class="ufub-debug-panel-content" data-panel="system">
                                <div class="ufub-system-info" id="ufub-system-info"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },
        
        /**
         * Bind debug panel events
         */
        bindEvents: function() {
            var self = this;
            
            // Tab switching
            $(document).on('click', '.ufub-debug-tab', function() {
                var tab = $(this).data('tab');
                self.switchTab(tab);
            });
            
            // Panel controls
            $(document).on('click', '.ufub-debug-minimize', function() {
                self.toggleMinimize();
            });
            
            $(document).on('click', '.ufub-debug-close', function() {
                self.closePanel();
            });
            
            // Filter controls
            $(document).on('change', '.ufub-debug-filters input', function() {
                var filter = $(this).data('filter');
                self.state.filters[filter] = $(this).is(':checked');
                self.refreshLogs();
            });
            
            // Clear logs
            $(document).on('click', '.ufub-debug-clear', function() {
                self.clearLogs();
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.keyCode === 68) { // Ctrl+Shift+D
                    self.togglePanel();
                }
            });
        },
        
        /**
         * Switch debug tab
         */
        switchTab: function(tab) {
            $('.ufub-debug-tab').removeClass('active');
            $('.ufub-debug-tab[data-tab="' + tab + '"]').addClass('active');
            
            $('.ufub-debug-panel-content').removeClass('active');
            $('.ufub-debug-panel-content[data-panel="' + tab + '"]').addClass('active');
            
            // Load tab-specific data
            switch(tab) {
                case 'performance':
                    this.updatePerformanceStats();
                    break;
                case 'api':
                    this.updateApiCalls();
                    break;
                case 'database':
                    this.updateDatabaseQueries();
                    break;
                case 'tracking':
                    this.updateTrackingEvents();
                    break;
                case 'system':
                    this.updateSystemInfo();
                    break;
            }
        },
        
        /**
         * Log debug message
         */
        log: function(message, type, category) {
            type = type || 'info';
            category = category || 'general';
            
            var logEntry = {
                timestamp: new Date(),
                message: message,
                type: type,
                category: category,
                id: this.logs.length
            };
            
            this.logs.push(logEntry);
            
            // Limit log size
            if (this.logs.length > this.state.maxLogs) {
                this.logs = this.logs.slice(-this.state.maxLogs);
            }
            
            // Update display if panel is active
            if (this.state.isActive) {
                this.addLogToDisplay(logEntry);
            }
            
            // Console output with styling
            var styles = this.getLogStyles(type);
            console.log(`%c[UFUB ${type.toUpperCase()}]%c ${message}`, styles.prefix, styles.message);
        },
        
        /**
         * Get console styling for log types
         */
        getLogStyles: function(type) {
            var styles = {
                info: {
                    prefix: 'background: #3ab24a; color: white; padding: 2px 4px; border-radius: 2px;',
                    message: 'color: #333;'
                },
                warn: {
                    prefix: 'background: #ffc107; color: black; padding: 2px 4px; border-radius: 2px;',
                    message: 'color: #856404;'
                },
                error: {
                    prefix: 'background: #dc3545; color: white; padding: 2px 4px; border-radius: 2px;',
                    message: 'color: #721c24;'
                },
                success: {
                    prefix: 'background: #28a745; color: white; padding: 2px 4px; border-radius: 2px;',
                    message: 'color: #155724;'
                }
            };
            return styles[type] || styles.info;
        },
        
        /**
         * Add log entry to display
         */
        addLogToDisplay: function(logEntry) {
            if (!this.state.filters[logEntry.category] && logEntry.category !== 'general') {
                return;
            }
            
            var $logs = $('#ufub-debug-logs');
            var timestamp = logEntry.timestamp.toLocaleTimeString();
            var logHTML = `
                <div class="ufub-log-entry ufub-log-${logEntry.type}" data-category="${logEntry.category}">
                    <span class="ufub-log-time">${timestamp}</span>
                    <span class="ufub-log-type">${logEntry.type.toUpperCase()}</span>
                    <span class="ufub-log-category">[${logEntry.category}]</span>
                    <span class="ufub-log-message">${logEntry.message}</span>
                </div>
            `;
            
            $logs.append(logHTML);
            $logs.scrollTop($logs[0].scrollHeight);
        },
        
        /**
         * Monitor AJAX calls
         */
        monitorAjaxCalls: function() {
            var self = this;
            
            // Intercept jQuery AJAX
            $(document).ajaxSend(function(event, xhr, options) {
                var apiCall = {
                    timestamp: new Date(),
                    url: options.url,
                    method: options.type || 'GET',
                    status: 'pending',
                    startTime: Date.now()
                };
                
                self.performance.apiCalls.push(apiCall);
                self.log(`API Call: ${apiCall.method} ${apiCall.url}`, 'info', 'api');
            });
            
            $(document).ajaxComplete(function(event, xhr, options) {
                var duration = Date.now() - xhr.startTime;
                self.log(`API Complete: ${options.url} (${duration}ms) - Status: ${xhr.status}`, 
                         xhr.status >= 400 ? 'error' : 'success', 'api');
            });
            
            $(document).ajaxError(function(event, xhr, options, error) {
                self.log(`API Error: ${options.url} - ${error}`, 'error', 'api');
            });
        },
        
        /**
         * Track JavaScript errors
         */
        trackJavaScriptErrors: function() {
            var self = this;
            
            window.addEventListener('error', function(e) {
                var error = {
                    timestamp: new Date(),
                    message: e.message,
                    filename: e.filename,
                    line: e.lineno,
                    column: e.colno,
                    stack: e.error ? e.error.stack : null
                };
                
                self.performance.jsErrors.push(error);
                self.log(`JS Error: ${error.message} (${error.filename}:${error.line})`, 'error', 'errors');
            });
            
            window.addEventListener('unhandledrejection', function(e) {
                self.log(`Unhandled Promise Rejection: ${e.reason}`, 'error', 'errors');
            });
        },
        
        /**
         * Start performance monitoring
         */
        startPerformanceMonitoring: function() {
            var self = this;
            
            setInterval(function() {
                self.collectPerformanceData();
            }, 1000);
        },
        
        /**
         * Collect performance data
         */
        collectPerformanceData: function() {
            if (typeof performance !== 'undefined' && performance.memory) {
                this.performance.memoryUsage.push({
                    timestamp: Date.now(),
                    used: performance.memory.usedJSHeapSize,
                    total: performance.memory.totalJSHeapSize,
                    limit: performance.memory.jsHeapSizeLimit
                });
                
                // Keep only last 100 entries
                if (this.performance.memoryUsage.length > 100) {
                    this.performance.memoryUsage = this.performance.memoryUsage.slice(-100);
                }
            }
        },
        
        /**
         * Update performance statistics
         */
        updatePerformanceStats: function() {
            var memory = this.performance.memoryUsage;
            if (memory.length > 0) {
                var latest = memory[memory.length - 1];
                $('#ufub-memory-usage').text(this.formatBytes(latest.used));
            }
            
            $('#ufub-js-errors').text(this.performance.jsErrors.length);
            $('#ufub-total-requests').text(this.performance.apiCalls.length);
            
            // Calculate average API response time
            var apiTimes = this.performance.apiCalls
                .filter(call => call.duration)
                .map(call => call.duration);
            
            if (apiTimes.length > 0) {
                var avgTime = apiTimes.reduce((a, b) => a + b, 0) / apiTimes.length;
                $('#ufub-api-response').text(Math.round(avgTime) + 'ms');
            }
        },
        
        /**
         * Format bytes for display
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        /**
         * Update API calls display
         */
        updateApiCalls: function() {
            var $container = $('#ufub-api-calls');
            $container.empty();
            
            this.performance.apiCalls.slice(-20).forEach(function(call) {
                var callHTML = `
                    <div class="ufub-api-call">
                        <div class="ufub-api-call-header">
                            <span class="ufub-api-method">${call.method}</span>
                            <span class="ufub-api-url">${call.url}</span>
                            <span class="ufub-api-time">${call.timestamp.toLocaleTimeString()}</span>
                        </div>
                        <div class="ufub-api-call-details">
                            Status: ${call.status} | Duration: ${call.duration || 'pending'}ms
                        </div>
                    </div>
                `;
                $container.append(callHTML);
            });
        },
        
        /**
         * Update system information
         */
        updateSystemInfo: function() {
            var info = {
                'User Agent': navigator.userAgent,
                'Screen Resolution': screen.width + 'x' + screen.height,
                'Viewport Size': window.innerWidth + 'x' + window.innerHeight,
                'Color Depth': screen.colorDepth + '-bit',
                'Memory Limit': performance.memory ? this.formatBytes(performance.memory.jsHeapSizeLimit) : 'Unknown',
                'Cookie Enabled': navigator.cookieEnabled,
                'Language': navigator.language,
                'Platform': navigator.platform,
                'Connection': navigator.connection ? navigator.connection.effectiveType : 'Unknown'
            };
            
            var $container = $('#ufub-system-info');
            $container.empty();
            
            Object.keys(info).forEach(function(key) {
                $container.append(`
                    <div class="ufub-system-item">
                        <label>${key}:</label>
                        <span>${info[key]}</span>
                    </div>
                `);
            });
        },
        
        /**
         * Clear all logs
         */
        clearLogs: function() {
            this.logs = [];
            $('#ufub-debug-logs').empty();
            this.log('Debug logs cleared', 'info', 'general');
        },
        
        /**
         * Toggle panel visibility
         */
        togglePanel: function() {
            $('#ufub-debug-panel').toggle();
        },
        
        /**
         * Close panel
         */
        closePanel: function() {
            $('#ufub-debug-panel').hide();
        },
        
        /**
         * Toggle minimize
         */
        toggleMinimize: function() {
            $('#ufub-debug-panel').toggleClass('minimized');
        },
        
        /**
         * Refresh log display
         */
        refreshLogs: function() {
            var $logs = $('#ufub-debug-logs');
            $logs.empty();
            
            this.logs.forEach(log => {
                this.addLogToDisplay(log);
            });
        },
        
        /**
         * Save panel position
         */
        savePanelPosition: function() {
            var $panel = $('#ufub-debug-panel');
            var position = $panel.position();
            localStorage.setItem('ufub_debug_panel_position', JSON.stringify(position));
        },
        
        /**
         * Load panel position
         */
        loadPanelPosition: function() {
            var position = localStorage.getItem('ufub_debug_panel_position');
            if (position) {
                position = JSON.parse(position);
                $('#ufub-debug-panel').css(position);
            }
        },
        
        /**
         * Intercept console for logging
         */
        interceptConsole: function() {
            var self = this;
            var originalLog = console.log;
            var originalWarn = console.warn;
            var originalError = console.error;
            
            console.log = function() {
                originalLog.apply(console, arguments);
                if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].includes('UFUB')) {
                    self.log(arguments[0], 'info', 'console');
                }
            };
            
            console.warn = function() {
                originalWarn.apply(console, arguments);
                if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].includes('UFUB')) {
                    self.log(arguments[0], 'warn', 'console');
                }
            };
            
            console.error = function() {
                originalError.apply(console, arguments);
                if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].includes('UFUB')) {
                    self.log(arguments[0], 'error', 'console');
                }
            };
        },
        
        /**
         * Initialize real-time updates
         */
        initRealTimeUpdates: function() {
            var self = this;
            
            if (self.state.autoRefresh) {
                setInterval(function() {
                    if ($('#ufub-debug-panel').is(':visible')) {
                        var activeTab = $('.ufub-debug-tab.active').data('tab');
                        if (activeTab === 'performance') {
                            self.updatePerformanceStats();
                        }
                    }
                }, self.state.refreshInterval);
            }
        },
        
        // Public API methods
        api: {
            log: function(message, type, category) {
                UFUBDebugPanel.log(message, type, category);
            },
            
            show: function() {
                UFUBDebugPanel.togglePanel();
            },
            
            clear: function() {
                UFUBDebugPanel.clearLogs();
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        UFUBDebugPanel.init();
        
        // Make debug panel globally accessible
        window.UFUBDebug = UFUBDebugPanel.api;
    });
    
})(jQuery);
