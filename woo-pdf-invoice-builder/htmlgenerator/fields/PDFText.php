<?php

namespace  rnwcinv\htmlgenerator\fields;



use RednaoWooCommercePDFInvoice;
use rnwcinv\pr\Manager\TagManager;

/**
 * Created by PhpStorm.
 * User: Edgar
 * Date: 10/6/2017
 * Time: 6:52 AM
 */

class PDFText extends PDFFieldBase
{

    protected function InternalGetHTML()
    {
        $text=$this->tagGenerator->StartTag('p','',array('vertical-align'=>'top'),null);
        $textContent = $this->orderValueRetriever->TranslateText($this->options->fieldID,'text',$this->GetPropertyValue('Text'));
        $textContent = $this->processInlineImages($textContent);
        $text.=' '.$textContent;
        $text.=' </p>';

        if(RednaoWooCommercePDFInvoice::IsPR())
        {
            $tag=new TagManager($this->orderValueRetriever);
            $text=$tag->Process($text);
        }
        return $text;
    }

    /**
     * Process inline <img> tags in text content, resolving image sources
     * to local file paths for DOMPDF rendering.
     *
     * Handles two cases:
     * 1. data-file-path: Temp images (emojis from AI generator) stored in public_temp/
     * 2. data-wp-id: WordPress media library attachments
     */
    private function processInlineImages($html)
    {
        // First pass: resolve data-file-path images (temp emoji/AI images)
        $html = preg_replace_callback(
            '/<img([^>]*?)data-file-path=["\']([^"\']+)["\']([^>]*?)\/?>/i',
            function($matches) {
                $beforeAttr = $matches[1];
                $relativePath = $matches[2];
                $afterAttr = $matches[3];

                // Security: reject path traversal
                if (strpos($relativePath, '..') !== false) {
                    return $matches[0];
                }

                // Resolve to absolute path
                require_once \RednaoWooCommercePDFInvoice::$DIR . 'utilities/FileManager.php';
                $fileManager = new \rnwcinv\utilities\FileManager();
                $rootPath = $fileManager->GetRootFolderPath();
                $absolutePath = $rootPath . ltrim($relativePath, '/\\');

                // Security: verify the resolved path is inside public_temp/
                $realAbsolute = realpath($absolutePath);
                $realPublicTemp = realpath($fileManager->GetPublicTempFolderRootPath());
                if ($realAbsolute === false || $realPublicTemp === false) {
                    return $matches[0];
                }
                if (strpos(wp_normalize_path($realAbsolute), wp_normalize_path($realPublicTemp)) !== 0) {
                    return $matches[0];
                }

                // Verify it's a valid image file
                $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($ext, $allowedExts)) {
                    return $matches[0];
                }

                if (!file_exists($absolutePath)) {
                    return $matches[0];
                }

                // Rebuild tag with local file path as src
                $fullTag = '<img' . $beforeAttr . 'data-file-path="' . htmlspecialchars($relativePath) . '"' . $afterAttr . '/>';
                $fullTag = preg_replace('/src=["\'][^"\']*["\']/', 'src="' . htmlspecialchars($absolutePath) . '"', $fullTag);
                return $fullTag;
            },
            $html
        );

        // Second pass: resolve data-wp-id images (WordPress media library)
        $html = preg_replace_callback(
            '/<img([^>]*?)data-wp-id=["\'](\d+)["\']([^>]*?)\/?>/i',
            function($matches) {
                $beforeAttr = $matches[1];
                $wpId = intval($matches[2]);
                $afterAttr = $matches[3];

                $filePath = \get_attached_file($wpId);
                if (!empty($filePath) && file_exists($filePath)) {
                    // Rebuild the tag and replace src with local file path
                    $fullTag = '<img' . $beforeAttr . 'data-wp-id="' . $wpId . '"' . $afterAttr . '/>';
                    $fullTag = preg_replace('/src=["\'][^"\']*["\']/', 'src="' . htmlspecialchars($filePath) . '"', $fullTag);
                    return $fullTag;
                }
                // Fallback: return original tag unchanged
                return $matches[0];
            },
            $html
        );

        return $html;
    }
}

