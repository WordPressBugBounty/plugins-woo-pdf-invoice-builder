<?php
/**
 * AITemplateProcessor - Handles AI-based PDF template generation.
 * 
 * Orchestrates the flow:
 *   1. Load system prompt from template_generation_prompt.txt
 *   2. Call the appropriate AI provider via AICallerBase factory
 *   3. Return the AI-generated HTML to the frontend for preview
 * 
 * The frontend renders the HTML in a preview panel and (in a later step)
 * will use getBoundingClientRect() to convert elements into native
 * DocumentOptions blocks for the builder.
 */

namespace rnwcinv\api;

class AITemplateProcessor {

    /** @var string The API key */
    private $apiKey;

    /** @var string The model to use */
    private $model;

    /** @var array Files attached to the message */
    private $files;

    /** @var int Page width in pixels (default A4 = 794) */
    private $pageWidth;

    public function __construct() {
        $this->apiKey    = '';
        $this->model     = '';
        $this->files     = [];
        $this->pageWidth = 794;
    }

    /**
     * Process an AI message and generate a PDF template.
     * 
     * @param string $message  The user's message describing the desired template
     * @param array  $history  Previous conversation history for context
     * @param string $model    The AI model to use (e.g., 'gemini25pro', 'deepseek')
     * @param string $apiKey   The API key for the selected model
     * @param array  $files    Attached files [{name, mimeType, base64Data}, ...]
     * @return array Response with HTML, message, and updated history
     */
    public function process($message, $history = [], $model = '', $apiKey = '', $files = []) {
        $this->model   = $model;
        $this->apiKey  = $apiKey;
        $this->files   = $files;

        if (empty($this->apiKey)) {
            return [
                'error' => __('AI API key is not configured. Please set it in Settings > AI.', 'woo-pdf-invoice-builder')
            ];
        }

        // Load the system prompt from the text file
        $systemPrompt = $this->loadSystemPrompt();
        if ($systemPrompt === false) {
            return [
                'error' => __('Could not load AI prompt template file.', 'woo-pdf-invoice-builder')
            ];
        }

        // Build conversation messages
        $messages = [];
        if (!empty($history)) {
            foreach ($history as $historyItem) {
                // History items arrive as stdClass from json_decode — use object access
                $role = is_array($historyItem) ? $historyItem['role'] : $historyItem->role;
                $content = is_array($historyItem) ? $historyItem['content'] : $historyItem->content;
                $messages[] = [
                    'role'    => $role,
                    'content' => $content
                ];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // Call the AI via the appropriate provider
        try {
            require_once __DIR__ . '/ai_callers/AICallerBase.php';
            $caller = AICallerBase::create($this->model, $this->apiKey);
            $aiResponse = $caller->callAI($systemPrompt, $messages, $this->files);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        // Extract the HTML from the AI response
        $html = $this->extractHtmlFromResponse($aiResponse);

        // Log the AI HTML for debugging
        \rnwcinv\Managers\LogManager::LogDebug("=== AI TEMPLATE HTML START ===\r\n" . $html . "\r\n=== AI TEMPLATE HTML END ===");

        // Update conversation history
        $updatedHistory   = $history;
        $updatedHistory[] = ['role' => 'user', 'content' => $message];
        $updatedHistory[] = ['role' => 'assistant', 'content' => $aiResponse];

        return [
            'html'    => $html,
            'history' => $updatedHistory
        ];
    }

    /**
     * Public accessor for the compiled system prompt.
     * Used by the external chat feature to give users the prompt they need.
     *
     * @return string|false The prompt text, or false on failure
     */
    public function getSystemPrompt() {
        return $this->loadSystemPrompt();
    }

    /**
     * Public accessor for HTML extraction from AI response text.
     * Used by the external chat feature to process manually-pasted AI responses.
     *
     * @param string $response The raw AI response text
     * @return string The extracted HTML
     */
    public function extractHtml($response) {
        return $this->extractHtmlFromResponse($response);
    }

    /**
     * Loads the system prompt from the template file and replaces placeholders.
     * 
     * @return string|false The prompt text, or false on failure
     */
    private function loadSystemPrompt() {
        $promptFile = __DIR__ . '/template_generation_prompt.txt';
        
        if (!file_exists($promptFile)) {
            return false;
        }

        $prompt = file_get_contents($promptFile);
        if ($prompt === false) {
            return false;
        }

        // Replace dynamic placeholders
        $prompt = str_replace('{PAGE_WIDTH}', strval($this->pageWidth), $prompt);

        return $prompt;
    }

    /**
     * Extracts the HTML content from the AI response.
     * 
     * The AI should return raw HTML, but sometimes it wraps it in
     * ```html code blocks or adds commentary. This method handles
     * all cases and extracts the clean HTML.
     * 
     * @param string $response The raw AI response text
     * @return string The extracted HTML
     */
    private function extractHtmlFromResponse($response) {
        $response = trim($response);

        // Case 1: AI wrapped the HTML in ```html ... ``` code blocks
        if (preg_match('/```(?:html)?\s*\n([\s\S]*?)\n```/', $response, $matches)) {
            return trim($matches[1]);
        }

        // Case 2: Response starts with a tag — it's already raw HTML
        if (preg_match('/^\s*</', $response)) {
            return $response;
        }

        // Case 3: There's text before the HTML (AI added commentary despite instructions)
        // Find the first HTML tag and take everything from there
        $firstTagPos = strpos($response, '<');
        if ($firstTagPos !== false) {
            return trim(substr($response, $firstTagPos));
        }

        // Fallback: return as-is
        return $response;
    }

    /**
     * Generate a real PDF preview from DocumentOptions.
     * 
     * Uses the existing RednaoPDFGenerator with test data to produce
     * a PDF that matches what the final template will look like.
     * 
     * @param object $pageOptions The DocumentOptions (containerOptions, pages, etc.)
     * @return array ['pdf' => base64 string] or ['error' => message]
     */
    public function generatePreview($pageOptions) {
        try {
            require_once \RednaoWooCommercePDFInvoice::$DIR . 'PDFGenerator.php';

            // Ensure required fields exist with defaults
            if (!isset($pageOptions->extensions)) {
                $pageOptions->extensions = [];
            }
            if (!isset($pageOptions->conditions)) {
                $pageOptions->conditions = '';
            }
            if (!isset($pageOptions->invoiceTemplateId)) {
                $pageOptions->invoiceTemplateId = 0;
            }
            if (!isset($pageOptions->containerOptions->InvoiceNumberFormat)) {
                $pageOptions->containerOptions->InvoiceNumberFormat = (object)[
                    'prefix' => 'INV-',
                    'digits' => 5,
                    'sufix'  => ''
                ];
            }
            if (!isset($pageOptions->containerOptions->orientation)) {
                $pageOptions->containerOptions->orientation = 'portrait';
            }

            // Generate PDF with test data (no real order needed)
            $generator = new \RednaoPDFGenerator($pageOptions, true, null);
            $generator->Generate(false);
            $pdfBytes = $generator->GetOutput();

            return [
                'pdf' => base64_encode($pdfBytes)
            ];
        } catch (\Exception $e) {
            return [
                'error' => __('Preview generation failed: ', 'woo-pdf-invoice-builder') . $e->getMessage()
            ];
        }
    }
}
