<?php

namespace App\Services\Verification;

class ScheduleReplayValidator
{
    protected float $tolerance = 0.01;

    public function __construct()
    {
        $this->tolerance = (float)config('gridwise.tolerance', 0.01);
    }

    /**
     * Independently replay and validate the generated hourly plan against all physical and directive rules.
     *
     * @param array $hourlyPlan
     * @param array $hours
     * @param array $battery
     * @param array $directives
     * @return array Array of violation strings (empty if valid)
     */
    public function validate(array $hourlyPlan, array $hours, array $battery, array $directives): array
    {
        $violations = [];

        if (count($hourlyPlan) !== 24) {
            $violations[] = 'Hourly plan must contain exactly 24 entries.';
            return $violations;
        }

        // Index input hours
        $hoursByHour = [];
        foreach ($hours as $h) {
            $hoursByHour[$h['hour']] = $h;
        }

        $effectiveSolar = [];
        $activeReserve = array_fill(0, 24, (float)$battery['minimum_energy_kwh']);
        $noCharge = array_fill(0, 24, false);
        $noDischarge = array_fill(0, 24, false);
        $maxGridCap = array_fill(0, 24, INF);

        for ($h = 0; $h < 24; $h++) {
            $effectiveSolar[$h] = (float)($hoursByHour[$h]['solar_kwh'] ?? 0.0);
        }

        // Apply ground-truth directives
        foreach ($directives as $dir) {
            if (empty($dir['applies']) || empty($dir['structured_adjustment'])) {
                continue;
            }
            $type = $dir['directive_type'];
            $adj = $dir['structured_adjustment'];
            $affectedHours = $adj['hours'] ?? [];

            switch ($type) {
                case 'solar_reduction':
                    $f = (float)($adj['factor'] ?? 1.0);
                    foreach ($affectedHours as $hr) {
                        if (isset($effectiveSolar[$hr])) {
                            $effectiveSolar[$hr] *= $f;
                        }
                    }
                    break;
                case 'minimum_battery_reserve':
                    $res = (float)($adj['minimum_energy_kwh'] ?? $battery['minimum_energy_kwh']);
                    foreach ($affectedHours as $hr) {
                        if ($hr >= 0 && $hr < 24) {
                            $activeReserve[$hr] = max($activeReserve[$hr], $res);
                        }
                    }
                    break;
                case 'no_charge_window':
                    foreach ($affectedHours as $hr) {
                        if ($hr >= 0 && $hr < 24) {
                            $noCharge[$hr] = true;
                        }
                    }
                    break;
                case 'no_discharge_window':
                    foreach ($affectedHours as $hr) {
                        if ($hr >= 0 && $hr < 24) {
                            $noDischarge[$hr] = true;
                        }
                    }
                    break;
                case 'max_grid_window':
                    $mg = (float)($adj['max_grid_kwh'] ?? INF);
                    foreach ($affectedHours as $hr) {
                        if ($hr >= 0 && $hr < 24) {
                            $maxGridCap[$hr] = min($maxGridCap[$hr], $mg);
                        }
                    }
                    break;
            }
        }

        $currentEnergy = (float)$battery['initial_energy_kwh'];
        $capacity = (float)$battery['capacity_kwh'];
        $maxChargeRate = (float)$battery['max_charge_kwh_per_hour'];
        $maxDischargeRate = (float)$battery['max_discharge_kwh_per_hour'];

        for ($h = 0; $h < 24; $h++) {
            $step = $hourlyPlan[$h];
            if ($step['hour'] !== $h) {
                $violations[] = "Hour mismatch at index {$h}: expected {$h}, got {$step['hour']}.";
            }

            $grid = (float)$step['grid_kwh'];
            $solarUsed = (float)$step['solar_used_kwh'];
            $action = $step['battery_action'];
            $batKwh = (float)$step['battery_kwh'];
            $batAfter = (float)$step['battery_energy_after_kwh'];

            $demand = (float)($hoursByHour[$h]['demand_kwh'] ?? 0.0);

            // 1. Non-negative checks
            if ($grid < -$this->tolerance || $solarUsed < -$this->tolerance || $batKwh < -$this->tolerance || $batAfter < -$this->tolerance) {
                $violations[] = "Hour {$h}: Negative energy values detected.";
            }

            // 2. Solar usage <= effective solar
            if ($solarUsed > $effectiveSolar[$h] + $this->tolerance) {
                $violations[] = "Hour {$h}: Solar used ({$solarUsed}) exceeds effective solar ({$effectiveSolar[$h]}).";
            }

            // 3. Battery action rate limit
            if ($action === 'charge') {
                if ($batKwh > $maxChargeRate + $this->tolerance) {
                    $violations[] = "Hour {$h}: Charge amount ({$batKwh}) exceeds max charge rate ({$maxChargeRate}).";
                }
                if ($noCharge[$h] && $batKwh > $this->tolerance) {
                    $violations[] = "Hour {$h}: Battery charged during no_charge_window.";
                }
                $expectedEnergyAfter = $currentEnergy + $batKwh;
            } elseif ($action === 'discharge') {
                if ($batKwh > $maxDischargeRate + $this->tolerance) {
                    $violations[] = "Hour {$h}: Discharge amount ({$batKwh}) exceeds max discharge rate ({$maxDischargeRate}).";
                }
                if ($noDischarge[$h] && $batKwh > $this->tolerance) {
                    $violations[] = "Hour {$h}: Battery discharged during no_discharge_window.";
                }
                $expectedEnergyAfter = $currentEnergy - $batKwh;
            } elseif ($action === 'idle') {
                if ($batKwh > $this->tolerance) {
                    $violations[] = "Hour {$h}: Idle action has non-zero battery_kwh ({$batKwh}).";
                }
                $expectedEnergyAfter = $currentEnergy;
            } else {
                $violations[] = "Hour {$h}: Invalid battery action '{$action}'.";
                $expectedEnergyAfter = $currentEnergy;
            }

            // 4. Battery energy transition
            if (abs($batAfter - $expectedEnergyAfter) > $this->tolerance) {
                $violations[] = "Hour {$h}: Battery transition mismatch. Reported: {$batAfter}, Expected: {$expectedEnergyAfter}.";
            }

            // 5. Battery bounds & minimum reserve
            if ($batAfter > $capacity + $this->tolerance) {
                $violations[] = "Hour {$h}: Battery energy ({$batAfter}) exceeds capacity ({$capacity}).";
            }
            if ($batAfter < $activeReserve[$h] - $this->tolerance) {
                $violations[] = "Hour {$h}: Battery energy ({$batAfter}) falls below active reserve ({$activeReserve[$h]}).";
            }

            // 6. Max grid window
            if (!is_infinite($maxGridCap[$h]) && $grid > $maxGridCap[$h] + $this->tolerance) {
                $violations[] = "Hour {$h}: Grid import ({$grid}) exceeds grid cap ({$maxGridCap[$h]}).";
            }

            // 7. Energy balance
            $discharge = ($action === 'discharge') ? $batKwh : 0.0;
            $charge = ($action === 'charge') ? $batKwh : 0.0;
            $supply = $grid + $solarUsed + $discharge;
            $required = $demand + $charge;
            if (abs($supply - $required) > $this->tolerance) {
                $violations[] = "Hour {$h}: Energy balance violation. Supply: {$supply}, Demand: {$required}.";
            }

            $currentEnergy = $batAfter;
        }

        // 8. End-of-day battery neutrality
        $initialEnergy = (float)$battery['initial_energy_kwh'];
        if (abs($currentEnergy - $initialEnergy) > $this->tolerance) {
            $violations[] = "End-of-day battery neutrality violated: final energy ({$currentEnergy}) does not equal initial ({$initialEnergy}).";
        }

        return $violations;
    }
}
