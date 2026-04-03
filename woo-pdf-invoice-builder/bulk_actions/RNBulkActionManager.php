<?php

namespace rnwcinv\bulk_actions;

use RednaoWooCommercePDFInvoice;

class RNBulkActionManager
{
    public function InitializeHooks()
    {
        add_filter("bulk_actions-woocommerce_page_wc-orders", array($this, 'AddBulkActions'));
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'HandleBulkAction'), 10, 3);
        add_filter("bulk_actions-edit-shop_order", array($this, 'AddBulkActions'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'HandleBulkAction'), 10, 3);
        add_action('admin_enqueue_scripts', array($this, 'EnqueueScript'));

        if (RednaoWooCommercePDFInvoice::IsPR()) {
            // Register the premium AJAX handler (schedules its own cleanup cron)
            new \rnwcinv\pr\bulk_actions\RNBulkActionAjax();
        }
    }

    public function EnqueueScript()
    {
        $screen = get_current_screen();

        // Support both HPOS and legacy screen detection
        $isOrderScreen = false;
        if ($screen != null) {
            if ($screen->post_type == 'shop_order') {
                $isOrderScreen = true;
            }
            if (function_exists('wc_get_page_screen_id') && $screen->id === wc_get_page_screen_id('shop-order')) {
                $isOrderScreen = true;
            }
        }

        if (!$isOrderScreen) {
            return;
        }

        // Enqueue the bulk manager JS
        wp_enqueue_script(
            'rednao_pdfinv_bulk_manager',
            RednaoWooCommercePDFInvoice::$URL . 'js/bulkManager/BulkManager.js',
            array('jquery'),
            RednaoWooCommercePDFInvoice::$FILE_VERSION,
            true
        );

        // Enqueue the progress popup CSS
        wp_enqueue_style(
            'rednao_pdfinv_bulk_actions_css',
            RednaoWooCommercePDFInvoice::$URL . 'css/bulkActions.css',
            array(),
            RednaoWooCommercePDFInvoice::$FILE_VERSION
        );

        global $wpdb;
        $invoices = $wpdb->get_results('select invoice_id InvoiceID, name Name from ' . RednaoWooCommercePDFInvoice::$INVOICE_TABLE . ' order by name');
        $invoices = apply_filters('rnwcinv_bulk_invoices', $invoices);

        wp_localize_script('rednao_pdfinv_bulk_manager', 'bulkManagerVar', array(
            'invoices' => $invoices,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'bulkNonce' => wp_create_nonce('rednao_bulk_action_nonce'),
            'isPr' => RednaoWooCommercePDFInvoice::IsPR(),
            'selectAPDFTemplate' => __("Select a pdf template", "woo-pdf-invoice-builder"),

            // Progress popup translations
            'processingPDFs' => __("Processing PDF invoices...", "woo-pdf-invoice-builder"),
            'preparing' => __("Preparing...", "woo-pdf-invoice-builder"),
            'cancel' => __("Cancel", "woo-pdf-invoice-builder"),
            'cancelling' => __("Cancelling...", "woo-pdf-invoice-builder"),
            'close' => __("Close", "woo-pdf-invoice-builder"),
            'finalizing' => __("Finalizing...", "woo-pdf-invoice-builder"),
            'creatingZip' => __("Creating ZIP file...", "woo-pdf-invoice-builder"),
            'mergingPDFs' => __("Merging PDFs...", "woo-pdf-invoice-builder"),
            'processingXofY' => __("Processing {current} of {total}", "woo-pdf-invoice-builder"),
            'generatingPDF' => __("Generating PDF for order #{orderId}...", "woo-pdf-invoice-builder"),
            'noOrdersSelected' => __("Please select at least one order.", "woo-pdf-invoice-builder"),
            'serverError' => __("Server error. Please try again.", "woo-pdf-invoice-builder"),
            'errorProcessingOrder' => __("Error processing order #{orderId}: {message}", "woo-pdf-invoice-builder"),
            'printSuccess' => __("Invoices sent to printer successfully!", "woo-pdf-invoice-builder"),
            'printFailed' => __("Could not send invoices to printer. Please check your printer configuration.", "woo-pdf-invoice-builder"),
            'bulkView' => __("Bulk View Invoices", "woo-pdf-invoice-builder"),
            'bulkPrint' => __("Bulk Print Invoices", "woo-pdf-invoice-builder"),
            'bulkDownload' => __("Bulk Download Invoices", "woo-pdf-invoice-builder"),
            'fullVersionOnly' => __("Sorry, this feature is only available in the full version.", "woo-pdf-invoice-builder"),
        ));
    }

    /**
     * Handle bulk action — now just returns redirect since processing is done via AJAX
     * This is kept as a safety fallback in case the JS interception doesn't fire
     */
    public function HandleBulkAction($redirect_to, $action, $post_ids)
    {
        if (!RednaoWooCommercePDFInvoice::IsPR()) {
            return $redirect_to;
        }

        // All bulk actions are now handled client-side via AJAX queue
        // This handler only fires if JS fails to intercept (e.g. noscript)
        if (in_array($action, array('rnview_invoice', 'rnprint_invoice', 'rndownload_invoice'))) {
            // Add a query arg to inform the user to enable JavaScript
            return add_query_arg(array('rn_bulk_notice' => 'js_required'), $redirect_to);
        }

        return $redirect_to;
    }

    public function AddBulkActions($actions)
    {
        if (RednaoWooCommercePDFInvoice::IsPR()) {
            $actions['rnview_invoice'] = __('Bulk view invoices', 'woo-pdf-invoice-builder');
            $actions['rnprint_invoice'] = __('Bulk print invoices', 'woo-pdf-invoice-builder');
            $actions['rndownload_invoice'] = __('Bulk download invoices', 'woo-pdf-invoice-builder');
        } else {
            $actions['rnview_invoice'] = __('Bulk view invoices (full version only)', 'woo-pdf-invoice-builder');
            $actions['rnprint_invoice'] = __('Bulk print invoices (full version only)', 'woo-pdf-invoice-builder');
            $actions['rndownload_invoice'] = __('Bulk download invoices (full version only)', 'woo-pdf-invoice-builder');
        }
        return $actions;
    }
}