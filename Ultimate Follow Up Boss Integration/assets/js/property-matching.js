/* Ghostly Labs Ultimate Follow Up Boss Integration - Property Matching
 * Version: 2.1.2
 * Premium AI-Powered Real Estate Integration - Property Matching Interface
 * Brand: Ghostly Labs - Artificial Intelligence
 */

(function($) {
    'use strict';
    
    /**
     * Advanced Property Matching System with AI-powered suggestions and WPL integration
     */
    var UFUBPropertyMatching = {
        
        // State management
        state: {
            isLoading: false,
            currentPage: 1,
            totalPages: 1,
            totalProperties: 0,
            selectedProperties: [],
            filters: {
                status: 'all',
                priceMin: '',
                priceMax: '',
                bedrooms: '',
                bathrooms: '',
                propertyType: 'all',
                location: ''
            },
            searchQuery: '',
            sortBy: 'date_desc',
            lastUpdate: 0
        },
        
        // Configuration
        config: {
            propertiesPerPage: 12,
            apiTimeout: 30000,
            refreshInterval: 60000,
            autoSync: true,
            matchingThreshold: 0.8
        },
        
        // Performance tracking
        performance: {
            loadTimes: [],
            apiCalls: 0,
            cacheHits: 0,
            lastOptimization: Date.now()
        },
        
        /**
         * Initialize property matching interface
         */
        init: function() {
            console.log('🏠 Ghostly Labs Property Matching: Initializing advanced matching system...');
            
            // Verify required dependencies
            if (!this.checkDependencies()) {
                console.error('❌ Property Matching: Missing required dependencies');
                return;
            }
            
            this.bindEvents();
            this.initSearchInterface();
            this.initFilters();
            this.initPropertyGrid();
            this.initPagination();
            this.initBulkActions();
            this.loadProperties();
            this.startAutoRefresh();
            
            console.log('🏠 Property Matching: Advanced system initialized successfully');
        },
        
        /**
         * Check required dependencies
         */
        checkDependencies: function() {
            var dependencies = [
                { name: 'jQuery', check: function() { return typeof $ !== 'undefined'; } },
                { name: 'UFUB Admin Variables', check: function() { return typeof ufub_admin !== 'undefined'; } },
                { name: 'Property Container', check: function() { return $('.ufub-property-grid').length > 0; } }
            ];
            
            var missing = [];
            dependencies.forEach(function(dep) {
                if (!dep.check()) {
                    missing.push(dep.name);
                }
            });
            
            if (missing.length > 0) {
                console.error('🏠 Missing dependencies:', missing);
                return false;
            }
            
            return true;
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Search functionality
            $(document).on('input', '.ufub-search-input', function() {
                clearTimeout(self.searchTimeout);
                self.searchTimeout = setTimeout(function() {
                    self.handleSearch();
                }, 500);
            });
            
            $(document).on('click', '.ufub-search-btn', function(e) {
                e.preventDefault();
                self.handleSearch();
            });
            
            // Filter changes
            $(document).on('change', '.ufub-filter-select, .ufub-filter-input', function() {
                self.handleFilterChange();
            });
            
            // Property actions
            $(document).on('click', '.ufub-property-btn.primary', function(e) {
                e.preventDefault();
                var propertyId = $(this).closest('.ufub-property-card').data('property-id');
                self.matchProperty(propertyId);
            });
            
            $(document).on('click', '.ufub-property-btn.secondary', function(e) {
                e.preventDefault();
                var propertyId = $(this).closest('.ufub-property-card').data('property-id');
                self.viewProperty(propertyId);
            });
            
            // Pagination
            $(document).on('click', '.ufub-page-btn', function(e) {
                e.preventDefault();
                if (!$(this).hasClass('active') && !$(this).is(':disabled')) {
                    var page = $(this).data('page');
                    self.loadPage(page);
                }
            });
            
            // Bulk actions
            $(document).on('change', '.ufub-property-checkbox', function() {
                self.handlePropertySelection();
            });
            
            $(document).on('click', '.ufub-bulk-match', function(e) {
                e.preventDefault();
                self.bulkMatchProperties();
            });
            
            // Configuration updates
            $(document).on('click', '.ufub-save-config', function(e) {
                e.preventDefault();
                self.saveConfiguration();
            });
            
            // Real-time updates
            $(document).on('click', '.ufub-refresh-properties', function(e) {
                e.preventDefault();
                self.refreshProperties();
            });
        },
        
        /**
         * Initialize search interface
         */
        initSearchInterface: function() {
            var $searchContainer = $('.ufub-search-bar');
            if ($searchContainer.length === 0) {
                console.warn('🏠 Search container not found, creating default');
                this.createSearchInterface();
            }
            
            // Initialize autocomplete if available
            if ($.fn.autocomplete) {
                $('.ufub-search-input').autocomplete({
                    source: this.getSearchSuggestions.bind(this),
                    minLength: 2,
                    delay: 300
                });
            }
        },
        
        /**
         * Create search interface if missing
         */
        createSearchInterface: function() {
            var searchHTML = `
                <div class="ufub-search-bar">
                    <input type="text" class="ufub-search-input" placeholder="Search properties by address, MLS, or keywords...">
                    <select class="ufub-filter-select" data-filter="status">
                        <option value="all">All Status</option>
                        <option value="available">Available</option>
                        <option value="pending">Pending</option>
                        <option value="sold">Sold</option>
                    </select>
                    <select class="ufub-filter-select" data-filter="propertyType">
                        <option value="all">All Types</option>
                        <option value="residential">Residential</option>
                        <option value="commercial">Commercial</option>
                        <option value="land">Land</option>
                    </select>
                    <button class="ufub-search-btn">Search</button>
                </div>
            `;
            
            $('.ufub-property-matching-container').prepend(searchHTML);
        },
        
        /**
         * Initialize filters
         */
        initFilters: function() {
            // Load saved filter state
            var savedFilters = localStorage.getItem('ufub_property_filters');
            if (savedFilters) {
                try {
                    this.state.filters = Object.assign(this.state.filters, JSON.parse(savedFilters));
                    this.applyFiltersToUI();
                } catch (e) {
                    console.warn('🏠 Could not load saved filters:', e);
                }
            }
        },
        
        /**
         * Apply filters to UI elements
         */
        applyFiltersToUI: function() {
            var self = this;
            Object.keys(this.state.filters).forEach(function(key) {
                var value = self.state.filters[key];
                $('[data-filter="' + key + '"]').val(value);
            });
        },
        
        /**
         * Initialize property grid
         */
        initPropertyGrid: function() {
            var $grid = $('.ufub-property-grid');
            if ($grid.length === 0) {
                $('.ufub-property-matching-container').append('<div class="ufub-property-grid"></div>');
            }
            
            // Initialize masonry layout if available
            if ($.fn.masonry) {
                $grid.masonry({
                    itemSelector: '.ufub-property-card',
                    columnWidth: 350,
                    gutter: 25
                });
            }
        },
        
        /**
         * Initialize pagination
         */
        initPagination: function() {
            var $pagination = $('.ufub-pagination');
            if ($pagination.length === 0) {
                $('.ufub-property-matching-container').append('<div class="ufub-pagination"></div>');
            }
        },
        
        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            var bulkHTML = `
                <div class="ufub-bulk-actions" style="display: none;">
                    <span class="ufub-selected-count">0 properties selected</span>
                    <button class="ufub-bulk-match">Match Selected</button>
                    <button class="ufub-bulk-export">Export Selected</button>
                    <button class="ufub-bulk-clear">Clear Selection</button>
                </div>
            `;
            
            if ($('.ufub-bulk-actions').length === 0) {
                $('.ufub-property-matching-container').prepend(bulkHTML);
            }
        },
        
        /**
         * Load properties from server
         */
        loadProperties: function(page) {
            var self = this;
            page = page || 1;
            
            if (this.state.isLoading) {
                console.log('🏠 Already loading properties, skipping request');
                return;
            }
            
            this.state.isLoading = true;
            this.showLoadingState();
            
            var startTime = Date.now();
            
            var requestData = {
                action: 'ufub_load_properties',
                nonce: ufub_admin.nonce,
                page: page,
                per_page: this.config.propertiesPerPage,
                filters: this.state.filters,
                search: this.state.searchQuery,
                sort: this.state.sortBy
            };
            
            $.ajax({
                url: ufub_admin.ajax_url,
                type: 'POST',
                data: requestData,
                timeout: this.config.apiTimeout,
                success: function(response) {
                    self.performance.apiCalls++;
                    var loadTime = Date.now() - startTime;
                    self.performance.loadTimes.push(loadTime);
                    
                    if (response.success) {
                        self.renderProperties(response.data);
                        self.updatePagination(response.data.pagination);
                        self.state.currentPage = page;
                        self.state.lastUpdate = Date.now();
                        
                        console.log(`🏠 Loaded ${response.data.properties.length} properties in ${loadTime}ms`);
                    } else {
                        self.showError(response.data.message || 'Failed to load properties');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('🏠 Property loading failed:', error);
                    self.showError('Failed to load properties: ' + error);
                },
                complete: function() {
                    self.state.isLoading = false;
                    self.hideLoadingState();
                }
            });
        },
        
        /**
         * Render properties in grid
         */
        renderProperties: function(data) {
            var $grid = $('.ufub-property-grid');
            
            if (data.properties.length === 0) {
                this.showEmptyState();
                return;
            }
            
            var html = '';
            data.properties.forEach(function(property) {
                html += this.buildPropertyCard(property);
            }.bind(this));
            
            $grid.html(html);
            
            // Reinitialize masonry if available
            if ($.fn.masonry) {
                $grid.masonry('reloadItems').masonry();
            }
            
            // Update stats
            this.state.totalProperties = data.pagination.total;
            this.updateStatsDisplay();
        },
        
        /**
         * Build property card HTML
         */
        buildPropertyCard: function(property) {
            var statusClass = 'status-' + (property.status || 'unknown').toLowerCase();
            var imageUrl = property.featured_image || '/wp-content/plugins/ultimate-fub-integration/assets/images/property-placeholder.jpg';
            
            return `
                <div class="ufub-property-card" data-property-id="${property.id}">
                    <div class="ufub-property-image">
                        <img src="${imageUrl}" alt="${property.title}" loading="lazy">
                        <div class="ufub-property-status ${statusClass}">${property.status || 'Available'}</div>
                        <div class="ufub-property-price">$${this.formatPrice(property.price)}</div>
                        <input type="checkbox" class="ufub-property-checkbox" value="${property.id}">
                    </div>
                    <div class="ufub-property-content">
                        <h3 class="ufub-property-title">${property.title}</h3>
                        <div class="ufub-property-address">
                            <span class="dashicons dashicons-location"></span>
                            ${property.address}
                        </div>
                        <div class="ufub-property-details">
                            <div class="ufub-detail-item">
                                <div class="ufub-detail-value">${property.bedrooms || '--'}</div>
                                <div class="ufub-detail-label">Beds</div>
                            </div>
                            <div class="ufub-detail-item">
                                <div class="ufub-detail-value">${property.bathrooms || '--'}</div>
                                <div class="ufub-detail-label">Baths</div>
                            </div>
                            <div class="ufub-detail-item">
                                <div class="ufub-detail-value">${property.sqft ? this.formatNumber(property.sqft) : '--'}</div>
                                <div class="ufub-detail-label">Sq Ft</div>
                            </div>
                        </div>
                        <div class="ufub-property-actions">
                            <button class="ufub-property-btn primary">Match to FUB</button>
                            <button class="ufub-property-btn secondary">View Details</button>
                        </div>
                    </div>
                </div>
            `;
        },
        
        /**
         * Handle search functionality
         */
        handleSearch: function() {
            this.state.searchQuery = $('.ufub-search-input').val().trim();
            this.state.currentPage = 1;
            this.loadProperties();
            
            // Save search history
            this.saveSearchHistory(this.state.searchQuery);
        },
        
        /**
         * Handle filter changes
         */
        handleFilterChange: function() {
            var self = this;
            
            // Update filter state
            $('.ufub-filter-select, .ufub-filter-input').each(function() {
                var filterName = $(this).data('filter');
                if (filterName) {
                    self.state.filters[filterName] = $(this).val();
                }
            });
            
            // Save filters
            localStorage.setItem('ufub_property_filters', JSON.stringify(this.state.filters));
            
            // Reload with new filters
            this.state.currentPage = 1;
            this.loadProperties();
        },
        
        /**
         * Match property to Follow Up Boss
         */
        matchProperty: function(propertyId) {
            var self = this;
            
            var requestData = {
                action: 'ufub_match_property',
                nonce: ufub_admin.nonce,
                property_id: propertyId
            };
            
            $.ajax({
                url: ufub_admin.ajax_url,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Property matched successfully to Follow Up Boss');
                        // Update property card status
                        self.updatePropertyStatus(propertyId, 'matched');
                    } else {
                        self.showError(response.data.message || 'Failed to match property');
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Failed to match property: ' + error);
                }
            });
        },
        
        /**
         * View property details
         */
        viewProperty: function(propertyId) {
            // Open property details in modal or new tab
            var url = ufub_admin.property_url.replace('%id%', propertyId);
            window.open(url, '_blank');
        },
        
        /**
         * Handle property selection for bulk actions
         */
        handlePropertySelection: function() {
            var selectedCount = $('.ufub-property-checkbox:checked').length;
            
            if (selectedCount > 0) {
                $('.ufub-bulk-actions').show();
                $('.ufub-selected-count').text(selectedCount + ' properties selected');
            } else {
                $('.ufub-bulk-actions').hide();
            }
            
            this.state.selectedProperties = $('.ufub-property-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
        },
        
        /**
         * Start auto-refresh timer
         */
        startAutoRefresh: function() {
            var self = this;
            
            if (this.config.autoSync) {
                setInterval(function() {
                    if (!self.state.isLoading && Date.now() - self.state.lastUpdate > self.config.refreshInterval) {
                        console.log('🏠 Auto-refreshing properties...');
                        self.loadProperties(self.state.currentPage);
                    }
                }, this.config.refreshInterval);
            }
        },
        
        /**
         * Utility functions
         */
        formatPrice: function(price) {
            if (!price) return '0';
            return parseInt(price).toLocaleString();
        },
        
        formatNumber: function(num) {
            if (!num) return '0';
            return parseInt(num).toLocaleString();
        },
        
        showLoadingState: function() {
            $('.ufub-property-grid').addClass('ufub-property-loading');
        },
        
        hideLoadingState: function() {
            $('.ufub-property-grid').removeClass('ufub-property-loading');
        },
        
        showError: function(message) {
            console.error('🏠 Property Matching Error:', message);
            // Could integrate with notification system
        },
        
        showSuccess: function(message) {
            console.log('🏠 Property Matching Success:', message);
            // Could integrate with notification system
        },
        
        showEmptyState: function() {
            $('.ufub-property-grid').html(`
                <div class="ufub-empty-properties">
                    <div class="ufub-empty-icon">🏠</div>
                    <div class="ufub-empty-title">No Properties Found</div>
                    <div class="ufub-empty-description">
                        Try adjusting your search criteria or filters to find properties.
                    </div>
                </div>
            `);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        UFUBPropertyMatching.init();
        
        // Make globally accessible for debugging
        window.UFUBPropertyMatching = UFUBPropertyMatching;
    });
    
})(jQuery);
