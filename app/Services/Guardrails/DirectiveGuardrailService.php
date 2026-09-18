<?php

namespace App\Services\Guardrails;

class DirectiveGuardrailService
{
    public const ALLOWED_TYPES = [
        'solar_reduction',
        'minimum_battery_reserve',
        'no_charge_window',
        'no_discharge_window',
        'max_grid_window',
        'no_op'
    ];

    /**
     * Validate and normalize the directive interpretations.
     *
     * @param array $rawInterpretations
     * @param int $noteCount
     * @param array $batteryContext
     * @return array Normalized, guaranteed-valid directive_interpretation array
     */
    public function validateAndNormalize(array $rawInterpretations, int $noteCount, array $batteryContext): array
    {
        $normalized = [];
        $batteryCapacity = (float)($batteryContext['capacity_kwh'] ?? 200.0);

        // Map by note_index if available
        $byIndex = [];
        foreach ($rawInterpretations as $item) {
            if (is_array($item) && isset($item['note_index']) && is_numeric($item['note_index'])) {
                $idx = (int)$item['note_index'];
                if ($idx >= 0 && $idx < $noteCount && !isset($byIndex[$idx])) {
                    $byIndex[$idx] = $item;
                }
            }
        }

        for ($i = 0; $i < $noteCount; $i++) {
            $raw = $byIndex[$i] ?? ($rawInterpretations[$i] ?? null);
            $normalized[] = $this->sanitizeEntry($i, $raw, $batteryCapacity);
        }

        return $normalized;
    }

    protected function sanitizeEntry(int $noteIndex, mixed $entry, float $batteryCapacity): array
    {
        $defaultNoOp = [
            'note_index' => $noteIndex,
            'applies' => false,
            'directive_type' => 'no_op',
            'structured_adjustment' => null,
            'explanation' => 'This note does not affect today\'s 24-hour energy schedule.'
        ];

        if (!is_array($entry)) {
            return $defaultNoOp;
        }

        $type = $entry['directive_type'] ?? 'no_op';
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return $defaultNoOp;
        }

        $explanation = (string)($entry['explanation'] ?? 'Directive interpretation.');

        if ($type === 'no_op') {
            return [
                'note_index' => $noteIndex,
                'applies' => false,
                'directive_type' => 'no_op',
                'structured_adjustment' => null,
                'explanation' => $explanation
            ];
        }

        // For non-no_op: applies must be true
        $adjustment = $entry['structured_adjustment'] ?? null;
        if (!is_array($adjustment)) {
            return $defaultNoOp;
        }

        $hours = $this->sanitizeHours($adjustment['hours'] ?? []);
        if (empty($hours)) {
            return $defaultNoOp;
        }

        switch ($type) {
            case 'solar_reduction':
                if (!isset($adjustment['factor']) || !is_numeric($adjustment['factor'])) {
                    return $defaultNoOp;
                }
                $factor = (float)$adjustment['factor'];
                if ($factor < 0.0 || $factor > 1.0) {
                    $factor = max(0.0, min(1.0, $factor));
                }
                return [
                    'note_index' => $noteIndex,
                    'applies' => true,
                    'directive_type' => 'solar_reduction',
                    'structured_adjustment' => [
                        'hours' => $hours,
                        'factor' => round($factor, 4)
                    ],
                    'explanation' => $explanation
                ];

            case 'minimum_battery_reserve':
                if (!isset($adjustment['minimum_energy_kwh']) || !is_numeric($adjustment['minimum_energy_kwh'])) {
                    return $defaultNoOp;
                }
                $reserve = (float)$adjustment['minimum_energy_kwh'];
                if ($reserve < 0.0) {
                    $reserve = 0.0;
                }
                if ($reserve > $batteryCapacity) {
                    $reserve = $batteryCapacity;
                }
                return [
                    'note_index' => $noteIndex,
                    'applies' => true,
                    'directive_type' => 'minimum_battery_reserve',
                    'structured_adjustment' => [
                        'hours' => $hours,
                        'minimum_energy_kwh' => round($reserve, 2)
                    ],
                    'explanation' => $explanation
                ];

            case 'no_charge_window':
                return [
                    'note_index' => $noteIndex,
                    'applies' => true,
                    'directive_type' => 'no_charge_window',
                    'structured_adjustment' => [
                        'hours' => $hours
                    ],
                    'explanation' => $explanation
                ];

            case 'no_discharge_window':
                return [
                    'note_index' => $noteIndex,
                    'applies' => true,
                    'directive_type' => 'no_discharge_window',
                    'structured_adjustment' => [
                        'hours' => $hours
                    ],
                    'explanation' => $explanation
                ];

            case 'max_grid_window':
                if (!isset($adjustment['max_grid_kwh']) || !is_numeric($adjustment['max_grid_kwh'])) {
                    return $defaultNoOp;
                }
                $maxGrid = max(0.0, (float)$adjustment['max_grid_kwh']);
                return [
                    'note_index' => $noteIndex,
                    'applies' => true,
                    'directive_type' => 'max_grid_window',
                    'structured_adjustment' => [
                        'hours' => $hours,
                        'max_grid_kwh' => round($maxGrid, 2)
                    ],
                    'explanation' => $explanation
                ];

            default:
                return $defaultNoOp;
        }
    }

    protected function sanitizeHours(mixed $rawHours): array
    {
        if (!is_array($rawHours)) {
            return [];
        }

        $clean = [];
        foreach ($rawHours as $h) {
            if (is_numeric($h)) {
                $val = (int)$h;
                if ($val >= 0 && $val <= 23) {
                    $clean[] = $val;
                }
            }
        }

        $clean = array_values(array_unique($clean));
        sort($clean);
        return $clean;
    }
}
