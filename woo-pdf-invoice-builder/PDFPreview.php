<?php
if(!current_user_can('manage_options'))
    die('Forbidden');
$rest_json = file_get_contents("php://input");
$options=stripslashes($_POST['data']);
$options=json_decode($options);
require_once 'PDFGenerator.php';

$pageOptions=$options->pageOptions;

// Security: prevent non-SuperAdmins from previewing templates with dynamic PHP code
require_once RednaoWooCommercePDFInvoice::$DIR.'Managers/SaveManager.php';
$pagesJson = isset($pageOptions->pages) ? json_encode($pageOptions->pages) : '';
$containerOptionsJson = isset($pageOptions->containerOptions) ? json_encode($pageOptions->containerOptions) : '';
$dynamicCodeError = SaveManager::ValidateDynamicCodeSecurity(0, $pagesJson, $containerOptionsJson);
if($dynamicCodeError !== null)
    die($dynamicCodeError);
$previewType=$options->previewType;
$orderNumberToPreview='';
if(isset($options->orderNumberToPreview))
    $orderNumberToPreview=$options->orderNumberToPreview;

$generator;
if($previewType=='orderNumber')
{
    $order=wc_get_order($orderNumberToPreview);
    if($order==false)
    {
        echo __("invalid order number","woo-pdf-invoice-builder");
        die();
    }else{
        $generator=\rnwcinv\GeneratorFactory::GetGenerator($pageOptions,$order);
    }

}else
{
    $generator=new RednaoPDFGenerator($pageOptions,true,null);
}

$generator->GeneratePreview();
