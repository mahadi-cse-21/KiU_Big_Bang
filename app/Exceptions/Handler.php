<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if ($request->is('optimize-energy') || $request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'Method not allowed. /optimize-energy expects a POST request with scenario JSON.',
                    'hint' => 'Send a POST request with scenario_id, operator_notes, hours, and battery.'
                ], 405);
            }
        });

        $this->renderable(function (\Symfony\Component\HttpKernel\Exception\BadRequestHttpException $e, $request) {
            if ($request->is('optimize-energy') || $request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'Malformed JSON or structurally invalid request'
                ], 400);
            }
        });

        $this->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->is('optimize-energy') || $request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage() ?: 'HTTP error occurred'
                ], $e->getStatusCode());
            }
        });

        $this->renderable(function (Throwable $e, $request) {
            if (($request->is('optimize-energy') || $request->is('api/*') || $request->expectsJson()) 
                && !($e instanceof \Illuminate\Validation\ValidationException) 
                && !($e instanceof \Illuminate\Http\Exceptions\HttpResponseException)
                && !($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException)) {
                return response()->json([
                    'error' => 'A controlled server error occurred while processing the request.'
                ], 500);
            }
        });

    }

}
