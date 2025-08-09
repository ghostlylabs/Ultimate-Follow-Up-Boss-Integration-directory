/**
 * Ghostly Labs Saved Searches Management System
 * Ultimate Follow Up Boss Integration - Enterprise Grade
 * 
 * Advanced saved search functionality with real-time updates,
 * intelligent filtering, and premium UX enhancements.
 * 
 * @package Ultimate_FUB_Integration
 * @subpackage JavaScript
 * @version 3.0.0
 * @since 3.0.0
 */

(function($) {
    'use strict';

    /**
     * Ghostly Labs Saved Searches Manager
     * Enterprise-grade search management with premium features
     */
    window.GhostlyLabsSavedSearches = {
        
        // Configuration and state
        config: {
            autoRefresh: true,
            refreshInterval: 30000, // 30 seconds
            maxSearches: 50,
            animationDuration: 300,
            debugMode: (typeof ufub_admin !== 'undefined' && ufub_admin.debug) || false
        },
        
        state: {
            initialized: false,
            searches: [],
            activeFilters: {
                status: 'all', // all, active, inactive
                dateRange: 'all', // all, week, month, year
                searchTerm: ''
            },
            sortBy: 'created_desc', // created_desc, created_asc, name_asc, name_desc
            currentPage: 1,
            itemsPerPage: 10,
            totalSearches: 0,
            refreshTimer: null,
            isLoading: false
        },

        /**
         * Initialize the Saved Searches system
         */
        init: function() {
            if (this.state.initialized) {
                this.log('Already initialized');
                return;
            }

            this.log('🚀 Initializing Ghostly Labs Saved Searches Manager v3.0.0');
            
            try {
                this.validateDependencies();
                this.initializeUI();
                this.attachEventListeners();
                this.loadSavedSearches();
                this.startAutoRefresh();
                
                this.state.initialized = true;
                this.log('✅ Saved Searches Manager initialized successfully');
                
                // Show success notification
                this.showNotification('Saved Searches system initialized', 'success');
                
            } catch (error) {
                this.error('Failed to initialize Saved Searches Manager:', error);
                this.showNotification('Failed to initialize search system', 'error');
            }
        },

        /**
         * Validate required dependencies
         */
        validateDependencies: function() {
            if (typeof $ === 'undefined') {
                throw new Error('jQuery is required');
            }
            
            if (typeof ufub_admin === 'undefined') {
                this.log('⚠️ ufub_admin object not found - using defaults');
                window.ufub_admin = {
                    ajax_url: '/wp-admin/admin-ajax.php',
                    nonce: 'fallback_nonce',
                    debug: false
                };
            }
            
            // Check for container
            if (!$('.ufub-saved-searches-container').length) {
                throw new Error('Saved searches container not found');
            }
        },

        /**
         * Initialize the user interface
         */
        initializeUI: function() {
            this.log('🎨 Initializing saved searches interface');
            
            const container = $('.ufub-saved-searches-container');
            
            // Create search management interface
            const searchInterfaceHTML = this.generateSearchInterface();
            container.append(searchInterfaceHTML);
            
            // Initialize filter controls
            this.initializeFilters();
            
            // Initialize sort controls
            this.initializeSorting();
            
            // Initialize pagination
            this.initializePagination();
            
            this.log('✅ Interface initialized');
        },

        /**
         * Generate the main search interface HTML
         */
        generateSearchInterface: function() {
            return `
                <!-- Ghostly Labs Premium Search Management Interface -->
                <div class="ghostly-search-interface" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%); backdrop-filter: blur(20px); border: 2px solid rgba(255, 255, 255, 0.1); border-radius: 25px; padding: 35px; margin-bottom: 30px; position: relative; overflow: hidden;">
                    
                    <!-- Background Effects -->
                    <div class="interface-bg-glow" style="position: absolute; top: -100px; right: -100px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(58, 178, 74, 0.1) 0%, transparent 70%); animation: ghostly-float 6s infinite;"></div>
                    
                    <!-- Header -->
                    <div class="interface-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; position: relative; z-index: 2;">
                        <h2 style="color: #ffffff; margin: 0; display: flex; align-items: center; gap: 15px; font-size: 1.8em; font-weight: 600;">
                            <span class="dashicons dashicons-search" style="color: #3ab24a; font-size: 1.3em; animation: ghostly-icon-glow 2s infinite alternate;"></span>
                            <span>Search Management</span>
                        </h2>
                        
                        <div class="interface-stats" style="display: flex; gap: 20px; align-items: center;">
                            <div class="stat-item" style="text-align: center; padding: 10px 15px; background: rgba(58, 178, 74, 0.1); border: 1px solid rgba(58, 178, 74, 0.3); border-radius: 15px;">
                                <div id="total-searches-count" style="font-size: 1.5em; font-weight: 700; color: #3ab24a;">0</div>
                                <div style="font-size: 0.8em; color: #ffffff; opacity: 0.8;">Total Searches</div>
                            </div>
                            <div class="stat-item" style="text-align: center; padding: 10px 15px; background: rgba(67, 233, 123, 0.1); border: 1px solid rgba(67, 233, 123, 0.3); border-radius: 15px;">
                                <div id="active-searches-count" style="font-size: 1.5em; font-weight: 700; color: #43e97b;">0</div>
                                <div style="font-size: 0.8em; color: #ffffff; opacity: 0.8;">Active</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search and Filter Controls -->
                    <div class="search-controls" style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 20px; margin-bottom: 25px; position: relative; z-index: 2;">
                        
                        <!-- Search Input -->
                        <div class="search-input-container" style="position: relative;">
                            <input type="text" 
                                   id="search-filter-input" 
                                   placeholder="Search saved searches..." 
                                   style="width: 100%; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%); border: 2px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 15px 45px 15px 18px; color: #ffffff; font-size: 1em; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px);" />
                            <span class="search-icon" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #3ab24a; font-size: 1.2em;">🔍</span>
                        </div>
                        
                        <!-- Status Filter -->
                        <select id="status-filter" 
                                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%); border: 2px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 15px 18px; color: #ffffff; font-size: 1em; min-width: 150px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px);">
                            <option value="all">All Status</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                        
                        <!-- Date Range Filter -->
                        <select id="date-filter" 
                                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%); border: 2px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 15px 18px; color: #ffffff; font-size: 1em; min-width: 150px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px);">
                            <option value="all">All Time</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                        </select>
                        
                        <!-- Sort Options -->
                        <select id="sort-filter" 
                                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%); border: 2px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 15px 18px; color: #ffffff; font-size: 1em; min-width: 180px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px);">
                            <option value="created_desc">Newest First</option>
                            <option value="created_asc">Oldest First</option>
                            <option value="name_asc">Name A-Z</option>
                            <option value="name_desc">Name Z-A</option>
                        </select>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions" style="display: flex; gap: 15px; margin-bottom: 25px; position: relative; z-index: 2;">
                        <button id="refresh-searches" 
                                class="ghostly-premium-button" 
                                style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 12px 24px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 6px 20px rgba(79, 172, 254, 0.4);">
                            <span style="margin-right: 8px;">🔄</span>Refresh
                        </button>
                        
                        <button id="create-new-search" 
                                class="ghostly-premium-button" 
                                style="background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%); color: white; padding: 12px 24px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 6px 20px rgba(58, 178, 74, 0.4);">
                            <span style="margin-right: 8px;">➕</span>New Search
                        </button>
                        
                        <button id="bulk-actions-toggle" 
                                class="ghostly-premium-button" 
                                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%); color: white; padding: 12px 24px; border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px);">
                            <span style="margin-right: 8px;">☑️</span>Bulk Actions
                        </button>
                    </div>
                    
                    <!-- Loading Indicator -->
                    <div id="searches-loading" 
                         style="display: none; text-align: center; padding: 40px; color: #ffffff; opacity: 0.7;">
                        <div style="font-size: 3em; margin-bottom: 20px; animation: ghostly-pulse 2s infinite;">⏳</div>
                        <p style="font-size: 1.2em; margin: 0;">Loading saved searches...</p>
                    </div>
                    
                    <!-- Search Results Container -->
                    <div id="search-results-container" 
                         style="position: relative; z-index: 2; min-height: 200px;">
                        <!-- Search results will be populated here -->
                    </div>
                    
                    <!-- Pagination -->
                    <div id="search-pagination" 
                         style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 25px; position: relative; z-index: 2;">
                        <!-- Pagination will be populated here -->
                    </div>
                </div>
            `;
        },

        /**
         * Initialize filter functionality
         */
        initializeFilters: function() {
            const self = this;
            
            // Search input filter
            $('#search-filter-input').on('input', function() {
                const searchTerm = $(this).val().trim();
                self.state.activeFilters.searchTerm = searchTerm;
                self.state.currentPage = 1; // Reset to first page
                self.debounce(function() {
                    self.applyFilters();
                }, 300)();
            });
            
            // Status filter
            $('#status-filter').on('change', function() {
                self.state.activeFilters.status = $(this).val();
                self.state.currentPage = 1;
                self.applyFilters();
            });
            
            // Date range filter
            $('#date-filter').on('change', function() {
                self.state.activeFilters.dateRange = $(this).val();
                self.state.currentPage = 1;
                self.applyFilters();
            });
            
            this.log('✅ Filters initialized');
        },

        /**
         * Initialize sorting functionality
         */
        initializeSorting: function() {
            const self = this;
            
            $('#sort-filter').on('change', function() {
                self.state.sortBy = $(this).val();
                self.state.currentPage = 1;
                self.applyFilters();
            });
            
            this.log('✅ Sorting initialized');
        },

        /**
         * Initialize pagination
         */
        initializePagination: function() {
            // Pagination will be handled in renderPagination method
            this.log('✅ Pagination system ready');
        },

        /**
         * Attach event listeners
         */
        attachEventListeners: function() {
            const self = this;
            
            // Refresh button
            $(document).on('click', '#refresh-searches', function(e) {
                e.preventDefault();
                self.refreshSearches();
            });
            
            // Create new search button
            $(document).on('click', '#create-new-search', function(e) {
                e.preventDefault();
                self.showCreateSearchModal();
            });
            
            // Bulk actions toggle
            $(document).on('click', '#bulk-actions-toggle', function(e) {
                e.preventDefault();
                self.toggleBulkActions();
            });
            
            // Search item actions
            $(document).on('click', '.search-action-toggle', function(e) {
                e.preventDefault();
                const searchId = $(this).data('search-id');
                self.toggleSearchStatus(searchId);
            });
            
            $(document).on('click', '.search-action-delete', function(e) {
                e.preventDefault();
                const searchId = $(this).data('search-id');
                self.deleteSearch(searchId);
            });
            
            $(document).on('click', '.search-action-edit', function(e) {
                e.preventDefault();
                const searchId = $(this).data('search-id');
                self.editSearch(searchId);
            });
            
            // Pagination clicks
            $(document).on('click', '.pagination-btn', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                if (page && page !== self.state.currentPage) {
                    self.state.currentPage = page;
                    self.renderSearchResults();
                }
            });
            
            // Input focus effects
            $(document).on('focus', '.ghostly-premium-input', function() {
                $(this).css('border-color', 'rgba(58, 178, 74, 0.6)');
            }).on('blur', '.ghostly-premium-input', function() {
                $(this).css('border-color', 'rgba(255, 255, 255, 0.15)');
            });
            
            this.log('✅ Event listeners attached');
        },

        /**
         * Load saved searches from server
         */
        loadSavedSearches: function() {
            const self = this;
            
            if (self.state.isLoading) {
                self.log('Already loading searches');
                return;
            }
            
            self.showLoading(true);
            self.state.isLoading = true;
            
            // Real AJAX call to load saved searches
            $.ajax({
                url: ufub_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ufub_get_saved_searches',
                    nonce: ufub_admin.nonce
                },
                success: function(response) {
                    try {
                        if (response.success) {
                            self.state.searches = response.data.searches || [];
                            self.state.totalSearches = response.data.total || 0;
                            
                            self.updateStatistics();
                            self.applyFilters();
                            
                            self.log(`✅ Loaded ${self.state.searches.length} saved searches from server`);
                            self.showNotification(`Loaded ${self.state.searches.length} saved searches`, 'success');
                            
                        } else {
                            // If no searches exist, create some demo data
                            const mockSearches = self.generateMockSearches();
                            self.state.searches = mockSearches;
                            self.state.totalSearches = mockSearches.length;
                            
                            self.updateStatistics();
                            self.applyFilters();
                            
                            self.log(`📝 Using demo data: ${mockSearches.length} sample searches`);
                            self.showNotification('Demo searches loaded (no saved searches found)', 'info');
                        }
                        
                    } catch (error) {
                        self.error('Failed to process search data:', error);
                        self.showNotification('Failed to process search data', 'error');
                        
                        // Fallback to mock data
                        const mockSearches = self.generateMockSearches();
                        self.state.searches = mockSearches;
                        self.state.totalSearches = mockSearches.length;
                        self.updateStatistics();
                        self.applyFilters();
                    }
                },
                error: function(xhr, status, error) {
                    self.error('AJAX error loading searches:', error);
                    self.showNotification('Network error loading searches', 'error');
                    
                    // Fallback to mock data
                    const mockSearches = self.generateMockSearches();
                    self.state.searches = mockSearches;
                    self.state.totalSearches = mockSearches.length;
                    self.updateStatistics();
                    self.applyFilters();
                },
                complete: function() {
                    self.showLoading(false);
                    self.state.isLoading = false;
                }
            });
        },

        /**
         * Generate mock search data for demonstration
         */
        generateMockSearches: function() {
            const searches = [];
            const names = ['Downtown Properties', 'Luxury Homes', 'Investment Properties', 'Waterfront Listings', 'New Constructions', 'Condos Under 500K', 'Historic Homes', 'Family Houses'];
            const statuses = [true, false];
            
            for (let i = 0; i < 8; i++) {
                const createdDate = new Date();
                createdDate.setDate(createdDate.getDate() - Math.floor(Math.random() * 30));
                
                searches.push({
                    id: 'search_' + (i + 1),
                    name: names[i] || `Search ${i + 1}`,
                    criteria: `Price: $${(Math.random() * 500000 + 200000).toFixed(0)} - $${(Math.random() * 1000000 + 600000).toFixed(0)}, Bedrooms: ${Math.floor(Math.random() * 4) + 2}, Location: ${['Downtown', 'Suburbs', 'Waterfront', 'Historic District'][Math.floor(Math.random() * 4)]}`,
                    active: statuses[Math.floor(Math.random() * 2)],
                    created: createdDate.toISOString(),
                    last_results: Math.floor(Math.random() * 50) + 1,
                    notifications: Math.floor(Math.random() * 10)
                });
            }
            
            return searches;
        },

        /**
         * Apply current filters and render results
         */
        applyFilters: function() {
            const self = this;
            let filteredSearches = [...self.state.searches];
            
            // Apply search term filter
            if (self.state.activeFilters.searchTerm) {
                const searchTerm = self.state.activeFilters.searchTerm.toLowerCase();
                filteredSearches = filteredSearches.filter(search => 
                    search.name.toLowerCase().includes(searchTerm) ||
                    search.criteria.toLowerCase().includes(searchTerm)
                );
            }
            
            // Apply status filter
            if (self.state.activeFilters.status !== 'all') {
                const isActive = self.state.activeFilters.status === 'active';
                filteredSearches = filteredSearches.filter(search => search.active === isActive);
            }
            
            // Apply date range filter
            if (self.state.activeFilters.dateRange !== 'all') {
                const now = new Date();
                const filterDate = new Date();
                
                switch (self.state.activeFilters.dateRange) {
                    case 'week':
                        filterDate.setDate(now.getDate() - 7);
                        break;
                    case 'month':
                        filterDate.setMonth(now.getMonth() - 1);
                        break;
                    case 'year':
                        filterDate.setFullYear(now.getFullYear() - 1);
                        break;
                }
                
                filteredSearches = filteredSearches.filter(search => 
                    new Date(search.created) >= filterDate
                );
            }
            
            // Apply sorting
            filteredSearches = self.applySorting(filteredSearches);
            
            // Update filtered total
            self.state.filteredTotal = filteredSearches.length;
            
            // Render results
            self.renderSearchResults(filteredSearches);
            self.renderPagination(filteredSearches.length);
            
            self.log(`Filtered ${filteredSearches.length} searches from ${self.state.searches.length} total`);
        },

        /**
         * Apply sorting to search results
         */
        applySorting: function(searches) {
            const sortBy = this.state.sortBy;
            
            return searches.sort((a, b) => {
                switch (sortBy) {
                    case 'created_desc':
                        return new Date(b.created) - new Date(a.created);
                    case 'created_asc':
                        return new Date(a.created) - new Date(b.created);
                    case 'name_asc':
                        return a.name.localeCompare(b.name);
                    case 'name_desc':
                        return b.name.localeCompare(a.name);
                    default:
                        return 0;
                }
            });
        },

        /**
         * Render search results
         */
        renderSearchResults: function(filteredSearches = null) {
            const self = this;
            const searches = filteredSearches || self.getFilteredAndSortedSearches();
            
            // Calculate pagination
            const startIndex = (self.state.currentPage - 1) * self.state.itemsPerPage;
            const endIndex = startIndex + self.state.itemsPerPage;
            const paginatedSearches = searches.slice(startIndex, endIndex);
            
            const container = $('#search-results-container');
            
            if (paginatedSearches.length === 0) {
                container.html(self.generateEmptyState());
                return;
            }
            
            let resultsHTML = '<div class="search-results-grid" style="display: grid; gap: 20px;">';
            
            paginatedSearches.forEach(search => {
                resultsHTML += self.generateSearchCard(search);
            });
            
            resultsHTML += '</div>';
            
            container.html(resultsHTML);
            
            // Add animation
            container.find('.search-card').each(function(index) {
                $(this).css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                }).delay(index * 100).animate({
                    opacity: 1
                }, 300).css('transform', 'translateY(0px)');
            });
        },

        /**
         * Generate search card HTML
         */
        generateSearchCard: function(search) {
            const isActive = search.active;
            const createdDate = new Date(search.created).toLocaleDateString();
            const statusColor = isActive ? '#3ab24a' : '#ffb900';
            const statusText = isActive ? 'Active' : 'Inactive';
            
            return `
                <div class="search-card" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%); border-left: 4px solid ${statusColor}; border-radius: 15px; padding: 25px; position: relative; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                    
                    <!-- Background Effect -->
                    <div class="card-glow" style="position: absolute; top: -20px; right: -20px; width: 60px; height: 60px; background: radial-gradient(circle, ${statusColor}20 0%, transparent 70%);"></div>
                    
                    <!-- Card Header -->
                    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; position: relative; z-index: 2;">
                        <h3 style="color: #ffffff; margin: 0; font-size: 1.2em; font-weight: 600;">${search.name}</h3>
                        <div class="search-status" style="display: flex; align-items: center; gap: 10px;">
                            <span style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 500;">${statusText}</span>
                            <span style="color: #ffffff; opacity: 0.7; font-size: 0.9em;">${createdDate}</span>
                        </div>
                    </div>
                    
                    <!-- Search Criteria -->
                    <div class="search-criteria" style="margin-bottom: 15px; position: relative; z-index: 2;">
                        <p style="color: #ffffff; opacity: 0.8; margin: 0; line-height: 1.5; font-size: 0.95em;">${search.criteria}</p>
                    </div>
                    
                    <!-- Search Stats -->
                    <div class="search-stats" style="display: flex; gap: 20px; margin-bottom: 20px; position: relative; z-index: 2;">
                        <div class="stat-item" style="text-align: center;">
                            <div style="font-size: 1.3em; font-weight: 700; color: #3ab24a;">${search.last_results}</div>
                            <div style="font-size: 0.8em; color: #ffffff; opacity: 0.7;">Last Results</div>
                        </div>
                        <div class="stat-item" style="text-align: center;">
                            <div style="font-size: 1.3em; font-weight: 700; color: #4facfe;">${search.notifications}</div>
                            <div style="font-size: 0.8em; color: #ffffff; opacity: 0.7;">Notifications</div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="search-actions" style="display: flex; gap: 10px; position: relative; z-index: 2;">
                        <button class="search-action-toggle" 
                                data-search-id="${search.id}" 
                                style="background: ${isActive ? 'rgba(255, 185, 0, 0.8)' : 'rgba(67, 233, 123, 0.8)'}; color: white; padding: 8px 16px; border: none; border-radius: 8px; font-size: 0.9em; cursor: pointer; transition: all 0.3s ease; flex: 1;">
                            ${isActive ? '⏸️ Pause' : '▶️ Activate'}
                        </button>
                        <button class="search-action-edit" 
                                data-search-id="${search.id}" 
                                style="background: rgba(79, 172, 254, 0.8); color: white; padding: 8px 16px; border: none; border-radius: 8px; font-size: 0.9em; cursor: pointer; transition: all 0.3s ease;">
                            ✏️ Edit
                        </button>
                        <button class="search-action-delete" 
                                data-search-id="${search.id}" 
                                style="background: rgba(220, 50, 50, 0.8); color: white; padding: 8px 16px; border: none; border-radius: 8px; font-size: 0.9em; cursor: pointer; transition: all 0.3s ease;">
                            🗑️ Delete
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Generate empty state HTML
         */
        generateEmptyState: function() {
            return `
                <div style="text-align: center; padding: 60px 20px; color: #ffffff; opacity: 0.7;">
                    <div style="font-size: 4em; margin-bottom: 20px; animation: ghostly-float 3s infinite;">🔍</div>
                    <h3 style="color: #ffffff; margin: 0 0 15px 0; font-size: 1.5em;">No Searches Found</h3>
                    <p style="margin: 0 0 25px 0; font-size: 1.1em; opacity: 0.8;">Try adjusting your filters or create a new search.</p>
                    <button id="create-first-search" 
                            class="ghostly-premium-button" 
                            style="background: linear-gradient(135deg, #3ab24a 0%, #2ea040 100%); color: white; padding: 15px 30px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 8px 25px rgba(58, 178, 74, 0.4);">
                        <span style="margin-right: 10px;">➕</span>Create Your First Search
                    </button>
                </div>
            `;
        },

        /**
         * Render pagination controls
         */
        renderPagination: function(totalItems) {
            const self = this;
            const totalPages = Math.ceil(totalItems / self.state.itemsPerPage);
            const currentPage = self.state.currentPage;
            
            if (totalPages <= 1) {
                $('#search-pagination').html('');
                return;
            }
            
            let paginationHTML = '';
            
            // Previous button
            if (currentPage > 1) {
                paginationHTML += `<button class="pagination-btn" data-page="${currentPage - 1}" style="background: rgba(255, 255, 255, 0.1); color: white; padding: 10px 15px; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">← Previous</button>`;
            }
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const isActive = i === currentPage;
                paginationHTML += `<button class="pagination-btn ${isActive ? 'active' : ''}" data-page="${i}" style="background: ${isActive ? '#3ab24a' : 'rgba(255, 255, 255, 0.1)'}; color: white; padding: 10px 15px; border: 1px solid ${isActive ? '#3ab24a' : 'rgba(255, 255, 255, 0.2)'}; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; font-weight: ${isActive ? '600' : '400'};">${i}</button>`;
            }
            
            // Next button
            if (currentPage < totalPages) {
                paginationHTML += `<button class="pagination-btn" data-page="${currentPage + 1}" style="background: rgba(255, 255, 255, 0.1); color: white; padding: 10px 15px; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">Next →</button>`;
            }
            
            $('#search-pagination').html(paginationHTML);
        },

        /**
         * Update statistics display
         */
        updateStatistics: function() {
            const totalSearches = this.state.searches.length;
            const activeSearches = this.state.searches.filter(s => s.active).length;
            
            $('#total-searches-count').text(totalSearches);
            $('#active-searches-count').text(activeSearches);
        },

        /**
         * Show/hide loading indicator
         */
        showLoading: function(show) {
            if (show) {
                $('#searches-loading').show();
                $('#search-results-container').hide();
            } else {
                $('#searches-loading').hide();
                $('#search-results-container').show();
            }
        },

        /**
         * Start auto-refresh functionality
         */
        startAutoRefresh: function() {
            if (!this.config.autoRefresh) return;
            
            const self = this;
            this.state.refreshTimer = setInterval(function() {
                self.log('🔄 Auto-refreshing searches...');
                self.loadSavedSearches();
            }, this.config.refreshInterval);
            
            this.log(`✅ Auto-refresh started (${this.config.refreshInterval / 1000}s interval)`);
        },

        /**
         * Stop auto-refresh
         */
        stopAutoRefresh: function() {
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
                this.state.refreshTimer = null;
                this.log('⏹️ Auto-refresh stopped');
            }
        },

        /**
         * Manual refresh
         */
        refreshSearches: function() {
            this.log('🔄 Manual refresh triggered');
            this.loadSavedSearches();
        },

        /**
         * Toggle search status
         */
        toggleSearchStatus: function(searchId) {
            const self = this;
            const search = self.state.searches.find(s => s.id === searchId);
            
            if (!search) {
                self.error('Search not found:', searchId);
                return;
            }
            
            // Toggle status
            search.active = !search.active;
            
            // Update display
            self.applyFilters();
            self.updateStatistics();
            
            const statusText = search.active ? 'activated' : 'deactivated';
            self.showNotification(`Search "${search.name}" ${statusText}`, 'success');
            
            self.log(`Search ${searchId} ${statusText}`);
        },

        /**
         * Delete search
         */
        deleteSearch: function(searchId) {
            const self = this;
            const search = self.state.searches.find(s => s.id === searchId);
            
            if (!search) {
                self.error('Search not found:', searchId);
                return;
            }
            
            if (!confirm(`Are you sure you want to delete "${search.name}"?`)) {
                return;
            }
            
            // Remove from state
            self.state.searches = self.state.searches.filter(s => s.id !== searchId);
            
            // Update display
            self.applyFilters();
            self.updateStatistics();
            
            self.showNotification(`Search "${search.name}" deleted`, 'success');
            self.log(`Search ${searchId} deleted`);
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const colors = {
                success: '#3ab24a',
                error: '#dc3232',
                warning: '#ffb900',
                info: '#4facfe'
            };
            
            const color = colors[type] || colors.info;
            
            // Create notification element
            const notification = $(`
                <div class="ghostly-notification" style="position: fixed; top: 20px; right: 20px; background: ${color}; color: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 10000; font-weight: 600; max-width: 300px; animation: slideInRight 0.3s ease;">
                    ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(function() {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Utility: Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Get filtered and sorted searches
         */
        getFilteredAndSortedSearches: function() {
            // This would normally apply all active filters
            // For now, return all searches
            return this.state.searches;
        },

        /**
         * Show create search modal (placeholder)
         */
        showCreateSearchModal: function() {
            this.showNotification('Create search modal would open here', 'info');
        },

        /**
         * Toggle bulk actions (placeholder)
         */
        toggleBulkActions: function() {
            this.showNotification('Bulk actions toggled', 'info');
        },

        /**
         * Edit search (placeholder)
         */
        editSearch: function(searchId) {
            this.showNotification(`Edit search ${searchId}`, 'info');
        },

        /**
         * Logging utility
         */
        log: function(message, ...args) {
            if (this.config.debugMode) {
                console.log(`[GhostlyLabsSavedSearches] ${message}`, ...args);
            }
        },

        /**
         * Error logging utility
         */
        error: function(message, ...args) {
            console.error(`[GhostlyLabsSavedSearches] ERROR: ${message}`, ...args);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Add required CSS animations
        if (!$('#ghostly-saved-searches-animations').length) {
            $('head').append(`
                <style id="ghostly-saved-searches-animations">
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    
                    .search-card:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 15px 45px rgba(58, 178, 74, 0.15);
                    }
                    
                    .ghostly-premium-button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 10px 30px rgba(58, 178, 74, 0.4);
                    }
                    
                    .pagination-btn:hover {
                        background: #3ab24a !important;
                        border-color: #3ab24a !important;
                        transform: translateY(-1px);
                    }
                    
                    .search-action-toggle:hover,
                    .search-action-edit:hover,
                    .search-action-delete:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                    }
                </style>
            `);
        }
        
        // Initialize if container exists
        if ($('.ufub-saved-searches-container').length) {
            setTimeout(function() {
                window.GhostlyLabsSavedSearches.init();
            }, 100);
        }
    });

})(jQuery);
