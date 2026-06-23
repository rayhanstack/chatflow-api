<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    // ── GET /analytics/overview ───────────────────────────────────────────

    public function overview(): JsonResponse
    {
        $today     = now()->startOfDay();
        $thisWeek  = now()->startOfWeek();
        $lastWeek  = now()->subWeek()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        $weekRevenue  = $this->revenue($thisWeek);
        $lastWeekRev  = $this->revenue($lastWeek, now()->subWeek()->endOfWeek());

        return response()->json([
            'today_orders'       => Order::where('created_at', '>=', $today)->count(),
            'week_orders'        => Order::where('created_at', '>=', $thisWeek)->count(),
            'month_orders'       => Order::where('created_at', '>=', $thisMonth)->count(),
            'week_revenue'       => $weekRevenue,
            'revenue_delta_pct'  => $lastWeekRev > 0
                ? round((($weekRevenue - $lastWeekRev) / $lastWeekRev) * 100, 1)
                : 0,
            'pending_orders'     => Order::where('status', 'pending')->count(),
            'total_customers'    => Customer::count(),
            'new_customers_week' => Customer::where('created_at', '>=', $thisWeek)->count(),
            'ai_handled'         => $this->aiHandledPct(),
            'avg_response_sec'   => $this->avgResponseTime(),
        ]);
    }

    // ── GET /analytics/orders-per-day?days=14 ────────────────────────────

    public function ordersPerDay(): JsonResponse
    {
        $days = min((int) request('days', 14), 90);

        $rows = Order::select(
                DB::raw("DATE(created_at) as date"),
                DB::raw("COUNT(*) as orders"),
                DB::raw("SUM(total) as revenue")
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing days with 0
        $map    = $rows->keyBy('date');
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d          = now()->subDays($i)->toDateString();
            $result[]   = [
                'date'    => $d,
                'orders'  => $map[$d]->orders ?? 0,
                'revenue' => (float) ($map[$d]->revenue ?? 0),
            ];
        }

        return response()->json($result);
    }

    // ── GET /analytics/platform-breakdown ────────────────────────────────

    public function platformBreakdown(): JsonResponse
    {
        $rows = Conversation::select('platform', DB::raw('COUNT(*) as count'))
            ->groupBy('platform')
            ->get();

        $total = $rows->sum('count');

        return response()->json($rows->map(fn ($r) => [
            'platform' => $r->platform,
            'count'    => $r->count,
            'pct'      => $total > 0 ? round(($r->count / $total) * 100, 1) : 0,
        ]));
    }

    // ── GET /analytics/top-products?limit=10 ─────────────────────────────

    public function topProducts(): JsonResponse
    {
        $limit  = min((int) request('limit', 8), 20);
        $orders = Order::whereNotIn('status', ['cancelled'])
            ->pluck('items')
            ->flatten(1);

        $tally = [];
        foreach ($orders as $item) {
            $name = $item['name'] ?? 'Unknown';
            if (! isset($tally[$name])) {
                $tally[$name] = ['name' => $name, 'qty' => 0, 'revenue' => 0.0];
            }
            $tally[$name]['qty']     += (int) ($item['qty'] ?? 1);
            $tally[$name]['revenue'] += (float) (($item['qty'] ?? 1) * ($item['unit_price'] ?? 0));
        }

        usort($tally, fn ($a, $b) => $b['qty'] - $a['qty']);

        return response()->json(array_slice(array_values($tally), 0, $limit));
    }

    // ── GET /analytics/order-status-dist ─────────────────────────────────

    public function orderStatusDist(): JsonResponse
    {
        $rows = Order::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        return response()->json($rows);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function revenue($from, $to = null): float
    {
        return (float) Order::where('status', 'delivered')
            ->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->sum('total');
    }

    private function aiHandledPct(): float
    {
        $total = Message::where('direction', 'outbound')->count();
        if ($total === 0) return 0;
        $ai = Message::where('direction', 'outbound')->where('sender_type', 'ai')->count();
        return round(($ai / $total) * 100, 1);
    }

    private function avgResponseTime(): int
    {
        // Avg seconds between last inbound and first outbound per conversation (last 7 days)
        $result = DB::select("
            SELECT AVG(EXTRACT(EPOCH FROM (out_time - in_time))) AS avg_sec
            FROM (
                SELECT
                    c.id,
                    MAX(CASE WHEN m.direction='inbound'  THEN m.created_at END) AS in_time,
                    MIN(CASE WHEN m.direction='outbound' THEN m.created_at END) AS out_time
                FROM conversations c
                JOIN messages m ON m.conversation_id = c.id
                WHERE m.created_at >= NOW() - INTERVAL '7 days'
                GROUP BY c.id
                HAVING MAX(CASE WHEN m.direction='inbound'  THEN m.created_at END) IS NOT NULL
                   AND MIN(CASE WHEN m.direction='outbound' THEN m.created_at END) IS NOT NULL
            ) t
            WHERE out_time > in_time
        ");

        return (int) round($result[0]->avg_sec ?? 0);
    }
}
