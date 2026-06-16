/**
 * Backend module JavaScript for nr_temporal_cache
 *
 * TYPO3 v13 ES6 module for temporal cache management
 */
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';

class TemporalCacheModule {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    init() {
        // Content harmonization
        this.initializeHarmonization();

        // Wizard preset application
        this.initializeWizard();

        // Keyboard navigation
        this.initializeKeyboardNavigation();
    }

    /**
     * Initialize harmonization functionality for content table
     */
    initializeHarmonization() {
        const selectAllCheckbox = document.getElementById('select-all');
        const contentCheckboxes = document.querySelectorAll('.content-checkbox');
        const harmonizeBtn = document.getElementById('harmonize-selected-btn');

        if (!harmonizeBtn) {
            return; // Not on content page
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                contentCheckboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
                this.updateHarmonizeButton();
            });
        }

        contentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateHarmonizeButton());
        });

        harmonizeBtn.addEventListener('click', () => this.performHarmonization());
    }

    /**
     * Update harmonize button state based on selection
     */
    updateHarmonizeButton() {
        const harmonizeBtn = document.getElementById('harmonize-selected-btn');
        const checkedCount = document.querySelectorAll('.content-checkbox:checked').length;

        if (harmonizeBtn) {
            harmonizeBtn.disabled = checkedCount === 0;

            // Get localized text from data attributes or fallback
            const labelSingle = harmonizeBtn.dataset.labelSingle || 'Harmonize selected';
            const labelMultiple = harmonizeBtn.dataset.labelMultiple || 'Harmonize {count} selected';

            harmonizeBtn.textContent = checkedCount > 0
                ? labelMultiple.replace('{count}', checkedCount)
                : labelSingle;
        }
    }

    /**
     * Perform harmonization with TYPO3 Modal confirmation
     */
    async performHarmonization() {
        // Carry both uid and table so pages and content elements are routed to the
        // correct database table (uids are not unique across tables).
        const selectedItems = Array.from(document.querySelectorAll('.content-checkbox:checked'))
            .map(cb => ({ uid: parseInt(cb.dataset.uid, 10), table: cb.dataset.table }));

        if (selectedItems.length === 0) {
            return;
        }

        const harmonizeBtn = document.getElementById('harmonize-selected-btn');
        const harmonizeUri = harmonizeBtn.dataset.actionUri;

        if (!harmonizeUri) {
            Notification.error(
                'Configuration Error',
                'Harmonization action URI not configured'
            );
            return;
        }

        // Use TYPO3 Modal for confirmation
        Modal.confirm(
            'Confirm Harmonization',
            `Harmonize ${selectedItems.length} content elements? This will align temporal boundaries to configured time slots.`,
            Modal.SeverityEnum.warning,
            [
                {
                    text: 'Cancel',
                    active: true,
                    btnClass: 'btn-default',
                    trigger: () => Modal.dismiss()
                },
                {
                    text: 'Harmonize',
                    btnClass: 'btn-warning',
                    trigger: () => {
                        Modal.dismiss();
                        this.executeHarmonization(harmonizeUri, selectedItems);
                    }
                }
            ]
        );
    }

    /**
     * Execute harmonization AJAX call
     */
    async executeHarmonization(uri, selectedItems) {
        try {
            const response = await fetch(uri, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    content: selectedItems,
                    dryRun: false
                })
            });

            const data = await response.json();

            if (data.success) {
                Notification.success(
                    'Harmonization Successful',
                    data.message
                );

                // Reload page after short delay to show updated data
                setTimeout(() => window.location.reload(), 1500);
            } else {
                Notification.error(
                    'Harmonization Failed',
                    data.message
                );
            }
        } catch (error) {
            Notification.error(
                'Error',
                'Failed to harmonize content: ' + error.message
            );
        }
    }

    /**
     * Initialize wizard preset functionality
     */
    initializeWizard() {
        const customForm = document.getElementById('custom-config-form');

        if (customForm) {
            customForm.addEventListener('submit', (e) => this.handleWizardSubmit(e));
        }

        // Preset buttons
        document.querySelectorAll('[data-preset]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyPreset(e.target.dataset.preset);
            });
        });
    }

    /**
     * Apply configuration preset
     */
    async applyPreset(presetKey) {
        Modal.confirm(
            'Apply Preset Configuration',
            `Apply "${presetKey}" preset? This configuration should be applied in Extension Configuration.`,
            Modal.SeverityEnum.info,
            [
                {
                    text: 'Cancel',
                    active: true,
                    btnClass: 'btn-default',
                    trigger: () => Modal.dismiss()
                },
                {
                    text: 'Continue',
                    btnClass: 'btn-primary',
                    trigger: () => {
                        Modal.dismiss();

                        Notification.info(
                            'Configuration Preset',
                            'Please apply this configuration in:\nAdmin Tools → Settings → Extension Configuration → nr_temporal_cache'
                        );

                        // Redirect to summary
                        const summaryUri = document.querySelector('[data-wizard-summary-uri]')?.dataset.wizardSummaryUri;
                        if (summaryUri) {
                            window.location.href = summaryUri;
                        }
                    }
                }
            ]
        );
    }

    /**
     * Handle wizard custom configuration submission
     */
    handleWizardSubmit(e) {
        e.preventDefault();

        Notification.info(
            'Configuration',
            'Please apply this configuration in:\nAdmin Tools → Settings → Extension Configuration → nr_temporal_cache'
        );

        const summaryUri = e.target.dataset.summaryUri;
        if (summaryUri) {
            window.location.href = summaryUri;
        }
    }

    /**
     * Initialize keyboard navigation
     */
    initializeKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + A: Select all (on content page with checkboxes)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                const selectAll = document.getElementById('select-all');
                if (selectAll && document.activeElement.tagName !== 'INPUT') {
                    e.preventDefault();
                    selectAll.checked = true;
                    selectAll.dispatchEvent(new Event('change'));
                }
            }
        });
    }
}

// Initialize and export
export default new TemporalCacheModule();
