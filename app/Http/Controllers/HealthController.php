<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Readiness endpoint for the judging harness.
     *
     * @return JsonResponse HTTP 200 with status: ok
     */
    public function check(): JsonResponse
    {
        return response()->json([
            'status' => 'ok'
        ], 200);
    }
}
