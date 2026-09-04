<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Laravel's own `/up` (configured in bootstrap/app.php) is a liveness check —
 * it only confirms the app booted. This adds a readiness check that also
 * confirms the database is actually reachable, which is what a deployment
 * platform's load balancer / orchestrator needs before routing traffic to
 * this instance. Deliberately returns only a non-sensitive status — no
 * connection strings, stack traces, or other internals.
 */
class HealthController extends Controller
{
    public function db(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json(['status' => 'ok', 'database' => 'ok']);
        } catch (Throwable) {
            return response()->json(['status' => 'error', 'database' => 'unreachable'], 503);
        }
    }
}
