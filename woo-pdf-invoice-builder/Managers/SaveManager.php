<?php

/**
 * Handles security validations during template save operations.
 * Prevents non-SuperAdmin users from injecting dynamic PHP code.
 */
class SaveManager
{
    /**
     * Validates that a non-SuperAdmin user is not injecting or modifying dynamic PHP code.
     *
     * @param int $pageId The template ID (0 for new templates)
     * @param string $pagesJson The pages JSON string being saved
     * @param string $containerOptionsJson The container options JSON string being saved
     * @return string|null Error message if validation fails, null if OK
     */
    public static function ValidateDynamicCodeSecurity($pageId, $pagesJson, $containerOptionsJson)
    {
        if(is_super_admin())
            return null;

        $newDynamicCodes = self::ExtractDynamicCodes($pagesJson);
        self::ExtractDynamicCodesFromContainerOptions($containerOptionsJson, $newDynamicCodes);

        if($pageId == 0 || $pageId == null)
        {
            // New template: no dynamic code allowed at all
            if(count($newDynamicCodes) > 0)
            {
                return 'Only super admins can create templates with dynamic code';
            }
        }
        else
        {
            // Update: compare against existing template
            global $wpdb;
            $existingRow = $wpdb->get_row($wpdb->prepare(
                'SELECT pages, options FROM '.RednaoWooCommercePDFInvoice::$INVOICE_TABLE.' WHERE invoice_id=%d',
                $pageId
            ));
            $existingDynamicCodes = [];
            if($existingRow !== null)
            {
                if(!empty($existingRow->pages))
                    $existingDynamicCodes = self::ExtractDynamicCodes($existingRow->pages);
                if(!empty($existingRow->options))
                    self::ExtractDynamicCodesFromContainerOptions($existingRow->options, $existingDynamicCodes);
            }

            // Check if any new dynamic code was added or existing code was modified
            foreach($newDynamicCodes as $key => $code)
            {
                if(!isset($existingDynamicCodes[$key]))
                {
                    return 'Only super admins can add dynamic code to templates';
                }
                if($existingDynamicCodes[$key] !== $code)
                {
                    return 'Only super admins can edit dynamic code in templates';
                }
            }
        }

        return null;
    }

    /**
     * Extracts all dynamic PHP code entries from template pages JSON.
     * Returns an associative array keyed by a unique identifier for each dynamic code entry.
     *
     * Scans for:
     *  - Custom field blocks (type='custom') where CustomFieldId='dynamic' → DynamicCode
     *  - Table columns (type='table' or 'refundtable') where customProperties.id starts with 'Dynamic__' → customProperties.code
     *
     * @param string $pagesJson The pages JSON string
     * @return array Keyed array of [ identifier => code ]
     */
    private static function ExtractDynamicCodes($pagesJson)
    {
        $codes = [];
        if(empty($pagesJson))
            return $codes;

        $pages = json_decode($pagesJson);
        if($pages === null || !is_array($pages))
            return $codes;

        foreach($pages as $page)
        {
            if(!isset($page->fields) || !is_array($page->fields))
                continue;
            foreach($page->fields as $field)
            {
                self::ExtractDynamicCodesFromField($field, $codes);
            }
        }

        return $codes;
    }

    /**
     * Recursively extracts dynamic code from a single field (and its children).
     *
     * @param object $field The field object
     * @param array &$codes The codes array to populate
     */
    private static function ExtractDynamicCodesFromField($field, &$codes)
    {
        if(!isset($field->type))
            return;

        $type = $field->type;

        // Custom field block with dynamic code
        if($type === 'custom')
        {
            $customFieldId = isset($field->CustomFieldId) ? $field->CustomFieldId : '';
            if($customFieldId === 'dynamic')
            {
                $dynamicCode = isset($field->DynamicCode) ? $field->DynamicCode : '';
                $fieldId = isset($field->fieldID) ? $field->fieldID : 'unknown';
                $codes['customfield_' . $fieldId] = $dynamicCode;
            }
        }

        // Invoice detail or refund detail table — scan columns
        if($type === 'table' || $type === 'refundtable')
        {
            $columnOptions = isset($field->ColumnOptions) ? $field->ColumnOptions : [];
            if(is_array($columnOptions))
            {
                foreach($columnOptions as $column)
                {
                    if(!isset($column->customProperties))
                        continue;
                    $cp = $column->customProperties;
                    $id = isset($cp->id) ? $cp->id : '';
                    if(strpos($id, 'Dynamic__') === 0)
                    {
                        $code = isset($cp->code) ? $cp->code : '';
                        $fieldId = isset($field->fieldID) ? $field->fieldID : 'unknown';
                        $codes['column_' . $fieldId . '_' . $id] = $code;
                    }
                }
            }
        }


    }

    /**
     * Extracts dynamic codes from containerOptions JSON (repeatable headers/footers).
     *
     * @param string $containerOptionsJson The container options JSON string
     * @param array &$codes The codes array to populate
     */
    private static function ExtractDynamicCodesFromContainerOptions($containerOptionsJson, &$codes)
    {
        if(empty($containerOptionsJson))
            return;

        $containerOptions = json_decode($containerOptionsJson);
        if($containerOptions === null)
            return;

        // Check repeatable header field
        if(isset($containerOptions->RepeatableHeaderField) && is_array($containerOptions->RepeatableHeaderField))
        {
            foreach($containerOptions->RepeatableHeaderField as $field)
            {
                self::ExtractDynamicCodesFromField($field, $codes);
            }
        }

        // Check repeatable footer field
        if(isset($containerOptions->RepeatableFooterField) && is_array($containerOptions->RepeatableFooterField))
        {
            foreach($containerOptions->RepeatableFooterField as $field)
            {
                self::ExtractDynamicCodesFromField($field, $codes);
            }
        }

    }

    /**
     * Promotes temporary images (AI-generated SVG→JPEG, emoji PNGs) to the WordPress media library.
     *
     * @param string $pagesJson The pages JSON string
     * @return string The updated pages JSON string
     */
    public static function PromoteTempImages($pagesJson)
    {
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

                    $result = self::PromoteFileToMedia($field->FilePath, $rootPath, $fileManager);
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
                        '/<img([^>]*?)data-file-path=["\']([^"\']+)["\']([^>]*?)\/?\>/i',
                        function($matches) use ($rootPath, $fileManager, &$textModified) {
                            $beforeAttr = $matches[1];
                            $relativePath = $matches[2];
                            $afterAttr = $matches[3];

                            $result = self::PromoteFileToMedia($relativePath, $rootPath, $fileManager);
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
     * @param string $relativePath Relative path from the plugin's root folder
     * @param string $rootPath     Absolute root path from FileManager
     * @param \rnwcinv\utilities\FileManager $fileManager
     * @return array|null ['url' => string, 'attachmentId' => int] on success, null on failure
     */
    private static function PromoteFileToMedia($relativePath, $rootPath, $fileManager) {
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
}
