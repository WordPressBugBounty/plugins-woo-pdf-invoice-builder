<?php
wp_enqueue_style('wcrbc-bootstrap',RednaoWooCommercePDFInvoice::$URL.'css/bootstrap/css/bootstrap.min.css');
wp_enqueue_style('wcrbc-bootstrap-theme',RednaoWooCommercePDFInvoice::$URL.'css/bootstrap/css/bootstrap-theme.min.css');
wp_enqueue_style('wcrbc-bootstrap-slider',RednaoWooCommercePDFInvoice::$URL.'css/bootstrap-slider/bootstrap-slider.min.css');
wp_enqueue_style('wcrbc-ladda',RednaoWooCommercePDFInvoice::$URL.'css/ladda/ladda.min.css');
wp_enqueue_style('wcrbc-ladda',RednaoWooCommercePDFInvoice::$URL.'css/ladda/ladda-themeless.min.css');
wp_enqueue_script('wcrbc-pdfbuilder-ladda-spin',RednaoWooCommercePDFInvoice::$URL.'js/lib/ladda/spin.js',array('jquery'));
wp_enqueue_script('wcrbc-pdfbuilder-ladda',RednaoWooCommercePDFInvoice::$URL.'js/lib/ladda/ladda.min.js',array('jquery','wcrbc-pdfbuilder-ladda-spin'));
wp_enqueue_script('jquery');
wp_enqueue_script('woopdfinvoice-errorresolver',RednaoWooCommercePDFInvoice::$URL.'js/screens/errorresolver/ErrorResolver.js',array('jquery'),RednaoWooCommercePDFInvoice::$FILE_VERSION);
global $wpdb;
$invoices=$result=$wpdb->get_results("SELECT invoice_id,name from ".RednaoWooCommercePDFInvoice::$INVOICE_TABLE,'ARRAY_A');


wp_localize_script('woopdfinvoice-errorresolver','rnErrorResolver',array(
    'nonce'=>wp_create_nonce('woopdfinvoice_errorresolver')
));
?>
<div class="bootstrap-wrapper">
    <h4><?php echo esc_html__('When creating an invoice or executing the preview are you getting any of these issues?','woo-pdf-invoice-builder');?></h4>
    <ul style="list-style: circle inside;margin-left:10px;">
        <li><?php echo esc_html__('Blank page','woo-pdf-invoice-builder');?></li>
        <li><?php echo esc_html__('500 error message','woo-pdf-invoice-builder');?></li>
    </ul>
     <h4><?php echo esc_html__('If so this page will try to help you find the problem (and hopefully solve it)','woo-pdf-invoice-builder');?></h4>

<hr/>

    <h4 style="margin-top: 50px;"><?php echo esc_html__('To begin please tell me which template is having the issue and when are you getting this error','woo-pdf-invoice-builder');?></h4>
    <div>
        <label style="font-weight: normal;"><?php echo esc_html__('Template that is having this problem:','woo-pdf-invoice-builder');?></label><select style="width: 200px;display: inline;" id="templateName" class="form-control">
            <?php
                foreach($invoices as $invoice)
                {
                    echo '<option value="'.esc_attr($invoice['invoice_id']).'">'.esc_html($invoice['name']).'</option>';
                }
            ?>
        </select>
    </div>
    <input value="preview" name="issueType" class="issueType" type="radio" style="margin:0;outline: none;" id="preview"/><label  for="preview" style="margin:0 0 0 10px;font-weight: normal;"> <?php echo esc_html__('I get this error in the invoice designer, after clicking Preview','woo-pdf-invoice-builder');?> </label><br/>
    <input value="order" name="issueType" class="issueType" type="radio" style="margin:0;outline: none;" id="invoiceCreation"/><label  for="invoiceCreation" style="margin:0 0 0 10px;font-weight: normal;"> <?php echo esc_html__('I get this error when i try to view or create the invoice of one of my WooCommerce orders','woo-pdf-invoice-builder');?> </label>
    <div style="margin-left: 10px;display: none;" id="orderDetail">
        <label><?php echo esc_html__('Which order number are you using?','woo-pdf-invoice-builder');?></label><input id="orderNumber" style="width: 100px;display: inline;margin-left: 4px;" class="form-control" type="text">
    </div>
    <div style="display: block;margin-top:10px;">
        <button data-style="expand-right"  id="analyze" style="display: none;" class="btn btn-success"><?php echo esc_html__('Thanks, now please click this button to start analyzing the problem','woo-pdf-invoice-builder');?></button>
    </div>

    <div id="ErrorDetail" style="display: none;">
        <h3><?php echo esc_html__('Ohhhh i found something, the invoice generation seems to be failing due this problem:','woo-pdf-invoice-builder');?></h3>
        <table class="table table table-striped">
            <tr>
                <th><?php echo esc_html__('Error Message','woo-pdf-invoice-builder');?></th>
                <td id="edErrorMessage"></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Error Number','woo-pdf-invoice-builder');?></th>
                <td id="edErrorNumber"></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Error File','woo-pdf-invoice-builder');?></th>
                <td id="edErrorFile"></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Error Line','woo-pdf-invoice-builder');?></th>
                <td id="edErrorLine"></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Error Context','woo-pdf-invoice-builder');?></th>
                <td id="edErrorContext"></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Error Detail','woo-pdf-invoice-builder');?></th>
                <td id="edErrorDetail"></td>
            </tr>

        </table>
        <h3><?php echo esc_html__('If by seeing this error you know how to fix it GREAT!, if not please contact support including this information in your ticket =)','woo-pdf-invoice-builder');?></h3>
    </div>
</div>
