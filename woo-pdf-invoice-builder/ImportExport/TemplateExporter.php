<?php

namespace rnwcinv\ImportExport;

use rnwcinv\pr\utilities\FontManager;
use rnwcinv\utilities\ArrayUtils;
use rnwcinv\utilities\FileManager;

class TemplateExporter
{
    /** @var \ZipArchive  */
    private $zip;
    private $path='';
    private $fileCount=0;
    public $CachedImages;
    public function __construct()
    {
        $this->zip=new \ZipArchive();


    }


    public function Export($options)
    {
        $this->CachedImages=[];
        if(!$this->CreateFile($options))
            return '';

        $this->ParseCSSImages($options);
        if(isset($options->options->BackgroundFile)&&$options->options->BackgroundFile!='')
        {
            $file=$this->ResolveFile($options->options->BackgroundFile);

            $options->options->BackgroundImage='';
            $options->options->BackgroundFile='';
            if($file!=false)
            {
                $fileName=$this->AddImage(null,$file,'');
                // Store the filename at the root level (the importer reads it from here)
                $options->BackgroundImage=$fileName;

                // Also strip the background-image URL from the .pdfBody CSS rule,
                // since the actual file is now in the ZIP and handled via BackgroundImage property.
                // The importer will re-inject the new URL after importing the image.
                $styles=$options->options->styles;
                // Target only the .pdfBody rule and strip its background-related properties
                $styles=preg_replace_callback(
                    '/(\.pdfBody\s*\{)([^}]*)(\})/',
                    function($m){
                        $body=$m[2];
                        $body=preg_replace('/background-image\s*:\s*url\([^)]*\)\s*;?/','',$body);
                        $body=preg_replace('/background-repeat\s*:\s*[^;]+;?/','',$body);
                        $body=preg_replace('/background-position\s*:\s*[^;]+;?/','',$body);
                        $body=preg_replace('/background-size\s*:\s*[^;]+;?/','',$body);
                        // If the rule is now empty, remove it entirely
                        if(trim($body)=='')
                            return '';
                        return $m[1].$body.$m[3];
                    },
                    $styles
                );
                $options->options->styles=$styles;
            }

        }
        $needsPR=false;

        foreach($options->pages as $currentPage)
        {
            foreach($currentPage->fields as $currentField)
            {
                if($currentField->type=='image')
                {

                    $file=$this->ResolveFile($currentField->URL_ID);
                    $currentField->URL_ID='';
                    $currentField->URL='';
                    if($file===false)
                        continue;
                    $this->AddImage($currentField,$file,'URL_ID');
                }

                if(in_array($currentField->type,['qrcode','barcode']))
                    $needsPR=true;
            }
        }

        if($this->ExportFonts($options->options->styles))
        {
            $needsPR=true;
        }


        $options->NeedsPR=$needsPR;
        $this->zip->addFromString('Options.json',json_encode($options));
        $this->zip->close();


        return $this->path;




    }

    private function CreateFile($options)
    {
        $name='';
        if(isset($options->name))
            $name=trim(sanitize_file_name($options->name)).'.zip';
        if($name=='')
            return false;

        $fileManager=new FileManager();
        $this->path=$fileManager->GetTemporalFolderPath().$name;
        $this->zip->open($this->path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE);
        return true;
    }

    public function Destroy()
    {
        $fileManager=new FileManager();
        $fileManager->GetTempFolderRootPath();

        if(strpos($this->path, $fileManager->GetTempFolderRootPath())===false)
            return;
        if(is_dir($this->path))
            array_map('unlink', glob($this->path."/*.*"));
        else
            unlink($this->path);
        rmdir(dirname($this->path));
    }

    private function AddImage($options, $imagePath, $propertyName)
    {
        $cachedImage=ArrayUtils::Find($this->CachedImages,function ($item)use($imagePath){
            if($item->Path==$imagePath)
                return $item;
        });

        if($cachedImage==null) {
            $this->fileCount++;
            $fileName = 'File' . $this->fileCount . '.' . pathinfo($imagePath, PATHINFO_EXTENSION);
            $this->zip->addFromString('Images/'.$fileName,file_get_contents($imagePath));

            $cachedImage=new \stdClass();
            $cachedImage->Path=$imagePath;
            $cachedImage->FileName=$fileName;
            $this->CachedImages[]=$cachedImage;
        }

        if($options!=null)
            $options->{$propertyName}=$cachedImage->FileName;

        return $cachedImage->FileName;

    }

    /**
     * Resolves a file reference to an absolute path.
     * Handles both WordPress attachment IDs (numeric) and relative file paths
     * from AI-generated templates (e.g. 'public_temp/temp467/file.png').
     */
    private function ResolveFile($fileRef)
    {
        if($fileRef===''||$fileRef===null)
            return false;

        // Numeric = WordPress attachment ID
        if(is_numeric($fileRef))
        {
            $file=get_attached_file(intval($fileRef));
            return ($file!==false && file_exists($file)) ? $file : false;
        }

        // String = relative path from FileManager root (AI templates)
        $fileManager=new FileManager();
        $absolutePath=$fileManager->GetRootFolderPath().$fileRef;
        if(file_exists($absolutePath))
            return $absolutePath;

        return false;
    }

    private function ExportFonts($styles)
    {
        $useCustomFonts=false;
        if(\RednaoWooCommercePDFInvoice::IsPR()) {
            $fontManager=new FontManager();
            $fonts=$fontManager->GetAvailableFonts(false);
            $matches = array();
            preg_match_all('/font-family:([^!]*) !important;/', $styles, $matches, PREG_SET_ORDER);

            foreach ($matches as $currentMatch) {
                if (count($currentMatch) != 2)
                    continue;

                $fontToExport=sanitize_file_name($currentMatch[1]);

                $fontToExport=ArrayUtils::Find($fonts,function ($item)use($fontToExport){
                    return $item->Name==$fontToExport;
                });

                if($fontToExport==null)
                    continue;

                $physicalBaseName=$fontToExport->Name;

                $fontName=$physicalBaseName.'.ttf';
                if(file_exists($fontManager->folderPath.$fontName))
                {
                    $useCustomFonts=true;
                    $this->zip->addFromString('Fonts/'.$fontName,file_get_contents($fontManager->folderPath.$fontName));
                }else{
                    continue;
                }

                if($fontToExport->HasBold)
                {
                    $fontName=$physicalBaseName.'__bold.ttf';
                    if(file_exists($fontManager->folderPath.$fontName))
                    {
                        $this->zip->addFromString('Fonts/'.$fontName,file_get_contents($fontManager->folderPath.$fontName));
                    }
                }

                if($fontToExport->HasItalic)
                {
                    $fontName=$physicalBaseName.'__italic.ttf';
                    if(file_exists($fontManager->folderPath.$fontName))
                    {
                        $this->zip->addFromString('Fonts/'.$fontName,file_get_contents($fontManager->folderPath.$fontName));
                    }
                }

                if($fontToExport->HasBoldItalic)
                {
                    $fontName=$physicalBaseName.'__bolditalic.ttf';
                    if(file_exists($fontManager->folderPath.$fontName))
                    {
                        $this->zip->addFromString('Fonts/'.$fontName,file_get_contents($fontManager->folderPath.$fontName));
                    }
                }



            }
        }

        return $useCustomFonts;
    }

    private function ParseCSSImages($options)
    {
        $styles=$options->options->styles;
        $matches=[];
        preg_match_all('/url\([^\)]*\)\/\*FileId:([^\*]*)[^;]*;/',$styles,$matches,PREG_SET_ORDER);

        foreach($matches as $currentMatch)
        {
            if(count($currentMatch)!=2)
                continue;
            $file=get_attached_file($currentMatch[1]);
            if($file==false)
                continue;
            $fileName=$this->AddImage(null,$file,'');
            $styles=str_replace($currentMatch[0],'@@@@File'.$fileName.'File@@@@',$styles);

        }

        $options->options->styles=$styles;
    }


}