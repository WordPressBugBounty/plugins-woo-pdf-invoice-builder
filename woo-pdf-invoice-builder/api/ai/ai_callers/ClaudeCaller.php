<?php
/**
 * ClaudeCaller - Anthropic Claude API provider.
 *
 * Uses the /v1/messages endpoint.
 * Supports multimodal requests (text + images + PDFs) via base64 content blocks.
 * Authentication via x-api-key header (NOT Bearer).
 */

namespace rnwcinv\api;

class ClaudeCaller extends AICallerBase {

    private static $API_URL = 'https://api.anthropic.com/v1/messages';
    private static $ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Maps internal model names to Anthropic API model IDs.
     */
    private static $MODEL_MAP = [
        'claudeopus47'   => 'claude-opus-4-7',
        'claudesonnet46' => 'claude-sonnet-4-6',
    ];

    /**
     * @inheritdoc
     */
    public function callAI($systemPrompt, $messages, $files = []) {
        $apiModelId = $this->getApiModelId();

        $body = [
            'model'      => $apiModelId,
            'max_tokens' => 8192,
            'system'     => $systemPrompt,
            'messages'   => $this->buildMessages($messages, $files),
        ];

        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => self::$ANTHROPIC_VERSION,
        ];

        $responseBody = $this->makeRequest(self::$API_URL, $headers, $body);

        return $this->extractTextFromResponse($responseBody);
    }

    /**
     * Builds the Claude `messages` array from conversation messages and files.
     *
     * Claude format:
     * - role "user" / "assistant"
     * - content is either a string (text-only) or an array of content blocks
     *   (text, image, document) when files are attached
     * - Files are attached only to the last user message
     */
    private function buildMessages($messages, $files) {
        $apiMessages = [];

        foreach ($messages as $index => $msg) {
            $role = $msg['role'] === 'assistant' ? 'assistant' : 'user';
            $isLastUserMessage = ($role === 'user') && $this->isLastUserMessage($messages, $index);

            if ($isLastUserMessage && !empty($files)) {
                $contentBlocks = [];
                foreach ($files as $file) {
                    if (empty($file['base64Data']) || empty($file['mimeType'])) {
                        continue;
                    }

                    $block = $this->buildFileBlock($file);
                    if ($block !== null) {
                        $contentBlocks[] = $block;
                    }
                }
                $contentBlocks[] = ['type' => 'text', 'text' => $msg['content']];

                $apiMessages[] = [
                    'role'    => $role,
                    'content' => $contentBlocks,
                ];
            } else {
                $apiMessages[] = [
                    'role'    => $role,
                    'content' => $msg['content'],
                ];
            }
        }

        return $apiMessages;
    }

    /**
     * Builds a Claude content block for a file attachment.
     * PDFs use the "document" type; images use the "image" type.
     */
    private function buildFileBlock($file) {
        $mimeType = $file['mimeType'];
        $isPdf    = stripos($mimeType, 'pdf') !== false;
        $isImage  = stripos($mimeType, 'image/') === 0;

        if (!$isPdf && !$isImage) {
            return null;
        }

        return [
            'type'   => $isPdf ? 'document' : 'image',
            'source' => [
                'type'       => 'base64',
                'media_type' => $mimeType,
                'data'       => $file['base64Data'],
            ],
        ];
    }

    /**
     * Check if the message at $index is the last user message in the array.
     */
    private function isLastUserMessage($messages, $index) {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user') {
                return $i === $index;
            }
        }
        return false;
    }

    /**
     * Extracts the text content from Claude's response format.
     *
     * Response structure: { content: [{ type: "text", text: "..." }, ...] }
     */
    private function extractTextFromResponse($responseBody) {
        if (isset($responseBody['content']) && is_array($responseBody['content'])) {
            $textParts = [];
            foreach ($responseBody['content'] as $part) {
                if (isset($part['type']) && $part['type'] === 'text' && isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }
            if (!empty($textParts)) {
                return implode('', $textParts);
            }
        }

        throw new \Exception(
            __('No response content received from Claude API', 'woo-pdf-invoice-builder')
        );
    }

    /**
     * Gets the Anthropic API model ID from the internal model name.
     */
    private function getApiModelId() {
        if (isset(self::$MODEL_MAP[$this->model])) {
            return self::$MODEL_MAP[$this->model];
        }

        return $this->model;
    }

    /**
     * @inheritdoc
     */
    protected function extractErrorMessage($responseBody, $responseCode) {
        // Anthropic error format: { error: { type: "...", message: "..." } }
        if (isset($responseBody['error']['message'])) {
            return 'Claude API: ' . $responseBody['error']['message'];
        }

        return parent::extractErrorMessage($responseBody, $responseCode);
    }
}
