<?php

namespace  rnwcinv\htmlgenerator\fields;



/**
 * Created by PhpStorm.
 * User: Edgar
 * Date: 10/6/2017
 * Time: 6:52 AM
 */

class PDFImage extends PDFFieldBase
{

    protected function InternalGetHTML()
    {
        $fieldId=$this->GetPropertyValue('URL_ID');
        if($fieldId==''||get_attached_file($fieldId)=='') {
            // No valid WordPress attachment — check for a direct FilePath (relative to FileManager root)
            $filePath = $this->GetPropertyValue('FilePath');
            $resolvedPath = !empty($filePath) ? $this->resolveImagePath($filePath) : false;
            if ($resolvedPath !== false) {
                $path = $resolvedPath;
            } else {
                $path = \RednaoWooCommercePDFInvoice::$DIR . 'images/temporalImage.png';
            }
        } else {
            $path=get_attached_file($fieldId);
        }
        $imgHtml = '<img '.$this->CreateStyleString(array(
                'width'=>$this->GetStyleValue('width'),
                'height'=>$this->GetStyleValue('height')

            )).' src="'.htmlspecialchars($path).'"/>';

        // Wrap in link if LinkType is set
        $linkType = $this->GetPropertyValue('LinkType');
        if (!empty($linkType) && $linkType !== 'none') {
            $url = '';
            if ($linkType === 'PaymentPage') {
                if ($this->orderValueRetriever->useTestData) {
                    $url = 'http://fakeurl.usedforpreview';
                } else {
                    $url = $this->orderValueRetriever->order->get_checkout_payment_url();
                }
            } else if ($linkType === 'CustomUrl') {
                $url = $this->GetPropertyValue('CustomUrl');
            }

            if (!empty($url)) {
                $imgHtml = '<a target="_blank" href="'.esc_attr($url).'">'.$imgHtml.'</a>';
            }
        }

        return $imgHtml;

    }

    /**
     * Resolves a relative file path (relative to FileManager root folder) to an
     * absolute path. Returns the absolute path if valid, or false if:
     *  1. Path contains traversal sequences
     *  2. Resolved path escapes the root folder
     *  3. File doesn't exist
     *  4. Extension is not a valid image type
     */
    private function resolveImagePath($relativePath) {
        // Block path traversal
        if (strpos($relativePath, '..') !== false) {
            return false;
        }

        // Build absolute path from FileManager root + relative path
        $fileManager = new \rnwcinv\utilities\FileManager();
        $rootPath = $fileManager->GetRootFolderPath();
        $absolutePath = $rootPath . ltrim($relativePath, '/\\');

        // Resolve to real path (must exist on disk)
        $realPath = realpath($absolutePath);
        if ($realPath === false) {
            return false;
        }

        // Verify it's still inside the root folder
        $realRoot = realpath($rootPath);
        if ($realRoot === false) {
            return false;
        }

        $realPath = wp_normalize_path($realPath);
        $realRoot = wp_normalize_path($realRoot);

        if (strpos($realPath, $realRoot) !== 0) {
            return false;
        }

        // Must have a valid image extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            return false;
        }

        return $realPath;
    }
}