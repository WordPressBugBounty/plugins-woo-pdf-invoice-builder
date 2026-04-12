<?php
/**
 * Created by PhpStorm.
 * User: Edgar
 * Date: 10/10/2017
 * Time: 7:50 AM
 */


use rnwcinv\compatibility\DocumentOptionsCompatibility;
use rnwcinv\htmlgenerator\FieldDTO;
use rnwcinv\htmlgenerator\fields\FieldFactory;
use rnwcinv\htmlgenerator\OrderValueRetriever;
use rnwcinv\pr\CustomField\CustomFieldBase;
use rnwcinv\pr\CustomField\CustomFieldFactory;
use rnwcinv\pr\CustomField\SimpleCustomField;
use rnwcinv\pr\CustomField\utilities\CustomFieldValueRetriever;
use rnwcinv\pr\CustomFieldV2\BasicFields\CArrayField;
use rnwcinv\pr\CustomFieldV2\BasicFields\CImageField;
use rnwcinv\pr\CustomFieldV2\BasicFields\CSimpleField;
use rnwcinv\pr\utilities\FontManager;
use rnwcinv\utilities\InvoiceInitialDataGenerator;

require_once RednaoWooCommercePDFInvoice::$DIR.'utilities/HttpPostProcessor.php';
final class RednaoWooCommercePDFInvoiceAjax{
    public $data=null;
    public $detailCatched=false;
    public function __construct()
    {
        add_action('wp_ajax_rednao_wcpdfinv_get_field_preview',array($this,'GetFieldPreview'));
        add_action('wp_ajax_rednao_wcpdfinv_get_qr_preview',array($this,'GetQrPreview'));
        add_action('wp_ajax_rednao_wcpdfinv_get_barcode_preview',array($this,'GetBarcodePreview'));
        add_action('wp_ajax_rednao_wcpdfinv_get_designer_preview',array($this,'GetDesignerPreview'));
        add_action('wp_ajax_rednao_wcpdfinv_save',array($this,'Save'));
        add_action('wp_ajax_rednao_search_invoice',array($this,'SearchInvoice'));
        add_action('wp_ajax_rednao_check_if_order_is_valid',array($this,'CheckIfOrderIsValid'));
        add_action('wp_ajax_rednao_wcpdfinv_generate_pdf',array($this,'CreatePDF'));
        add_action('wp_ajax_rednao_update_template',array($this,'UpdateTemplate'));
        add_action('wp_ajax_rednao_wcpdfinv_get_designer_export',array($this,'Export'));
        add_action('wp_ajax_rednao_wcpdfinv_remind_me',array($this,'RemindMeLater'));
        add_action('wp_ajax_rednao_wcpdfinv_dont_show_again',array($this,'DontShowAgain'));
        add_action('wp_ajax_rednao_wcpdfinv_diagnose_error',array($this,'DiagnoseError'));
        add_action('wp_ajax_rednao_wcpdfinv_get_latest_error',array($this,'GetLatestError'));
        add_action('wp_ajax_rednao_wcpdfinv_dont_show_again_nl',array($this,'DontShowNewsletter'));
        add_action('wp_ajax_rednao_wcpdfinv_inspect_order',array($this,'InspectOrder'));
        add_action('wp_ajax_rednao_wcpdfinv_preview_custom_field',array($this,'PreviewCustomField'));
        add_action('wp_ajax_rednao_wcpdfinv_get_invoice_details',array($this,'GetInvoiceDetail'));
        add_action('wp_ajax_rednao_wcpdfinv_load_template',array($this,'LoadTemplate'));
        add_action('wp_ajax_rednao_wcpdfinv_email_pdf',array($this,'EmailPDF'));
        add_action('wp_ajax_rednao_wcpdfinv_delete_pdf',array($this,'DeletePDF'));
        add_action('wp_ajax_rednao_wcpdfinv_manage_delete',array($this,'ManageDelete'));
        add_action('wp_ajax_rednao_wcpdfinv_search',array($this,'Search'));
        add_action('wp_ajax_rednao_wcpdfinv_manage_view',array($this,'ManageView'));
        add_action('wp_ajax_rednao_wcpdfinv_download',array($this,'Download'));
        add_action('wp_ajax_rednao_wcpdfinv_save_next_number',array($this,'SaveNextNumber'));
        add_action('wp_ajax_rednao_wcpdfinv_ai_generate_template',array($this,'AIGenerateTemplate'));
        add_action('wp_ajax_rednao_wcpdfinv_ai_preview_template',array($this,'AIPreviewTemplate'));
        add_action('wp_ajax_rednao_wcpdfinv_ai_get_prompt',array($this,'AIGetPrompt'));
        add_action('wp_ajax_rednao_wcpdfinv_ai_process_external',array($this,'AIProcessExternalResponse'));
        add_action('wp_ajax_rednao_wcpdfinv_ai_upload_temp_image',array($this,'AIUploadTempImage'));


    }



    public function  SaveNextNumber(){
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor=new HttpPostProcessor();

        $invoiceId=$processor->GetRequired('invoiceid');
        $nextNumber=$processor->GetRequired('number');

        update_option($invoiceId. '_rednao_pdf_invoice_number',apply_filters('wcpdfi_update_latest_invoice_number',$nextNumber,$invoiceId));

        $processor->SendSuccessMessage();
    }

    public function ManageDelete(){
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor=new HttpPostProcessor();
        $nonce=$processor->GetRequired('Nonce');
        if(!wp_verify_nonce($nonce,'pdfi_manage_nonce'))
            die('Forbidden');

        $invoiceList=$processor->GetRequired('Invoices');
        global $wpdb;
        $ids='';
        $allDeleted=true;
        foreach($invoiceList as $invoice)
        {
            $result=$wpdb->query($wpdb->prepare('delete from '.RednaoWooCommercePDFInvoice::$INVOICES_CREATED_TABLE. ' where invoice_id =%s and order_id=%s',$invoice->InvoiceId,$invoice->OrderId));
            if($result==false)
            {
                $allDeleted=true;
            }



        }

        if(!$allDeleted)
        {
            $this->SendErrorMessage('Some items could not be deleted, please try again');
        }

        $this->SendSuccessMessage('Items deleted successfully');
    }

    public function Download(){
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor=new HttpPostProcessor();
        $nonce=$processor->GetRequired('Nonce');
        if(!wp_verify_nonce($nonce,'pdfi_manage_nonce'))
            die('Forbidden');

        $invoiceList=$processor->GetRequired('Invoices');

        if(count($invoiceList)==1)
        {
            $orderId=$invoiceList[0]->OrderId;
            $invoiceId=$invoiceList[0]->InvoiceId;
            $order=wc_get_order($orderId);
            if($order==false)
            {
                echo __("Invalid Order Number","woo-pdf-invoice-builder");
                die();
            }

            require_once 'PDFGenerator.php';

            $generator=\rnwcinv\GeneratorFactory::GetGenerator(RednaoPDFGenerator::GetPageOptionsById($invoiceId),$order);
            $generator->Generate(true,true);

            header("Content-type: application/pdf");
            header("Content-disposition: attachment; filename=".basename($generator->GetFileName()).'.pdf');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            echo $generator->GetOutput();

            die();
        }else{

            $uploadDir=wp_upload_dir();
            $path= $uploadDir['basedir'].'/sf_pdfs_bulk';

            if(!is_dir($path))
                RednaoWooCommercePDFInvoice::CreateFolder($path);


            $zip=new \ZipArchive();
            $usedNames=[];
            $zip->open( $path.'documents.zip',\ZipArchive::CREATE|\ZipArchive::OVERWRITE);
            foreach($invoiceList as $invoice)
            {
                $order=wc_get_order($invoice->OrderId);
                if($order==false)
                {
                    echo "Invalid Order Number";
                    die();
                }

                require_once 'PDFGenerator.php';

                $generator=\rnwcinv\GeneratorFactory::GetGenerator(RednaoPDFGenerator::GetPageOptionsById($invoice->InvoiceId),$order);
                $generator->Generate(true,true);


                $name=$generator->GetFileName();
                $nameToCheck=strtolower($name);
                $index=1;
                while(array_search($nameToCheck,$usedNames)!==false)
                {
                    $nameToCheck=strtolower($name).'('.$index.')';
                    $index++;
                }
                $usedNames[]=$nameToCheck;


                $zip->addFromString($nameToCheck.'.pdf',$generator->GetOutput());
            }


            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=documents.zip");
            header("Content-Length: " . filesize($path.'documents.zip'));
            readfile($path.'documents.zip');


            $files = glob($path.'*'); // get all file names
            foreach($files as $file){ // iterate files
                if(is_file($file))
                    unlink($file); // delete file
            }

            die();
        }
    }

    public function ManageView(){
        $orderId=$_GET['orderid'];
        $invoiceId=$_GET['invoiceid'];
        $nonce=$_GET['nonce'];

        if(!wp_verify_nonce($nonce,'pdfi_manage_nonce'))
            die('Forbidden');

        $orderId=$_GET['orderid'];
        $order=wc_get_order($orderId);
        if($order==false)
        {
            echo "Invalid Order Number";
            die();
        }else{
            $invoiceId=-1;
            if(isset($_GET['invoiceid']))
                $invoiceId=$_GET['invoiceid'];
            require_once 'PDFGenerator.php';

            $generator=\rnwcinv\GeneratorFactory::GetGenerator(RednaoPDFGenerator::GetPageOptionsById($invoiceId),$order);
            $generator->GeneratePreview(true);
            die();
        }
    }

    public function Search(){
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor=new HttpPostProcessor();
        $startDate=$processor->GetRequired('StartDate');
        $endDate=$processor->GetRequired('EndDate');
        $customerName=$processor->GetRequired('CustomerName');
        $invoiceNumber=$processor->GetRequired('InvoiceNumber');
        $invoiceId=$processor->GetRequired('InvoiceId');

        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();

        global $wpdb;
        $where=$wpdb->prepare('invoice_id=%s',$invoiceId);

        if($startDate>0)
        {
            $where.=$wpdb->prepare(' and date>=FROM_UNIXTIME(%d)',$startDate);
        }

        if($endDate>0)
        {
            $where.=$wpdb->prepare(' and date<=FROM_UNIXTIME(%d)',$endDate);
        }

        if($customerName!='')
        {
            $where.=' and user.display_name like \'%'.esc_sql($wpdb->esc_like($customerName)).'%\'';
        }

        if($invoiceNumber!='')
        {
            $where.=' and formatted_invoice_number like \'%'.esc_sql($wpdb->esc_like($invoiceNumber)).'%\'';
        }


        $results=$wpdb->get_results("
            select invoice_id InvoiceId,order_id OrderId,UNIX_TIMESTAMP(date) Date,formatted_invoice_number FormattedInvoiceNumber,post.post_status Status,meta_total.meta_value Total,
            concat(coalesce(meta_firstname.meta_value,''),\" \", coalesce(meta_lastname.meta_value,''),\" (\",user.display_name,\")\")  CustomerName
            from ".RednaoWooCommercePDFInvoice::$INVOICES_CREATED_TABLE." created
            join ".$wpdb->posts." post
            on created.order_id=post.ID
            left join ".$wpdb->postmeta." meta_total
            on meta_total.post_id=post.ID and meta_total.meta_key='_order_total'
            left join ".$wpdb->postmeta." meta_user
            on post.ID=meta_user.post_id and meta_user.meta_key='_customer_user'
            left join ".$wpdb->users." user
            on user.ID=meta_user.meta_value 
            left join ".$wpdb->usermeta." meta_firstname
            on user.ID=meta_firstname.user_id and meta_firstname.meta_key='billing_first_name'
            left join ".$wpdb->usermeta." meta_lastname
            on user.ID=meta_lastname.user_id and meta_lastname.meta_key='billing_last_name'
            where 
        ".$where);

        $processor->SendSuccessMessage($results);



    }

    public function DeletePDF(){
        $processor=new HttpPostProcessor();
        $OrderId=$processor->GetRequired('OrderId');
        $InvoiceId=$processor->GetRequired('InvoiceId');
        $nonce=$processor->GetRequired('Nonce');

        if(!wp_verify_nonce($nonce,'delete_'.$OrderId))
        {
            $processor->SendErrorMessage('Invalid request, please refresh and try again');
        }

        global $wpdb;
        $wpdb->delete(RednaoWooCommercePDFInvoice::$INVOICES_CREATED_TABLE,
            array(
               'invoice_id'=>$InvoiceId,
                'order_id'=>$OrderId
            ));

        $this->SendSuccessMessage('');

    }

    public function LoadTemplate()
    {
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();

        $processor=new HttpPostProcessor();
        $source=$processor->GetRequired('source');
        $fileName=preg_replace("/[^a-z0-9.]+/i", "",$processor->GetRequired('fileName'));

        $path=RednaoWooCommercePDFInvoice::$DIR;
        if($source=='pr')
            $path.='pr/';
        else
            $path.='js/';
        $path.='templates/'.$fileName.'.json';

        if(!file_exists($path))
            $processor->SendErrorMessage('Template does not exists!');
        $content=file_get_contents($path);
        $content=json_decode($content);

        $content->containerOptions=json_decode($content->containerOptions);
        $content->pages=json_decode($content->pages);
        if($content->pages==false)
            $content->pages=[];

        $content=DocumentOptionsCompatibility::execute($content);
        $processor->SendSuccessMessage($content);
    }

    public function EmailPDF(){
        $processor=new HttpPostProcessor();
        $To=$processor->GetRequired('To');
        $Subject=$processor->GetRequired('Subject');
        $Body=$processor->GetRequired('Body');
        $OrderId=$processor->GetRequired('OrderId');
        $InvoiceId=$processor->GetRequired('InvoiceId');
        $nonce=$processor->GetRequired('Nonce');
        $saveTemplate=$processor->GetRequired('SaveTemplate');
      //  RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        if(!wp_verify_nonce($nonce,'pdfi_manage_nonce'))
        {
            $processor->SendErrorMessage('Invalid request, please refresh and try again');
        }

        global $wpdb;
        if($saveTemplate&&RednaoWooCommercePDFInvoice::IsPR())
        {
            $wpdb->update(RednaoWooCommercePDFInvoice::$INVOICE_TABLE,array(
                'email_config'=>json_encode(array(
                    'Subject'=>$Subject,
                    'Body'=>$Body
                ))
            ),array('invoice_id'=>$InvoiceId));

        }


        global $order;

        $order=wc_get_order($OrderId);
        if($order==false)
        {
            $processor->SendSuccessMessage('Invalid order number');
            die();
        }else{
            require_once RednaoWooCommercePDFInvoice::$DIR. 'PDFGenerator.php';
            $options=RednaoPDFGenerator::GetPageOptionsById($InvoiceId);
            //remove printer so it is not printed automatically
            for($i=0;$i<count($options->extensions);$i++)
            {
                if($options->extensions[$i]->extensionId=='printer')
                {
                    array_splice($options->extensions,$i,1);
                }
            }
            $generator=\rnwcinv\GeneratorFactory::GetGenerator($options,$order);
            $tmp_path = RednaoWooCommercePDFInvoice::GetSubFolderPath('attachments');
            $tempFolderToReturn='';
            while(is_dir($tempFolderToReturn=$tmp_path.'temp'.$i.'/'))
            {
                $i++;
            }

            if(!\mkdir($tempFolderToReturn))
                throw new Exception('Could not create folder '.$tempFolderToReturn);

            $tmp_path=$tempFolderToReturn;



            $attachments=array();
            $generator->GenerateAttachment($tmp_path,$attachments,0);
            if(RednaoWooCommercePDFInvoice::IsPR())
            {
                ini_set('display_errors', 0);
                $tagManager=new \rnwcinv\pr\Manager\TagManager($generator->orderValueRetriever);
                $Subject=$tagManager->Process($Subject);
            }

            $headers = array('Content-Type: text/html; charset=UTF-8');
            do_action('rnwcinv_send_pdf_email',$order->get_id(),$InvoiceId);


            $Body=do_shortcode($Body);

            $emailData=(Object)[
                'Order'=>$order,
                'InvoiceId'=>$InvoiceId,
                'To'=>$To,
                'Subject'=>$Subject,
                'Body'=>$Body,
                'Attachments'=>$attachments,
                'Headers'=>$headers
            ];

            $emailData=apply_filters('rnwcinv_before_sending_email',$emailData);

            $result=wp_mail($emailData->To,$emailData->Subject,$emailData->Body,$emailData->Headers,$emailData->Attachments);
            if($result==false)
                $this->SendErrorMessage('The email could not be send, please try again');
            else
                $processor->SendSuccessMessage('');
            die();
        }
    }

    public function GetInvoiceDetail(){
        $processor=new HttpPostProcessor();
        $orderNumber=$processor->GetRequired('OrderNumber');
        $invoiceId=$processor->GetRequired('InvoiceId');;
        $nonce=$processor->GetRequired('Nonce');;


        if(wp_verify_nonce('can_view_order_'.$orderNumber,$nonce))
            $this->SendErrorMessage('Invalid nonce, please refresh the screen and try again');
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('select invoice_number InvoiceNumber,formatted_invoice_number FormattedInvoiceNumber,unix_timestamp(date) Date from '.RednaoWooCommercePDFInvoice::$INVOICES_CREATED_TABLE.
            ' where order_id=%s and invoice_id=%s',$orderNumber,$invoiceId));


        $this->SendSuccessMessage($row);
    }

    public function PreviewCustomField(){
        error_reporting(E_ERROR);
        $processor=new HttpPostProcessor();
        $options=$processor->GetRequired('Options');
        CustomFieldValueRetriever::$order=new WC_Order($options->OrderNumber);

        if($options->FieldType=='table')
        {
            $lineItems=CustomFieldValueRetriever::$order->get_items();
            if(count($lineItems)>0)
            {
                $value=reset($lineItems);
                CustomFieldValueRetriever::$lineItem = $value;
            }

        }

        if(isset($options->OrderFields)&&count($options->OrderFields)>0&&$options->OrderFields[0]->dataType=='array')
        {
            $orderField=$options->OrderFields[0];

            $this->SendSuccessMessage(array('html'=>(new CArrayField($orderField->fieldType,$orderField->source,$orderField->key))->GetHTML()));
        }

        $preview='';
        foreach($options->OrderFields as $field)
        {
            $subTypeData=null;
            if(isset($field->subTypeData))
                $subTypeData=$field->subTypeData;
            $integration=null;
            if(isset($field->integration))
                $integration=$field->integration;
            $preview='';
            if($field->fieldType=='rnepo')
            {
                $preview.=$field->html;
            }


            if($options->FormattingOptions->Type=='image')
            {
                $preview.=(new CImageField($field->fieldType,$field->source,$field->path,$integration,$subTypeData,$options->FormattingOptions->Width,$options->FormattingOptions->Height))->GetHTML().' ';
            }else if($options->FormattingOptions->Type=='qrcode')
            {
                $preview.=(new CSimpleField($field->fieldType,$field->source,$field->path,$integration,$subTypeData))->GetStringValue().' ';

                require_once RednaoWooCommercePDFInvoice::$DIR.'vendor/phpqrcode/qrlib.php';
                $svgCode = \QRcode::svg($preview,false,QR_ECLEVEL_L,3,0);
                $preview= '<img   src="data:image/svg+xml;base64,' . base64_encode($svgCode).'"></img>';


            } else
                $preview.=(new CSimpleField($field->fieldType,$field->source,$field->path,$integration,$subTypeData))->GetHTML().' ';
        }
        $this->SendSuccessMessage(array('html'=>$preview));


    }

    public function GetQrPreview(){
        if(wp_verify_nonce($this->GetStringValue('nonce',true),'rnwcinv_savenonce')==false)
            $this->SendErrorMessage('Invalid request');
        $options=(object)$this->GetArrayValue('options');
        $field=FieldFactory::GetField($options,new OrderValueRetriever(null,null,true,null,null));
        $this->SendSuccessMessage(array('image'=>$field->GetImage()));
    }

    public function GetBarcodePreview(){
        if(wp_verify_nonce($this->GetStringValue('nonce',true),'rnwcinv_savenonce')==false)
            $this->SendErrorMessage('Invalid request');
        $options=(object)$this->GetArrayValue('options');
        $field=FieldFactory::GetField($options,new OrderValueRetriever(null,null,true,null,null));
        $this->SendSuccessMessage(array('image'=>$field->GetImage()));
    }

    public function InspectOrder(){
        $processor=new HttpPostProcessor();

        $orderNumber=$processor->GetRequired('OrderNumber');
        $type=$processor->GetRequired('Type');

        require_once RednaoWooCommercePDFInvoice::$DIR.'utilities/WCInspector.php';
        $inspector=new WCInspector($orderNumber);

        if($type=='normal')
            $processor->SendSuccessMessage($inspector->InspectOrder());
        else
        if($type=='row')
            $processor->SendSuccessMessage($inspector->InspectPossibleRows());
        else
            $processor->SendSuccessMessage($inspector->InspectOrderDetails());
        die();

    }

    public function SearchInvoice(){
        $processor=new HttpPostProcessor();

        $criteria=$processor->GetRequired('SearchCriteria');

        global $wpdb;

        if(!wp_verify_nonce('search_invoice','wc_search_invoice'))
            die('Forbidden');

        $query = "
            select wp_posts.ID OrderNumber,invoice_date_meta.meta_value Date, invoice_number_meta.meta_value InvoiceNumber
            from ".$wpdb->posts." 
              join ".$wpdb->postmeta."  invoice_date_meta
              on invoice_date_meta.post_id=wp_posts.ID and invoice_date_meta.meta_key='REDNAO_WCPDFI_INVOICE_DATE'
              join ".$wpdb->postmeta."  invoice_number_meta
              on invoice_number_meta.post_id=wp_posts.ID and invoice_number_meta.meta_key='REDNAO_WCPDFI_INVOICE_ID'
        ";

        if($criteria=="InvoiceNumber"){
            $query.=$wpdb->prepare(' where invoice_number_meta.meta_value=%s',$processor->GetRequired('InvoiceNumber'));
        }

        if($criteria=="InvoiceDate"){
            $startDate=strtotime($processor->GetRequired('StartDate'));
            $endDate=strtotime($processor->GetRequired('EndDate').' +1 day');
            $query.=$wpdb->prepare(' where invoice_date_meta.meta_value between %d and %d',$startDate,$endDate);
        }

        if($criteria=="OrderNumber"){
            $query.=$wpdb->prepare(' where wp_posts.ID=%s',$processor->GetRequired('OrderNumber'));
        }


        $results=$wpdb->get_results($query,'ARRAY_A');

        foreach($results as &$result)
        {
            $result['Url']=wp_specialchars_decode(get_edit_post_link($result['OrderNumber']));
            $result['ViewUrl']=wp_specialchars_decode(wp_nonce_url( admin_url( "admin-ajax.php?action=rednao_wcpdfinv_generate_pdf&orderid=" . $result['OrderNumber'] ), 'rednao_wcpdfinv_generate_pdf_'.$result['OrderNumber'] ));


        }

        $processor->SendSuccessMessage($results);
        die();
    }

    public function DontShowNewsletter(){
        update_option('pdfinvoice_newsletter',2);
        $this->SendSuccessMessage(true);
        die();
    }

    public function GetLatestError(){
        // register_shutdown_function(array($this, 'CatchShutdownHandler'));
        echo get_option('PDFInvoiceErrorMessage','');
        die();
    }

    public function RemindMeLater(){
        $currentStage=get_option('wopdfinv_stage',0);
        update_option('wopdfinv_stage',$currentStage+1);
    }

    public function DontShowAgain(){
        update_option('wopdfinv_stage',4);
        $this->SendSuccessMessage('');
    }

    public function DiagnoseError(){
        register_shutdown_function( array($this,'ShutDownCatch'));
        set_error_handler(array($this, 'CatchShutdownHandler'));
        delete_option('PDFInvoiceErrorMessage');


        $nonce=$_POST['nonce'];
        if(!wp_verify_nonce($nonce,'woopdfinvoice_errorresolver'))
            die('Forbidden');



        $invoiceId=$_POST['invoiceId'];
        require_once 'PDFGenerator.php';
        if($_POST['testType']=='preview'){

            $generator=new RednaoPDFGenerator(RednaoPDFGenerator::GetPageOptionsById($invoiceId),true,null);

        }else{
            $orderNumber=$_POST['orderNumber'];
            $order=wc_get_order($orderNumber);
            if($order==false)
            {
                die();
            }else{
                $generator=new RednaoPDFGenerator(RednaoPDFGenerator::GetPageOptionsById($invoiceId),false,$order);
            }

        }

        $generator->GeneratePreview();
        die();
    }

    public function ShutDownCatch(){
        if($this->detailCatched)
            return;
        $error = error_get_last();
        if( $error !== NULL) {



            update_option('PDFInvoiceErrorMessage',json_encode(array(
                "ErrorNumber"=>$error["type"],
                "ErrorMessage"=>$error["message"],
                "ErrorFile"=>$error["file"],
                "ErrorLine"=>$error["line"],
                "ErrorContext"=>"N/A",
                "Detail"=>"Unknown"

            )));
        }
    }

    public function CatchShutdownHandler($errorNumber, $errorStr,$errorFile,$errorLine){
        $this->detailCatched=true;
        $debug=json_encode(debug_backtrace());

        update_option('PDFInvoiceErrorMessage',json_encode(array(
            "ErrorNumber"=>$errorNumber,
            "ErrorMessage"=>$errorStr,
            "ErrorFile"=>$errorFile,
            "ErrorLine"=>$errorLine,
            "ErrorContext"=>null,
            "Detail"=>$debug

        )));
    }



    private function ProcessPostParameter()
    {
        if(!isset($_POST['data']))
            throw new Exception('Invalid post parameters');

        $this->data=json_decode(stripslashes($_POST['data']),true);
        if($this->data==null)
            throw new Exception('Invalid post parameters');
    }

    public function Export(){
        global $wpdb;
     
        if(!isset($_POST['pageId']))
        {
            return;
        }

        $nonce=$_POST['nonce'];
        if(wp_verify_nonce($nonce,'rnwcinv_savenonce')==false)
        {
            echo 'Invalid request';
            die();
        }

        $invoiceData=$wpdb->get_row("select extensions,conditions,attach_to,invoice_id,name,options,type,html,pages from ".RednaoWooCommercePDFInvoice::$INVOICE_TABLE." where invoice_id=".intval($_POST['pageId']));
        if($invoiceData==null){
            return;
        }
        $invoiceData->extensions=json_decode($invoiceData->extensions);
        $invoiceData->conditions=json_decode($invoiceData->conditions);
        $invoiceData->attach_to=json_decode($invoiceData->attach_to);
        $invoiceData->options=json_decode($invoiceData->options);
        $invoiceData->pages=json_decode($invoiceData->pages);
        $exporter=new \rnwcinv\ImportExport\TemplateExporter();
        $path=$exporter->Export($invoiceData);



        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=".basename($path));
        header("Content-Length: " . filesize($path));
        readfile($path);

        
        $exporter->Destroy();
        die();
    }

    public function GetOptionalJsonValue($propertyName,$defaultValue=null)
    {
        if($this->data==null)
            $this->ProcessPostParameter();

        if(!isset($this->data[$propertyName]))
            return $defaultValue;

        return json_decode($this->data[$propertyName],true);

    }




    public function GetJsonValue($propertyName)
    {
        if($this->data==null)
            $this->ProcessPostParameter();

        return json_decode($this->data[$propertyName],true);
    }

    public function GetDesignerPreview()
    {
        if(wp_verify_nonce($_POST['nonce'],'rnwcinv_savenonce')==false)
            $this->SendErrorMessage('Invalid request');
        require_once('PDFPreview.php');
    }

    public function CheckIfOrderIsValid()
    {
        $orderId=$this->GetNumberValue('OrderNumber');
        $post=wc_get_order($orderId);
        if($post==false)
            $this->SendErrorMessage("Order Not Found");
        /*if($post->post_status!='wc-completed')
            $this->SendErrorMessage('Order is not completed');*/
        $this->SendSuccessMessage('success');
    }

    public function GetStringValue($propertyName,$required){
        if($this->data==null)
            $this->ProcessPostParameter();

        if(!isset($this->data[$propertyName]))
            if($required)
                throw new Exception("Parameter not found ".$propertyName);
            else
                return '';

        return strval($this->data[$propertyName]);
    }

    public function UpdateTemplate(){
        $this->Save();
    }

    public function CreatePDF(){

        if(!isset($_GET['orderid'])|| wp_verify_nonce($_GET['_wpnonce'], 'rednao_wcpdfinv_generate_pdf_'.intval($_GET['orderid']))==false){
            die('Forbidden');
        }


        if(!isset($_GET['orderid'])||$_GET['orderid']=='')
        {
            echo "Invalid request, please try again";
            die();
        }

        $actionid='View';
        if(isset($_GET['actionid'])&&($_GET['actionid']=='View'||$_GET['actionid']=='Download'))
            $actionid=strval($_GET['actionid']);

        $orderId=$_GET['orderid'];

        $order=wc_get_order($orderId);
        if($order==false)
        {
            echo "Invalid Order Number";
            die();
        }else{
            $invoiceId=-1;
            if(isset($_GET['invoice_id']))
                $invoiceId=$_GET['invoice_id'];
            require_once 'PDFGenerator.php';

            $generator=\rnwcinv\GeneratorFactory::GetGenerator(RednaoPDFGenerator::GetOptionsForOrder($order,$invoiceId),$order);
            if($actionid=='View')
                $generator->GeneratePreview(true);
            else
            {
                $generator->Generate();
                header("Content-type: application/pdf");
                header("Content-disposition: attachment; filename=".basename($generator->GetFileName()).'.pdf');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                echo $generator->GetOutput();
            }
            die();
        }


    }

    public function GetNumberValue($propertyName, $required=false){
        if($this->data==null)
            $this->ProcessPostParameter();


        if($required&&!is_numeric($this->data[$propertyName]))
            throw new Exception("Invalid numeric parameter ".$propertyName);
        return intval($this->data[$propertyName]);
    }

    public function GetBoolValue($propertyName, $required=false){
        if($this->data==null)
            $this->ProcessPostParameter();


        if($required&&!isset($this->data[$propertyName]))
            throw new Exception("Invalid numeric parameter ".$propertyName);

        return $this->data[$propertyName]==true;
    }

    public function GetArrayValue($propertyName)
    {
        if($this->data==null)
            $this->ProcessPostParameter();

        if(!is_array($this->data[$propertyName]))
            return array();

        return $this->data[$propertyName];

    }


    public function GetFieldPreview(){

        if(wp_verify_nonce($this->GetStringValue('nonce',true),'rnwcinv_savenonce')==false)
            $this->SendErrorMessage('Invalid request');
        $type=$this->GetStringValue('type',false);
        $options=(object)$this->GetArrayValue('fieldOptions');

        /** @var FieldDTO $fieldOptions */
        $fieldOptions=new stdClass();
        $fieldOptions->type='field';
        $fieldOptions->fieldOptions=$options;
        $fieldOptions->fieldOptions->fieldType=$type;
        $field=FieldFactory::GetField($fieldOptions,new OrderValueRetriever(null,null,true,null,null));

        if($fieldOptions->fieldOptions->fieldType=='inv_number')
        {
            $additionalOptions=(object)$this->GetArrayValue('AdditionalOptions');
            $formattedNumber=(new InvoiceInitialDataGenerator())->Create(0,0,(object)$additionalOptions->Format,true,new OrderValueRetriever(null,null,true,null,null));
            $this->SendSuccessMessage($formattedNumber->FormattedInvoiceNumber);
        }
        $this->SendSuccessMessage($field->FormatValue($field->GetFieldValue()));

    }

    public function Save(){
        $pageId=$this->GetNumberValue('pageId',true);
        $pageType=$this->GetNumberValue('pageType',true);
        $name=$this->GetStringValue('name',true);
        $containerOptions=$this->GetStringValue('containerOptions',true);
        $attachTo=$this->GetStringValue('attachTo',true);
        $conditionOptions=$this->GetStringValue('conditions',false);
        $pages=$this->GetStringValue('pages',false);
        $createWhen=$this->GetStringValue('createWhen',false);
        $originalExtensions=$this->GetJsonValue('extensions');
        $myAccountDownload=$this->GetBoolValue('myAccountDownload',true);
        $myAccountDownloadText=$this->GetStringValue('myAccountDownloadText',false);

        $nonce=$this->GetStringValue('nonce',true);

        if(wp_verify_nonce($nonce,'rnwcinv_savenonce')==false)
            $this->SendErrorMessage('Invalid request');

        $orderActions=$this->GetOptionalJsonValue('orderActions');
        if($orderActions!=null)
            $orderActions=json_encode($orderActions);

        $extensions=json_encode(apply_filters('rnpdf_invoice_process_extensions_before_save',$originalExtensions));

        global $wpdb;
        $result=false;
        $rowId=0;
        $html='';

        // Promote any temp images to WordPress media library before saving
        $pages = $this->promoteTempImages($pages, $nonce);

        if($pageId==0||$pageId==null)
        {


            $result=$wpdb->insert(RednaoWooCommercePDFInvoice::$INVOICE_TABLE,array(
                'name'=>$name,
                'options'=>$containerOptions,
                'type'=>$pageType,
                'options'=>$containerOptions,
                'attach_to'=>$attachTo,
                'extensions'=>$extensions,
                'conditions'=>$conditionOptions,
                'create_when'=>$createWhen,
                'order_actions'=>$orderActions,
                'pages'=>$pages,
                'html'=>$html,
                'my_account_download'=>$myAccountDownload,
                'my_account_download_text'=>$myAccountDownloadText
            ));
            $rowId=$wpdb->insert_id;
        }else{
            $result=$wpdb->update(RednaoWooCommercePDFInvoice::$INVOICE_TABLE,array(
                'name'=>$name,
                'options'=>$containerOptions,
                'type'=>$pageType,
                'options'=>$containerOptions,
                'pages'=>$pages,
                'attach_to'=>$attachTo,
                'order_actions'=>$orderActions,
                'extensions'=>$extensions,
                'create_when'=>$createWhen,
                'conditions'=>$conditionOptions,
                'my_account_download'=>$myAccountDownload,
                'my_account_download_text'=>$myAccountDownloadText,
                'html'=>$html
            ),array('invoice_id'=>$pageId));
            $rowId=$pageId;
        }

        do_action('rnpdf_invoice_process_extensions_after_save',array('pageId'=>$rowId,'extensions'=>$originalExtensions));

        if($result===false)
            $this->SendErrorMessage('Data could not be inserted. Reason='.$wpdb->last_error);
        else
        {
            update_option('REDNAO_PDF_INVOICE_EDITED',true);
            $this->SendSuccessMessage(array('row_id' => $rowId));
        }
    }

    /**
     * Uploads a base64-encoded image to the temp folder.
     * Used by the AI template converter to save SVG→JPEG conversions.
     */
    public function AIUploadTempImage() {
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor = new HttpPostProcessor();
        $data = $processor->data;

        $nonce = isset($data->nonce) ? $data->nonce : '';
        if (wp_verify_nonce($nonce, 'rnwcinv_savenonce') === false) {
            $processor->SendErrorMessage('Invalid nonce');
        }

        $imageData = isset($data->imageData) ? $data->imageData : '';
        $filename = isset($data->filename) ? sanitize_file_name($data->filename) : 'image.jpg';

        if (empty($imageData)) {
            $processor->SendErrorMessage('No image data provided');
        }

        // Decode the base64 image
        $decoded = base64_decode($imageData);
        if ($decoded === false) {
            $processor->SendErrorMessage('Invalid base64 image data');
        }

        // Save to public temp folder (accessible via HTTP for PDF preview)
        require_once RednaoWooCommercePDFInvoice::$DIR . 'utilities/FileManager.php';
        $fileManager = new \rnwcinv\utilities\FileManager();
        $tempFolder = $fileManager->GetPublicTemporalFolderPath();
        $filePath = $tempFolder . $filename;

        if (file_put_contents($filePath, $decoded) === false) {
            $processor->SendErrorMessage('Failed to save image file');
        }

        // Calculate the relative path from the FileManager root
        $rootPath = $fileManager->GetRootFolderPath();
        $relativePath = str_replace($rootPath, '', $filePath);

        // Build the URL for the saved file
        $url = $fileManager->GetFontURL() . $relativePath;

        $processor->SendSuccessMessage([
            'url' => $url,
            'filePath' => $relativePath
        ]);
    }

    /**
     * Scans all fields in the pages JSON and promotes temp images to WordPress media.
     * Handles:
     *   - type=image fields with FilePath (SVG conversions)
     *   - type=text fields with inline <img data-file-path="..."> tags (emoji conversions)
     * Returns the modified pages JSON string.
     */
    private function promoteTempImages($pagesJson, $nonce) {
        if (empty($pagesJson)) return $pagesJson;

        $pages = json_decode($pagesJson);
        if (!is_array($pages)) return $pagesJson;

        require_once RednaoWooCommercePDFInvoice::$DIR . 'utilities/FileManager.php';
        $fileManager = new \rnwcinv\utilities\FileManager();
        $rootPath = $fileManager->GetRootFolderPath();
        $modified = false;

        foreach ($pages as $page) {
            if (!isset($page->fields) || !is_array($page->fields)) continue;

            foreach ($page->fields as $field) {
                if (!isset($field->type)) continue;

                // Case 1: Image fields with FilePath (SVG→JPEG from AI)
                if ($field->type === 'image') {
                    if (empty($field->FilePath)) continue;
                    if (!empty($field->URL_ID)) continue; // Already has a WP attachment

                    $result = $this->promoteFileToMedia($field->FilePath, $rootPath, $fileManager);
                    if ($result !== null) {
                        $field->URL = $result['url'];
                        $field->URL_ID = $result['attachmentId'];
                        unset($field->FilePath);
                        $modified = true;
                    }
                }

                // Case 2: Text fields with inline <img data-file-path="..."> (emoji PNGs)
                if ($field->type === 'text' && !empty($field->Text)) {
                    $textHtml = $field->Text;
                    $textModified = false;

                    // Find all <img> tags with data-file-path
                    $textHtml = preg_replace_callback(
                        '/<img([^>]*?)data-file-path=["\']([^"\']+)["\']([^>]*?)\/?>/i',
                        function($matches) use ($rootPath, $fileManager, &$textModified) {
                            $beforeAttr = $matches[1];
                            $relativePath = $matches[2];
                            $afterAttr = $matches[3];

                            $result = $this->promoteFileToMedia($relativePath, $rootPath, $fileManager);
                            if ($result === null) {
                                return $matches[0]; // Keep original if promotion fails
                            }

                            $textModified = true;

                            // Rebuild the tag: update src, add data-wp-id, remove data-file-path
                            $newTag = '<img' . $beforeAttr . $afterAttr . '/>';
                            // Remove any existing src and data-wp-id
                            $newTag = preg_replace('/\s*src=["\'][^"\']*["\']/', '', $newTag);
                            $newTag = preg_replace('/\s*data-wp-id=["\'][^"\']*["\']/', '', $newTag);
                            // Insert new attributes after <img
                            $newTag = preg_replace(
                                '/^<img/',
                                '<img src="' . esc_attr($result['url']) . '" data-wp-id="' . intval($result['attachmentId']) . '"',
                                $newTag
                            );
                            return $newTag;
                        },
                        $textHtml
                    );

                    if ($textModified) {
                        $field->Text = $textHtml;
                        $modified = true;
                    }
                }
            }
        }

        if ($modified) {
            return json_encode($pages);
        }
        return $pagesJson;
    }

    /**
     * Promotes a single temp file to the WordPress media library.
     * Validates the file path, copies to uploads, creates attachment.
     *
     * @param string $relativePath Relative path from the plugin's root folder (e.g. "public_temp/temp1/emoji_123.png")
     * @param string $rootPath     Absolute root path from FileManager
     * @param \rnwcinv\utilities\FileManager $fileManager
     * @return array|null ['url' => string, 'attachmentId' => int] on success, null on failure
     */
    private function promoteFileToMedia($relativePath, $rootPath, $fileManager) {
        // Security: reject path traversal
        if (strpos($relativePath, '..') !== false) return null;

        $absolutePath = $rootPath . ltrim($relativePath, '/\\');
        if (!file_exists($absolutePath)) return null;

        // Verify it's a valid image extension
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts)) return null;

        // Only promote files from the temp or public_temp folder
        $tempRoot = $fileManager->GetTempFolderRootPath();
        $publicTempRoot = $fileManager->GetPublicTempFolderRootPath();
        $realAbsolute = realpath($absolutePath);
        $realTemp = realpath($tempRoot);
        $realPublicTemp = realpath($publicTempRoot);
        if ($realAbsolute === false) return null;
        $normalizedAbsolute = wp_normalize_path($realAbsolute);
        $inTemp = ($realTemp !== false && strpos($normalizedAbsolute, wp_normalize_path($realTemp)) === 0);
        $inPublicTemp = ($realPublicTemp !== false && strpos($normalizedAbsolute, wp_normalize_path($realPublicTemp)) === 0);
        if (!$inTemp && !$inPublicTemp) return null;

        // Promote to WordPress media library
        $uploadDir = wp_upload_dir();
        $targetFilename = basename($absolutePath);

        // Ensure unique filename
        $uniqueName = wp_unique_filename($uploadDir['path'], $targetFilename);
        $targetPath = $uploadDir['path'] . '/' . $uniqueName;

        // Copy file to uploads
        if (!copy($absolutePath, $targetPath)) return null;

        // Get the MIME type
        $mimeType = wp_check_filetype(basename($targetPath));

        // Create attachment
        $attachment = [
            'guid'           => $uploadDir['url'] . '/' . basename($targetPath),
            'post_mime_type' => $mimeType['type'],
            'post_title'     => sanitize_file_name(pathinfo(basename($targetPath), PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attachmentId = wp_insert_attachment($attachment, $targetPath);
        if (is_wp_error($attachmentId) || $attachmentId === 0) return null;

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachData = wp_generate_attachment_metadata($attachmentId, $targetPath);
        wp_update_attachment_metadata($attachmentId, $attachData);

        // Clean up temp file
        @unlink($absolutePath);

        return [
            'url' => wp_get_attachment_url($attachmentId),
            'attachmentId' => $attachmentId
        ];
    }



    public function SendSuccessMessage($data)
    {
        echo json_encode(array(
            'success'=>true,
            'result'=>$data)
        );
        die;
    }

    public function SendErrorMessage($errorMessage)
    {
        echo json_encode(array(
                'success'=>false,
                'errorMessage'=>$errorMessage)
        );
        die;
    }

    /**
     * AI Template Generation handler.
     * Processes user messages and generates PDF template data via AI.
     */
    public function AIGenerateTemplate(){
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor = new HttpPostProcessor();
        $nonce = $processor->GetRequired('nonce');
        
        if(wp_verify_nonce($nonce, 'rnwcinv_savenonce') === false){
            $processor->SendErrorMessage('Invalid request');
        }

        $data = $processor->data;
        $message = isset($data->message) ? $data->message : '';
        $history = isset($data->history) && is_array($data->history) ? $data->history : [];
        $model = isset($data->model) ? $data->model : rnwcinv_get_ai_default_model();
        $files = isset($data->files) && is_array($data->files) ? $data->files : [];

        // Get the API key for the selected model
        $apiKey = rnwcinv_get_ai_api_key($model);
        if(empty($apiKey)){
            $processor->SendErrorMessage('No API key configured for this model. Please go to Settings > AI to add one.');
        }

        require_once RednaoWooCommercePDFInvoice::$DIR . 'api/ai/AITemplateProcessor.php';
        $aiProcessor = new \rnwcinv\api\AITemplateProcessor();
        $result = $aiProcessor->process($message, $history, $model, $apiKey, $files);

        if(isset($result['error'])){
            $processor->SendErrorMessage($result['error']);
        }

        // Return the HTML and updated history to the frontend
        $processor->SendSuccessMessage([
            'html'    => isset($result['html']) ? $result['html'] : '',
            'history' => isset($result['history']) ? $result['history'] : []
        ]);
    }

    /**
     * AJAX handler: Generate a real PDF preview from DocumentOptions.
     * Returns base64-encoded PDF data.
     * 
     * Data arrives via WpAjaxPost which wraps everything in $_POST['data'] as JSON.
     */
    public function AIPreviewTemplate(){
        $processor=new HttpPostProcessor();

        $rawData = isset($_POST['data']) ? stripslashes($_POST['data']) : '';
        if(empty($rawData)){
            $processor->SendErrorMessage('No template data provided');
        }

        $data = json_decode($rawData);
        if($data === null || !isset($data->pageOptions)){
            $processor->SendErrorMessage('Invalid template data');
        }

        // pageOptions arrives as a JSON string inside data — decode it
        $pageOptions = is_string($data->pageOptions) ? json_decode($data->pageOptions) : $data->pageOptions;
        if($pageOptions === null){
            $processor->SendErrorMessage('Could not parse template options');
        }

        require_once RednaoWooCommercePDFInvoice::$DIR . 'api/ai/AITemplateProcessor.php';
        \rnwcinv\Managers\LogManager::LogDebug("=== AI DOCUMENT OPTIONS ===\r\n" . json_encode($pageOptions, JSON_PRETTY_PRINT) . "\r\n=== END DOCUMENT OPTIONS ===");
        $aiProcessor = new \rnwcinv\api\AITemplateProcessor();
        $result = $aiProcessor->generatePreview($pageOptions);

        if(isset($result['error'])){
            $processor->SendErrorMessage($result['error']);
        }

        $processor->SendSuccessMessage([
            'pdf' => isset($result['pdf']) ? $result['pdf'] : ''
        ]);
    }

    /**
     * AJAX handler: Return the compiled system prompt for external chat mode.
     * The frontend uses this to build a complete prompt the user can copy.
     */
    public function AIGetPrompt(){
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor = new HttpPostProcessor();
        $nonce = $processor->GetRequired('nonce');

        if(wp_verify_nonce($nonce, 'rnwcinv_savenonce') === false){
            $processor->SendErrorMessage('Invalid request');
        }

        require_once RednaoWooCommercePDFInvoice::$DIR . 'api/ai/AITemplateProcessor.php';
        $aiProcessor = new \rnwcinv\api\AITemplateProcessor();
        $systemPrompt = $aiProcessor->getSystemPrompt();

        if($systemPrompt === false){
            $processor->SendErrorMessage('Could not load AI prompt template file.');
        }

        $processor->SendSuccessMessage([
            'systemPrompt' => $systemPrompt
        ]);
    }

    /**
     * AJAX handler: Process an externally-pasted AI response.
     * Extracts HTML from the raw text (handles markdown code blocks, etc.).
     */
    public function AIProcessExternalResponse(){
        RednaoWooCommercePDFInvoice::CheckIfPDFAdmin();
        $processor = new HttpPostProcessor();
        $nonce = $processor->GetRequired('nonce');

        if(wp_verify_nonce($nonce, 'rnwcinv_savenonce') === false){
            $processor->SendErrorMessage('Invalid request');
        }

        $data = $processor->data;
        $responseText = isset($data->responseText) ? $data->responseText : '';

        if(empty($responseText)){
            $processor->SendErrorMessage('No response text provided.');
        }

        require_once RednaoWooCommercePDFInvoice::$DIR . 'api/ai/AITemplateProcessor.php';
        $aiProcessor = new \rnwcinv\api\AITemplateProcessor();
        $html = $aiProcessor->extractHtml($responseText);

        if(empty($html)){
            $processor->SendErrorMessage('Could not extract HTML from the provided response.');
        }

        $processor->SendSuccessMessage([
            'html' => $html
        ]);
    }


}

new RednaoWooCommercePDFInvoiceAjax();

