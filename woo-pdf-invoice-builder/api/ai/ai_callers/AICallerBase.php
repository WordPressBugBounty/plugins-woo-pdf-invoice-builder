<?php
/**
 * AICallerBase - Abstract base class for AI API providers.
 * 
 * Handles shared logic (HTTP requests, error handling, factory method).
 * Concrete subclasses implement the specific API format for each provider.
 */

namespace rnwcinv\api;

abstract class AICallerBase {

    /** @var string The API key for authentication */
    protected $apiKey;

    /** @var string The internal model name (e.g., 'gemini25pro', 'deepseek') */
    protected $model;

    /**
     * @param string $apiKey The API key
     * @param string $model  The internal model name
     */
    public function __construct($apiKey, $model) {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    /**
     * Send a request to the AI API and return the text response.
     * 
     * @param string $systemPrompt The system instruction prompt
     * @param array  $messages     Conversation history [{role, content}, ...]
     * @param array  $files        Attached files [{name, mimeType, base64Data}, ...]
     * @return string The AI's text response
     * @throws \Exception On API error
     */
    abstract public function callAI($systemPrompt, $messages, $files = []);

    /**
     * Factory method - creates the appropriate caller based on model name.
     * 
     * @param string $modelName The internal model name
     * @param string $apiKey    The API key
     * @return AICallerBase
     * @throws \Exception If model is not supported
     */
    public static function create($modelName, $apiKey) {
        // Gemini models all use the same caller
        if (strpos($modelName, 'gemini') === 0) {
            require_once __DIR__ . '/GeminiCaller.php';
            return new GeminiCaller($apiKey, $modelName);
        }

        if ($modelName === 'deepseek') {
            require_once __DIR__ . '/DeepSeekCaller.php';
            return new DeepSeekCaller($apiKey, $modelName);
        }

        throw new \Exception(
            sprintf(__('Unsupported AI model: %s', 'woo-pdf-invoice-builder'), $modelName)
        );
    }

    /**
     * Shared HTTP POST helper using WordPress wp_remote_post.
     * 
     * @param string $url     The API endpoint URL
     * @param array  $headers HTTP headers
     * @param array  $body    Request body (will be JSON-encoded)
     * @param int    $timeout Request timeout in seconds
     * @return array Decoded JSON response body
     * @throws \Exception On HTTP or API error
     */
    protected function makeRequest($url, $headers, $body, $timeout = 120) {
        $response = wp_remote_post($url, [
            'headers'     => $headers,
            'body'        => json_encode($body),
            'timeout'     => $timeout,
            'data_format' => 'body'
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($responseCode !== 200) {
            $errorMessage = $this->extractErrorMessage($responseBody, $responseCode);
            throw new \Exception($errorMessage);
        }

        return $responseBody;
    }

    /**
     * Extract a human-readable error message from an API error response.
     * Subclasses can override for provider-specific error formats.
     * 
     * @param array|null $responseBody The decoded response body
     * @param int        $responseCode The HTTP status code
     * @return string
     */
    protected function extractErrorMessage($responseBody, $responseCode) {
        if (isset($responseBody['error']['message'])) {
            return $responseBody['error']['message'];
        }

        if (isset($responseBody['error']) && is_string($responseBody['error'])) {
            return $responseBody['error'];
        }

        return sprintf(
            __('API request failed with status code %d', 'woo-pdf-invoice-builder'),
            $responseCode
        );
    }
}
