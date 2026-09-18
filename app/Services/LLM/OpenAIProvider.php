<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements LLMProviderInterface
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('gridwise.openai.api_key');
        $this->model = config('gridwise.openai.model', 'gpt-4o-mini');
        $this->baseUrl = rtrim(config('gridwise.openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->timeout = config('gridwise.timeout', 15);
    }

    public function interpret(array $operatorNotes, array $batteryContext): ?array
    {
        if (empty($this->apiKey)) {
            Log::info('OpenAIProvider: OPENAI_API_KEY is not configured.');
            return null;
        }

        $systemPrompt = PromptBuilder::buildSystemPrompt($batteryContext);
        $userPrompt = PromptBuilder::buildUserPrompt($operatorNotes);

        $endpoint = "{$this->baseUrl}/chat/completions";

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post($endpoint, [
                    'model' => $this->model,
                    'temperature' => 0.0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ]
                ]);

            if (!$response->successful()) {
                Log::warning('OpenAIProvider request failed', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message') ?? 'Unknown error'
                ]);
                return null;
            }

            $rawText = $response->json('choices.0.message.content');
            if (empty($rawText)) {
                return null;
            }

            $decoded = json_decode($rawText, true);
            if (!is_array($decoded)) {
                return null;
            }

            return $decoded['directive_interpretation'] ?? $decoded;
        } catch (\Throwable $e) {
            Log::warning('OpenAIProvider exception occurred: ' . $e->getMessage());
            return null;
        }
    }
}
