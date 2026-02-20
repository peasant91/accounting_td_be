<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {
    }

    /**
     * GET /dashboard/summary - Get dashboard summary statistics.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->dashboardService->getSummary();

        return response()->json([
            'data' => $summary,
        ]);
    }
}
