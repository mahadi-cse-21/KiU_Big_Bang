<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class OptimizeEnergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scenario_id' => ['required', 'string'],
            'operator_notes' => ['required', 'array', 'min:1', 'max:3'],
            'operator_notes.*' => ['required', 'string'],
            'hours' => ['required', 'array', 'size:24'],
            'hours.*.hour' => ['required', 'integer', 'between:0,23'],
            'hours.*.demand_kwh' => ['required', 'numeric', 'min:0'],
            'hours.*.solar_kwh' => ['required', 'numeric', 'min:0'],
            'hours.*.tariff_bdt_per_kwh' => ['required', 'numeric', 'min:0'],
            'battery' => ['required', 'array'],
            'battery.capacity_kwh' => ['required', 'numeric', 'gt:0'],
            'battery.initial_energy_kwh' => ['required', 'numeric', 'min:0'],
            'battery.minimum_energy_kwh' => ['required', 'numeric', 'min:0'],
            'battery.max_charge_kwh_per_hour' => ['required', 'numeric', 'min:0'],
            'battery.max_discharge_kwh_per_hour' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $hours = $this->input('hours');
            if (is_array($hours)) {
                $hourList = array_column($hours, 'hour');
                if (count(array_unique($hourList)) !== 24) {
                    $validator->errors()->add('hours', 'The hours array must contain unique hours from 0 to 23.');
                }
            }

            $battery = $this->input('battery');
            if (is_array($battery)) {
                $cap = $battery['capacity_kwh'] ?? null;
                $init = $battery['initial_energy_kwh'] ?? null;
                $min = $battery['minimum_energy_kwh'] ?? null;

                if ($cap !== null && $init !== null && $init > $cap) {
                    $validator->errors()->add('battery.initial_energy_kwh', 'Initial energy cannot exceed capacity.');
                }
                if ($cap !== null && $min !== null && $min > $cap) {
                    $validator->errors()->add('battery.minimum_energy_kwh', 'Minimum reserve cannot exceed capacity.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => 'Malformed JSON or structurally invalid request',
            'details' => $validator->errors()
        ], 400));
    }
}
