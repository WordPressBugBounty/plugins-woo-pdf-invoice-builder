<?php


namespace rnwcinv\utilities;


use Exception;
use function wp_upload_dir;

class FileManager
{


    private $_rootPath='';

    public function __construct()
    {


    }


    public function GetRootFolderPath()
    {
        if($this->_rootPath=='')
        {
            $uploadDir=wp_upload_dir();
            $this->_rootPath=$uploadDir['basedir'].'/rnwcinv/';
            $this->MaybeCreateFolder($this->_rootPath,false);
        }
        return $this->_rootPath;
    }


    public function GetFontURL(){
        $dir=wp_upload_dir();
        return $dir['baseurl'].'/rnwcinv/';
    }


    public function GetTempFolderRootPath()
    {
        $tempFolder=$this->GetRootFolderPath().'temp/';
        $this->MaybeCreateFolder($tempFolder,true);
        return $tempFolder;

    }

    public function RemoveTempFolders(){
        $path=$this->GetTempFolderRootPath();
        foreach(( glob( $path.'*' ) ? glob( $path.'*' ) : array() ) as $path)
        {
            if(!\is_dir($path))
                continue;
            $this->recursiveRemove($path);
        }

        // Also clean public temp subfolders
        $publicPath = $this->GetPublicTempFolderRootPath();
        foreach (( glob( $publicPath.'*' ) ? glob( $publicPath.'*' ) : array() ) as $dir) {
            if (!\is_dir($dir)) continue;
            $this->recursiveRemove($dir);
        }
    }

    public function recursiveRemove($dir) {
        $structure = glob(rtrim($dir, "/").'/*');
        if (is_array($structure)) {
            foreach($structure as $file) {
                if (is_dir($file)) $this->recursiveRemove($file);
                elseif (is_file($file)) unlink($file);
            }
        }
        rmdir($dir);
    }
    public function GetTemporalFolderPath(){
        $tempPath=$this->GetTempFolderRootPath();
        $i=1;
        $tempFolderToReturn='';
        while(is_dir($tempFolderToReturn=$tempPath.'temp'.$i.'/'))
        {
            $i++;
        }

        if(!\mkdir($tempFolderToReturn))
            throw new Exception('Could not create folder '.$tempFolderToReturn);

        return $tempFolderToReturn;
    }

    /**
     * Returns the root path for publicly-accessible temp files (no .htaccess deny).
     * Used for AI-generated images that need to be served via HTTP.
     */
    public function GetPublicTempFolderRootPath()
    {
        $publicTempFolder = $this->GetRootFolderPath() . 'public_temp/';
        if (!is_dir($publicTempFolder)) {
            if (!mkdir($publicTempFolder, 0777, true))
                throw new Exception('Could not create folder ' . $publicTempFolder);
            // Only add index.php to prevent directory listing — NO .htaccess deny
            @touch($publicTempFolder . 'index.php');
        }
        return $publicTempFolder;
    }

    /**
     * Creates and returns a unique subfolder inside public_temp/.
     * These files are accessible via HTTP (no .htaccess blocking).
     */
    public function GetPublicTemporalFolderPath()
    {
        $tempPath = $this->GetPublicTempFolderRootPath();

        // Use uniqid() with extra entropy so parallel AJAX requests (e.g. the AI
        // converter uploading multiple SVG images at once) cannot race on the same
        // folder name. The previous sequential scan + mkdir was not atomic and
        // could fail when two requests both targeted the next free tempN.
        $tempFolderToReturn = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $tempFolderToReturn = $tempPath . 'temp' . uniqid('', true) . '/';
            if (!is_dir($tempFolderToReturn) && @\mkdir($tempFolderToReturn)) {
                return $tempFolderToReturn;
            }
        }

        throw new Exception('Could not create folder ' . $tempFolderToReturn);
    }

    public function MaybeCreateFolder($directory,$secure=false)
    {
        if(!is_dir($directory))
            if(!mkdir($directory,0777,true))
                throw new Exception('Could not create folder '.$this->_rootPath);
            else{
                if($secure)
                {
                    @file_put_contents( $directory . '.htaccess', 'deny from all' );
                    @touch( $directory . 'index.php' );
                }
            }


    }

    public function GetLoggerPath()
    {
        $logFolder = $this->GetRootFolderPath() . 'log/';
        $this->MaybeCreateFolder($logFolder, true);
        return $logFolder;
    }

}