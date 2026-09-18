<?php

namespace App\Console\Commands;

use App\Services\LLM\LLMInterpreterService;
use App\Services\Optimizer\EnergyOptimizationService;
use App\Services\Verification\ScheduleReplayValidator;
use Illuminate\Console\Command;

class TestSampleCasesCommand extends Command
{
    protected $signature = 'gridwise:test-samples';
    protected $description = 'Run the 10 public GridWise sample cases and verify against reference benchmarks';

    public function handle(
        LLMInterpreterService $llmService,
        EnergyOptimizationService $optimizerService,
        ScheduleReplayValidator $replayValidator
    ): int {
        $path = storage_path('data/public_sample_cases.json');
        if (!file_exists($path)) {
            $this->error("Sample pack not found at {$path}");
            return 1;
        }

        $json = json_decode(file_get_contents($path), true);
        $cases = $json['cases'] ?? [];

        $this->info("================================================================================");
        $this->info("  BUP CSE Fest 2026 Hackathon — GridWise Public Sample Benchmark Suite");
        $this->info("================================================================================");

        $tableRows = [];
        $passedCount = 0;
        $totalCount = count($cases);

        foreach ($cases as $case) {
            $id = $case['id'];
            $label = $case['label'];
            $input = $case['input'];
            $expected = $case['expected_output'];

            $start = microtime(true);

            // 1. LLM Interpretation & Guardrails
            $interpretations = $llmService->interpret($input['operator_notes'], $input['battery']);

            // 2. Optimization
            $result = $optimizerService->optimize($input['hours'], $input['battery'], $interpretations);

            // 3. Replay validation
            $violations = $replayValidator->validate(
                $result['hourly_plan'],
                $input['hours'],
                $input['battery'],
                $interpretations
            );

            $durationMs = round((microtime(true) - $start) * 1000, 1);

            $costDiff = abs($result['total_cost_bdt'] - $expected['total_cost_bdt']);
            $costMatch = $costDiff <= 1.0; // within 1 BDT tolerance for floating-point LP variations
            $valid = empty($violations);

            // Check directive matching
            $directivesMatch = true;
            $expDirs = $expected['directive_interpretation'];
            if (count($interpretations) !== count($expDirs)) {
                $directivesMatch = false;
            } else {
                foreach ($expDirs as $idx => $expD) {
                    $gotD = $interpretations[$idx] ?? [];
                    if (($gotD['directive_type'] ?? '') !== ($expD['directive_type'] ?? '')) {
                        $directivesMatch = false;
                        break;
                    }
                    if (($gotD['applies'] ?? false) !== ($expD['applies'] ?? false)) {
                        $directivesMatch = false;
                        break;
                    }
                }
            }

            $pass = $valid && $costMatch && $directivesMatch;
            if ($pass) {
                $passedCount++;
            }

            $statusStr = $pass ? '<info>PASS</info>' : '<error>FAIL</error>';

            $tableRows[] = [
                $id,
                $label,
                $directivesMatch ? 'YES' : 'NO',
                number_format($result['total_cost_bdt'], 2) . ' / ' . number_format($expected['total_cost_bdt'], 2),
                number_format($result['total_grid_kwh'], 2) . ' / ' . number_format($expected['total_grid_kwh'], 2),
                $valid ? 'YES' : 'NO',
                "{$durationMs}ms",
                $statusStr
            ];
        }

        $this->table(
            ['Case ID', 'Description', 'Directives OK', 'Cost (Got/Exp)', 'Grid kWh (Got/Exp)', 'Replay OK', 'Latency', 'Status'],
            $tableRows
        );

        $this->newLine();
        if ($passedCount === $totalCount) {
            $this->info("RESULT: All {$passedCount}/{$totalCount} sample cases PASSED perfectly!");
            return 0;
        } else {
            $this->warn("RESULT: {$passedCount}/{$totalCount} sample cases passed.");
            return 1;
        }
    }
}
