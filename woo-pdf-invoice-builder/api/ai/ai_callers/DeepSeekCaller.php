<?php
/**
 * DeepSeekCaller - DeepSeek API provider.
 * 
 * Uses the OpenAI-compatible chat completions endpoint.
 * Does NOT support file attachments (text only).
 * Authentication via Bearer token in Authorization header.
 */

namespace rnwcinv\api;

class DeepSeekCaller extends AICallerBase {

    private static $API_URL = 'https://api.deepseek.com/chat/completions';
    private static $API_MODEL = 'deepseek-chat';

    /**
     * @inheritdoc
     */
    public function callAI($systemPrompt, $messages, $files = []) {
        // Build OpenAI-compatible messages array
        $apiMessages = $this->buildMessages($systemPrompt, $messages);

        $body = [
            'model'       => self::$API_MODEL,
            'messages'    => $apiMessages,
            'temperature' => 0.7,
            'max_tokens'  => 8192
        ];

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey
        ];

        $responseBody = $this->makeRequest(self::$API_URL, $headers, $body);

        return $this->extractTextFromResponse($responseBody);
    }

    /**
     * Builds the OpenAI-compatible messages array.
     * 
     * Format: system message first, then user/assistant alternating.
     * DeepSeek does not support file attachments, so files are ignored.
     */
    private function buildMessages($systemPrompt, $messages) {
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        foreach ($messages as $msg) {
            $apiMessages[] = [
                'role'    => $msg['role'], // 'user' or 'assistant' — same as OpenAI
                'content' => $msg['content']
            ];
        }

        return $apiMessages;
    }

    /**
     * Extracts the text content from DeepSeek's OpenAI-compatible response format.
     * 
     * Response structure: { choices: [{ message: { content: "..." } }] }
     */
    private function extractTextFromResponse($responseBody) {
        if (isset($responseBody['choices'][0]['message']['content'])) {
            return $responseBody['choices'][0]['message']['content'];
        }

        throw new \Exception(
            __('No response content received from DeepSeek API', 'woo-pdf-invoice-builder')
        );
    }

    /**
     * @inheritdoc
     */
    protected function extractErrorMessage($responseBody, $responseCode) {
        // DeepSeek follows the OpenAI error format
        if (isset($responseBody['error']['message'])) {
            return 'DeepSeek API: ' . $responseBody['error']['message'];
        }

        return parent::extractErrorMessage($responseBody, $responseCode);
    }
}
