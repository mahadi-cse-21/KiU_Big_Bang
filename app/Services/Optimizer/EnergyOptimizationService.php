<?php

namespace App\Services\Optimizer;

class EnergyOptimizationService
{
    /**
     * Solve 24-hour energy scheduling given hours, battery parameters, and validated directives.
     *
     * @param array $hours 24 hourly entries
     * @param array $battery Battery specifications
     * @param array $directives Validated directive_interpretation entries
     * @return array Contains hourly_plan, total_grid_kwh, total_cost_bdt, peak_grid_kwh, plan_summary
     */
    public function optimize(array $hours, array $battery, array $directives): array
    {
        // Sort hours 0..23
        usort($hours, fn($a, $b) => $a['hour'] <=> $b['hour']);

        $cap = (float)$battery['capacity_kwh'];
        $initialEnergy = (float)$battery['initial_energy_kwh'];
        $baseMinEnergy = (float)$battery['minimum_energy_kwh'];
        $maxChargeRate = (float)$battery['max_charge_kwh_per_hour'];
        $maxDischargeRate = (float)$battery['max_discharge_kwh_per_hour'];

        // Initialize hourly constraint arrays
        $effectiveSolar = [];
        $activeReserve = array_fill(0, 24, $baseMinEnergy);
        $noCharge = array_fill(0, 24, false);
        $noDischarge = array_fill(0, 24, false);
        $maxGridCap = array_fill(0, 24, INF);

        foreach ($hours as $h) {
            $effectiveSolar[$h['hour']] = (float)$h['solar_kwh'];
        }

        // Apply directives
        foreach ($directives as $dir) {
            if (empty($dir['applies']) || empty($dir['structured_adjustment'])) {
                continue;
            }

            $type = $dir['directive_type'];
            $adj = $dir['structured_adjustment'];
            $affectedHours = $adj['hours'] ?? [];

            switch ($type) {
                case 'solar_reduction':
                    $factor = (float)($adj['factor'] ?? 1.0);
                    foreach ($affectedHours as $hr) {
                        if (isset($effectiveSolar[$hr])) {
                            $effectiveSolar[$hr] *= $factor;
                        }
                    }
                    break;

                case 'minimum_battery_reserve':
                    $reqReserve = (float)($adj['minimum_energy_kwh'] ?? $baseMinEnergy);
                    foreach ($affectedHours as $hr) {
                        if ($hr >= 0 && $hr < 24) {
                            $activeReserve[$hr] = max($activeReserve[$hr], $reqReserve);
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
                    $capVal = (float)($adj['max_grid_kwh'] ?? INF);
                    foreach ($affectedHours as $hr) {
                        if ($hr >= 0 && $hr < 24) {
                            $maxGridCap[$hr] = min($maxGridCap[$hr], $capVal);
                        }
                    }
                    break;
            }
        }

        // Variable layout for 24 hours:
        // g_h: 0 .. 23
        // s_h: 24 .. 47
        // c_h: 48 .. 71
        // d_h: 72 .. 95
        // E_{h+1}: 96 .. 119
        $numVars = 120;

        // Primary objective: minimize cost, with tiny secondary regularizers to prefer solar and lower grid
        $c = array_fill(0, $numVars, 0.0);
        for ($h = 0; $h < 24; $h++) {
            $tariff = (float)$hours[$h]['tariff_bdt_per_kwh'];
            $c[$h] = $tariff + 1e-6; // Minimize grid import slightly if tariffs tie
            $c[24 + $h] = -1e-7;     // Use free solar rather than curtailing
        }

        $bounds = [];
        for ($i = 0; $i < $numVars; $i++) {
            $bounds[$i] = [0.0, INF];
        }

        for ($h = 0; $h < 24; $h++) {
            // g_h
            $bounds[$h] = [0.0, is_infinite($maxGridCap[$h]) ? INF : $maxGridCap[$h]];

            // s_h
            $bounds[24 + $h] = [0.0, max(0.0, $effectiveSolar[$h])];

            // c_h
            $bounds[48 + $h] = [0.0, $noCharge[$h] ? 0.0 : $maxChargeRate];

            // d_h
            $bounds[72 + $h] = [0.0, $noDischarge[$h] ? 0.0 : $maxDischargeRate];

            // E_{h+1}
            $bounds[96 + $h] = [min($cap, $activeReserve[$h]), $cap];
        }

        $A_eq = [];
        $b_eq = [];
        $A_ub = [];
        $b_ub = [];

        // 1. Energy balance for each hour:
        // g_h + s_h + d_h - c_h = demand_kwh[h]
        for ($h = 0; $h < 24; $h++) {
            $row = array_fill(0, $numVars, 0.0);
            $row[$h] = 1.0;
            $row[24 + $h] = 1.0;
            $row[72 + $h] = 1.0;
            $row[48 + $h] = -1.0;
            $A_eq[] = $row;
            $b_eq[] = (float)$hours[$h]['demand_kwh'];
        }

        // 2. Battery state transition:
        // E_{h+1} - E_h - c_h + d_h = 0, where E_0 = initial_energy_kwh
        for ($h = 0; $h < 24; $h++) {
            $row = array_fill(0, $numVars, 0.0);
            $row[96 + $h] = 1.0;
            if ($h > 0) {
                $row[96 + $h - 1] = -1.0;
            }
            $row[48 + $h] = -1.0;
            $row[72 + $h] = 1.0;
            $A_eq[] = $row;
            $b_eq[] = ($h === 0) ? $initialEnergy : 0.0;
        }

        // 3. End-of-day battery neutrality:
        // E_24 = initial_energy_kwh
        $row = array_fill(0, $numVars, 0.0);
        $row[96 + 23] = 1.0;
        $A_eq[] = $row;
        $b_eq[] = $initialEnergy;

        $sol = SimplexSolver::solve($c, $A_ub, $b_ub, $A_eq, $b_eq, $bounds);

        if ($sol === null) {
            throw new \RuntimeException('Unable to find a feasible energy schedule for the given constraints.');
        }

        // Reconstruct plan
        $hourlyPlan = [];
        $totalGridKwh = 0.0;
        $totalCostBdt = 0.0;
        $peakGridKwh = 0.0;

        $currentBatteryEnergy = $initialEnergy;

        for ($h = 0; $h < 24; $h++) {
            $rawG = max(0.0, $sol[$h]);
            $rawS = max(0.0, min($effectiveSolar[$h], $sol[24 + $h]));
            $rawC = max(0.0, $sol[48 + $h]);
            $rawD = max(0.0, $sol[72 + $h]);

            // Clean up simultaneous charge and discharge
            if ($rawC > $rawD + 1e-4) {
                $batteryAction = 'charge';
                $batteryKwh = $rawC - $rawD;
                $currentBatteryEnergy += $batteryKwh;
            } elseif ($rawD > $rawC + 1e-4) {
                $batteryAction = 'discharge';
                $batteryKwh = $rawD - $rawC;
                $currentBatteryEnergy -= $batteryKwh;
            } else {
                $batteryAction = 'idle';
                $batteryKwh = 0.0;
            }

            $demand = (float)$hours[$h]['demand_kwh'];
            $tariff = (float)$hours[$h]['tariff_bdt_per_kwh'];

            // Solar used
            $solarUsed = round($rawS, 4);

            // Grid imported to perfectly satisfy balance: grid = demand + charge - solar_used - discharge
            $gridImport = ($batteryAction === 'charge')
                ? ($demand + $batteryKwh - $solarUsed)
                : ($demand - $batteryKwh - $solarUsed);
            $gridImport = max(0.0, round($gridImport, 4));

            $batteryEnergyAfter = round($currentBatteryEnergy, 4);

            $hourlyPlan[] = [
                'hour' => $h,
                'grid_kwh' => $gridImport + 0,
                'solar_used_kwh' => $solarUsed + 0,
                'battery_action' => $batteryAction,
                'battery_kwh' => round($batteryKwh, 4) + 0,
                'battery_energy_after_kwh' => $batteryEnergyAfter + 0,
            ];

            $totalGridKwh += $gridImport;
            $totalCostBdt += $gridImport * $tariff;
            $peakGridKwh = max($peakGridKwh, $gridImport);
        }

        $summary = $this->generateSummary($directives, $totalCostBdt, $totalGridKwh, $peakGridKwh);

        return [
            'hourly_plan' => $hourlyPlan,
            'total_grid_kwh' => round($totalGridKwh, 4) + 0,
            'total_cost_bdt' => round($totalCostBdt, 2) + 0,
            'peak_grid_kwh' => round($peakGridKwh, 4) + 0,
            'plan_summary' => $summary,
        ];
    }

    protected function generateSummary(array $directives, float $cost, float $grid, float $peak): string
    {
        $activeTypes = [];
        foreach ($directives as $dir) {
            if (!empty($dir['applies']) && ($dir['directive_type'] ?? '') !== 'no_op') {
                $activeTypes[] = str_replace('_', ' ', $dir['directive_type']);
            }
        }

        if (empty($activeTypes)) {
            return sprintf(
                "Optimal 24-hour schedule generated minimizing grid cost to %.2f BDT (%.2f kWh grid import, peak %.2f kWh) while respecting battery neutrality.",
                $cost, $grid, $peak
            );
        }

        $dirSummary = implode(', ', array_unique($activeTypes));
        return sprintf(
            "Optimal 24-hour schedule incorporating %s directives, minimizing grid cost to %.2f BDT (%.2f kWh grid import, peak %.2f kWh) with end-of-day battery neutrality.",
            $dirSummary, $cost, $grid, $peak
        );
    }
}
