<?php

namespace App\Services\LLM\Contracts;

interface LLMProviderInterface
{
    /**
     * Interpret operator notes into machine-checkable directives.
     *
     * @param array<int, string> $operatorNotes
     * @param array $batteryContext
     * @return array|null Returns array of directive interpretations or null on provider failure
     */
    public function interpret(array $operatorNotes, array $batteryContext): ?array;
}
