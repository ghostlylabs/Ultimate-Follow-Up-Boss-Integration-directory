/**
 * Ultimate Follow Up Boss Integration - Debug Console
 * 
 * Advanced debugging interface with real-time logging and error tracking
 * 
 * @package Ultimate_FUB_Integration
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    // Main debug object
    window.UFUBDebug = {
        
        // Configuration
        enabled: false,
        logs: [],
        maxLogs: 1000,
        
        // UI Elements
        $panel: null,
        $toggle: null,
        panelVisible: false,
        
        // Filters
        logLevels: ['ERROR', 'WARNING', 'INFO', 'DEBUG'],
        activeFilters: ['ERROR', 'WARNING', 'INFO'],
        
        /**
         * Initialize debug system
         */
        init: function() {
            if (!window.ufub_debug || !window.ufub_debug.debug_enabled) {
                return;
            }
            
            this.enabled = true;
            this.createDebugPanel();
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.startPerformanceMonitoring();
            
            // Hook into JavaScript errors
            this.setupErrorHandling();
            
            this.log('INFO', 'Debug system initialized');
        },
        
        /**
         * Create debug panel HTML
         */
        createDebugPanel: function() {
            var panelHtml = `
                <div id="ufub-debug-panel" class="ufub-debug-panel">
                    <div class="ufub-debug-header">
                        <div class="ufub-debug-title">
                            <span class="ufub-debug-icon">🔧</span>
                            <h3>FUB Debug Console</h3>
                            <span class="ufub-debug-version">v1.0.0</span>
                        </div>
                        <div class="ufub-debug-controls">
                            <div class="ufub-debug-stats">
                                <span id="ufub-error-count" class="error-count">0 errors</span>
                                <span id="ufub-memory-usage" class="memory-usage">0 MB</span>
                            </div>
                            <div class="ufub-debug-buttons">
                                <button id="ufub-clear-logs" class="debug-btn clear" title="Clear logs">🗑️</button>
                                <button id="ufub-export-logs" class="debug-btn export" title="Export logs">📤</button>
                                <button id="ufub-filter-logs" class="debug-btn filter" title="Filter logs">🔍</button>
                                <button id="ufub-test-api" class="debug-btn test" title="Test API">⚡</button>
                                <button id="ufub-minimize" class="debug-btn minimize" title="Minimize">➖</button>
                                <button id="ufub-close-debug" class="debug-btn close" title="Close">✖️</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ufub-debug-content">
                        <div class="ufub-debug-tabs">
                            <button class="debug-tab active" data-tab="logs">Logs</button>
                            <button class="debug-tab" data-tab="network">Network</button>
                            <button class="debug-tab" data-tab="performance">Performance</button>
                            <button class="debug-tab" data-tab="system">System</button>
                        </div>
                        
                        <div class="ufub-debug-tab-content">
                            <!-- Logs Tab -->
                            <div id="debug-tab-logs" class="debug-tab-panel active">
                                <div class="ufub-debug-filters">
                                    <label>
                                        <input type="checkbox" value="ERROR" checked> Errors
                                    </label>
                                    <label>
                                        <input type="checkbox" value="WARNING" checked> Warnings
                                    </label>
                                    <label>
                                        <input type="checkbox" value="INFO" checked> Info
                                    </label>
                                    <label>
                                        <input type="checkbox" value="DEBUG"> Debug
                                    </label>
                                    <div class="filter-actions">
                                        <button id="select-all-filters">All</button>
                                        <button id="clear-all-filters">None</button>
                                    </div>
                                </div>
                                <div id="ufub-debug-logs" class="debug-logs-container">
                                    <!-- Logs will be populated here -->
                                </div>
                            </div>
                            
                            <!-- Network Tab -->
                            <div id="debug-tab-network" class="debug-tab-panel">
                                <div id="ufub-network-requests" class="network-container">
                                    <div class="network-header">
                                        <span>Method</span>
                                        <span>URL</span>
                                        <span>Status</span>
                                        <span>Time</span>
                                        <span>Size</span>
                                    </div>
                                    <div class="network-requests">
                                        <!-- Network requests will be populated here -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Performance Tab -->
                            <div id="debug-tab-performance" class="debug-tab-panel">
                                <div class="performance-metrics">
                                    <div class="metric-card">
                                        <h4>Memory Usage</h4>
                                        <div id="memory-chart" class="chart-container"></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>API Response Times</h4>
                                        <div id="api-chart" class="chart-container"></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Page Performance</h4>
                                        <div class="performance-stats">
                                            <div>DOM Ready: <span id="dom-ready-time">-</span></div>
                                            <div>Load Time: <span id="load-time">-</span></div>
                                            <div>FCP: <span id="fcp-time">-</span></div>
                                            <div>LCP: <span id="lcp-time">-</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- System Tab -->
                            <div id="debug-tab-system" class="debug-tab-panel">
                                <div class="system-info">
                                    <div class="system-section">
                                        <h4>Configuration</h4>
                                        <div class="config-grid">
                                            <div>API Configured: <span id="api-configured">-</span></div>
                                            <div>Debug Mode: <span id="debug-mode">-</span></div>
                                            <div>Source Domain: <span id="source-domain">-</span></div>
                                            <div>Scroll Threshold: <span id="scroll-threshold">-</span></div>
                                            <div>Time Threshold: <span id="time-threshold">-</span></div>
                                            <div>Popup Threshold: <span id="popup-threshold">-</span></div>
                                        </div>
                                    </div>
                                    
                                    <div class="system-section">
                                        <h4>Browser Info</h4>
                                        <div class="browser-grid">
                                            <div>User Agent: <span id="user-agent">-</span></div>
                                            <div>Viewport: <span id="viewport">-</span></div>
                                            <div>Platform: <span id="platform">-</span></div>
                                            <div>Language: <span id="language">-</span></div>
                                        </div>
                                    </div>
                                    
                                    <div class="system-section">
                                        <h4>Tracking Status</h4>
                                        <div class="tracking-grid">
                                            <div>Session ID: <span id="session-id">-</span></div>
                                            <div>Property Views: <span id="property-views">-</span></div>
                                            <div>Search Count: <span id="search-count">-</span></div>
                                            <div>Engagement Score: <span id="engagement-score">-</span></div>
                                            <div>Time on Page: <span id="time-on-page">-</span></div>
                                            <div>Scroll Depth: <span id="scroll-depth">-</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Debug toggle button -->
                <div id="ufub-debug-toggle" class="ufub-debug-toggle" title="Toggle Debug Panel (Ctrl+Shift+D)">
                    🔧
                    <span class="debug-badge" id="debug-error-badge">0</span>
                </div>
                
                <!-- Filter modal -->
                <div id="ufub-filter-modal" class="ufub-filter-modal" style="display: none;">
                    <div class="filter-modal-content">
                        <h3>Filter Debug Logs</h3>
                        <div class="filter-options">
                            <label><input type="checkbox" value="ERROR"> 🔴 Errors</label>
                            <label><input type="checkbox" value="WARNING"> 🟡 Warnings</label>
                            <label><input type="checkbox" value="INFO"> 🔵 Info</label>
                            <label><input type="checkbox" value="DEBUG"> ⚪ Debug</label>
                        </div>
                        <div class="filter-actions">
                            <button id="apply-filters">Apply</button>
                            <button id="cancel-filters">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(panelHtml);
            this.$panel = $('#ufub-debug-panel');
            this.$toggle = $('#ufub-debug-toggle');
            
            // Initially hidden
            this.$panel.hide();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Toggle panel
            this.$toggle.on('click', function() {
                self.togglePanel();
            });
            
            // Panel controls
            $('#ufub-close-debug').on('click', function() {
                self.hidePanel();
            });
            
            $('#ufub-minimize').on('click', function() {
                self.minimizePanel();
            });
            
            $('#ufub-clear-logs').on('click', function() {
                self.clearLogs();
            });
            
            $('#ufub-export-logs').on('click', function() {
                self.exportLogs();
            });
            
            $('#ufub-filter-logs').on('click', function() {
                self.showFilterModal();
            });
            
            $('#ufub-test-api').on('click', function() {
                self.testAPI();
            });
            
            // Tab switching
            $('.debug-tab').on('click', function() {
                var tab = $(this).data('tab');
                self.switchTab(tab);
            });
            
            // Filter checkboxes
            $('.ufub-debug-filters input[type="checkbox"]').on('change', function() {
                self.updateFilters();
                self.renderLogs();
            });
            
            $('#select-all-filters').on('click', function() {
                $('.ufub-debug-filters input[type="checkbox"]').prop('checked', true);
                self.updateFilters();
                self.renderLogs();
            });
            
            $('#clear-all-filters').on('click', function() {
                $('.ufub-debug-filters input[type="checkbox"]').prop('checked', false);
                self.updateFilters();
                self.renderLogs();
            });
            
            // Filter modal
            $('#apply-filters').on('click', function() {
                self.applyFilters();
            });
            
            $('#cancel-filters').on('click', function() {
                self.hideFilterModal();
            });
        },
        
        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts: function() {
            var self = this;
            
            $(document).on('keydown', function(e) {
                // Ctrl+Shift+D - Toggle debug panel
                if (e.ctrlKey && e.shiftKey && e.which === 68) {
                    e.preventDefault();
                    self.togglePanel();
                }
                
                // Ctrl+Shift+E - Export logs
                if (e.ctrlKey && e.shiftKey && e.which === 69) {
                    e.preventDefault();
                    self.exportLogs();
                }
                
                // Ctrl+Shift+C - Clear logs
                if (e.ctrlKey && e.shiftKey && e.which === 67) {
                    e.preventDefault();
                    self.clearLogs();
                }
                
                // Ctrl+Shift+T - Test API
                if (e.ctrlKey && e.shiftKey && e.which === 84) {
                    e.preventDefault();
                    self.testAPI();
                }
                
                // Escape - Close panel
                if (e.which === 27 && self.panelVisible) {
                    e.preventDefault();
                    self.hidePanel();
                }
            });
        },
        
        /**
         * Setup error handling
         */
        setupErrorHandling: function() {
            var self = this;
            
            // Capture JavaScript errors
            window.addEventListener('error', function(e) {
                self.log('ERROR', 'JavaScript Error: ' + e.message, {
                    filename: e.filename,
                    lineno: e.lineno,
                    colno: e.colno,
                    stack: e.error ? e.error.stack : 'No stack trace'
                });
            });
            
            // Capture unhandled promise rejections
            window.addEventListener('unhandledrejection', function(e) {
                self.log('ERROR', 'Unhandled Promise Rejection: ' + e.reason, {
                    type: 'promise_rejection',
                    reason: e.reason
                });
            });
            
            // Capture console errors
            var originalError = console.error;
            console.error = function() {
                originalError.apply(console, arguments);
                self.log('ERROR', 'Console Error: ' + Array.prototype.join.call(arguments, ' '));
            };
        },
        
        /**
         * Start performance monitoring
         */
        startPerformanceMonitoring: function() {
            var self = this;
            
            // Monitor memory usage
            setInterval(function() {
                if (self.panelVisible) {
                    self.updateMemoryUsage();
                }
            }, 2000);
            
            // Monitor page performance
            this.trackPagePerformance();
            
            // Update system info
            this.updateSystemInfo();
        },
        
        /**
         * Log a message
         */
        log: function(level, message, data) {
            if (!this.enabled) return;
            
            var logEntry = {
                timestamp: new Date(),
                level: level,
                message: message,
                data: data || {},
                id: this.logs.length
            };
            
            this.logs.push(logEntry);
            
            // Limit log size
            if (this.logs.length > this.maxLogs) {
                this.logs.shift();
            }
            
            // Update UI if panel is visible
            if (this.panelVisible) {
                this.renderNewLog(logEntry);
                this.updateErrorCount();
            }
            
            // Update badge
            this.updateDebugBadge();
            
            // Send to server if it's an error
            if (level === 'ERROR' && window.ufub_debug) {
                this.sendErrorToServer(logEntry);
            }
        },
        
        /**
         * Render logs in the panel
         */
        renderLogs: function() {
            var $container = $('#ufub-debug-logs');
            $container.empty();
            
            var filteredLogs = this.logs.filter(function(log) {
                return this.activeFilters.indexOf(log.level) !== -1;
            }.bind(this));
            
            filteredLogs.slice(-100).forEach(function(log) {
                this.renderNewLog(log);
            }.bind(this));
            
            // Auto-scroll to bottom
            $container.scrollTop($container[0].scrollHeight);
        },
        
        /**
         * Render a new log entry
         */
        renderNewLog: function(log) {
            if (this.activeFilters.indexOf(log.level) === -1) {
                return;
            }
            
            var $container = $('#ufub-debug-logs');
            var timeStr = log.timestamp.toLocaleTimeString();
            var icon = this.getLogIcon(log.level);
            var className = 'log-entry log-' + log.level.toLowerCase();
            
            var $logEntry = $(`
                <div class="${className}" data-log-id="${log.id}">
                    <div class="log-header">
                        <span class="log-icon">${icon}</span>
                        <span class="log-level">${log.level}</span>
                        <span class="log-time">${timeStr}</span>
                        <span class="log-expand">▼</span>
                    </div>
                    <div class="log-message">${this.escapeHtml(log.message)}</div>
                    <div class="log-data" style="display: none;">
                        <pre>${JSON.stringify(log.data, null, 2)}</pre>
                    </div>
                </div>
            `);
            
            // Toggle data visibility
            $logEntry.find('.log-header').on('click', function() {
                var $data = $logEntry.find('.log-data');
                var $expand = $logEntry.find('.log-expand');
                
                if ($data.is(':visible')) {
                    $data.hide();
                    $expand.text('▼');
                } else {
                    $data.show();
                    $expand.text('▲');
                }
            });
            
            $container.append($logEntry);
            
            // Auto-scroll to bottom
            $container.scrollTop($container[0].scrollHeight);
        },
        
        /**
         * Get icon for log level
         */
        getLogIcon: function(level) {
            var icons = {
                'ERROR': '🔴',
                'WARNING': '🟡',
                'INFO': '🔵',
                'DEBUG': '⚪'
            };
            return icons[level] || '⚪';
        },
        
        /**
         * Toggle debug panel
         */
        togglePanel: function() {
            if (this.panelVisible) {
                this.hidePanel();
            } else {
                this.showPanel();
            }
        },
        
        /**
         * Show debug panel
         */
        showPanel: function() {
            this.$panel.show();
            this.panelVisible = true;
            this.renderLogs();
            this.updateSystemInfo();
            this.updateMemoryUsage();
        },
        
        /**
         * Hide debug panel
         */
        hidePanel: function() {
            this.$panel.hide();
            this.panelVisible = false;
        },
        
        /**
         * Minimize panel
         */
        minimizePanel: function() {
            this.$panel.toggleClass('minimized');
        },
        
        /**
         * Switch tabs
         */
        switchTab: function(tab) {
            $('.debug-tab').removeClass('active');
            $('.debug-tab[data-tab="' + tab + '"]').addClass('active');
            
            $('.debug-tab-panel').removeClass('active');
            $('#debug-tab-' + tab).addClass('active');
            
            // Load tab-specific content
            switch (tab) {
                case 'network':
                    this.loadNetworkData();
                    break;
                case 'performance':
                    this.loadPerformanceData();
                    break;
                case 'system':
                    this.updateSystemInfo();
                    break;
            }
        },
        
        /**
         * Clear all logs
         */
        clearLogs: function() {
            this.logs = [];
            $('#ufub-debug-logs').empty();
            this.updateErrorCount();
            this.updateDebugBadge();
            this.log('INFO', 'Debug logs cleared');
        },
        
        /**
         * Export logs
         */
        exportLogs: function() {
            var exportData = {
                timestamp: new Date().toISOString(),
                logs: this.logs,
                systemInfo: this.getSystemInfo(),
                url: window.location.href,
                userAgent: navigator.userAgent
            };
            
            var blob = new Blob([JSON.stringify(exportData, null, 2)], {
                type: 'application/json'
            });
            
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'ufub-debug-logs-' + Date.now() + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            this.log('INFO', 'Debug logs exported');
        },
        
        /**
         * Test API connection
         */
        testAPI: function() {
            this.log('INFO', 'Testing API connection...');
            
            if (window.UFUBTracking && window.UFUBTracking.testAPI) {
                window.UFUBTracking.testAPI();
            } else {
                this.log('WARNING', 'UFUBTracking not available for API test');
            }
        },
        
        /**
         * Update filters
         */
        updateFilters: function() {
            this.activeFilters = [];
            $('.ufub-debug-filters input[type="checkbox"]:checked').each(function() {
                this.activeFilters.push($(this).val());
            }.bind(this));
        },
        
        /**
         * Update error count
         */
        updateErrorCount: function() {
            var errorCount = this.logs.filter(function(log) {
                return log.level === 'ERROR';
            }).length;
            
            $('#ufub-error-count').text(errorCount + ' errors');
        },
        
        /**
         * Update debug badge
         */
        updateDebugBadge: function() {
            var errorCount = this.logs.filter(function(log) {
                return log.level === 'ERROR';
            }).length;
            
            var $badge = $('#debug-error-badge');
            $badge.text(errorCount);
            
            if (errorCount > 0) {
                $badge.addClass('has-errors');
            } else {
                $badge.removeClass('has-errors');
            }
        },
        
        /**
         * Update memory usage
         */
        updateMemoryUsage: function() {
            if (performance.memory) {
                var used = Math.round(performance.memory.usedJSHeapSize / 1024 / 1024);
                $('#ufub-memory-usage').text(used + ' MB');
                $('#memory-usage').text(used + ' MB');
            }
        },
        
        /**
         * Update system info
         */
        updateSystemInfo: function() {
            // Configuration
            $('#api-configured').text(window.ufub_config ? window.ufub_config.api_configured : 'Unknown');
            $('#debug-mode').text(window.ufub_debug ? window.ufub_debug.debug_enabled : 'Unknown');
            $('#source-domain').text(window.ufub_config ? window.ufub_config.source_domain : 'Unknown');
            $('#scroll-threshold').text(window.ufub_config ? window.ufub_config.scroll_threshold + '%' : 'Unknown');
            $('#time-threshold').text(window.ufub_config ? window.ufub_config.time_threshold + 's' : 'Unknown');
            $('#popup-threshold').text(window.ufub_config ? window.ufub_config.popup_threshold : 'Unknown');
            
            // Browser info
            $('#user-agent').text(navigator.userAgent);
            $('#viewport').text(window.innerWidth + 'x' + window.innerHeight);
            $('#platform').text(navigator.platform);
            $('#language').text(navigator.language);
            
            // Tracking status
            if (window.UFUBTracking) {
                $('#session-id').text(window.UFUBTracking.sessionId || 'Unknown');
                $('#property-views').text(window.UFUBTracking.propertyViews ? window.UFUBTracking.propertyViews.length : '0');
                $('#search-count').text(window.UFUBTracking.searchCount || '0');
                $('#engagement-score').text(window.UFUBTracking.engagementScore || '0');
                $('#time-on-page').text(window.UFUBTracking.timeOnPage ? Math.round(window.UFUBTracking.timeOnPage / 1000) + 's' : '0s');
                $('#scroll-depth').text(window.UFUBTracking.maxScrollDepth + '%' || '0%');
            }
        },
        
        /**
         * Track page performance
         */
        trackPagePerformance: function() {
            // Wait for page to load
            $(window).on('load', function() {
                if (performance.timing) {
                    var timing = performance.timing;
                    var domReady = timing.domContentLoadedEventEnd - timing.navigationStart;
                    var loadTime = timing.loadEventEnd - timing.navigationStart;
                    
                    $('#dom-ready-time').text(domReady + 'ms');
                    $('#load-time').text(loadTime + 'ms');
                }
                
                // Try to get FCP and LCP
                if (performance.getEntriesByType) {
                    var paintEntries = performance.getEntriesByType('paint');
                    paintEntries.forEach(function(entry) {
                        if (entry.name === 'first-contentful-paint') {
                            $('#fcp-time').text(Math.round(entry.startTime) + 'ms');
                        }
                    });
                }
            });
        },
        
        /**
         * Send error to server
         */
        sendErrorToServer: function(logEntry) {
            if (!window.ufub_debug || !window.ufub_debug.ajax_url) {
                return;
            }
            
            $.ajax({
                url: window.ufub_debug.ajax_url,
                type: 'POST',
                data: {
                    action: 'ufub_log_js_error',
                    nonce: window.ufub_debug.nonce,
                    message: logEntry.message,
                    level: logEntry.level,
                    data: JSON.stringify(logEntry.data),
                    url: window.location.href,
                    timestamp: logEntry.timestamp.toISOString()
                },
                success: function(response) {
                    // Error logged successfully
                },
                error: function() {
                    // Failed to log error - don't create infinite loop
                }
            });
        },
        
        /**
         * Get system info
         */
        getSystemInfo: function() {
            return {
                userAgent: navigator.userAgent,
                platform: navigator.platform,
                language: navigator.language,
                viewport: window.innerWidth + 'x' + window.innerHeight,
                url: window.location.href,
                timestamp: new Date().toISOString(),
                memory: performance.memory ? {
                    used: performance.memory.usedJSHeapSize,
                    total: performance.memory.totalJSHeapSize,
                    limit: performance.memory.jsHeapSizeLimit
                } : null
            };
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Show filter modal
         */
        showFilterModal: function() {
            $('#ufub-filter-modal').show();
        },
        
        /**
         * Hide filter modal
         */
        hideFilterModal: function() {
            $('#ufub-filter-modal').hide();
        },
        
        /**
         * Apply filters
         */
        applyFilters: function() {
            // Update main filter checkboxes based on modal
            var selectedFilters = [];
            $('#ufub-filter-modal input:checked').each(function() {
                selectedFilters.push($(this).val());
            });
            
            $('.ufub-debug-filters input[type="checkbox"]').each(function() {
                $(this).prop('checked', selectedFilters.indexOf($(this).val()) !== -1);
            });
            
            this.updateFilters();
            this.renderLogs();
            this.hideFilterModal();
        },
        
        /**
         * Load network data
         */
        loadNetworkData: function() {
            // Placeholder for network monitoring
            var $container = $('.network-requests');
            $container.html('<div class="network-placeholder">Network monitoring will be implemented in future version</div>');
        },
        
        /**
         * Load performance data
         */
        loadPerformanceData: function() {
            // Placeholder for performance charts
            $('#memory-chart').html('<div class="chart-placeholder">Memory usage chart will be implemented</div>');
            $('#api-chart').html('<div class="chart-placeholder">API response time chart will be implemented</div>');
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        window.UFUBDebug.init();
    });
    
})(jQuery);