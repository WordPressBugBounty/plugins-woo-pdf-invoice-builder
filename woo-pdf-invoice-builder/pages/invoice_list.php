<?php
if(!defined('ABSPATH'))
    die('Forbidden');

global $wpdb;
$count=$wpdb->get_var('select count(*) count from '.RednaoWooCommercePDFInvoice::$INVOICE_TABLE);




if(isset($_GET['action']))
{
    switch ($_GET['action'])
    {
        case 'clone':
            if(!wp_verify_nonce($_GET['nonce'],'clone_invoice_'.$_GET['id']))
            {
                echo '<script type="application/javascript">alert("'.esc_js(__('Invalid nonce, please refresh your screen and try again','woo-pdf-invoice-builder')).'")</script>';

            }else
            {

                if (isset($_GET['id']))
                {
                    if ($count > 0 && !RednaoWooCommercePDFInvoice::IsPR())
                    {
                        echo '<script type="application/javascript">alert("'.esc_js(__('Sorry, you can have only one invoice template in the free version','woo-pdf-invoice-builder')).'")</script>';


                    } else
                    {


                        $invoiceId = $_GET['id'];
                        $template = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . RednaoWooCommercePDFInvoice::$INVOICE_TABLE . ' WHERE invoice_id = %d', $invoiceId));
                        if ($template)
                        {
                            $newInvoiceName = $template->name . ' (Clone)';
                            $wpdb->insert(
                                RednaoWooCommercePDFInvoice::$INVOICE_TABLE,
                                array(
                                    'name' => $newInvoiceName,
                                    'attach_to' => $template->attach_to,
                                    'options' => $template->options,
                                    'create_when' => $template->create_when,
                                    'html' => $template->html,
                                    'conditions' => $template->conditions,
                                    'order_actions' => $template->order_actions,
                                    'type' => $template->type,
                                    'my_account_download' => $template->my_account_download,
                                    'extensions' => $template->extensions,
                                    'pages' => $template->pages,
                                    'email_config' => $template->email_config
                                )
                            );
                            echo '<script type="application/javascript">window.location="?page=wc_invoice_menu&id=' . $wpdb->insert_id . '&action=edit"</script>';
                        } else
                        {
                            echo '<script type="application/javascript">alert("'.esc_js(__('Invoice template not found','woo-pdf-invoice-builder')).'");</script>';
                        }
                    }
                }
            }
            break;
        case 'add':

            if($count>0&&!RednaoWooCommercePDFInvoice::IsPR())
            {
                echo '<script type="application/javascript">alert("'.esc_js(__('Sorry, you can have only one invoice template in the free version','woo-pdf-invoice-builder')).'")</script>';

            }else
            {
                require_once RednaoWooCommercePDFInvoice::$DIR . 'pages/invoice_builder.php';
                return;
            }
            break;
        case 'delete':
            if(!isset($_GET['id'])){
                return;
            }


            $invoiceId=$_GET['id'];
            if(!wp_verify_nonce($_GET['nonce'],'delete_invoice_'.$_GET['id']))
            {
                echo '<script type="application/javascript">alert("'.esc_js(__('Invalid nonce, please refresh your screen and try again','woo-pdf-invoice-builder')).'")</script>';
            }else
            {

                $wpdb->query($wpdb->prepare('delete from ' . RednaoWooCommercePDFInvoice::$INVOICE_TABLE . ' where invoice_id=%d', $invoiceId));
                $count -= 1;
            }
            break;
        case 'bulk_delete':
            if(!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'],'bulk-templates'))
            {
                echo '<script type="application/javascript">alert("'.esc_js(__('Invalid nonce, please refresh your screen and try again','woo-pdf-invoice-builder')).'")</script>';
            }else
            {
                $ids = isset($_GET['template_ids']) ? array_map('intval', (array)$_GET['template_ids']) : array();
                if(!empty($ids))
                {
                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                    $wpdb->query($wpdb->prepare('DELETE FROM ' . RednaoWooCommercePDFInvoice::$INVOICE_TABLE . ' WHERE invoice_id IN (' . $placeholders . ')', $ids));
                    $count -= count($ids);
                }
            }
            break;
        case 'edit':
            require_once RednaoWooCommercePDFInvoice::$DIR . 'pages/invoice_builder.php';
            return;
        case 'import':
            require_once RednaoWooCommercePDFInvoice::$DIR.'template-importer.php';
            break;

    }
}
if(!class_exists('WP_LIST_TABLE'))
{
    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
}


wp_enqueue_script('jquery');
wp_enqueue_script('wrcrbc-bootstrap-list',RednaoWooCommercePDFInvoice::$URL.'js/screens/InvoiceList.js');
wp_localize_script('wrcrbc-bootstrap-list','rednaoPDFInvoiceParamsList',array(
    'AddNewURL'=>sprintf('?page=%s&action=%s','wc_invoice_menu','add'),
    'TemplateCount'=>$count,
    'IsPR'=>RednaoWooCommercePDFInvoice::IsPR()

));

wp_enqueue_style('wcrbc-bootstrap',RednaoWooCommercePDFInvoice::$URL.'css/bootstrap/css/bootstrap.min.css');
wp_enqueue_style('wcrbc-bootstrap-theme',RednaoWooCommercePDFInvoice::$URL.'css/bootstrap/css/bootstrap-theme.min.css');




?>
    <div class="bootstrap-wrapper">
        <button class="btn btn-success createInvoice" href="#" style="margin-top: 10px;" ><span class="glyphicon glyphicon-plus" style="padding-right:10px;"></span><?php echo esc_html__('Create New Invoice','woo-pdf-invoice-builder');?></button>

        <button id="invoiceImport" class="btn btn-warning" href="#" style="margin-top: 10px;" ><span class="glyphicon glyphicon-import" style="padding-right:10px;"></span><?php echo esc_html__('Import','woo-pdf-invoice-builder');?></button>
            <form action="?page=wc_invoice_menu&action=import" method="post" enctype="multipart/form-data" id="formImporter" style="display: none;">
                <input name="files" accept=".zip" type="file" id="fileToImport"/>

            </form>


    </div>


<?php
class InvoiceList extends WP_List_Table
{
    function __construct()
    {
        parent::__construct(array('plural' => 'templates'));
    }

    function get_columns()
    {
        return array(
            'cb'=>'<input type="checkbox" />',
            'name'=>__('Template Name','woo-pdf-invoice-builder')
        );
    }

    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="template_ids[]" value="%d" />', $item->invoice_id);
    }

    function get_bulk_actions()
    {
        return array(
            'bulk_delete' => __('Delete', 'woo-pdf-invoice-builder')
        );
    }

    function prepare_items()
    {
        $this->_column_headers=array($this->get_columns(),array(),$this->get_sortable_columns());
        global $wpdb;
        $query="SELECT invoice_id,name,attach_to from ".RednaoWooCommercePDFInvoice::$INVOICE_TABLE;

        if(isset($_GET['s']))
        {

            $query=$query." where name like '%".esc_sql($wpdb->esc_like(sanitize_text_field($_GET['s'])))."%'";
        }
        $invoices=$result=$wpdb->get_results($query.' order by '.sanitize_sql_orderby($this->GetOrderByName().' '.$this->GetOrderByDirection()));
        foreach($invoices as $invoice)
        {
            $invoice->name=esc_html($invoice->name);
            $attachTo=json_decode($invoice->attach_to);
            $attachToText='';
            if($attachTo!=null)
                foreach ($attachTo as $attachToItem)
                {
                    if($attachToText!='')
                        $attachToText.=',';
                    $attachToText.=esc_html($attachToItem);
                }
            $invoice->attach_to=$attachToText;
        }
        $this->items=$invoices;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'name'     => array('name',!isset($_GET['order'])?true:$this->GetOrderByDirection())
        );
        return $sortable_columns;
    }

    function GetOrderByName(){
        return 'name';
    }


    function GetOrderByDirection(){
        $orderBy='asc';
        if(isset($_GET['order']))
            $orderBy=strval($_GET['order']);
        if($orderBy!='desc'&&$orderBy!='asc')
            $orderBy='asc';

        return $orderBy;

    }
    function column_default($item, $column_name)
    {
        return $item->$column_name;
    }

    function column_name($item) {
        $actions = array(
            __('edit','woo-pdf-invoice-builder')      => sprintf('<a href="?page=%s&id=%s&action=%s">'.esc_html__('Edit','woo-pdf-invoice-builder').'</a>','wc_invoice_menu',$item->invoice_id,'edit'),
            __('delete','woo-pdf-invoice-builder')    => sprintf('<a href="javascript:(function(event){confirm(\''.esc_js(__('Are you sure you want to delete the form?','woo-pdf-invoice-builder')).'\')?(window.location=\'?page=%s&id=%s&action=%s&nonce=%s\'):\'\'; return false;})()">'.esc_html__('Delete','woo-pdf-invoice-builder').'</a>','wc_invoice_menu',$item->invoice_id,'delete',wp_create_nonce('delete_invoice_'.$item->invoice_id)),
            __('clone','woo-pdf-invoice-builder')      => sprintf('<a href="?page=%s&id=%s&action=%s&nonce=%s">'.esc_html__('Clone','woo-pdf-invoice-builder').'</a>','wc_invoice_menu',$item->invoice_id,'clone',wp_create_nonce('clone_invoice_'.$item->invoice_id)),
        );

        return sprintf('%1$s %2$s', $item->name, $this->row_actions($actions) );
    }
}


echo '<style>.tablenav.top { margin-bottom: 10px; }</style>';
echo '<form method="get">';
echo "<input type='hidden' name='page' value='wc_invoice_menu'/>";
$invoiceList=new InvoiceList();
$invoiceList->prepare_items();
$invoiceList->search_box(__('Search Template','woo-pdf-invoice-builder'),'Name');
$invoiceList->display();
echo '</form>';
?>