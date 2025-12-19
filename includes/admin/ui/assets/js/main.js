/**
 * Admin UI - Shared JavaScript
 * 
 * This file contains:
 * - Shared utilities
 * - Common event handlers
 * - AJAX helpers
 * - UI helpers
 */

(function() {
    'use strict';

    // Admin UI namespace
    window.AAAdmin = window.AAAdmin || {};

    /**
     * AJAX helper for admin requests
     */
    AAAdmin.ajax = function(action, data, callback) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', window.aaAdminNonce || '');

        if (data) {
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });
        }

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(callback)
        .catch(error => {
            console.error('AJAX Error:', error);
        });
    };

    /**
     * Show notification message
     */
    AAAdmin.notify = function(message, type = 'info') {
        // Implementation for notifications
        console.log(`[${type}] ${message}`);
    };

    /**
     * Iframe auto-resize functionality
     * Sends content height to parent window for dynamic iframe sizing
     */
    AAAdmin.iframeResize = function() {
        // Only run if we're inside an iframe
        if (window.self === window.top) return;

        let lastHeight = 0;
        let resizeTimeout = null;

        function sendHeight(source = 'unknown') {
            // Get the actual content container instead of body
            // The body has min-h-screen which forces minimum height
            const appContainer = document.getElementById('aa-admin-app');
            const body = document.body;
            const html = document.documentElement;

            let height;

            if (appContainer) {
                // Measure the actual content container
                // Use getBoundingClientRect for accurate measurement
                const rect = appContainer.getBoundingClientRect();
                height = Math.ceil(rect.height);
                
                // Fallback: use scrollHeight if getBoundingClientRect doesn't work
                if (height === 0 || isNaN(height)) {
                    height = appContainer.scrollHeight;
                }
            } else {
                // Fallback to body measurement if container not found
                height = Math.max(
                    body.scrollHeight,
                    body.offsetHeight,
                    html.clientHeight,
                    html.scrollHeight,
                    html.offsetHeight
                );
            }

            // Debug logging
            console.log(`[iframe-resize] Source: ${source}, Height: ${height}px, Last: ${lastHeight}px, Container: ${appContainer ? 'found' : 'not found'}`);

            // Only send if height changed (avoid unnecessary updates)
            if (height !== lastHeight) {
                lastHeight = height;

                console.log(`[iframe-resize] Sending new height: ${height}px to parent window`);

                // Send height to parent window
                window.parent.postMessage({
                    type: 'aa-iframe-resize',
                    height: height,
                    iframeId: 'aa-settings-iframe'
                }, '*');
            } else {
                console.log(`[iframe-resize] Height unchanged (${height}px), skipping update`);
            }
        }

        // Send height on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                sendHeight('DOMContentLoaded');
            });
        } else {
            sendHeight('initial');
        }

        // Send height when content changes (debounced)
        function debouncedSendHeight(source = 'debounced') {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                sendHeight(source);
            }, 100);
        }

        // Watch for content changes - observe the app container instead of body
        const appContainer = document.getElementById('aa-admin-app');
        const targetElement = appContainer || document.body;

        if (typeof ResizeObserver !== 'undefined') {
            const resizeObserver = new ResizeObserver(function(entries) {
                debouncedSendHeight('ResizeObserver');
            });
            resizeObserver.observe(targetElement);
        }

        // Also watch for DOM mutations (dynamic content)
        if (typeof MutationObserver !== 'undefined') {
            const mutationObserver = new MutationObserver(function(mutations) {
                debouncedSendHeight('MutationObserver');
            });
            mutationObserver.observe(targetElement, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class', 'open'] // Include 'open' attribute for details elements
            });
        }

        // Send height on window resize (in case of viewport changes)
        window.addEventListener('resize', function() {
            debouncedSendHeight('window-resize');
        });

        // Watch for accordion (details) toggle events
        // This ensures height updates when accordions are opened/closed
        function observeAccordions() {
            const detailsElements = document.querySelectorAll('details');
            
            console.log(`[accordion-observer] Found ${detailsElements.length} accordion(s)`);
            
            detailsElements.forEach((details, index) => {
                details.addEventListener('toggle', function(event) {
                    const isOpen = this.open;
                    const accordionTitle = this.querySelector('h3')?.textContent || `Accordion ${index + 1}`;
                    
                    console.log(`[accordion-toggle] ${accordionTitle} - ${isOpen ? 'OPENED' : 'CLOSED'}`);
                    
                    // Use a longer delay to allow the browser to finish the transition
                    // and recalculate layout
                    setTimeout(function() {
                        sendHeight(`accordion-${isOpen ? 'opened' : 'closed'}`);
                    }, 200);
                });
            });
        }

        // Observe accordions on load and when DOM changes
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', observeAccordions);
        } else {
            observeAccordions();
        }

        // Also watch for new accordions added dynamically
        if (typeof MutationObserver !== 'undefined') {
            const accordionObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if the added node is a details element
                            if (node.tagName === 'DETAILS') {
                                console.log('[accordion-observer] New accordion detected, adding listener');
                                node.addEventListener('toggle', function() {
                                    const isOpen = this.open;
                                    console.log(`[accordion-toggle] Dynamic accordion - ${isOpen ? 'OPENED' : 'CLOSED'}`);
                                    setTimeout(function() {
                                        sendHeight(`dynamic-accordion-${isOpen ? 'opened' : 'closed'}`);
                                    }, 200);
                                });
                            }
                            // Check for details elements inside the added node
                            const detailsInside = node.querySelectorAll && node.querySelectorAll('details');
                            if (detailsInside && detailsInside.length > 0) {
                                console.log(`[accordion-observer] Found ${detailsInside.length} accordion(s) inside new node`);
                                detailsInside.forEach(function(details) {
                                    details.addEventListener('toggle', function() {
                                        const isOpen = this.open;
                                        console.log(`[accordion-toggle] Nested accordion - ${isOpen ? 'OPENED' : 'CLOSED'}`);
                                        setTimeout(function() {
                                            sendHeight(`nested-accordion-${isOpen ? 'opened' : 'closed'}`);
                                        }, 200);
                                    });
                                });
                            }
                        }
                    });
                });
            });

            accordionObserver.observe(targetElement, {
                childList: true,
                subtree: true
            });
        }
    };

    /**
     * Modal System - Global modal management
     * Provides reusable modal functionality for all modules
     */
    AAAdmin.modal = (function() {
        let isModalOpen = false;
        const modalRoot = null; // Will be set on init

        /**
         * Get modal root element
         * @returns {HTMLElement|null}
         */
        function getModalRoot() {
            return document.getElementById('aa-modal-root');
        }

        /**
         * Get modal elements
         * @returns {Object|null}
         */
        function getModalElements() {
            const root = getModalRoot();
            if (!root) return null;

            return {
                root: root,
                overlay: root.querySelector('.aa-modal-overlay'),
                modal: root.querySelector('.aa-modal'),
                title: root.querySelector('.aa-modal-title'),
                body: root.querySelector('.aa-modal-body'),
                footer: root.querySelector('.aa-modal-footer')
            };
        }

        /**
         * Insert content into element (supports string or HTMLElement)
         * @param {HTMLElement} container - Container element
         * @param {string|HTMLElement} content - Content to insert
         */
        function insertContent(container, content) {
            if (!container) return;

            // Clear existing content
            container.innerHTML = '';

            if (typeof content === 'string') {
                container.innerHTML = content;
            } else if (content instanceof HTMLElement) {
                container.appendChild(content);
            } else if (content instanceof Node) {
                container.appendChild(content);
            }
        }

        /**
         * Block body scroll
         */
        function blockBodyScroll() {
            document.body.classList.add('aa-modal-open');
        }

        /**
         * Restore body scroll
         */
        function restoreBodyScroll() {
            document.body.classList.remove('aa-modal-open');
        }

        /**
         * Open modal
         * @param {Object} options - Modal options
         * @param {string|HTMLElement} options.title - Modal title
         * @param {string|HTMLElement} options.body - Modal body content
         * @param {string|HTMLElement} [options.footer] - Optional modal footer
         */
        function openModal(options) {
            if (!options || (!options.title && !options.body)) {
                console.warn('AAAdmin.openModal: title or body is required');
                return;
            }

            const elements = getModalElements();
            if (!elements) {
                console.error('AAAdmin.openModal: Modal root not found');
                return;
            }

            // Close any existing modal first
            if (isModalOpen) {
                closeModal();
            }

            // Insert content
            if (options.title) {
                insertContent(elements.title, options.title);
            }

            if (options.body) {
                insertContent(elements.body, options.body);
            }

            if (options.footer) {
                insertContent(elements.footer, options.footer);
            } else {
                // Clear footer if not provided
                elements.footer.innerHTML = '';
            }

            // Show modal
            elements.root.classList.remove('hidden');
            blockBodyScroll();
            isModalOpen = true;
        }

        /**
         * Close modal
         */
        function closeModal() {
            const elements = getModalElements();
            if (!elements) return;

            // Hide modal
            elements.root.classList.add('hidden');
            restoreBodyScroll();

            // Clear content
            elements.title.innerHTML = '';
            elements.body.innerHTML = '';
            elements.footer.innerHTML = '';

            isModalOpen = false;
        }

        /**
         * Initialize modal system
         * Sets up event delegation for close actions
         */
        function init() {
            // Event delegation for close actions
            document.addEventListener('click', function(event) {
                if (!isModalOpen) return;

                // Check if click is on a close trigger
                const closeTrigger = event.target.closest('[data-aa-modal-close]');
                if (!closeTrigger) return;

                // If clicking on overlay, close modal
                if (closeTrigger.classList.contains('aa-modal-overlay')) {
                    closeModal();
                    return;
                }

                // If clicking on close button, close modal
                if (closeTrigger.classList.contains('aa-modal-close') || 
                    closeTrigger.closest('.aa-modal-close')) {
                    closeModal();
                    return;
                }

                // Any other element with data-aa-modal-close should close
                closeModal();
            });

            // ESC key to close
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && isModalOpen) {
                    closeModal();
                }
            });
        }

        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        // Public API
        return {
            open: openModal,
            close: closeModal,
            isOpen: function() {
                return isModalOpen;
            }
        };
    })();

    /**
     * Alias for backward compatibility and convenience
     */
    AAAdmin.openModal = function(options) {
        AAAdmin.modal.open(options);
    };

    AAAdmin.closeModal = function() {
        AAAdmin.modal.close();
    };

    /**
     * Collapsible card overlay system
     * Handles cards with [data-aa-card] attribute and toggles via [data-aa-card-toggle]
     */
    AAAdmin.initCardOverlays = function() {
        /**
         * Close all open cards
         */
        function closeAllCards() {
            const openCards = document.querySelectorAll('[data-aa-card].is-open');
            openCards.forEach(function(card) {
                card.classList.remove('is-open');
            });
        }

        /**
         * Open a specific card
         * @param {HTMLElement} card - The card element with [data-aa-card]
         */
        function openCard(card) {
            if (!card || !card.hasAttribute('data-aa-card')) {
                return;
            }
            closeAllCards();
            card.classList.add('is-open');
        }

        /**
         * Check if click target is inside a card or its overlay
         * @param {HTMLElement} target - The clicked element
         * @param {HTMLElement} card - The card element to check against
         * @returns {boolean} - True if target is inside the card
         */
        function isClickInsideCard(target, card) {
            if (!card || !target) {
                return false;
            }
            return card.contains(target);
        }

        // Event delegation: handle clicks on toggle elements
        document.addEventListener('click', function(event) {
            const toggle = event.target.closest('[data-aa-card-toggle]');
            
            if (toggle) {
                // Find the parent card
                const card = toggle.closest('[data-aa-card]');
                
                if (card) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Toggle: if already open, close it; otherwise open it
                    if (card.classList.contains('is-open')) {
                        card.classList.remove('is-open');
                    } else {
                        openCard(card);
                    }
                    return;
                }
            }

            // Handle clicks outside cards
            const openCards = document.querySelectorAll('[data-aa-card].is-open');
            if (openCards.length > 0) {
                let clickedInsideAnyCard = false;
                
                openCards.forEach(function(card) {
                    if (isClickInsideCard(event.target, card)) {
                        clickedInsideAnyCard = true;
                    }
                });
                
                // If clicked outside all open cards, close them
                if (!clickedInsideAnyCard) {
                    closeAllCards();
                }
            }
        });
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Admin UI initialized');
        
        // Initialize iframe auto-resize
        AAAdmin.iframeResize();
        
        // Initialize card overlay system
        AAAdmin.initCardOverlays();
    });

})();

