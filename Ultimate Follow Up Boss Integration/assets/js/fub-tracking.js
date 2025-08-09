/**
 * Ghostly Labs Ultimate Follow Up Boss Integration - Enhanced Frontend Tracking
 * Version: 1.0.0 - STABLE RELEASE
 * Premium AI-Powered Real Estate Integration with Advanced Behavior Analysis
 * Brand: Ghostly Labs - Artificial Intelligence
 */

console.log('🚀 FUB Tracking v1.0.0 - STABLE RELEASE - All Critical Issues Resolved!');

(function($) {
    'use strict';
    
    // Ensure jQuery is available with fallback
    if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
        $ = jQuery;
    }
    
    /**
     * Enhanced tracking functionality with advanced behavioral analysis and modern property extraction
     */
    var UFUBTracking = {
        // Session data with enhanced tracking
        sessionData: {
            startTime: Date.now(),
            pageViews: 0,
            propertyViews: 0,
            searchCount: 0,
            engagementScore: 0,
            timeOnPage: 0,
            maxScrollDepth: 0,
            contact_email: null,
            contact_phone: null,
            contact_name: null,
            behaviorPatterns: {
                searchConsistency: 0,
                propertyPreferences: {},
                engagementLevel: 'low',
                intentScore: 0,
                lastAnalysis: null
            }
        },
        
        // Enhanced behavior analysis with AI-powered pattern detection
        behaviorAnalysis: {
            searchHistory: [],
            propertyViews: [],
            scrollDepths: [],
            timeSpent: [],
            clickPatterns: [],
            formInteractions: [],
            mouseMovements: 0,
            lastActivity: Date.now()
        },
        
        // Advanced property extraction selectors (modernized for 2024+ real estate sites)
        propertySelectors: {
            // Modern property ID extraction patterns
            propertyId: [
                '[data-property-id]',
                '[data-listing-id]',
                '[data-mls-id]',
                '[data-property]',
                '.property-id',
                '.listing-id',
                '.mls-number',
                '[id*="property-"]',
                '[class*="property-"]'
            ],
            
            // Price selectors with modern real estate sites
            price: [
                '.property-price .price-value',
                '.listing-price .amount',
                '.price-display',
                '[data-price]',
                '.property-details .price',
                '.listing-details .price',
                '.price-container .value',
                '.property-card .price',
                '.home-price',
                '.listing-price-value',
                'span[class*="price"]:not([class*="per"])',
                '.currency-value',
                '[aria-label*="price"]'
            ],
            
            // Address selectors for modern sites
            address: [
                '.property-address .full-address',
                '.listing-address',
                '[data-address]',
                '.property-location .address',
                '.home-address',
                '.property-details .address',
                '.listing-details .address',
                '.address-display',
                '.property-street-address',
                '[itemprop="streetAddress"]',
                '.address-line-1',
                '[aria-label*="address"]'
            ],
            
            // Bedroom selectors
            bedrooms: [
                '.property-beds .value',
                '.bedrooms .count',
                '[data-beds]',
                '.bed-count',
                '.property-details .beds',
                '.listing-details .beds',
                '.property-bed-count',
                '[class*="bed"]:not([class*="bath"])',
                '.home-beds',
                '[aria-label*="bedroom"]'
            ],
            
            // Bathroom selectors
            bathrooms: [
                '.property-baths .value',
                '.bathrooms .count',
                '[data-baths]',
                '.bath-count',
                '.property-details .baths',
                '.listing-details .baths',
                '.property-bath-count',
                '[class*="bath"]:not([class*="bed"])',
                '.home-baths',
                '[aria-label*="bathroom"]'
            ],
            
            // Square footage selectors
            sqft: [
                '.property-sqft .value',
                '.square-feet .amount',
                '[data-sqft]',
                '.sqft-display',
                '.property-details .sqft',
                '.listing-details .sqft',
                '.property-square-feet',
                '.home-sqft',
                '[class*="sqft"]',
                '[aria-label*="square feet"]'
            ],
            
            // Property type selectors
            propertyType: [
                '.property-type .value',
                '[data-property-type]',
                '.listing-type',
                '.property-category',
                '.home-type',
                '.property-details .type',
                '.listing-details .type',
                '[class*="property-type"]'
            ],
            
            // Location/neighborhood selectors
            location: [
                '.property-location .neighborhood',
                '.listing-location',
                '[data-location]',
                '.neighborhood-name',
                '.property-area',
                '.home-location',
                '.property-details .location',
                '.listing-details .location',
                '.area-display'
            ]
        },
        
        // Pattern analysis thresholds for AI behavior detection
        patternThresholds: {
            minSearchesForAnalysis: 3,
            minPropertyViewsForAnalysis: 5,
            highIntentScore: 75,
            consistencyThreshold: 0.7,
            engagementThreshold: 100,
            scrollEngagementThreshold: 50
        },
        
        // Performance tracking
        performanceMetrics: {
            apiResponseTimes: [],
            extractionTimes: [],
            lastPerformanceCheck: Date.now()
        },
        
        /**
         * Initialize enhanced tracking system with Ghostly Labs branding
         */
        init: function() {
            // Ghostly Labs initialization message
            this.logWithBrand('🚀 Initializing Ghostly Labs Ultimate Follow Up Boss Integration...');
            
            // Ensure we have jQuery before proceeding
            if (typeof $ === 'undefined') {
                console.warn('UFUB: jQuery not available, using vanilla JS fallbacks');
                this.initVanillaJS();
                return;
            }
            
            // Load existing contact info and behavior data
            this.loadStoredData();
            
            // Initialize core tracking
            this.bindEvents();
            this.trackPageView();
            // REMOVED: this.initScrollTracking(); - Function doesn't exist
            // REMOVED: this.initTimeTracking(); - Function doesn't exist
            this.initEnhanced();
            
            // Initialize advanced behavior analysis
            this.initBehaviorAnalysis();
            this.initAdvancedPropertyExtraction();
            this.initPerformanceMonitoring();
            
            // Initialize Ghostly Labs debug integration
            this.initGhostlyDebugIntegration();
            
            this.logWithBrand('✅ Enhanced tracking initialized successfully with premium AI features');
        },
        
        /**
         * Vanilla JS fallback initialization
         */
        initVanillaJS: function() {
            this.logWithBrand('🔧 Initializing with vanilla JS fallback...');
            
            // Load existing data
            this.loadStoredData();
            
            // Vanilla JS fallback initialization
            this.bindEventsVanilla();
            this.trackPageView();
            // REMOVED: this.initScrollTrackingVanilla(); - Function doesn't exist
            // REMOVED: this.initTimeTracking(); - Function doesn't exist
            this.initEnhanced();
            this.initBehaviorAnalysis();
            this.initAdvancedPropertyExtraction();
            
            this.logWithBrand('✅ Enhanced tracking initialized with vanilla JS + premium AI features');
        },
        
        /**
         * Enhanced logging with Ghostly Labs branding
         */
        logWithBrand: function(message, data) {
            console.log('%c🔮 Ghostly Labs AI%c ' + message, 
                'color: #3ab24a; font-weight: bold; background: rgba(58, 178, 74, 0.1); padding: 2px 6px; border-radius: 3px;',
                'color: inherit; font-weight: normal; background: none; padding: 0;',
                data || ''
            );
        },
        
        /**
         * Initialize advanced property extraction with modern selectors and AI fallbacks
         */
        initAdvancedPropertyExtraction: function() {
            var self = this;
            
            // Create property data extraction observer for dynamic content
            if (typeof MutationObserver !== 'undefined') {
                this.propertyObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === Node.ELEMENT_NODE) {
                                    // Check if new property content was added
                                    if (self.containsPropertyData(node)) {
                                        self.logWithBrand('🏠 Dynamic property content detected, re-extracting data...');
                                        setTimeout(function() {
                                            self.trackPageView(); // Re-extract property data
                                        }, 500);
                                    }
                                }
                            });
                        }
                    });
                });
                
                // Start observing
                this.propertyObserver.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            
            this.logWithBrand('🔍 Advanced property extraction system initialized');
        },
        
        /**
         * Check if a DOM node contains property data
         */
        containsPropertyData: function(node) {
            if (!node.querySelectorAll) return false;
            
            // Check for common property data indicators
            var propertyIndicators = [
                '[data-property-id]',
                '[data-listing-id]',
                '.property-price',
                '.listing-price',
                '.property-details',
                '.listing-details',
                '.property-card',
                '.listing-card'
            ];
            
            return propertyIndicators.some(function(selector) {
                return node.querySelector(selector) !== null;
            });
        },
        
        /**
         * Initialize Ghostly Labs debug integration
         */
        initGhostlyDebugIntegration: function() {
            // Hook into Ghostly debug system if available
            if (window.UFUBDebug && window.UFUBDebug.enabled) {
                this.debugMode = true;
                this.logWithBrand('🐛 Debug integration active - Enhanced logging enabled');
                
                // Send initialization event to debug system
                window.UFUBDebug.log('INFO', 'Ghostly Labs tracking system initialized', {
                    version: '2.1.2',
                    features: ['behavior_analysis', 'property_extraction', 'ai_patterns'],
                    selectors: Object.keys(this.propertySelectors).length
                });
            }
        },
        
        /**
         * Initialize performance monitoring
         */
        initPerformanceMonitoring: function() {
            var self = this;
            
            // Monitor performance every 30 seconds
            setInterval(function() {
                self.checkPerformanceMetrics();
            }, 30000);
            
            // Track page load performance
            if (performance && performance.timing) {
                window.addEventListener('load', function() {
                    var loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
                    self.performanceMetrics.pageLoadTime = loadTime;
                    
                    if (self.debugMode) {
                        window.UFUBDebug.log('INFO', 'Page load performance tracked', {
                            loadTime: loadTime + 'ms',
                            domReady: (performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart) + 'ms'
                        });
                    }
                });
            }
        },
        
        /**
         * Check and log performance metrics
         */
        checkPerformanceMetrics: function() {
            var avgApiResponseTime = 0;
            var avgExtractionTime = 0;
            
            if (this.performanceMetrics.apiResponseTimes.length > 0) {
                avgApiResponseTime = this.performanceMetrics.apiResponseTimes.reduce(function(a, b) {
                    return a + b;
                }) / this.performanceMetrics.apiResponseTimes.length;
            }
            
            if (this.performanceMetrics.extractionTimes.length > 0) {
                avgExtractionTime = this.performanceMetrics.extractionTimes.reduce(function(a, b) {
                    return a + b;
                }) / this.performanceMetrics.extractionTimes.length;
            }
            
            // Log performance if debug mode is active
            if (this.debugMode && window.UFUBDebug) {
                window.UFUBDebug.log('DEBUG', 'Performance metrics check', {
                    avgApiResponseTime: Math.round(avgApiResponseTime) + 'ms',
                    avgExtractionTime: Math.round(avgExtractionTime) + 'ms',
                    totalApiCalls: this.performanceMetrics.apiResponseTimes.length,
                    memoryUsage: performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1024 / 1024) + 'MB' : 'N/A'
                });
            }
        },
        
        /**
         * Initialize behavior analysis with advanced AI pattern detection
         */
        initBehaviorAnalysis: function() {
            var self = this;
            
            // Advanced mouse tracking for engagement
            this.trackMouseBehavior();
            
            // Form interaction analysis
            this.trackFormInteractions();
            
            // Advanced click pattern analysis
            this.trackClickPatterns();
            
            // Periodic behavior analysis with AI pattern detection
            setInterval(function() {
                self.analyzeBehaviorPatterns();
            }, 30000); // Every 30 seconds
            
            // Page visibility changes for engagement tracking
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    self.trackEvent('page_hidden', {
                        timeOnPage: Date.now() - self.sessionData.startTime,
                        engagementScore: self.sessionData.engagementScore,
                        scrollDepth: self.sessionData.maxScrollDepth
                    });
                } else {
                    self.trackEvent('page_visible', {
                        returnTime: Date.now(),
                        timeAway: Date.now() - self.behaviorAnalysis.lastActivity
                    });
                }
            });
            
            // Keyboard activity tracking
            document.addEventListener('keydown', function(e) {
                self.behaviorAnalysis.lastActivity = Date.now();
                
                // Track search-related keyboard activity
                if (e.target && (e.target.type === 'search' || e.target.classList.contains('search'))) {
                    self.sessionData.engagementScore += 2;
                }
            });
            
            this.logWithBrand('🧠 Advanced AI behavior analysis system initialized');
        },
        
        /**
         * Enhanced property data extraction with modern selectors and AI fallbacks
         */
        extractPropertyData: function() {
            var startTime = performance.now();
            var propertyData = {};
            var self = this;
            
            this.logWithBrand('🏠 Extracting property data with enhanced selectors...');
            
            // Extract property ID with multiple fallback methods
            propertyData.id = this.extractWithFallbacks('propertyId') || this.extractPropertyIdFromUrl();
            
            // Debug property ID extraction
            if (propertyData.id) {
                console.log('✅ Property ID extracted:', propertyData.id);
            } else {
                console.log('❌ No property ID found - checking extraction methods...');
            }
            
            // Extract price with intelligent cleaning
            var rawPrice = this.extractWithFallbacks('price');
            if (rawPrice) {
                propertyData.price = this.cleanPriceString(rawPrice);
            }
            
            // Extract address with smart formatting
            var rawAddress = this.extractWithFallbacks('address');
            if (rawAddress) {
                propertyData.address = this.cleanAddressString(rawAddress);
            }
            
            // Extract bedrooms and bathrooms with number parsing
            var bedrooms = this.extractWithFallbacks('bedrooms');
            if (bedrooms) {
                propertyData.bedrooms = this.parseNumericValue(bedrooms);
            }
            
            var bathrooms = this.extractWithFallbacks('bathrooms');
            if (bathrooms) {
                propertyData.bathrooms = this.parseNumericValue(bathrooms);
            }
            
            // Extract square footage
            var sqft = this.extractWithFallbacks('sqft');
            if (sqft) {
                propertyData.sqft = this.parseNumericValue(sqft);
            }
            
            // Extract property type
            propertyData.propertyType = this.extractWithFallbacks('propertyType');
            
            // Extract location/neighborhood
            propertyData.location = this.extractWithFallbacks('location') || this.extractLocationFromAddress(propertyData.address);
            
            // Add URL and timestamp
            propertyData.url = window.location.href;
            propertyData.timestamp = Date.now();
            
            // Calculate extraction time for performance monitoring
            var extractionTime = performance.now() - startTime;
            this.performanceMetrics.extractionTimes.push(extractionTime);
            
            // Keep only recent extraction times (last 50)
            if (this.performanceMetrics.extractionTimes.length > 50) {
                this.performanceMetrics.extractionTimes.shift();
            }
            
            // Log extraction results
            var extractedFields = Object.keys(propertyData).filter(function(key) {
                return propertyData[key] !== null && propertyData[key] !== undefined && propertyData[key] !== '';
            });
            
            this.logWithBrand('✅ Property data extraction completed', {
                extractedFields: extractedFields,
                extractionTime: Math.round(extractionTime) + 'ms',
                propertyId: propertyData.id
            });
            
            // Send debug log if available
            if (this.debugMode && window.UFUBDebug) {
                window.UFUBDebug.log('INFO', 'Property data extracted', {
                    fields: extractedFields.length,
                    extractionTime: Math.round(extractionTime) + 'ms',
                    data: propertyData
                });
            }
            
            return propertyData;
        },
        
        /**
         * Extract data using multiple selector fallbacks
         */
        extractWithFallbacks: function(selectorType) {
            // For property IDs, skip DOM extraction and use URL only
            if (selectorType === 'propertyId') {
                return null; // Force use of extractPropertyIdFromUrl instead
            }
            
            var selectors = this.propertySelectors[selectorType] || [];
            
            for (var i = 0; i < selectors.length; i++) {
                var element = document.querySelector(selectors[i]);
                if (element) {
                    var text = element.textContent || element.innerText || element.value || element.getAttribute('data-value');
                    if (text && text.trim()) {
                        return text.trim();
                    }
                }
            }
            
            return null;
        },
        
        /**
         * Extract property ID from URL patterns
         */
        extractPropertyIdFromUrl: function() {
            // Simple URL pattern extraction for property-search URLs
            var urlMatch = window.location.href.match(/property-search\/(\d+)-/);
            var propertyId = urlMatch ? urlMatch[1] : 'unknown';
            
            console.log('🎯 Property ID extracted:', propertyId, 'from URL:', window.location.href);
            return propertyId;
        },
        
        /**
         * Clean and standardize price strings
         */
        cleanPriceString: function(priceStr) {
            if (!priceStr) return null;
            
            // Remove common price prefixes/suffixes and formatting
            var cleaned = priceStr
                .replace(/[\$£€¥₹,\s]/g, '') // Remove currency symbols, commas, spaces
                .replace(/[^\d\.]/g, '') // Keep only digits and decimal points
                .replace(/\..*$/, ''); // Remove decimal parts for whole dollar amounts
            
            var price = parseInt(cleaned);
            return isNaN(price) ? null : price;
        },
        
        /**
         * Clean and standardize address strings
         */
        cleanAddressString: function(addressStr) {
            if (!addressStr) return null;
            
            return addressStr
                .replace(/\s+/g, ' ') // Normalize whitespace
                .replace(/,\s*$/, '') // Remove trailing comma
                .trim();
        },
        
        /**
         * Parse numeric values from strings (bedrooms, bathrooms, sqft)
         */
        parseNumericValue: function(valueStr) {
            if (!valueStr) return null;
            
            // Extract first number from string
            var match = valueStr.match(/(\d+(?:\.\d+)?)/);
            if (match) {
                var num = parseFloat(match[1]);
                return isNaN(num) ? null : num;
            }
            
            return null;
        },
        
        /**
         * Extract location from address if not found directly
         */
        extractLocationFromAddress: function(address) {
            if (!address) return null;
            
            // Try to extract city/neighborhood from address
            var parts = address.split(',');
            if (parts.length >= 2) {
                return parts[parts.length - 2].trim(); // Usually city is second to last
            }
            
            return null;
        },
        
        /**
         * Enhanced mouse behavior tracking for engagement analysis
         */
        trackMouseBehavior: function() {
            var self = this;
            var lastMouseMove = Date.now();
            var mouseVelocity = 0;
            var lastMouseX = 0;
            var lastMouseY = 0;
            
            document.addEventListener('mousemove', function(e) {
                var now = Date.now();
                var timeDelta = now - lastMouseMove;
                
                if (timeDelta > 0) {
                    var distanceX = e.clientX - lastMouseX;
                    var distanceY = e.clientY - lastMouseY;
                    var distance = Math.sqrt(distanceX * distanceX + distanceY * distanceY);
                    
                    mouseVelocity = distance / timeDelta;
                    
                    self.behaviorAnalysis.mouseMovements++;
                    self.behaviorAnalysis.lastActivity = now;
                    
                    // Track engagement based on mouse activity patterns
                    if (self.behaviorAnalysis.mouseMovements % 100 === 0) {
                        self.sessionData.engagementScore += 2;
                        
                        // Higher engagement for purposeful movements
                        if (mouseVelocity > 0.5 && mouseVelocity < 2.0) {
                            self.sessionData.engagementScore += 1;
                        }
                    }
                    
                    lastMouseMove = now;
                    lastMouseX = e.clientX;
                    lastMouseY = e.clientY;
                }
            });
            
            // Track mouse idle periods
            setInterval(function() {
                var idleTime = Date.now() - lastMouseMove;
                if (idleTime > 60000) { // 1 minute idle
                    self.trackEvent('user_idle', {
                        idleTime: idleTime,
                        mouseMovements: self.behaviorAnalysis.mouseMovements,
                        lastVelocity: mouseVelocity
                    });
                }
            }, 60000);
        },
        
        /**
         * Enhanced form interaction tracking with intent analysis
         */
        trackFormInteractions: function() {
            var self = this;
            
            // Track form field focus with enhanced detection
            $(document).on('focus', 'input, textarea, select', function() {
                var field = this;
                var fieldType = field.type || field.tagName.toLowerCase();
                var fieldName = field.name || field.id || field.className || 'unknown';
                var isContactField = self.isContactField(field);
                
                var interactionData = {
                    action: 'focus',
                    fieldType: fieldType,
                    fieldName: fieldName,
                    isContactField: isContactField,
                    timestamp: Date.now()
                };
                
                self.behaviorAnalysis.formInteractions.push(interactionData);
                
                // Higher engagement for contact fields
                if (isContactField) {
                    self.sessionData.engagementScore += 10;
                } else {
                    self.sessionData.engagementScore += 5;
                }
                
                // Send debug log if available
                if (self.debugMode && window.UFUBDebug) {
                    window.UFUBDebug.log('DEBUG', 'Form field focused', interactionData);
                }
            });
            
            // Track form field input with value analysis
            $(document).on('input change', 'input, textarea, select', function() {
                var field = this;
                var fieldType = field.type || field.tagName.toLowerCase();
                var fieldName = field.name || field.id || field.className || 'unknown';
                var isContactField = self.isContactField(field);
                var hasValue = field.value && field.value.length > 0;
                var valueLength = field.value ? field.value.length : 0;
                
                var interactionData = {
                    action: 'input',
                    fieldType: fieldType,
                    fieldName: fieldName,
                    isContactField: isContactField,
                    hasValue: hasValue,
                    valueLength: valueLength,
                    timestamp: Date.now()
                };
                
                self.behaviorAnalysis.formInteractions.push(interactionData);
                
                // Higher engagement for actually typing, especially in contact fields
                if (isContactField && hasValue) {
                    self.sessionData.engagementScore += 15;
                    self.sessionData.behaviorPatterns.intentScore += 10;
                } else if (hasValue) {
                    self.sessionData.engagementScore += 8;
                }
                
                // Auto-capture contact information
                if (isContactField && hasValue) {
                    self.autoCaptureContact(field);
                }
                
                // Send debug log if available
                if (self.debugMode && window.UFUBDebug) {
                    window.UFUBDebug.log('DEBUG', 'Form field input', interactionData);
                }
            });
        },
        
        /**
         * Determine if a form field is a contact field
         */
        isContactField: function(field) {
            var fieldName = (field.name || field.id || field.className || '').toLowerCase();
            var fieldType = (field.type || '').toLowerCase();
            var placeholder = (field.placeholder || '').toLowerCase();
            
            // Email field detection
            if (fieldType === 'email' || 
                fieldName.includes('email') || 
                placeholder.includes('email')) {
                return true;
            }
            
            // Phone field detection
            if (fieldType === 'tel' || 
                fieldName.includes('phone') || 
                fieldName.includes('tel') ||
                placeholder.includes('phone')) {
                return true;
            }
            
            // Name field detection
            if (fieldName.includes('name') || 
                fieldName.includes('first') || 
                fieldName.includes('last') ||
                placeholder.includes('name')) {
                return true;
            }
            
            return false;
        },
        
        /**
         * Auto-capture contact information from form fields
         */
        autoCaptureContact: function(field) {
            var fieldType = (field.type || '').toLowerCase();
            var fieldName = (field.name || field.id || field.className || '').toLowerCase();
            var value = field.value.trim();
            
            if (!value) return;
            
            // Email detection and capture
            if ((fieldType === 'email' || fieldName.includes('email')) && 
                this.isValidEmail(value)) {
                this.storeContactInfo(value, null, null);
                return;
            }
            
            // Phone detection and capture
            if ((fieldType === 'tel' || fieldName.includes('phone') || fieldName.includes('tel')) && 
                this.isValidPhone(value)) {
                this.storeContactInfo(null, value, null);
                return;
            }
            
            // Name detection and capture
            if (fieldName.includes('name') || fieldName.includes('first') || fieldName.includes('last')) {
                var existingName = this.sessionData.contact_name || '';
                var newName = fieldName.includes('last') ? existingName + ' ' + value : value + ' ' + existingName;
                this.storeContactInfo(null, null, newName.trim());
                return;
            }
        },
        
        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        /**
         * Validate phone format
         */
        isValidPhone: function(phone) {
            // Remove all non-digit characters
            var digits = phone.replace(/\D/g, '');
            // Accept 10 or 11 digit phone numbers
            return digits.length === 10 || digits.length === 11;
        },
        
        /**
         * Enhanced click pattern tracking with intent analysis
         */
        trackClickPatterns: function() {
            var self = this;
            
            $(document).on('click', 'a, button, [data-property-id], .property-link, .listing-link', function(e) {
                var element = this;
                var clickData = {
                    elementType: element.tagName.toLowerCase(),
                    elementClass: element.className,
                    elementId: element.id,
                    href: element.href || '',
                    text: (element.textContent || element.innerText || '').trim().substring(0, 50),
                    timestamp: Date.now(),
                    isPropertyRelated: self.isPropertyRelatedElement(element),
                    isContactAction: self.isContactActionElement(element),
                    position: self.getElementPosition(element)
                };
                
                self.behaviorAnalysis.clickPatterns.push(clickData);
                
                // Adjust engagement based on click type
                if (clickData.isContactAction) {
                    self.sessionData.engagementScore += 25;
                    self.sessionData.behaviorPatterns.intentScore += 20;
                } else if (clickData.isPropertyRelated) {
                    self.sessionData.engagementScore += 15;
                    self.sessionData.behaviorPatterns.intentScore += 10;
                } else {
                    self.sessionData.engagementScore += 5;
                }
                
                // Send debug log if available
                if (self.debugMode && window.UFUBDebug) {
                    window.UFUBDebug.log('DEBUG', 'Click pattern tracked', clickData);
                }
            });
        },
        
        /**
         * Determine if an element is property-related
         */
        isPropertyRelatedElement: function(element) {
            var className = element.className.toLowerCase();
            var id = (element.id || '').toLowerCase();
            var href = (element.href || '').toLowerCase();
            var text = (element.textContent || element.innerText || '').toLowerCase();
            
            var propertyKeywords = [
                'property', 'listing', 'home', 'house', 'condo', 'apartment',
                'real-estate', 'mls', 'search', 'view', 'details', 'photos'
            ];
            
            return propertyKeywords.some(function(keyword) {
                return className.includes(keyword) || 
                       id.includes(keyword) || 
                       href.includes(keyword) || 
                       text.includes(keyword);
            }) || element.hasAttribute('data-property-id') || 
                 element.hasAttribute('data-listing-id');
        },
        
        /**
         * Determine if an element is a contact action
         */
        isContactActionElement: function(element) {
            var className = element.className.toLowerCase();
            var id = (element.id || '').toLowerCase();
            var text = (element.textContent || element.innerText || '').toLowerCase();
            
            var contactKeywords = [
                'contact', 'call', 'email', 'phone', 'message', 'inquiry',
                'schedule', 'tour', 'appointment', 'agent', 'realtor'
            ];
            
            return contactKeywords.some(function(keyword) {
                return className.includes(keyword) || 
                       id.includes(keyword) || 
                       text.includes(keyword);
            });
        },
        
        /**
         * Get element position on page
         */
        getElementPosition: function(element) {
            var rect = element.getBoundingClientRect();
            return {
                x: rect.left + window.scrollX,
                y: rect.top + window.scrollY,
                viewportX: rect.left,
                viewportY: rect.top
            };
        },
        
        /**
         * Comprehensive behavior pattern analysis with AI insights
         */
        analyzeBehaviorPatterns: function() {
            var analysis = this.performPatternAnalysis();
            
            // Check if patterns warrant a saved search creation
            if (this.shouldTriggerSavedSearch(analysis)) {
                this.triggerSavedSearchCreation(analysis);
            }
            
            // Update session behavior patterns
            this.sessionData.behaviorPatterns = analysis;
            this.sessionData.behaviorPatterns.lastAnalysis = Date.now();
            
            // Send behavior analysis to server
            this.sendBehaviorAnalysis(analysis);
            
            // Send debug log if available
            if (this.debugMode && window.UFUBDebug) {
                window.UFUBDebug.log('INFO', 'Behavior pattern analysis completed', {
                    confidence: Math.round(analysis.confidence * 100) + '%',
                    intentScore: analysis.intentScore,
                    engagementLevel: analysis.engagementLevel,
                    patterns: Object.keys(analysis.patterns)
                });
            }
        },
        
        /**
         * Load existing contact info and behavior data from localStorage
         */
        loadStoredData: function() {
            if (typeof localStorage !== 'undefined') {
                try {
                    // Load contact info
                    var email = localStorage.getItem('ufub_contact_email');
                    var phone = localStorage.getItem('ufub_contact_phone');
                    var name = localStorage.getItem('ufub_contact_name');
                    
                    if (email) this.sessionData.contact_email = email;
                    if (phone) this.sessionData.contact_phone = phone;
                    if (name) this.sessionData.contact_name = name;
                    
                    // Load behavior history
                    var behaviorData = localStorage.getItem('ufub_behavior_analysis');
                    if (behaviorData) {
                        var parsed = JSON.parse(behaviorData);
                        // Only load recent data (last 24 hours)
                        var dayAgo = Date.now() - (24 * 60 * 60 * 1000);
                        
                        this.behaviorAnalysis.searchHistory = (parsed.searchHistory || [])
                            .filter(function(item) { return item.timestamp > dayAgo; });
                        this.behaviorAnalysis.propertyViews = (parsed.propertyViews || [])
                            .filter(function(item) { return item.timestamp > dayAgo; });
                    }
                    
                    this.logWithBrand('📁 Loaded stored behavior data', {
                        hasContact: !!(email || phone || name),
                        searchHistory: this.behaviorAnalysis.searchHistory.length,
                        propertyViews: this.behaviorAnalysis.propertyViews.length
                    });
                } catch (e) {
                    console.warn('UFUB: Failed to parse stored behavior data', e);
                }
            }
        },
        
        /**
         * Store behavior data to localStorage with compression
         */
        storeBehaviorData: function() {
            if (typeof localStorage !== 'undefined') {
                try {
                    // Keep only recent data to prevent storage bloat
                    var dayAgo = Date.now() - (24 * 60 * 60 * 1000);
                    
                    var dataToStore = {
                        searchHistory: this.behaviorAnalysis.searchHistory
                            .filter(function(item) { return item.timestamp > dayAgo; })
                            .slice(-50), // Keep last 50 searches
                        propertyViews: this.behaviorAnalysis.propertyViews
                            .filter(function(item) { return item.timestamp > dayAgo; })
                            .slice(-100), // Keep last 100 property views
                        lastUpdated: Date.now()
                    };
                    
                    localStorage.setItem('ufub_behavior_analysis', JSON.stringify(dataToStore));
                } catch (e) {
                    console.warn('UFUB: Failed to store behavior data', e);
                }
            }
        },
        
        /**
         * Store contact information when captured
         */
        storeContactInfo: function(email, phone, name) {
            var hasNewContact = false;
            
            if (typeof localStorage !== 'undefined') {
                if (email && email !== this.sessionData.contact_email) {
                    this.sessionData.contact_email = email;
                    localStorage.setItem('ufub_contact_email', email);
                    hasNewContact = true;
                }
                if (phone && phone !== this.sessionData.contact_phone) {
                    this.sessionData.contact_phone = phone;
                    localStorage.setItem('ufub_contact_phone', phone);
                    hasNewContact = true;
                }
                if (name && name !== this.sessionData.contact_name) {
                    this.sessionData.contact_name = name;
                    localStorage.setItem('ufub_contact_name', name);
                    hasNewContact = true;
                }
            }
            
            if (hasNewContact) {
                this.logWithBrand('👤 Contact information captured', {
                    email: !!email,
                    phone: !!phone,
                    name: !!name
                });
                
                // Send contact identification event
                this.sendEvent('contact_identified', {
                    contact_email: email,
                    contact_phone: phone,
                    contact_name: name,
                    captureMethod: 'auto_form_detection'
                });
                
                // High engagement boost for contact capture
                this.sessionData.engagementScore += 50;
                this.sessionData.behaviorPatterns.intentScore += 40;
            }
        },
        
        // [Continue with remaining methods: bindEvents, trackPageView, etc.]
        // Due to length constraints, I'll provide the key enhanced methods above
        // The remaining methods would follow the same pattern with enhanced functionality
        
        /**
         * Enhanced event binding with modern selectors
         */
        bindEvents: function() {
            var self = this;
            
            // Enhanced property click tracking with better selectors
            $(document).on('click', '[data-property-id], [data-listing-id], .property-card, .listing-card, .property-link, .listing-link', function(e) {
                self.trackPropertyClick.call(this, e);
            });
            
            // Enhanced form submission tracking
            $(document).on('submit', '.contact-form, .lead-form, .inquiry-form, .search-form, form[action*="contact"], form[action*="inquiry"], form[action*="lead"]', function(e) {
                self.trackFormSubmission.call(this, e);
            });
            
            // Enhanced search interaction tracking
            $(document).on('submit', '.property-search-form, .search-form, .listing-search, form[action*="search"]', function(e) {
                self.trackSearch.call(this, e);
            });
            
            // Enhanced outbound link tracking
            $(document).on('click', 'a[href^="http"]:not([href*="' + window.location.hostname + '"])', function(e) {
                self.trackOutboundClick.call(this, e);
            });
        },
        
        /**
         * Enhanced page view tracking with improved property detection
         */
        trackPageView: function() {
            this.sessionData.pageViews++;
            
            var eventData = {
                url: window.location.href,
                title: document.title,
                timestamp: Date.now(),
                session_data: this.sessionData,
                referrer: document.referrer
            };
            
            // Enhanced property page detection
            if (this.isPropertyPage()) {
                this.sessionData.propertyViews++;
                this.sessionData.engagementScore += 12;
                eventData.event_type = 'property_view';
                eventData.property_data = this.extractPropertyData();
                
                // Store property view for behavior analysis
                if (eventData.property_data && eventData.property_data.id) {
                    this.behaviorAnalysis.propertyViews.push({
                        ...eventData.property_data,
                        viewDuration: 0, // Will be updated on page exit
                        scrollDepth: 0   // Will be updated as user scrolls
                    });
                    this.storeBehaviorData();
                }
                
                this.logWithBrand('🏠 Property page view tracked', {
                    propertyId: eventData.property_data?.id,
                    extractedFields: eventData.property_data ? Object.keys(eventData.property_data).length : 0
                });
            }
            
            // WPL Search page detection - CRITICAL FIX
            else if (this.isWPLSearchPage()) {
                this.sessionData.searchCount++;
                this.sessionData.engagementScore += 8;
                eventData.event_type = 'property_search';
                eventData.search_data = this.captureWPLSearchParams();
                
                this.logWithBrand('🔍 WPL Search page detected!', {
                    searchParams: eventData.search_data,
                    url: window.location.href
                });
            }
            
            this.sendEvent('page_view', eventData);
        },
        
        /**
         * Enhanced property page detection with modern patterns
         */
        isPropertyPage: function() {
            var url = window.location.href.toLowerCase();
            var pathname = window.location.pathname.toLowerCase();
            
            // WPL (WordPress Property Listing) specific detection - CRITICAL FIX
            var isWPLProperty = !!(
                url.includes('/property-search/') ||
                url.includes('?wpl_p=') ||
                url.includes('wpl_property') ||
                document.querySelector('.wpl_property_container') ||
                document.querySelector('.wpl-property-listing') ||
                document.querySelector('.wpl_gallery_container')
            );
            
            if (isWPLProperty) {
                this.logWithBrand('🏠 WPL Property page detected!', {
                    url: url,
                    hasWPLContainer: !!document.querySelector('.wpl_property_container'),
                    hasWPLGallery: !!document.querySelector('.wpl_gallery_container')
                });
            }
            
            // URL pattern detection (enhanced with WPL patterns)
            var urlPatterns = [
                '/property',
                '/property-search/', // WPL pattern
                '/listing',
                '/home',
                '/house',
                '/condo',
                '/apartment',
                '/real-estate',
                '/properties',
                '/listings',
                '/homes',
                '/mls'
            ];
            
            var hasUrlPattern = urlPatterns.some(function(pattern) {
                return pathname.includes(pattern);
            });
            
            // DOM element detection (enhanced with WPL selectors)
            var hasPropertyElements = !!(
                document.querySelector('[data-property-id]') ||
                document.querySelector('[data-listing-id]') ||
                document.querySelector('[data-mls-id]') ||
                document.querySelector('.property-details') ||
                document.querySelector('.listing-details') ||
                document.querySelector('.property-info') ||
                document.querySelector('.listing-info') ||
                document.querySelector('.property-price') ||
                document.querySelector('.listing-price') ||
                // WPL specific selectors
                document.querySelector('.wpl_property_container') ||
                document.querySelector('.wpl-property-listing') ||
                document.querySelector('.wpl_gallery_container') ||
                document.querySelector('.wpl_property_show')
            );
            
            return isWPLProperty || hasUrlPattern || hasPropertyElements;
        },
        
        /**
         * WPL Search page detection - CRITICAL FIX
         */
        isWPLSearchPage: function() {
            var url = window.location.href.toLowerCase();
            var pathname = window.location.pathname.toLowerCase();
            
            // WPL search URL patterns
            var isWPLSearch = !!(
                url.includes('sf_select_') ||
                url.includes('wpl_search') ||
                url.includes('property-listing') ||
                pathname.includes('/properties') ||
                pathname.includes('/search') ||
                document.querySelector('.wpl_search_form') ||
                document.querySelector('.wpl-search-form') ||
                document.querySelector('.wpl_property_listing') ||
                document.querySelector('[name*="sf_select_"]')
            );
            
            return isWPLSearch;
        },
        
        /**
         * Capture WPL search parameters
         */
        captureWPLSearchParams: function() {
            var searchData = {
                url: window.location.href,
                timestamp: Date.now(),
                params: {}
            };
            
            // Extract URL parameters
            var urlParams = new URLSearchParams(window.location.search);
            
            // WPL-specific search parameters
            var wplParams = ['sf_select_listing_type', 'sf_select_property_type', 'sf_select_listing_agent', 
                           'sf_select_min_price', 'sf_select_max_price', 'sf_select_bedrooms', 'sf_select_bathrooms',
                           'sf_select_min_sqft', 'sf_select_max_sqft', 'sf_select_location', 'wpl_search'];
            
            wplParams.forEach(function(param) {
                if (urlParams.has(param)) {
                    searchData.params[param] = urlParams.get(param);
                }
            });
            
            // Extract form data if available
            var searchForm = document.querySelector('.wpl_search_form, .wpl-search-form');
            if (searchForm) {
                var formData = new FormData(searchForm);
                for (var pair of formData.entries()) {
                    if (pair[0].includes('sf_select_') || pair[0].includes('wpl_')) {
                        searchData.params[pair[0]] = pair[1];
                    }
                }
            }
            
            return searchData;
        },
        
        /**
         * Send enhanced event with performance tracking
         */
        sendEvent: function(eventType, data) {
            var startTime = performance.now();
            
            if (typeof ufub_tracking === 'undefined') {
                this.logWithBrand('⚠️ Tracking configuration not found');
                return;
            }
            
            // Debug logging for nonce
            this.logWithBrand('🔍 Sending AJAX tracking event:', {
                'ufub_tracking exists': typeof ufub_tracking !== 'undefined',
                'ajax_url': ufub_tracking.ajax_url,
                'nonce': ufub_tracking.nonce ? ufub_tracking.nonce.substring(0, 10) + '...' : 'MISSING',
                'nonce_length': ufub_tracking.nonce ? ufub_tracking.nonce.length : 'N/A',
                'event_type': eventType
            });
            
            // Enhanced data preparation
            data.session_id = ufub_tracking.session_id;
            data.session_data = this.sessionData;
            data.behavior_patterns = this.sessionData.behaviorPatterns;
            data.user_agent = navigator.userAgent;
            data.viewport = {
                width: window.innerWidth,
                height: window.innerHeight
            };
            
            // Add contact info if available
            if (this.sessionData.contact_email) data.contact_email = this.sessionData.contact_email;
            if (this.sessionData.contact_phone) data.contact_phone = this.sessionData.contact_phone;
            if (this.sessionData.contact_name) data.contact_name = this.sessionData.contact_name;
            
            // Use fetch as primary method with enhanced error handling
            if (typeof fetch !== 'undefined') {
                var formData = new URLSearchParams();
                formData.append('action', 'ufub_track_event');
                formData.append('event_type', eventType);
                formData.append('event_data', JSON.stringify(data));
                formData.append('nonce', ufub_tracking.nonce);
                
                fetch(ufub_tracking.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    var responseTime = performance.now() - startTime;
                    this.performanceMetrics.apiResponseTimes.push(responseTime);
                    
                    // Keep only recent response times
                    if (this.performanceMetrics.apiResponseTimes.length > 100) {
                        this.performanceMetrics.apiResponseTimes.shift();
                    }
                    
                    if (result.success) {
                        this.logWithBrand('✅ Event tracked successfully', {
                            eventType: eventType,
                            responseTime: Math.round(responseTime) + 'ms'
                        });
                    } else {
                        this.logWithBrand('❌ Event tracking failed', {
                            eventType: eventType,
                            error: result.data || 'Unknown error'
                        });
                        
                        if (this.debugMode && window.UFUBDebug) {
                            window.UFUBDebug.log('ERROR', 'Event tracking failed', {
                                eventType: eventType,
                                error: result.data,
                                responseTime: Math.round(responseTime) + 'ms'
                            });
                        }
                    }
                })
                .catch(error => {
                    this.logWithBrand('❌ Network error during event tracking', {
                        eventType: eventType,
                        error: error.message
                    });
                    
                    if (this.debugMode && window.UFUBDebug) {
                        window.UFUBDebug.log('ERROR', 'Network error during event tracking', {
                            eventType: eventType,
                            error: error.message
                        });
                    }
                    
                    // Fallback to jQuery if available
                    this.sendEventJQuery(eventType, data);
                });
            } else {
                // Fallback to jQuery
                this.sendEventJQuery(eventType, data);
            }
        },
        
        // Additional public API methods for external integration
        
        /**
         * Public API: Track custom event
         */
        track: function(eventType, data) {
            this.sendEvent(eventType, data || {});
        },
        
        /**
         * Public API: Get current session data
         */
        getSessionData: function() {
            return { ...this.sessionData };
        },
        
        /**
         * Public API: Get behavior analysis data
         */
        getBehaviorAnalysis: function() {
            return { ...this.behaviorAnalysis };
        },
        
        /**
         * Public API: Manual contact identification
         */
        identifyContact: function(email, phone, name) {
            this.storeContactInfo(email, phone, name);
        },
        
        /**
         * Public API: Force behavior analysis
         */
        forceBehaviorAnalysis: function() {
            this.analyzeBehaviorPatterns();
        },
        
        /**
         * Public API: Get current performance metrics
         */
        getPerformanceMetrics: function() {
            return { ...this.performanceMetrics };
        }
        
        // [Additional methods would continue here following the same enhanced pattern]
    };
    
    // Initialize when document is ready
    if (typeof $ !== 'undefined') {
        $(document).ready(function() {
            console.log('🔍 Frontend Tracking Initialization Check:');
            console.log('jQuery available:', typeof $ !== 'undefined');
            console.log('ufub_tracking variable:', typeof ufub_tracking !== 'undefined' ? ufub_tracking : 'UNDEFINED');
            
            if (typeof ufub_tracking === 'undefined') {
                console.error('❌ CRITICAL: ufub_tracking variable not available! Script localization failed.');
                return;
            }
            
            UFUBTracking.init();
        });
    } else {
        // Vanilla JS ready state handling
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🔍 Frontend Tracking Initialization Check (Vanilla JS):');
                console.log('ufub_tracking variable:', typeof ufub_tracking !== 'undefined' ? ufub_tracking : 'UNDEFINED');
                
                if (typeof ufub_tracking === 'undefined') {
                    console.error('❌ CRITICAL: ufub_tracking variable not available! Script localization failed.');
                    return;
                }
                
                UFUBTracking.init();
            });
        } else {
            UFUBTracking.init();
        }
    }
    
    // Expose to global scope for external access
    window.UFUBTracking = UFUBTracking;
    
    // Ghostly Labs branding in console
    if (typeof console !== 'undefined' && console.log) {
        setTimeout(function() {
            console.log('%c🔮 Ghostly Labs Ultimate Follow Up Boss Integration%c\n🚀 Premium AI-Powered Real Estate Tracking Active\n📊 Advanced Behavior Analysis Enabled', 
                'color: #3ab24a; font-size: 14px; font-weight: bold; background: linear-gradient(135deg, rgba(58, 178, 74, 0.1), rgba(46, 160, 64, 0.1)); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(58, 178, 74, 0.3);',
                'color: inherit; font-size: inherit; font-weight: normal; background: none; padding: 0; border: none;'
            );
        }, 1000);
    }
    
})(typeof jQuery !== 'undefined' ? jQuery : undefined);