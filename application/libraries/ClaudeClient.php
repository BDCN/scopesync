<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ClaudeClient {

    protected $CI;
    protected $_apiKey;

    const API_URL      = 'https://api.anthropic.com/v1/messages';
    const API_VERSION  = '2023-06-01';
    const TIMEOUT      = 300;
    const MAX_RETRIES  = 2;
    const RETRY_CODES  = [429, 503];

    public function __construct()
    {
        $this->CI     =& get_instance();
        $this->_apiKey = config_item('anthropic_api_key');
        $this->CI->load->library('PromptLoader');
    }

    /**
     * Extract structured data from a PDF document using a named prompt.
     *
     * @param  string $promptName   e.g. 'spec_section_extraction'
     * @param  string $documentPath Absolute filesystem path to the PDF
     * @param  array  $options      Template vars ({{key}} replacements) + optional 'model', 'max_tokens'
     * @return array  ['status', 'model', 'prompt_version', 'input_tokens', 'output_tokens',
     *                 'raw_response', 'structured_data', 'confidence', 'error']
     */
    public function extract(string $promptName, string $documentPath, array $options = []): array
    {
        if ( ! file_exists($documentPath)) {
            return $this->_errorResult("Document file not found: {$documentPath}");
        }

        $prompt = $this->CI->promptloader->load($promptName);

        // Substitute {{template_vars}} in user prompt
        $userText = $prompt['user'];
        foreach ($options as $key => $val) {
            $userText = str_replace('{{' . $key . '}}', (string) $val, $userText);
        }
        $userText = preg_replace('/\{\{[^}]+\}\}/', 'N/A', $userText);

        $pdfData   = base64_encode(file_get_contents($documentPath));
        $model     = $options['model']      ?? 'claude-sonnet-4-6';
        $maxTokens = $options['max_tokens'] ?? 16384;

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $prompt['system'],
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    [
                        'type'   => 'document',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => 'application/pdf',
                            'data'       => $pdfData,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $userText,
                    ],
                ],
            ]],
        ];

        $result                   = $this->_callWithRetry($payload);
        $result['model']          = $model;
        $result['prompt_version'] = $prompt['version'];
        return $result;
    }

    // -------------------------------------------------------------------------

    protected function _callWithRetry(array $payload): array
    {
        $attempt   = 0;
        $lastError = 'Unknown error';

        while ($attempt <= self::MAX_RETRIES) {
            if ($attempt > 0) {
                sleep((int) pow(2, $attempt)); // 2s, 4s
            }

            $resp = $this->_curlPost($payload);

            if ($resp['http_code'] === 200) {
                return $this->_parseSuccess($resp['body']);
            }

            if (in_array($resp['http_code'], self::RETRY_CODES, TRUE)) {
                $lastError = "HTTP {$resp['http_code']} from API (attempt " . ($attempt + 1) . ')';
                $attempt++;
                continue;
            }

            // Non-retryable
            return $this->_errorResult("HTTP {$resp['http_code']}: " . substr($resp['body'], 0, 500));
        }

        return $this->_errorResult("Max retries exceeded. Last: {$lastError}");
    }

    protected function _curlPost(array $payload): array
    {
        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POST           => TRUE,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '                   . $this->_apiKey,
                'anthropic-version: '           . self::API_VERSION,
                'anthropic-beta: pdfs-2024-09-25',
                'content-type: application/json',
                'Expect:',
            ],
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        $ch       = null; // PHP 8.0+: CurlHandle freed on null assignment; curl_close() deprecated

        if ($body === FALSE) {
            return ['http_code' => 0, 'body' => "cURL error: {$curlErr}"];
        }

        return ['http_code' => $httpCode, 'body' => $body];
    }

    protected function _parseSuccess(string $body): array
    {
        $data = json_decode($body, TRUE);
        if ( ! $data) {
            return $this->_errorResult('Invalid JSON in API response');
        }

        $rawText = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $rawText .= $block['text'];
            }
        }

        $structuredData = NULL;
        $confidence     = NULL;

        $decoded = json_decode(trim($rawText), TRUE);
        if (is_array($decoded)) {
            $structuredData = trim($rawText); // store as JSON string for DB
            $confidence     = $decoded['meta']['extraction_confidence'] ?? NULL;
            // Normalise confidence to allowed enum values
            if ( ! in_array($confidence, ['high', 'medium', 'low'], TRUE)) {
                $confidence = NULL;
            }
        }

        return [
            'status'          => 'completed',
            'input_tokens'    => (int) ($data['usage']['input_tokens']  ?? 0),
            'output_tokens'   => (int) ($data['usage']['output_tokens'] ?? 0),
            'raw_response'    => $body,
            'structured_data' => $structuredData,
            'confidence'      => $confidence,
            'error'           => NULL,
        ];
    }

    protected function _errorResult(string $message): array
    {
        return [
            'status'          => 'failed',
            'input_tokens'    => 0,
            'output_tokens'   => 0,
            'raw_response'    => NULL,
            'structured_data' => NULL,
            'confidence'      => NULL,
            'error'           => $message,
        ];
    }
}
