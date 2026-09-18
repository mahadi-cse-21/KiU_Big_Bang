<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSampleCasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Use the deterministic fallback provider in unit tests for instant, deterministic assertions
        config(['gridwise.provider' => 'fallback']);
    }

    public function test_all_10_public_sample_cases_pass_benchmarks(): void
    {
        $path = storage_path('data/public_sample_cases.json');
        $this->assertFileExists($path);

        $json = json_decode(file_get_contents($path), true);
        $cases = $json['cases'] ?? [];
        $this->assertCount(10, $cases);

        foreach ($cases as $case) {
            $id = $case['id'];
            $input = $case['input'];
            $expected = $case['expected_output'];

            $response = $this->postJson('/optimize-energy', $input);

            $response->assertStatus(200);

            // Assert top-level response schema
            $response->assertJsonStructure([
                'scenario_id',
                'directive_interpretation' => [
                    '*' => [
                        'note_index',
                        'applies',
                        'directive_type',
                        'structured_adjustment',
                        'explanation'
                    ]
                ],
                'hourly_plan' => [
                    '*' => [
                        'hour',
                        'grid_kwh',
                        'solar_used_kwh',
                        'battery_action',
                        'battery_kwh',
                        'battery_energy_after_kwh'
                    ]
                ],
                'total_grid_kwh',
                'total_cost_bdt',
                'peak_grid_kwh',
                'plan_summary'
            ]);

            $data = $response->json();

            // 1. Scenario ID echo
            $this->assertEquals($input['scenario_id'], $data['scenario_id']);

            // 2. Directives count and types
            $this->assertCount(count($expected['directive_interpretation']), $data['directive_interpretation']);
            foreach ($expected['directive_interpretation'] as $idx => $expDir) {
                $gotDir = $data['directive_interpretation'][$idx];
                $this->assertEquals($expDir['note_index'], $gotDir['note_index'], "Scenario {$id} note_index mismatch");
                $this->assertEquals($expDir['applies'], $gotDir['applies'], "Scenario {$id} applies mismatch");
                $this->assertEquals($expDir['directive_type'], $gotDir['directive_type'], "Scenario {$id} directive_type mismatch");

                if ($expDir['applies']) {
                    $this->assertNotNull($gotDir['structured_adjustment'], "Scenario {$id} expected structured_adjustment");
                    $expAdj = $expDir['structured_adjustment'];
                    $gotAdj = $gotDir['structured_adjustment'];

                    // Compare hours
                    $this->assertEquals($expAdj['hours'], $gotAdj['hours'], "Scenario {$id} hours mismatch");

                    // Compare numeric parameter
                    if (isset($expAdj['factor'])) {
                        $this->assertEqualsWithDelta($expAdj['factor'], $gotAdj['factor'], 0.01, "Scenario {$id} factor mismatch");
                    }
                    if (isset($expAdj['minimum_energy_kwh'])) {
                        $this->assertEqualsWithDelta($expAdj['minimum_energy_kwh'], $gotAdj['minimum_energy_kwh'], 0.01, "Scenario {$id} minimum_energy_kwh mismatch");
                    }
                    if (isset($expAdj['max_grid_kwh'])) {
                        $this->assertEqualsWithDelta($expAdj['max_grid_kwh'], $gotAdj['max_grid_kwh'], 0.01, "Scenario {$id} max_grid_kwh mismatch");
                    }
                } else {
                    $this->assertNull($gotDir['structured_adjustment'], "Scenario {$id} expected null structured_adjustment for no_op");
                }
            }

            // 3. Hourly plan count
            $this->assertCount(24, $data['hourly_plan']);

            // 4. Optimization cost and grid match reference within tolerance
            $this->assertEqualsWithDelta(
                $expected['total_cost_bdt'],
                $data['total_cost_bdt'],
                1.0,
                "Scenario {$id} cost mismatch: got {$data['total_cost_bdt']}, expected {$expected['total_cost_bdt']}"
            );

            $this->assertEqualsWithDelta(
                $expected['total_grid_kwh'],
                $data['total_grid_kwh'],
                1.0,
                "Scenario {$id} grid kWh mismatch: got {$data['total_grid_kwh']}, expected {$expected['total_grid_kwh']}"
            );

            // 5. Battery neutrality: E_24 = initial_energy_kwh
            $lastHour = $data['hourly_plan'][23];
            $this->assertEqualsWithDelta(
                $input['battery']['initial_energy_kwh'],
                $lastHour['battery_energy_after_kwh'],
                0.01,
                "Scenario {$id} battery neutrality violated"
            );
        }
    }
}
