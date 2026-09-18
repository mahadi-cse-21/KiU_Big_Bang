<?php

namespace App\Services\LLM;

class FallbackSemanticParser
{
    /**
     * Parse operator notes into structured directives deterministically.
     */
    public static function parse(array $operatorNotes, array $batteryContext): array
    {
        $interpretations = [];
        $capacity = (float)($batteryContext['capacity_kwh'] ?? 200.0);

        foreach ($operatorNotes as $index => $note) {
            $interpretations[] = self::parseSingleNote($index, trim($note), $capacity);
        }

        return $interpretations;
    }

    protected static function parseSingleNote(int $index, string $note, float $batteryCapacity): array
    {
        $lower = strtolower($note);

        // 1. Distractor checks (quick bail-out for known non-energy notes)
        if (self::isDistractor($lower)) {
            return [
                'note_index' => $index,
                'applies' => false,
                'directive_type' => 'no_op',
                'structured_adjustment' => null,
                'explanation' => 'This note does not affect today\'s 24-hour energy schedule.'
            ];
        }

        $hours = self::extractHours($lower);

        // 2. Solar Reduction
        if (self::isSolarReduction($lower)) {
            $factor = self::extractSolarFactor($lower);
            if (!empty($hours) && $factor !== null) {
                return [
                    'note_index' => $index,
                    'applies' => true,
                    'directive_type' => 'solar_reduction',
                    'structured_adjustment' => [
                        'hours' => $hours,
                        'factor' => round($factor, 4)
                    ],
                    'explanation' => 'Solar availability is reduced during the specified window.'
                ];
            }
        }

        // 3. No Charge Window
        if (self::isNoCharge($lower)) {
            if (!empty($hours)) {
                return [
                    'note_index' => $index,
                    'applies' => true,
                    'directive_type' => 'no_charge_window',
                    'structured_adjustment' => [
                        'hours' => $hours
                    ],
                    'explanation' => 'Battery charging is unavailable during the specified maintenance window.'
                ];
            }
        }

        // 4. No Discharge Window
        if (self::isNoDischarge($lower)) {
            if (!empty($hours)) {
                return [
                    'note_index' => $index,
                    'applies' => true,
                    'directive_type' => 'no_discharge_window',
                    'structured_adjustment' => [
                        'hours' => $hours
                    ],
                    'explanation' => 'Battery discharging is disabled during the specified test window.'
                ];
            }
        }

        // 5. Minimum Battery Reserve
        if (self::isMinimumBatteryReserve($lower)) {
            $minReserve = self::extractBatteryReserve($lower, $batteryCapacity);
            if (!empty($hours) && $minReserve !== null) {
                return [
                    'note_index' => $index,
                    'applies' => true,
                    'directive_type' => 'minimum_battery_reserve',
                    'structured_adjustment' => [
                        'hours' => $hours,
                        'minimum_energy_kwh' => round($minReserve, 2)
                    ],
                    'explanation' => "A {$minReserve} kWh battery reserve is required during the stated window."
                ];
            }
        }

        // 6. Max Grid Window
        if (self::isMaxGridWindow($lower)) {
            $maxGrid = self::extractMaxGridKwh($lower);
            if (!empty($hours) && $maxGrid !== null) {
                return [
                    'note_index' => $index,
                    'applies' => true,
                    'directive_type' => 'max_grid_window',
                    'structured_adjustment' => [
                        'hours' => $hours,
                        'max_grid_kwh' => round($maxGrid, 2)
                    ],
                    'explanation' => "Grid import is capped at {$maxGrid} kWh during the stated window."
                ];
            }
        }

        // Default to no_op
        return [
            'note_index' => $index,
            'applies' => false,
            'directive_type' => 'no_op',
            'structured_adjustment' => null,
            'explanation' => 'This note does not affect today\'s 24-hour energy schedule.'
        ];
    }

    protected static function isDistractor(string $text): bool
    {
        $distractorKeywords = [
            'sports office', 'registration deadline', 'cafeteria menu',
            'library', 'book-return', 'student affairs', 'club notices',
            'seminar room', 'booking was moved', 'next week', 'next month',
            'cafeteria', 'canteen', 'sports day'
        ];

        foreach ($distractorKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    protected static function isSolarReduction(string $text): bool
    {
        return (str_contains($text, 'solar') || str_contains($text, 'rooftop') || str_contains($text, 'pv') || str_contains($text, 'panel')) &&
            (str_contains($text, 'reduc') || str_contains($text, 'drop') || str_contains($text, 'clean') || str_contains($text, 'wash') || str_contains($text, 'cloud') || str_contains($text, 'leave') || str_contains($text, 'inverter'));
    }

    protected static function isNoCharge(string $text): bool
    {
        $isCharge = str_contains($text, 'charge') || str_contains($text, 'charger') || str_contains($text, 'charging');
        $isNo = str_contains($text, 'not charge') || str_contains($text, 'do not charge') || str_contains($text, 'isolated') || str_contains($text, 'unavailable') || str_contains($text, 'disabled');
        return $isCharge && $isNo && !str_contains($text, 'discharge');
    }

    protected static function isNoDischarge(string $text): bool
    {
        $isDischarge = str_contains($text, 'discharge') || str_contains($text, 'discharging');
        $isNo = str_contains($text, 'not discharge') || str_contains($text, 'do not discharge') || str_contains($text, 'disabled') || str_contains($text, 'unavailable') || str_contains($text, 'prohibited');
        return $isDischarge && $isNo;
    }

    protected static function isMinimumBatteryReserve(string $text): bool
    {
        return (str_contains($text, 'battery') || str_contains($text, 'stored') || str_contains($text, 'reserve')) &&
            (str_contains($text, 'reserve') || str_contains($text, 'at least') || str_contains($text, 'stored in the battery') || str_contains($text, 'remain in the battery'));
    }

    protected static function isMaxGridWindow(string $text): bool
    {
        return (str_contains($text, 'grid') || str_contains($text, 'feeder') || str_contains($text, 'transformer') || str_contains($text, 'substation') || str_contains($text, 'intake')) &&
            (str_contains($text, 'not exceed') || str_contains($text, 'stay at or below') || str_contains($text, 'limit') || str_contains($text, 'capped') || str_contains($text, 'max'));
    }

    public static function extractHours(string $text): array
    {
        $start = null;
        $end = null;

        // Pattern: 13:00 (and|to|until) 15:00
        if (preg_match('/(?:between|from)?\s*(\d{1,2}):00\s*(?:and|to|until|-)\s*(\d{1,2}):00/i', $text, $m)) {
            $start = (int)$m[1];
            $end = (int)$m[2];
        }
        // Pattern: noon until 2 PM / noon to 2 PM
        elseif (preg_match('/noon\s*(?:until|to|-)\s*(\d{1,2})\s*(am|pm)/i', $text, $m)) {
            $start = 12;
            $end = self::to24Hour((int)$m[1], $m[2]);
        }
        // Pattern: 10 AM until noon / 10 AM to noon
        elseif (preg_match('/(\d{1,2})\s*(am|pm)\s*(?:until|to|-)\s*noon/i', $text, $m)) {
            $start = self::to24Hour((int)$m[1], $m[2]);
            $end = 12;
        }
        // Pattern: 1-3 PM or 1 to 3 PM
        elseif (preg_match('/(\d{1,2})\s*(?:-|to|until)\s*(\d{1,2})\s*(am|pm)/i', $text, $m)) {
            $ampm = strtolower($m[3]);
            $h1 = (int)$m[1];
            $h2 = (int)$m[2];
            // If h1 <= 12 and h2 <= 12 and PM:
            if ($ampm === 'pm') {
                $start = ($h1 === 12) ? 12 : ($h1 + 12);
                $end = ($h2 === 12) ? 12 : ($h2 + 12);
            } else {
                $start = ($h1 === 12) ? 0 : $h1;
                $end = ($h2 === 12) ? 0 : $h2;
            }
        }
        // Pattern: from 1 PM until/to 3 PM / between 1 PM and 3 PM
        elseif (preg_match('/(\d{1,2})\s*(am|pm)\s*(?:until|to|and|-)\s*(\d{1,2})\s*(am|pm)/i', $text, $m)) {
            $start = self::to24Hour((int)$m[1], $m[2]);
            $end = self::to24Hour((int)$m[3], $m[4]);
        }
        // Pattern: words "one until three"
        elseif (preg_match('/(?:from\s+)?(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s*(?:until|to|-)\s*(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)/i', $text, $m)) {
            $wordMap = ['one'=>1, 'two'=>2, 'three'=>3, 'four'=>4, 'five'=>5, 'six'=>6, 'seven'=>7, 'eight'=>8, 'nine'=>9, 'ten'=>10, 'eleven'=>11, 'twelve'=>12];
            $h1 = $wordMap[strtolower($m[1])];
            $h2 = $wordMap[strtolower($m[2])];
            // Contextually afternoon if 1 to 3
            if ($h1 < 7) { $h1 += 12; }
            if ($h2 < 7) { $h2 += 12; }
            $start = $h1;
            $end = $h2;
        }

        if ($start !== null && $end !== null && $end > $start) {
            $hours = [];
            for ($h = $start; $h < $end; $h++) {
                if ($h >= 0 && $h <= 23) {
                    $hours[] = $h;
                }
            }
            sort($hours);
            return array_values(array_unique($hours));
        }

        return [];
    }

    protected static function to24Hour(int $hour, string $ampm): int
    {
        $ampm = strtolower($ampm);
        if ($ampm === 'am') {
            return ($hour === 12) ? 0 : $hour;
        }
        if ($ampm === 'pm') {
            return ($hour === 12) ? 12 : ($hour + 12);
        }
        return $hour;
    }

    protected static function extractSolarFactor(string $text): ?float
    {
        // 80% reduction => factor = 1 - 0.8 = 0.2
        if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*reduction/i', $text, $m)) {
            $pct = (float)$m[1];
            return max(0.0, min(1.0, 1.0 - ($pct / 100.0)));
        }

        // drop to about 20% or roughly 25% or to 25%
        if (preg_match('/(?:drop to(?: about)?|roughly|treated as(?: roughly)?|about|to)\s*(\d+(?:\.\d+)?)\s*%/i', $text, $m)) {
            $pct = (float)$m[1];
            return max(0.0, min(1.0, $pct / 100.0));
        }

        // half
        if (str_contains($text, 'half')) {
            return 0.5;
        }

        // one-fifth
        if (str_contains($text, 'one-fifth') || str_contains($text, '1/5')) {
            return 0.2;
        }

        // one-fourth or quarter
        if (str_contains($text, 'one-fourth') || str_contains($text, 'quarter') || str_contains($text, '1/4')) {
            return 0.25;
        }

        // one-third
        if (str_contains($text, 'one-third') || str_contains($text, '1/3')) {
            return 0.3333;
        }

        // Default reduction percentage if "reduction" is mentioned without specific %
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $text, $m)) {
            $pct = (float)$m[1];
            return max(0.0, min(1.0, $pct / 100.0));
        }

        return null;
    }

    protected static function extractBatteryReserve(string $text, float $batteryCapacity): ?float
    {
        // Percentage: e.g. "50% of the battery capacity" or "50%"
        if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*(?:of(?: the)? battery(?: capacity)?)?/i', $text, $m)) {
            $pct = (float)$m[1];
            return ($batteryCapacity * $pct) / 100.0;
        }

        // Explicit kWh: e.g. "at least 120 kWh" or "90 kWh"
        if (preg_match('/(\d+(?:\.\d+)?)\s*kwh/i', $text, $m)) {
            return (float)$m[1];
        }

        return null;
    }

    protected static function extractMaxGridKwh(string $text): ?float
    {
        // e.g. "not exceed 155 kWh" or "limit is 180 kWh" or "stay at or below 190 kWh"
        if (preg_match('/(?:exceed|limit is|below|cap(?:ped)? at)\s*(\d+(?:\.\d+)?)\s*kwh/i', $text, $m)) {
            return (float)$m[1];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*kwh/i', $text, $m)) {
            return (float)$m[1];
        }

        return null;
    }
}
