<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements LLMProviderInterface
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('gridwise.gemini.api_key');
        $this->model = config('gridwise.gemini.model', 'gemini-2.0-flash');
        $this->baseUrl = config('gridwise.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->timeout = config('gridwise.timeout', 15);
    }

    public function interpret(array $operatorNotes, array $batteryContext): ?array
    {
        if (empty($this->apiKey)) {
            Log::info('GeminiProvider: GEMINI_API_KEY is not configured.');
            return null;
        }

        $systemPrompt = PromptBuilder::buildSystemPrompt($batteryContext);
        $userPrompt = PromptBuilder::buildUserPrompt($operatorNotes);

        $endpoint = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout($this->timeout)->post($endpoint, [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $userPrompt]]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.0,
                ]
            ]);

            if (!$response->successful()) {
                Log::warning('GeminiProvider request failed', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message') ?? 'Unknown error'
                ]);
                return null;
            }

            $rawText = $response->json('candidates.0.content.parts.0.text');
            if (empty($rawText)) {
                return null;
            }

            $decoded = json_decode($rawText, true);
            if (!is_array($decoded)) {
                return null;
            }

            return $decoded['directive_interpretation'] ?? $decoded;
        } catch (\Throwable $e) {
            Log::warning('GeminiProvider exception occurred: ' . $e->getMessage());
            return null;
        }
    }
}
