<?php

namespace App\Http\Controllers;

use App\Http\Requests\OptimizeEnergyRequest;
use App\Services\LLM\LLMInterpreterService;
use App\Services\Optimizer\EnergyOptimizationService;
use App\Services\Verification\ScheduleReplayValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EnergyOptimizerController extends Controller
{
    protected LLMInterpreterService $llmService;
    protected EnergyOptimizationService $optimizerService;
    protected ScheduleReplayValidator $replayValidator;

    public function __construct(
        LLMInterpreterService $llmService,
        EnergyOptimizationService $optimizerService,
        ScheduleReplayValidator $replayValidator
    ) {
        $this->llmService = $llmService;
        $this->optimizerService = $optimizerService;
        $this->replayValidator = $replayValidator;
    }

    /**
     * Process energy optimization request:
     * 1. LLM operator note interpretation
     * 2. Deterministic guardrails
     * 3. 24-hour mathematical optimization
     * 4. Schedule replay verification
     */
    public function optimize(OptimizeEnergyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $scenarioId = (string)$validated['scenario_id'];
        $operatorNotes = (array)$validated['operator_notes'];
        $hours = (array)$validated['hours'];
        $battery = (array)$validated['battery'];

        try {
            // Step 1 & 2: LLM Interpretation with Deterministic Guardrails
            $directiveInterpretations = $this->llmService->interpret($operatorNotes, $battery);

            // Step 3: Mathematical Optimization (Pure PHP Two-Phase Simplex)
            $optimizationResult = $this->optimizerService->optimize($hours, $battery, $directiveInterpretations);

            $hourlyPlan = $optimizationResult['hourly_plan'];

            // Step 4: Schedule Replay Verification
            $violations = $this->replayValidator->validate(
                $hourlyPlan,
                $hours,
                $battery,
                $directiveInterpretations
            );

            if (!empty($violations)) {
                Log::warning("Schedule replay validation noted discrepancies for scenario {$scenarioId}: " . implode('; ', $violations));
            }

            // Step 5: Format Final Response Contract
            return response()->json([
                'scenario_id' => $scenarioId,
                'directive_interpretation' => $directiveInterpretations,
                'hourly_plan' => $hourlyPlan,
                'total_grid_kwh' => $optimizationResult['total_grid_kwh'],
                'total_cost_bdt' => $optimizationResult['total_cost_bdt'],
                'peak_grid_kwh' => $optimizationResult['peak_grid_kwh'],
                'plan_summary' => $optimizationResult['plan_summary'],
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Controlled optimization failure for scenario {$scenarioId}: " . $e->getMessage());

            return response()->json([
                'error' => 'A controlled internal error occurred while processing the schedule.'
            ], 500);
        }
    }
}
