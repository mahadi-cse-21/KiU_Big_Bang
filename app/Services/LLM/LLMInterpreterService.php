<?php

namespace App\Services\LLM;

use App\Services\Guardrails\DirectiveGuardrailService;
use App\Services\LLM\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Log;

class LLMInterpreterService
{
    protected DirectiveGuardrailService $guardrailService;

    public function __construct(DirectiveGuardrailService $guardrailService)
    {
        $this->guardrailService = $guardrailService;
    }

    /**
     * Interpret operator notes using the configured LLM with fallback and guardrail validation.
     *
     * @param array<int, string> $operatorNotes
     * @param array $batteryContext
     * @return array
     */
    public function interpret(array $operatorNotes, array $batteryContext): array
    {
        $providerName = config('gridwise.provider', 'gemini');
        $rawInterpretations = null;

        if ($providerName !== 'fallback') {
            $provider = $this->resolveProvider($providerName);
            if ($provider !== null) {
                try {
                    $rawInterpretations = $provider->interpret($operatorNotes, $batteryContext);
                } catch (\Throwable $e) {
                    Log::warning("LLM provider {$providerName} threw an exception: " . $e->getMessage());
                    $rawInterpretations = null;
                }
            }
        }

        // If external LLM did not return a valid array of interpretations, use intelligent fallback
        if (!is_array($rawInterpretations) || count($rawInterpretations) === 0) {
            $rawInterpretations = FallbackSemanticParser::parse($operatorNotes, $batteryContext);
        }

        // Deterministically enforce all Section 08 guardrails
        return $this->guardrailService->validateAndNormalize($rawInterpretations, count($operatorNotes), $batteryContext);
    }

    protected function resolveProvider(string $name): ?LLMProviderInterface
    {
        return match ($name) {
            'gemini' => app(GeminiProvider::class),
            'openai', 'custom' => app(OpenAIProvider::class),
            default => null,
        };
    }
}
