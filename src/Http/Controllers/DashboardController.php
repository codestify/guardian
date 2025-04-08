<?php

namespace Shah\Guardian\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Guardian dashboard.
     */
    public function index(Request $request): View
    {
        // Verify the user is authorized to access the dashboard
        $this->authorize('viewGuardianDashboard', $request->user());

        // Get summary statistics
        $stats = $this->getDashboardStats();

        // Get the latest detection logs
        $logs = $this->getLatestLogs();

        // Get chart data for the last 30 days
        $chartData = $this->getChartData();

        return view('guardian::dashboard.index', [
            'stats' => $stats,
            'logs' => $logs,
            'chartData' => $chartData,
        ]);
    }

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats(): array
    {
        $tableName = config('guardian.database.table', 'guardian_logs');

        // Get statistics for the last 30 days
        $thirtyDaysAgo = now()->subDays(30);

        return [
            'total_visitors' => DB::table($tableName)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count(),

            'detected_bots' => DB::table($tableName)
                ->where('is_bot', true)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count(),

            'high_score_visitors' => DB::table($tableName)
                ->where('score', '>=', config('guardian.thresholds.score', 10))
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count(),

            'blocked_requests' => DB::table($tableName)
                ->where('action_taken', 'block')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count(),

            'challenged_requests' => DB::table($tableName)
                ->where('action_taken', 'challenge')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count(),
        ];
    }

    /**
     * Get the latest detection logs.
     */
    protected function getLatestLogs(): array
    {
        $tableName = config('guardian.database.table', 'guardian_logs');

        return DB::table($tableName)
            ->select([
                'id',
                'ip_address',
                'user_agent',
                'score',
                'certainty',
                'url',
                'action_taken',
                'is_bot',
                'bot_type',
                'country_code',
                'created_at',
            ])
            ->where('score', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Get chart data for the last 30 days.
     */
    protected function getChartData(): array
    {
        $tableName = config('guardian.database.table', 'guardian_logs');
        $thirtyDaysAgo = now()->subDays(30);

        // Get daily counts for total visitors and bots
        $dailyCounts = DB::table($tableName)
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bots'),
            ])
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $dates = [];
        $totals = [];
        $bots = [];

        foreach ($dailyCounts as $count) {
            $dates[] = $count->date;
            $totals[] = $count->total;
            $bots[] = $count->bots;
        }

        return [
            'dates' => $dates,
            'totals' => $totals,
            'bots' => $bots,
        ];
    }
}
