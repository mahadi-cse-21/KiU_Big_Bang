<?php

namespace App\Services\LLM;

class PromptBuilder
{
    public static function buildSystemPrompt(array $batteryContext): string
    {
        $cap = $batteryContext['capacity_kwh'] ?? 200;
        $init = $batteryContext['initial_energy_kwh'] ?? 100;
        $min = $batteryContext['minimum_energy_kwh'] ?? 40;

        return <<<PROMPT
You are the operator-note interpreter for GridWise campus energy scheduling.
Your job is to read natural-language campus operator notes and translate each note into a machine-checkable directive.

Current Battery Context:
- Capacity: {$cap} kWh
- Initial Energy: {$init} kWh
- Base Minimum Energy: {$min} kWh

SUPPORTED DIRECTIVES:
1. "solar_reduction":
   Usable solar is reduced during specific hours.
   structured_adjustment: {"hours": [integer...], "factor": number}
   NOTE: "factor" is the USABLE fraction remaining (between 0.0 and 1.0).
   - "80% reduction" means factor = 0.2 (1.0 - 0.8)
   - "will drop to about 20%" means factor = 0.2
   - "usable solar treated as roughly 25%" means factor = 0.25
   - "leave about half" means factor = 0.5
   - "roughly one-fifth" means factor = 0.2

2. "minimum_battery_reserve":
   Keep battery energy at or above a required level during specific hours.
   structured_adjustment: {"hours": [integer...], "minimum_energy_kwh": number}
   NOTE: If expressed as a percentage of battery capacity (e.g. "at least 50% of the battery capacity"), compute the exact kWh value: 50% of {$cap} kWh = 100 kWh.

3. "no_charge_window":
   Battery charging is unavailable during specific hours (e.g. charger maintenance, circuit inspection).
   structured_adjustment: {"hours": [integer...]}

4. "no_discharge_window":
   Battery discharging is unavailable during specific hours (e.g. protection testing, relay testing).
   structured_adjustment: {"hours": [integer...]}

5. "max_grid_window":
   Grid import is capped at a stated amount during specific hours (e.g. feeder limit, transformer limit, substation constraint).
   structured_adjustment: {"hours": [integer...], "max_grid_kwh": number}

6. "no_op":
   The note does NOT affect the 24-hour campus energy schedule (e.g. sports office, cafeteria menu, library hours, seminar booking, club notices).
   structured_adjustment: null
   applies: false

TIME WINDOW RULES:
- Time intervals are whole-hour, start-inclusive and end-exclusive.
- "1 PM to 3 PM" or "13:00 to 15:00" or "1-3 PM" => hours [13, 14]
- "noon until 2 PM" => hours [12, 13]
- "2 AM until 5 AM" => hours [2, 3, 4]
- "6 PM until 9 PM" => hours [18, 19, 20]
- "6 PM until 10 PM" => hours [18, 19, 20, 21]
- "7 PM until 9 PM" => hours [19, 20]
- "7 PM until 10 PM" => hours [19, 20, 21]
- "11 AM until 1 PM" => hours [11, 12]
- "2 PM until 4 PM" => hours [14, 15]
- "5 PM until 7 PM" => hours [17, 18]
- "6 PM until 8 PM" => hours [18, 19]
- Every hours array MUST be unique integers in 0..23 in strictly ascending order.

OUTPUT RULES:
- Return valid JSON with a top-level key "directive_interpretation".
- Return EXACTLY one entry per operator note, in note_index order (0, 1, ...).
- For no_op: "applies" must be false, "structured_adjustment" must be null.
- For all other directives: "applies" must be true, "structured_adjustment" must match the required shape.
PROMPT;
    }

    public static function buildUserPrompt(array $operatorNotes): string
    {
        $notesJson = json_encode($operatorNotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return <<<PROMPT
Translate each of the following operator notes into its structured directive:

{$notesJson}

Respond ONLY with valid JSON in this exact shape:
{
  "directive_interpretation": [
    {
      "note_index": 0,
      "applies": true,
      "directive_type": "...",
      "structured_adjustment": { ... } or null,
      "explanation": "..."
    }
  ]
}
PROMPT;
    }
}
