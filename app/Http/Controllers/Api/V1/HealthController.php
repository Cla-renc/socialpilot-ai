<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $databaseReady = true;
        } catch (\Throwable $exception) {
            $databaseReady = false;
        }

        return response()->json([
            'status' => $databaseReady ? 'ok' : 'degraded',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'database' => $databaseReady,
                'users' => User::count() >= 0,
                'brands' => Brand::count() >= 0,
            ],
        ]);
    }

    public function ready(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $databaseReady = true;
        } catch (\Throwable $exception) {
            $databaseReady = false;
        }

        return response()->json([
            'ready' => $databaseReady,
            'service' => 'socialpilot-ai',
            'timestamp' => now()->toISOString(),
        ], $databaseReady ? 200 : 503);
    }
}
