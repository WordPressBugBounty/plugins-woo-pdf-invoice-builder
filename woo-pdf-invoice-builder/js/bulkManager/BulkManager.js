/**
 * Bulk Action Manager — Queue-based PDF processing
 * Intercepts WooCommerce bulk actions and processes PDFs one at a time
 * with a progress popup to avoid server memory exhaustion.
 */
(function ($) {
    'use strict';

    var BULK_ACTIONS = ['rnview_invoice', 'rnprint_invoice', 'rndownload_invoice'];

    var BulkManager = {
        batchId: null,
        batchNonce: null,
        orderIds: [],
        currentIndex: 0,
        actionType: '',
        templateId: -1,
        cancelled: false,
        $overlay: null,

        /**
         * Initialize the bulk manager
         */
        init: function () {
            this.createInvoiceDropdowns();
            this.interceptBulkActions();
        },

        /**
         * Create invoice template dropdowns (existing functionality)
         */
        createInvoiceDropdowns: function () {
            if (typeof bulkManagerVar === 'undefined' || bulkManagerVar.invoices.length <= 1) {
                return;
            }
            this._createDropDown('bulk-action-selector-top');
        },

        _createDropDown: function (selector) {
            var $select = $('<select id="invoiceTemplateId_' + selector + '" name="rnTemplateId"></select>');
            var $defaultOption = $('<option></option>').text(bulkManagerVar.selectAPDFTemplate);
            $select.append($defaultOption);

            var first = true;
            for (var i = 0; i < bulkManagerVar.invoices.length; i++) {
                var invoice = bulkManagerVar.invoices[i];
                var $option = $('<option></option>');
                if (first) $option.attr('selected', 'selected');
                first = false;
                $option.text(invoice.Name);
                $option.val(invoice.InvoiceID);
                $select.append($option);
            }
            $select.insertAfter($('#' + selector));
        },

        /**
         * Intercept the "Apply" button on the orders page
         */
        interceptBulkActions: function () {
            var self = this;
            var isProcessing = false;

            // Intercept #doaction button click (primary user interaction)
            $('#doaction').on('click', function (e) {
                var selectedAction = $('#bulk-action-selector-top').val();

                if (BULK_ACTIONS.indexOf(selectedAction) === -1) {
                    return; // Not our action, let WooCommerce handle it
                }

                if (isProcessing) {
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                isProcessing = true;

                var $form = $(this).closest('form');
                if (!$form.length) {
                    $form = $('#posts-filter, #wc-orders-filter').first();
                }

                self.startBulkProcess($form, selectedAction, function () {
                    isProcessing = false;
                });
            });
        },

        /**
         * Gather selected order IDs and start the process
         */
        startBulkProcess: function ($form, actionType, onDone) {
            var self = this;
            self.actionType = actionType;
            self.cancelled = false;
            self.currentIndex = 0;
            self.onDone = onDone || function () {};

            // Block free version users from using bulk actions
            if (!bulkManagerVar.isPr) {
                alert(bulkManagerVar.fullVersionOnly || 'Sorry, this feature is only available in the full version.');
                self.onDone();
                return;
            }

            // Gather selected order IDs (works for both HPOS and legacy)
            var orderIds = [];
            // HPOS uses cb-select-all-1, legacy uses post[]
            $form.find('input[name="id[]"]:checked, input[name="post[]"]:checked').each(function () {
                orderIds.push(parseInt($(this).val(), 10));
            });

            if (orderIds.length === 0) {
                alert(bulkManagerVar.noOrdersSelected || 'Please select at least one order.');
                self.onDone();
                return;
            }

            // Sort ascending so oldest orders are processed first
            orderIds.sort(function (a, b) { return a - b; });
            self.orderIds = orderIds;

            // Get the template ID from the dropdown
            var $templateDropdown = $('#invoiceTemplateId_bulk-action-selector-top');
            if ($templateDropdown.length > 0 && $templateDropdown.val()) {
                self.templateId = parseInt($templateDropdown.val(), 10) || -1;
            } else {
                self.templateId = -1;
            }

            // Show progress popup
            self.showProgressPopup();

            // Step 1: Init the batch
            self.initBatch();
        },

        /**
         * Build and show the progress popup
         */
        showProgressPopup: function () {
            var self = this;
            var actionLabel = self.getActionLabel();
            var totalCount = self.orderIds.length;

            var html =
                '<div class="rn-bulk-overlay" id="rn-bulk-overlay">' +
                '  <div class="rn-bulk-modal">' +
                '    <h3 class="rn-bulk-title">' + self.escapeHtml(actionLabel) + '</h3>' +
                '    <p class="rn-bulk-subtitle">' + self.escapeHtml(bulkManagerVar.processingPDFs || 'Processing PDF invoices...') + '</p>' +
                '    <div class="rn-bulk-progress-wrap">' +
                '      <div class="rn-bulk-progress-bar" id="rn-bulk-progress-bar"></div>' +
                '    </div>' +
                '    <div class="rn-bulk-counter" id="rn-bulk-counter">' +
                '      ' + self.escapeHtml(bulkManagerVar.preparing || 'Preparing...') +
                '    </div>' +
                '    <div class="rn-bulk-status" id="rn-bulk-status"></div>' +
                '    <div class="rn-bulk-error" id="rn-bulk-error"></div>' +
                '    <div class="rn-bulk-btn-row">' +
                '      <button type="button" class="rn-bulk-btn-cancel" id="rn-bulk-cancel">' +
                '        ' + self.escapeHtml(bulkManagerVar.cancel || 'Cancel') +
                '      </button>' +
                '    </div>' +
                '  </div>' +
                '</div>';

            $('body').append(html);
            self.$overlay = $('#rn-bulk-overlay');

            // Trigger fade-in
            requestAnimationFrame(function () {
                self.$overlay.addClass('rn-visible');
            });

            // Cancel button handler
            $('#rn-bulk-cancel').on('click', function () {
                self.cancelled = true;
                $(this).prop('disabled', true).text(bulkManagerVar.cancelling || 'Cancelling...');
            });
        },

        /**
         * Close the popup
         */
        closePopup: function () {
            var self = this;
            if (self.$overlay) {
                self.$overlay.removeClass('rn-visible');
                setTimeout(function () {
                    self.$overlay.remove();
                    self.$overlay = null;
                    if (self.onDone) {
                        self.onDone();
                    }
                }, 300);
            } else {
                if (self.onDone) {
                    self.onDone();
                }
            }
        },

        /**
         * Update progress display
         */
        updateProgress: function (current, total, statusText) {
            var percent = Math.round((current / total) * 100);
            $('#rn-bulk-progress-bar').css('width', percent + '%');
            $('#rn-bulk-counter').text(
                (bulkManagerVar.processingXofY || 'Processing {current} of {total}')
                    .replace('{current}', current)
                    .replace('{total}', total)
            );
            if (statusText) {
                $('#rn-bulk-status').text(statusText);
            }
        },

        showError: function (message) {
            $('#rn-bulk-error').text(message).addClass('rn-visible');
            $('#rn-bulk-cancel').text(bulkManagerVar.close || 'Close').prop('disabled', false);
            var self = this;
            $('#rn-bulk-cancel').off('click').on('click', function () {
                self.closePopup();
            });
        },

        /**
         * AJAX Step 1: Initialize batch
         */
        initBatch: function () {
            var self = this;

            $.ajax({
                url: bulkManagerVar.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'rednao_bulk_init',
                    nonce: bulkManagerVar.bulkNonce,
                    actionType: self.actionType,
                    templateId: self.templateId
                },
                success: function (response) {
                    if (!response.success) {
                        self.showError(response.data ? response.data.message : 'Failed to initialize batch');
                        return;
                    }

                    self.batchId = response.data.batchId;
                    self.batchNonce = response.data.nonce;

                    // Start processing the first order
                    self.updateProgress(0, self.orderIds.length);
                    self.processNext();
                },
                error: function () {
                    self.showError(bulkManagerVar.serverError || 'Server error. Please try again.');
                }
            });
        },

        /**
         * AJAX Step 2: Process orders one by one
         */
        processNext: function () {
            var self = this;

            if (self.cancelled) {
                self.closePopup();
                return;
            }

            if (self.currentIndex >= self.orderIds.length) {
                // All done — finalize
                self.finalizeBatch();
                return;
            }

            var orderId = self.orderIds[self.currentIndex];

            self.updateProgress(
                self.currentIndex + 1,
                self.orderIds.length,
                (bulkManagerVar.generatingPDF || 'Generating PDF for order #{orderId}...')
                    .replace('{orderId}', orderId)
            );

            $.ajax({
                url: bulkManagerVar.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'rednao_bulk_process_single',
                    batchId: self.batchId,
                    nonce: self.batchNonce,
                    orderId: orderId,
                    templateId: self.templateId
                },
                success: function (response) {
                    if (!response.success) {
                        self.showError(
                            (bulkManagerVar.errorProcessingOrder || 'Error processing order #{orderId}: {message}')
                                .replace('{orderId}', orderId)
                                .replace('{message}', response.data ? response.data.message : 'Unknown error')
                        );
                        return;
                    }

                    self.currentIndex++;
                    self.processNext();
                },
                error: function () {
                    self.showError(
                        (bulkManagerVar.errorProcessingOrder || 'Error processing order #{orderId}: {message}')
                            .replace('{orderId}', orderId)
                            .replace('{message}', 'Server error')
                    );
                }
            });
        },

        /**
         * AJAX Step 3: Finalize — merge, zip, or print
         */
        finalizeBatch: function () {
            var self = this;

            $('#rn-bulk-counter').text(bulkManagerVar.finalizing || 'Finalizing...');
            $('#rn-bulk-status').text(
                self.actionType === 'rndownload_invoice'
                    ? (bulkManagerVar.creatingZip || 'Creating ZIP file...')
                    : (bulkManagerVar.mergingPDFs || 'Merging PDFs...')
            );
            $('#rn-bulk-progress-bar').css('width', '100%');

            $.ajax({
                url: bulkManagerVar.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'rednao_bulk_finalize',
                    batchId: self.batchId,
                    nonce: self.batchNonce
                },
                success: function (response) {
                    if (!response.success) {
                        self.showError(response.data ? response.data.message : 'Failed to finalize');
                        return;
                    }

                    self.closePopup();

                    var result = response.data;

                    if (result.type === 'download') {
                        // Trigger download
                        self.triggerDownload(result.url);
                    } else if (result.type === 'view') {
                        // Open merged PDF in new tab
                        window.open(result.url, '_blank');
                    } else if (result.type === 'print') {
                        if (result.printed) {
                            alert(bulkManagerVar.printSuccess || 'Invoices sent to printer successfully!');
                        } else {
                            alert(bulkManagerVar.printFailed || 'Could not send invoices to printer. Please check your printer configuration.');
                        }
                    }
                },
                error: function () {
                    self.showError(bulkManagerVar.serverError || 'Server error during finalization. Please try again.');
                }
            });
        },

        /**
         * Trigger a file download via hidden iframe/link
         */
        triggerDownload: function (url) {
            var $a = $('<a></a>')
                .attr('href', url)
                .attr('download', 'documents.zip')
                .css('display', 'none');
            $('body').append($a);
            $a[0].click();
            setTimeout(function () {
                $a.remove();
            }, 5000);
        },

        /**
         * Get a human-readable label for the current action
         */
        getActionLabel: function () {
            switch (this.actionType) {
                case 'rnview_invoice':
                    return bulkManagerVar.bulkView || 'Bulk View Invoices';
                case 'rnprint_invoice':
                    return bulkManagerVar.bulkPrint || 'Bulk Print Invoices';
                case 'rndownload_invoice':
                    return bulkManagerVar.bulkDownload || 'Bulk Download Invoices';
                default:
                    return 'Processing';
            }
        },

        /**
         * Simple HTML escaping
         */
        escapeHtml: function (text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    $(function () {
        BulkManager.init();
    });

})(jQuery);