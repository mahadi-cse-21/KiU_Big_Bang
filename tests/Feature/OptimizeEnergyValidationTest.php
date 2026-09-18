<?php

namespace Tests\Feature;

use Tests\TestCase;

class OptimizeEnergyValidationTest extends TestCase
{
    public function test_empty_request_returns_400(): void
    {
        $response = $this->postJson('/optimize-energy', []);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error', 'details']);
    }

    public function test_missing_hours_returns_400(): void
    {
        $response = $this->postJson('/optimize-energy', [
            'scenario_id' => 'TEST-01',
            'operator_notes' => ['Test note'],
            'battery' => [
                'capacity_kwh' => 200,
                'initial_energy_kwh' => 100,
                'minimum_energy_kwh' => 40,
                'max_charge_kwh_per_hour' => 50,
                'max_discharge_kwh_per_hour' => 50
            ]
        ]);

        $response->assertStatus(400);
    }

    public function test_incorrect_number_of_hours_returns_400(): void
    {
        $response = $this->postJson('/optimize-energy', [
            'scenario_id' => 'TEST-02',
            'operator_notes' => ['Test note'],
            'hours' => [
                ['hour' => 0, 'demand_kwh' => 100, 'solar_kwh' => 0, 'tariff_bdt_per_kwh' => 5]
            ],
            'battery' => [
                'capacity_kwh' => 200,
                'initial_energy_kwh' => 100,
                'minimum_energy_kwh' => 40,
                'max_charge_kwh_per_hour' => 50,
                'max_discharge_kwh_per_hour' => 50
            ]
        ]);

        $response->assertStatus(400);
    }

    public function test_get_on_optimize_energy_as_json_returns_200_spec(): void
    {
        $response = $this->getJson('/optimize-energy');

        $response->assertStatus(200);
        $response->assertJsonStructure(['endpoint', 'method', 'status', 'required_fields']);
    }
}
