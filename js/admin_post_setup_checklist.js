/**
 * MemberPress Post-Setup Checklist JavaScript
 *
 * Handles sidebar interactions, minimize/expand, dismiss functionality,
 * and AJAX communication for the post-setup checklist.
 */

(function() {
    'use strict';

    const MeprChecklist = {
        /**
         * Configuration from localized script data
         */
        config: {},

        /**
         * DOM elements
         */
        elements: {
            container: null,
            minimizeBtn: null,
            expandBtn: null,
            dismissBtn: null
        },

        /**
         * Cookie name for keeping checklist expanded after step click
         */
        KEEP_EXPANDED_COOKIE: 'mepr_checklist_keep_expanded',

        /**
         * Initialize the checklist
         */
        init: function() {
            this.config = window.MeprPostSetupChecklist || {};
            this.cacheElements();

            if (!this.elements.container) {
                return;
            }

            this.bindEvents();
            this.setInitialState();
            this.handleScroll();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.container = document.getElementById('mepr-post-setup-checklist');

            if (!this.elements.container) {
                return;
            }

            this.elements.minimizeBtn = this.elements.container.querySelector('.mepr-psc-minimize');
            this.elements.expandBtn = this.elements.container.querySelector('.mepr-psc-expand');
            this.elements.dismissBtn = this.elements.container.querySelector('.mepr-psc-dismiss');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Minimize button click.
            if (this.elements.minimizeBtn) {
                this.elements.minimizeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.minimize();
                });
            }

            // Dismiss button click.
            if (this.elements.dismissBtn) {
                this.elements.dismissBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.confirmDismiss();
                });
            }

            // Expand button click.
            if (this.elements.expandBtn) {
                this.elements.expandBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.expand();
                });
            }

            // Keyboard accessibility.
            this.elements.container.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.minimize();
                }
            });

            // Skip buttons click (using event delegation).
            this.elements.container.addEventListener('click', (e) => {
                const skipBtn = e.target.closest('.mepr-psc-step-skip');
                if (skipBtn) {
                    e.preventDefault();
                    const stepId = skipBtn.dataset.stepId;
                    if (stepId) {
                        this.skipStep(stepId, skipBtn);
                    }
                }

                // Install plugin button click.
                const installBtn = e.target.closest('.mepr-psc-install-plugin');
                if (installBtn) {
                    e.preventDefault();
                    this.installPlugin(installBtn);
                }

                // Activate plugin button click.
                const activateBtn = e.target.closest('.mepr-psc-activate-plugin');
                if (activateBtn) {
                    e.preventDefault();
                    this.activatePlugin(activateBtn);
                }

                // Step action link click - set cookie to keep checklist expanded.
                const stepLink = e.target.closest('.mepr-psc-step-action');
                if (stepLink && stepLink.tagName === 'A') {
                    const href = stepLink.getAttribute('href');
                    if (this.isMemberPressPageUrl(href)) {
                        this.setKeepExpandedCookie();
                    }
                }
            });
        },

        /**
         * Set initial state based on config
         */
        setInitialState: function() {
            // Check for expand_checklist URL parameter (from dashboard "View Checklist" link).
            const urlParams = new URLSearchParams(window.location.search);
            const shouldExpandFromUrl = urlParams.get('expand_checklist') === '1';

            if (shouldExpandFromUrl) {
                // Force expand and save state.
                this.expand();
                return;
            }

            // Check if we should keep the checklist expanded (user clicked a step link).
            if (this.shouldKeepExpanded()) {
                this.elements.container.classList.remove('is-minimized');
                document.body.classList.remove('mepr-post-setup-checklist-minimized');
                // Refresh the cookie to keep it alive during the setup flow.
                // Cookie will only be cleared when user manually minimizes.
                this.setKeepExpandedCookie();
                return;
            }

            if (this.config.is_minimized) {
                this.elements.container.classList.add('is-minimized');
                document.body.classList.add('mepr-post-setup-checklist-minimized');
            }
        },

        /**
         * Check if the "keep expanded" cookie is set
         *
         * @returns {boolean} True if the cookie is set
         */
        shouldKeepExpanded: function() {
            return document.cookie.split(';').some(function(cookie) {
                return cookie.trim().startsWith(MeprChecklist.KEEP_EXPANDED_COOKIE + '=');
            });
        },

        /**
         * Set the "keep expanded" cookie
         */
        setKeepExpandedCookie: function() {
            // Set cookie that expires in 5 minutes (more than enough for page navigation).
            document.cookie = this.KEEP_EXPANDED_COOKIE + '=1; path=/; max-age=300; SameSite=Lax';
        },

        /**
         * Clear the "keep expanded" cookie
         */
        clearKeepExpandedCookie: function() {
            document.cookie = this.KEEP_EXPANDED_COOKIE + '=; path=/; max-age=0; SameSite=Lax';
        },

        /**
         * Check if a URL points to a MemberPress-related page
         *
         * @param {string} url - The URL to check
         * @returns {boolean} True if the URL is a MemberPress page
         */
        isMemberPressPageUrl: function(url) {
            if (!url) {
                return false;
            }

            // Check for MemberPress admin pages and post types.
            return url.includes('page=memberpress') ||
                   url.includes('post_type=memberpress') ||
                   url.includes('post_type=mp-');
        },

        /**
         * Handle scroll to adjust sidebar position
         */
        handleScroll: function() {
            // MemberPress admin header height.
            const headerHeight = 72;

            const updatePosition = () => {
                const scrollY = window.scrollY || window.pageYOffset;

                // If scrolled past the header, stick to admin bar.
                if (scrollY >= headerHeight) {
                    this.elements.container.classList.add('is-scrolled');
                } else {
                    this.elements.container.classList.remove('is-scrolled');
                }
            };

            // Initial check.
            updatePosition();

            // Listen for scroll events.
            window.addEventListener('scroll', updatePosition, { passive: true });
        },

        /**
         * Minimize the checklist sidebar
         */
        minimize: function() {
            this.elements.container.classList.add('is-minimized');
            document.body.classList.add('mepr-post-setup-checklist-minimized');

            // Clear the "keep expanded" cookie when user manually minimizes.
            this.clearKeepExpandedCookie();

            // Save state via AJAX.
            this.sendRequest('mepr_post_setup_checklist_minimize')
                .catch(() => {
                    console.warn('MemberPress Checklist: Failed to save minimized state');
                });
        },

        /**
         * Expand the checklist sidebar
         */
        expand: function() {
            this.elements.container.classList.remove('is-minimized');
            document.body.classList.remove('mepr-post-setup-checklist-minimized');

            // Save state via AJAX.
            this.sendRequest('mepr_post_setup_checklist_expand')
                .catch(() => {
                    console.warn('MemberPress Checklist: Failed to save expanded state');
                });
        },

        /**
         * Confirm and dismiss the checklist
         */
        confirmDismiss: function() {
            const confirmMessage = this.config.i18n?.confirm
                || 'Are you sure you want to dismiss the setup checklist?';

            if (confirm(confirmMessage)) {
                this.dismiss();
            }
        },

        /**
         * Dismiss the checklist
         */
        dismiss: function() {
            // Animate out.
            this.elements.container.style.opacity = '0';
            this.elements.container.style.transform = 'translateX(100%)';

            // Remove body class and margin.
            document.body.classList.remove('mepr-has-post-setup-checklist', 'mepr-post-setup-checklist-minimized');

            // Remove from DOM after animation.
            setTimeout(() => {
                this.elements.container?.remove();
            }, 300);

            // Save state via AJAX.
            this.sendRequest('mepr_post_setup_checklist_dismiss')
                .catch(() => {
                    console.warn('MemberPress Checklist: Failed to save dismissed state');
                });
        },

        /**
         * Install a plugin via AJAX
         *
         * @param {HTMLElement} button - The install button element
         */
        installPlugin: function(button) {
            const stepEl = button.closest('.mepr-psc-step');
            const downloadUrl = stepEl?.dataset.downloadUrl;
            const addonType = stepEl?.dataset.addonType || 'plugin';

            if (!downloadUrl) {
                console.warn('MemberPress Checklist: No download URL found');
                return;
            }

            // Disable button and show loading state.
            button.disabled = true;
            button.classList.add('is-loading');
            const originalText = button.textContent.trim();
            button.childNodes[0].textContent = this.config.i18n?.installing || 'Installing...';

            const formData = new FormData();
            formData.append('action', 'mepr_addon_install');
            formData.append('_ajax_nonce', this.config.addons_nonce);
            formData.append('plugin', downloadUrl);
            formData.append('type', addonType === 'addon' ? 'add-on' : 'plugin');

            fetch(this.config.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload to show updated state.
                    window.location.reload();
                } else {
                    throw new Error(data.data || 'Install failed');
                }
            })
            .catch(() => {
                button.disabled = false;
                button.classList.remove('is-loading');
                button.childNodes[0].textContent = originalText;
                alert(this.config.i18n?.install_err || 'Installation failed. Please try again.');
            });
        },

        /**
         * Activate a plugin via AJAX
         *
         * @param {HTMLElement} button - The activate button element
         */
        activatePlugin: function(button) {
            const stepEl = button.closest('.mepr-psc-step');
            const pluginFile = stepEl?.dataset.pluginFile;

            if (!pluginFile) {
                console.warn('MemberPress Checklist: No plugin file found');
                return;
            }

            // Disable button and show loading state.
            button.disabled = true;
            button.classList.add('is-loading');
            const originalText = button.textContent.trim();
            button.childNodes[0].textContent = this.config.i18n?.activating || 'Activating...';

            const formData = new FormData();
            formData.append('action', 'mepr_addon_activate');
            formData.append('_ajax_nonce', this.config.addons_nonce);
            formData.append('plugin', pluginFile);

            fetch(this.config.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload to show updated state.
                    window.location.reload();
                } else {
                    throw new Error(data.data || 'Activation failed');
                }
            })
            .catch(() => {
                button.disabled = false;
                button.classList.remove('is-loading');
                button.childNodes[0].textContent = originalText;
                alert(this.config.i18n?.activate_err || 'Activation failed. Please try again.');
            });
        },

        /**
         * Skip a step
         *
         * @param {string} stepId - The step ID to skip
         * @param {HTMLElement} button - The skip button element
         */
        skipStep: function(stepId, button) {
            // Disable button to prevent double-clicks.
            button.disabled = true;
            button.textContent = this.config.i18n?.skipping || 'Skipping...';

            this.sendRequest('mepr_post_setup_checklist_skip_step', { step_id: stepId })
                .then(() => {
                    // Reload the page to reflect the new state.
                    window.location.reload();
                })
                .catch(() => {
                    // Re-enable button on error.
                    button.disabled = false;
                    button.textContent = this.config.i18n?.skip || 'Skip';
                    console.warn('MemberPress Checklist: Failed to skip step');
                });
        },

        /**
         * Send an AJAX request
         *
         * @param {string} action - The WordPress AJAX action name
         * @param {Object} extraData - Additional data to send
         * @returns {Promise}
         */
        sendRequest: function(action, extraData = {}) {
            const formData = new FormData();

            formData.append('action', action);
            formData.append('nonce', this.config.nonce);

            Object.entries(extraData).forEach(([key, value]) => {
                formData.append(key, value);
            });

            return fetch(this.config.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.data || 'Request failed');
                }
                return data;
            });
        }
    };

    // Initialize when DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            MeprChecklist.init();
        });
    } else {
        MeprChecklist.init();
    }

    // Expose for external use if needed.
    window.MeprChecklist = MeprChecklist;

})();
