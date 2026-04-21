var InvoiceList = (function () {
    function InvoiceList() {
        jQuery(function () {
            jQuery('.createInvoice').click(function () {
                if (rednaoPDFInvoiceParamsList.IsPR != "1" && rednaoPDFInvoiceParamsList.TemplateCount > 0)
                    alert('Sorry, the free version support only one invoice template, please edit the existing template or get the Pro Version');
                else
                    window.location.href = rednaoPDFInvoiceParamsList.AddNewURL;
            });
            jQuery('#fileToImport').change(function () {
                if (jQuery('#fileToImport')[0].files.length > 0) {
                    jQuery('#formImporter').submit();
                }
            });
            jQuery('#invoiceImport').click(function (e) {
                e.preventDefault();
                jQuery('#fileToImport').click();
            });

            // Confirm bulk delete
            jQuery(document).on('submit', 'form', function (e) {
                var action = jQuery('#bulk-action-selector-top').val() || jQuery('#bulk-action-selector-bottom').val();
                if (action === 'bulk_delete') {
                    var checked = jQuery('input[name="template_ids[]"]:checked');
                    if (checked.length === 0) {
                        alert('Please select at least one template.');
                        e.preventDefault();
                        return false;
                    }
                    if (!confirm('Are you sure you want to delete the selected templates?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
    }
    return InvoiceList;
}());
new InvoiceList();
//# sourceMappingURL=InvoiceList.js.map