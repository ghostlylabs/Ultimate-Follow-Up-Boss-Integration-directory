/**
 * Ghostly Labs Ultimate Follow Up Boss Integration - Enhanced Admin JavaScript
 * Version: 2.1.2
 * Premium AI-Powered Real Estate Integration Interface
 * Brand: Ghostly Labs - Artificial Intelligence
 */

(function($) {
    'use strict';
    
    /**
     * Enhanced Admin functionality with Ghostly Labs premium experience
     */
    var UFUBAdmin = {
        
        // Enhanced admin state management
        state: {
            isLoading: false,
            lastDashboardUpdate: 0,
            connectionStatus: 'unknown',
            realTimeUpdates: true,
            debugMode: false
        },
        
        // Performance tracking
        performance: {
            apiResponseTimes: [],
            lastPerformanceCheck: Date.now()
        },
        
        /**
         * Initialize enhanced admin interface with Ghostly Labs branding
         */
        init: function() {
            this.logWithBrand('🚀 Initializing Ghostly Labs Admin Interface...');
            
            // Initialize core functionality
            this.bindEvents();
            this.initTooltips();
            this.initRealTimeDashboard();
            this.initEnhancedUI();
            this.initPerformanceMonitoring();
            
            // Load initial dashboard data
            this.loadDashboardData();
            
            // Initialize auto-refresh for real-time updates
            this.setupAutoRefresh();
            
            // Initialize Ghostly Labs debug integration
            this.initDebugIntegration();
            
            this.logWithBrand('✅ Enhanced admin interface initialized successfully');
        },
        
        /**
         * Enhanced logging with Ghostly Labs branding
         */
        logWithBrand: function(message, data) {
            console.log('%c🔮 Ghostly Admin%c ' + message, 
                'color: #3ab24a; font-weight: bold; background: rgba(58, 178, 74, 0.1); padding: 2px 6px; border-radius: 3px;',
                'color: inherit; font-weight: normal; background: none; padding: 0;',
                data || ''
            );
        },
        
        /**
         * Initialize real-time dashboard updates
         */
        initRealTimeDashboard: function() {
            var self = this;
            
            // Setup WebSocket connection if available
            if (typeof WebSocket !== 'undefined' && ufub_admin.websocket_url) {
                this.initWebSocketConnection();
            }
            
            // Fallback to polling
            this.setupPollingUpdates();
            
            // Initialize live statistics
            this.initLiveStatistics();
        },
        
        /**
         * Initialize WebSocket connection for real-time updates
         */
        initWebSocketConnection: function() {
            var self = this;
            
            try {
                this.websocket = new WebSocket(ufub_admin.websocket_url);
                
                this.websocket.onopen = function() {
                    self.logWithBrand('🔗 Real-time connection established');
                    self.updateConnectionStatus('connected');
                };
                
                this.websocket.onmessage = function(event) {
                    try {
                        var data = JSON.parse(event.data);
                        self.handleRealTimeUpdate(data);
                    } catch (e) {
                        console.warn('UFUB Admin: Invalid WebSocket message', e);
                    }
                };
                
                this.websocket.onclose = function() {
                    self.logWithBrand('🔌 Real-time connection lost, falling back to polling');
                    self.updateConnectionStatus('disconnected');
                    // Attempt to reconnect after 5 seconds
                    setTimeout(function() {
                        self.initWebSocketConnection();
                    }, 5000);
                };
                
                this.websocket.onerror = function(error) {
                    console.warn('UFUB Admin: WebSocket error', error);
                    self.updateConnectionStatus('error');
                };
                
            } catch (e) {
                console.warn('UFUB Admin: WebSocket not supported', e);
                this.setupPollingUpdates();
            }
        },
        
        /**
         * Handle real-time updates from WebSocket
         */
        handleRealTimeUpdate: function(data) {
            switch (data.type) {
                case 'dashboard_stats':
                    this.updateDashboardStats(data.stats);
                    break;
                case 'new_event':
                    this.handleNewEvent(data.event);
                    break;
                case 'connection_status':
                    this.updateApiStatus(data.status);
                    break;
                case 'performance_metrics':
                    this.updatePerformanceMetrics(data.metrics);
                    break;
                default:
                    this.logWithBrand('📡 Unknown real-time update type', data.type);
            }
        },
        
        /**
         * Setup polling updates as fallback
         */
        setupPollingUpdates: function() {
            var self = this;
            
            // Poll every 30 seconds for dashboard updates
            setInterval(function() {
                if (self.state.realTimeUpdates && !self.websocket) {
                    self.loadDashboardData(true); // Silent update
                }
            }, 30000);
        },
        
        /**
         * Initialize enhanced UI components
         */
        initEnhancedUI: function() {
            // Initialize loading animations
            this.initLoadingAnimations();
            
            // Initialize interactive elements
            this.initInteractiveElements();
            
            // Initialize responsive behavior
            this.initResponsiveBehavior();
            
            // Initialize accessibility features
            this.initAccessibilityFeatures();
        },
        
        /**
         * Initialize Ghostly Labs loading animations
         */
        initLoadingAnimations: function() {
            // Create custom loading overlay
            if (!document.getElementById('ufub-loading-overlay')) {
                var overlay = document.createElement('div');
                overlay.id = 'ufub-loading-overlay';
                overlay.className = 'ufub-loading-overlay';
                overlay.innerHTML = `
                    <div class="ufub-loading-content">
                        <div class="ufub-loading-spinner"></div>
                        <div class="ufub-loading-text">
                            <div class="ufub-loading-brand">🔮 Ghostly Labs</div>
                            <div class="ufub-loading-message">Processing...</div>
                        </div>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
        },
        
        /**
         * Show enhanced loading state
         */
        showLoading: function(message) {
            this.state.isLoading = true;
            var overlay = document.getElementById('ufub-loading-overlay');
            var messageEl = overlay.querySelector('.ufub-loading-message');
            
            if (messageEl) {
                messageEl.textContent = message || 'Processing...';
            }
            
            overlay.style.display = 'flex';
            
            // Add CSS if not already added
            if (!document.getElementById('ufub-loading-styles')) {
                var style = document.createElement('style');
                style.id = 'ufub-loading-styles';
                style.textContent = `
                    .ufub-loading-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(10, 10, 10, 0.9);
                        display: none;
                        align-items: center;
                        justify-content: center;
                        z-index: 99999;
                        backdrop-filter: blur(10px);
                    }
                    
                    .ufub-loading-content {
                        text-align: center;
                        color: white;
                    }
                    
                    .ufub-loading-spinner {
                        width: 60px;
                        height: 60px;
                        border: 4px solid rgba(58, 178, 74, 0.3);
                        border-top: 4px solid #3ab24a;
                        border-radius: 50%;
                        animation: ufub-spin 1s linear infinite;
                        margin: 0 auto 20px;
                    }
                    
                    .ufub-loading-brand {
                        font-size: 1.2em;
                        font-weight: bold;
                        color: #3ab24a;
                        margin-bottom: 10px;
                    }
                    
                    .ufub-loading-message {
                        font-size: 1em;
                        opacity: 0.8;
                    }
                    
                    @keyframes ufub-spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function() {
            this.state.isLoading = false;
            var overlay = document.getElementById('ufub-loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        },
        
        /**
         * Enhanced event binding with improved error handling
         */
        bindEvents: function() {
            var self = this;
            
            // Enhanced API connection testing
            $(document).on('click', '#test-api-connection', function(e) {
                self.testApiConnection.call(this, e);
            });
            
            // Enhanced settings saving
            $(document).on('click', '#save-settings', function(e) {
                self.saveSettings.call(this, e);
            });
            
            // Enhanced test buttons with better feedback
            $(document).on('click', '.ufub-test-btn', function(e) {
                self.handleTestButton.call(this, e);
            });
            
            // Enhanced webhook URL copying
            $(document).on('click', '.copy-webhook-url', function(e) {
                self.copyWebhookUrl.call(this, e);
            });
            
            // Enhanced form submission with validation
            $(document).on('submit', '#ufub-settings-form', function(e) {
                self.handleSettingsForm.call(this, e);
            });
            
            // Real-time toggle
            $(document).on('change', '#toggle-real-time', function() {
                self.toggleRealTimeUpdates();
            });
            
            // Debug mode toggle
            $(document).on('click', '#toggle-debug-mode', function() {
                self.toggleDebugMode();
            });
            
            // Dashboard refresh button
            $(document).on('click', '#refresh-dashboard', function() {
                self.refreshDashboard();
            });
        },
        
        /**
         * Enhanced API connection testing with detailed feedback
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#api-test-result');
            var apiKey = $('#ufub_api_key').val();
            var startTime = performance.now();
            
            if (!apiKey) {
                this.showNotice('Please enter an API key first.', 'error');
                return;
            }
            
            // Enhanced loading state
            $button.prop('disabled', true);
            $button.html('<span class="ufub-loading-icon">⏳</span> Testing Connection...');
            $result.html('');
            
            this.showLoading('Testing API Connection...');
            
            var self = this;
            
            $.ajax({
                url: ufub_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ufub_test_connection',
                    api_key: apiKey,
                    nonce: ufub_admin.nonce
                },
                timeout: 15000, // 15 second timeout
                success: function(response) {
                    var responseTime = performance.now() - startTime;
                    self.performance.apiResponseTimes.push(responseTime);
                    
                    if (response.success) {
                        var message = '✅ ' + response.data.message;
                        if (response.data.responseTime) {
                            message += ' (' + Math.round(responseTime) + 'ms)';
                        }
                        
                        $result.html('<div class="success-state">' + message + '</div>');
                        self.updateConnectionStatus('connected');
                        self.showNotice('API connection successful!', 'success');
                        
                        // Update dashboard with fresh data
                        setTimeout(function() {
                            self.loadDashboardData();
                        }, 1000);
                        
                        self.logWithBrand('✅ API connection test successful', {
                            responseTime: Math.round(responseTime) + 'ms'
                        });
                    } else {
                        $result.html('<div class="error-state">❌ ' + response.data + '</div>');
                        self.updateConnectionStatus('error');
                        self.showNotice('API connection failed: ' + response.data, 'error');
                        
                        self.logWithBrand('❌ API connection test failed', {
                            error: response.data,
                            responseTime: Math.round(responseTime) + 'ms'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    var responseTime = performance.now() - startTime;
                    var errorMessage = 'Connection test failed';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Connection timeout - API may be slow or unavailable';
                    } else if (xhr.status) {
                        errorMessage = 'HTTP ' + xhr.status + ': ' + error;
                    }
                    
                    $result.html('<div class="error-state">❌ ' + errorMessage + '</div>');
                    self.updateConnectionStatus('error');
                    self.showNotice(errorMessage, 'error');
                    
                    self.logWithBrand('❌ API connection test error', {
                        error: errorMessage,
                        status: status,
                        responseTime: Math.round(responseTime) + 'ms'
                    });
                },
                complete: function() {
                    $button.prop('disabled', false).html('Test Connection');
                    self.hideLoading();
                }
            });
        },
        
        /**
         * Enhanced settings saving with validation and feedback
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $button.closest('form');
            
            // Enhanced validation
            if (!this.validateSettings($form)) {
                return;
            }
            
            var formData = $form.serialize();
            var startTime = performance.now();
            
            $button.prop('disabled', true);
            $button.html('<span class="ufub-loading-icon">💾</span> Saving...');
            
            this.showLoading('Saving Settings...');
            
            var self = this;
            
            $.ajax({
                url: ufub_admin.ajax_url,
                type: 'POST',
                data: formData + '&action=ufub_save_settings&nonce=' + ufub_admin.nonce,
                timeout: 10000,
                success: function(response) {
                    var responseTime = performance.now() - startTime;
                    self.performance.apiResponseTimes.push(responseTime);
                    
                    if (response.success) {
                        self.showNotice('✅ Settings saved successfully!', 'success');
                        
                        // Update dashboard to reflect changes
                        setTimeout(function() {
                            self.loadDashboardData();
                        }, 500);
                        
                        // Show success animation
                        $button.html('✅ Saved!');
                        setTimeout(function() {
                            $button.html('Save Settings');
                        }, 2000);
                        
                        self.logWithBrand('✅ Settings saved successfully', {
                            responseTime: Math.round(responseTime) + 'ms'
                        });
                    } else {
                        self.showNotice('❌ Error saving settings: ' + response.data, 'error');
                        self.logWithBrand('❌ Settings save failed', {
                            error: response.data,
                            responseTime: Math.round(responseTime) + 'ms'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = status === 'timeout' ? 'Save request timed out' : 'Network error occurred';
                    self.showNotice('❌ ' + errorMessage, 'error');
                    self.logWithBrand('❌ Settings save error', {
                        error: errorMessage,
                        status: status
                    });
                },
                complete: function() {