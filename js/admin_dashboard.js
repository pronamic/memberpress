/**
 * MemberPress Dashboard JavaScript
 */

(function() {
    'use strict';

    const MeprDashboardApp = {
        /**
         * Initialize the dashboard.
         */
        init() {
            this.bindEvents();
            this.initFirstSaleModal();
        },

        /**
         * Bind event handlers.
         */
        bindEvents() {
            // Dismiss setup notice.
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mepr-dismiss-setup-notice')) {
                    this.dismissSetupNotice(e);
                }
            });

            // Dismiss NBA recommendation.
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mepr-nba-dismiss')) {
                    this.dismissNBA(e);
                }
            });

            // Close first sale modal.
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mepr-first-sale-close')) {
                    this.closeFirstSaleModal(e);
                }
            });
        },

        /**
         * Initialize the first sale celebration modal.
         */
        initFirstSaleModal() {
            const modal = document.getElementById('mepr-first-sale-modal');

            if (modal) {
                // Prevent body scroll when modal is open.
                document.body.style.overflow = 'hidden';
            }
        },

        /**
         * Close the first sale celebration modal.
         *
         * @param {Event} e Click event.
         */
        closeFirstSaleModal(e) {
            e.preventDefault();

            const button = e.target.closest('.mepr-first-sale-close');
            const modal = document.getElementById('mepr-first-sale-modal');

            if (!button || !modal) {
                return;
            }

            button.disabled = true;

            const formData = new FormData();
            formData.append('action', 'mepr_dashboard_celebrate_first_sale');
            formData.append('nonce', button.dataset.nonce || MeprDashboard.nonce);

            fetch(MeprDashboard.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    // Close modal regardless of success.
                    modal.style.animation = 'meprFadeIn 0.2s ease reverse';
                    document.body.style.overflow = '';

                    setTimeout(() => {
                        modal.remove();
                    }, 200);
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                    // Still close modal on error.
                    modal.remove();
                    document.body.style.overflow = '';
                });
        },

        /**
         * Dismiss the setup notice.
         *
         * @param {Event} e Click event.
         */
        dismissSetupNotice(e) {
            e.preventDefault();

            const button = e.target.closest('.mepr-dismiss-setup-notice');
            const notice = document.getElementById('mepr-dashboard-setup-notice');

            if (!button || !notice) {
                return;
            }

            const confirmMessage = MeprDashboard.i18n?.dismiss_setup_confirm
                || 'Are you sure you want to dismiss the setup checklist? You can always access setup options from the Settings menu.';

            if (!confirm(confirmMessage)) {
                return;
            }

            button.disabled = true;

            const formData = new FormData();
            formData.append('action', 'mepr_dashboard_dismiss_setup_notice');
            formData.append('nonce', MeprDashboard.nonce);

            fetch(MeprDashboard.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notice.style.transition = 'opacity 0.2s ease, height 0.2s ease, padding 0.2s ease, margin 0.2s ease';
                        notice.style.opacity = '0';
                        notice.style.height = '0';
                        notice.style.padding = '0';
                        notice.style.margin = '0';
                        notice.style.overflow = 'hidden';

                        setTimeout(() => {
                            notice.remove();
                        }, 200);
                    } else {
                        button.disabled = false;
                        console.error('Failed to dismiss notice:', data.data);
                    }
                })
                .catch(error => {
                    button.disabled = false;
                    console.error('AJAX error:', error);
                });
        },

        /**
         * Dismiss a Next Best Action recommendation.
         *
         * @param {Event} e Click event.
         */
        dismissNBA(e) {
            e.preventDefault();

            const button = e.target.closest('.mepr-nba-dismiss');
            const card = document.getElementById('mepr-nba-card');
            const actionId = button?.dataset.actionId;

            if (!button || !card || !actionId) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'mepr_dashboard_dismiss_nba');
            formData.append('action_id', actionId);
            formData.append('nonce', MeprDashboard.nonce);

            fetch(MeprDashboard.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const content = card.querySelector('.mepr-nba-content');

                        if (content) {
                            // Fade out content.
                            content.style.transition = 'opacity 0.2s ease';
                            content.style.opacity = '0';

                            setTimeout(() => {
                                content.innerHTML = '<p>' + MeprDashboard.i18n.dismiss + '</p>';
                                content.classList.add('mepr-nba-empty');
                                content.style.opacity = '1';
                            }, 200);
                        }

                        // Hide the dismiss button.
                        button.style.display = 'none';

                        // Reload the page to get next recommendation.
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        console.error('Failed to dismiss NBA:', data.data);
                    }
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                });
        }
    };

    // Initialize when document is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => MeprDashboardApp.init());
    } else {
        MeprDashboardApp.init();
    }

})();
