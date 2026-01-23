/**
 * Admin UI - Shared JavaScript
 * 
 * This file contains:
 * - Shared utilities
 * - Common event handlers
 * - AJAX helpers
 * - UI helpers
 */

// Asegurar que el namespace exista ANTES del IIFE
// Esto garantiza que AAAdmin esté disponible incluso si hay errores
window.AAAdmin = window.AAAdmin || {};

(function() {
    'use strict';

    // Admin UI namespace (reafirmar que existe)
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
     * Popover System - Global popover management
     * Provides lightweight popover functionality for labels and tooltips
     */
    AAAdmin.popover = (function() {
        let isPopoverOpen = false;
        let currentAnchor = null;
        let popoverRoot = null;
        let popoverElement = null;

        /**
         * Ensure popover root exists in DOM
         */
        function ensurePopoverRoot() {
            if (popoverRoot) return popoverRoot;

            popoverRoot = document.getElementById('aa-popover-root');
            if (!popoverRoot) {
                const appContainer = document.getElementById('aa-admin-app');
                if (appContainer) {
                    popoverRoot = document.createElement('div');
                    popoverRoot.id = 'aa-popover-root';
                    popoverRoot.style.position = 'absolute';
                    popoverRoot.style.top = '0';
                    popoverRoot.style.left = '0';
                    popoverRoot.style.width = '100%';
                    popoverRoot.style.height = '0';
                    popoverRoot.style.overflow = 'visible';
                    popoverRoot.style.pointerEvents = 'none';
                    popoverRoot.style.zIndex = '9998';
                    appContainer.appendChild(popoverRoot);
                }
            }
            return popoverRoot;
        }

        /**
         * Position popover relative to anchor element
         * @param {HTMLElement} anchorEl - The anchor element
         */
        function positionPopover(anchorEl) {
            console.log('[Popover] positionPopover called with anchorEl:', anchorEl);
            
            if (!popoverElement || !anchorEl) {
                console.warn('[Popover] positionPopover: missing popoverElement or anchorEl');
                return;
            }

            const anchorRect = anchorEl.getBoundingClientRect();
            const popoverRect = popoverElement.getBoundingClientRect();
            const appContainer = document.getElementById('aa-admin-app');
            const appRect = appContainer ? appContainer.getBoundingClientRect() : { left: 0, top: 0, right: window.innerWidth, bottom: window.innerHeight };

            console.log('[Popover] Positioning data:', {
                anchorRect: { left: anchorRect.left, top: anchorRect.top, width: anchorRect.width, height: anchorRect.height },
                popoverRect: { width: popoverRect.width, height: popoverRect.height },
                appRect: { left: appRect.left, top: appRect.top, width: appRect.width || window.innerWidth }
            });

            // Calculate position relative to app container
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

            // Center horizontally relative to anchor
            let left = anchorRect.left + (anchorRect.width / 2) - (popoverRect.width / 2) - appRect.left;
            
            // Position below anchor by default
            let top = anchorRect.bottom - appRect.top + 4; // 4px gap

            // Clamp horizontal position to stay within viewport
            const minLeft = 8; // padding from edge
            const maxLeft = appRect.width - popoverRect.width - 8;
            left = Math.max(minLeft, Math.min(left, maxLeft));

            // If popover would go below viewport, show above anchor
            const viewportHeight = window.innerHeight;
            if (anchorRect.bottom + popoverRect.height + 4 > viewportHeight) {
                top = anchorRect.top - appRect.top - popoverRect.height - 4;
                console.log('[Popover] Popover would overflow viewport, showing above anchor');
            }

            // Ensure top is not negative
            top = Math.max(4, top);

            popoverElement.style.left = left + 'px';
            popoverElement.style.top = top + 'px';
            
            console.log('[Popover] Popover positioned at:', { left: left + 'px', top: top + 'px' });
        }

        /**
         * Open popover
         * @param {Object} options - Popover options
         * @param {HTMLElement} options.anchorEl - Element to anchor popover to
         * @param {string} [options.title] - Optional title (bold)
         * @param {Array<string>} options.lines - Array of text lines to display
         */
        function openPopover(options) {
            console.log('[Popover] openPopover called with options:', options);
            
            if (!options || !options.anchorEl) {
                console.warn('AAAdmin.popover.open: anchorEl is required');
                return;
            }

            // Close any existing popover first
            if (isPopoverOpen) {
                console.log('[Popover] Closing existing popover before opening new one');
                closePopover();
            }

            ensurePopoverRoot();
            if (!popoverRoot) {
                console.error('AAAdmin.popover.open: Could not create popover root');
                return;
            }

            console.log('[Popover] Popover root found/created:', popoverRoot);

            currentAnchor = options.anchorEl;

            // Create popover element
            popoverElement = document.createElement('div');
            popoverElement.className = 'aa-popover';
            popoverElement.style.pointerEvents = 'auto';

            // Build content
            let contentHTML = '';

            if (options.title) {
                contentHTML += '<div class="aa-popover-title">' + escapeHtml(options.title) + '</div>';
            }

            if (options.lines && options.lines.length > 0) {
                options.lines.forEach(function(line) {
                    if (line && line.trim()) {
                        contentHTML += '<div class="aa-popover-line">' + escapeHtml(line) + '</div>';
                    }
                });
            }

            console.log('[Popover] Content HTML:', contentHTML);

            popoverElement.innerHTML = contentHTML;

            // Append to root (initially hidden for measurement)
            popoverElement.style.visibility = 'hidden';
            popoverRoot.appendChild(popoverElement);
            console.log('[Popover] Popover element appended to root');

            // Position after adding to DOM (so we can measure it)
            positionPopover(options.anchorEl);
            console.log('[Popover] Popover positioned, making visible');
            popoverElement.style.visibility = 'visible';

            isPopoverOpen = true;
            console.log('[Popover] Popover opened successfully, isPopoverOpen:', isPopoverOpen);
        }

        /**
         * Close popover
         */
        function closePopover() {
            if (popoverElement && popoverElement.parentNode) {
                popoverElement.parentNode.removeChild(popoverElement);
            }
            popoverElement = null;
            currentAnchor = null;
            isPopoverOpen = false;
        }

        /**
         * Escape HTML to prevent XSS
         * @param {string} str - String to escape
         * @returns {string} Escaped string
         */
        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /**
         * Initialize popover system
         * Sets up event delegation for popover triggers
         */
        function init() {
            console.log('[Popover] Initializing popover system');
            
            // Event delegation for popover triggers
            document.addEventListener('click', function(event) {
                console.log('[Popover] Click detected on:', event.target, 'closest trigger:', event.target.closest('[data-aa-popover="1"]'));
                
                const trigger = event.target.closest('[data-aa-popover="1"]');

                if (trigger) {
                    console.log('[Popover] Trigger found!', trigger);
                    console.log('[Popover] Trigger classes:', trigger.className);
                    console.log('[Popover] Trigger data attributes:', {
                        staff: trigger.getAttribute('data-aa-popover-staff'),
                        services: trigger.getAttribute('data-aa-popover-services')
                    });
                    
                    event.preventDefault();
                    event.stopPropagation();

                    // Toggle: if same anchor, close; otherwise open new
                    if (isPopoverOpen && currentAnchor === trigger) {
                        console.log('[Popover] Closing popover (toggle)');
                        closePopover();
                        return;
                    }

                    // Read data attributes
                    const staff = trigger.getAttribute('data-aa-popover-staff') || '';
                    const services = trigger.getAttribute('data-aa-popover-services') || '';

                    // Build lines
                    const lines = [];
                    if (staff) {
                        lines.push('Staff: ' + staff);
                    }
                    if (services) {
                        lines.push('Servicios: ' + services);
                    }

                    console.log('[Popover] Lines to display:', lines);

                    // Open popover if we have content
                    if (lines.length > 0) {
                        console.log('[Popover] Opening popover with', lines.length, 'lines');
                        openPopover({
                            anchorEl: trigger,
                            lines: lines
                        });
                    } else {
                        console.warn('[Popover] No lines to display, skipping popover');
                    }

                    return;
                }

                // Click outside popover: close it
                if (isPopoverOpen) {
                    // Check if click is inside the popover itself
                    if (popoverElement && popoverElement.contains(event.target)) {
                        console.log('[Popover] Click inside popover, keeping it open');
                        return; // Don't close if clicking inside popover
                    }
                    console.log('[Popover] Click outside popover, closing');
                    closePopover();
                }
            }, true); // Use capture phase to catch events earlier

            // ESC key to close
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && isPopoverOpen) {
                    closePopover();
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
            open: openPopover,
            close: closePopover,
            isOpen: function() {
                return isPopoverOpen;
            }
        };
    })();

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

