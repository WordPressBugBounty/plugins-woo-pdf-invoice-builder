<?php
/**
 * GeminiCaller - Gemini API provider.
 * 
 * Supports multimodal requests (text + images) via inlineData.
 * Uses systemInstruction for the system prompt.
 * Gemini uses "model" role instead of "assistant".
 */

namespace rnwcinv\api;

class GeminiCaller extends AICallerBase {

    /**
     * Maps internal model names to Gemini API model IDs.
     */
    private static $MODEL_MAP = [
        'gemini25pro' => 'gemini-2.5-pro-preview-05-06',
        'gemini3pro'  => 'gemini-2.5-flash',
    ];

    /**
     * @inheritdoc
     */
    public function callAI($systemPrompt, $messages, $files = []) {
        $apiModelId = $this->getApiModelId();
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' 
             . $apiModelId 
             . ':generateContent?key=' . $this->apiKey;

        $body = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents'         => $this->buildContents($messages, $files),
            'generationConfig' => [
                'temperature'    => 0.7,
                'maxOutputTokens' => 65536
            ]
        ];

        $headers = [
            'Content-Type' => 'application/json'
        ];

        $responseBody = $this->makeRequest($url, $headers, $body);

        return $this->extractTextFromResponse($responseBody);
    }

    /**
     * Builds the Gemini `contents` array from conversation messages and files.
     * 
     * Gemini format:
     * - role "user" for user messages
     * - role "model" for assistant messages (NOT "assistant")
     * - Files are sent as inlineData parts alongside the text
     */
    private function buildContents($messages, $files) {
        $contents = [];

        foreach ($messages as $index => $msg) {
            $role    = $msg['role'] === 'assistant' ? 'model' : 'user';
            $parts   = [['text' => $msg['content']]];

            // Attach files to the last user message
            $isLastUserMessage = ($role === 'user') && $this->isLastUserMessage($messages, $index);
            if ($isLastUserMessage && !empty($files)) {
                foreach ($files as $file) {
                    if (!empty($file['base64Data']) && !empty($file['mimeType'])) {
                        $parts[] = [
                            'inlineData' => [
                                'mimeType' => $file['mimeType'],
                                'data'     => $file['base64Data']
                            ]
                        ];
                    }
                }
            }

            $contents[] = [
                'role'  => $role,
                'parts' => $parts
            ];
        }

        return $contents;
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
     * Extracts the text content from Gemini's response format.
     * 
     * Response structure: { candidates: [{ content: { parts: [{ text: "..." }] } }] }
     */
    private function extractTextFromResponse($responseBody) {
        if (isset($responseBody['candidates'][0]['content']['parts'])) {
            $textParts = [];
            foreach ($responseBody['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }
            if (!empty($textParts)) {
                return implode('', $textParts);
            }
        }

        throw new \Exception(
            __('No response content received from Gemini API', 'woo-pdf-invoice-builder')
        );
    }

    /**
     * Gets the Gemini API model ID from the internal model name.
     */
    private function getApiModelId() {
        if (isset(self::$MODEL_MAP[$this->model])) {
            return self::$MODEL_MAP[$this->model];
        }

        // Fallback: use the model name as-is (allows custom model IDs)
        return $this->model;
    }

    /**
     * @inheritdoc
     */
    protected function extractErrorMessage($responseBody, $responseCode) {
        // Gemini may return errors in a different nested format
        if (isset($responseBody['error']['message'])) {
            return 'Gemini API: ' . $responseBody['error']['message'];
        }
        
        return parent::extractErrorMessage($responseBody, $responseCode);
    }
}
