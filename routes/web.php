<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\HealthController;
use App\Http\Controllers\EnergyOptimizerController;

// Dashboard for web browser visitors
Route::get('/', function (\Illuminate\Http\Request $request) {
    if ($request->expectsJson() && !$request->acceptsHtml()) {
        return response()->json([
            'service' => 'BUP CSE Fest 2026 GridWise Energy Optimization API',
            'status' => 'ok',
            'endpoints' => [
                'GET /health',
                'POST /optimize-energy'
            ]
        ]);
    }
    return view('dashboard');
});

// Serve sample cases for UI dropdown
Route::get('/data/public_sample_cases.json', function () {
    $path = storage_path('data/public_sample_cases.json');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'application/json']);
});

// GET /health readiness endpoint
Route::get('/health', [HealthController::class, 'check']);

// /optimize-energy endpoint (GET renders dashboard or info, POST executes optimization)
Route::get('/optimize-energy', function (\Illuminate\Http\Request $request) {
    if ($request->expectsJson() && !$request->acceptsHtml()) {
        return response()->json([
            'endpoint' => 'POST /optimize-energy',
            'method' => 'POST',
            'status' => 'listening',
            'required_fields' => [
                'scenario_id',
                'operator_notes',
                'hours',
                'battery'
            ]
        ], 200);
    }
    return view('dashboard');
});

Route::post('/optimize-energy', [EnergyOptimizerController::class, 'optimize']);


