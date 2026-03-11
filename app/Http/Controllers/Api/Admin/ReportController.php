<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RMARequest;
use App\Models\Product;
use App\Enums\RMAStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getDashboardOverview(Request $request)
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // 1. Total RMAs
        $totals = [
            'today' => RMARequest::whereDate('created_at', clone $today)->count(),
            'week' => RMARequest::where('created_at', '>=', $startOfWeek)->count(),
            'month' => RMARequest::where('created_at', '>=', $startOfMonth)->count(),
            'all_time' => RMARequest::count(),
        ];

        // 2. Status Breakdown
        $statusBreakdown = RMARequest::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                ];
            });

        // 3. Trend Line (Last 30 Days)
        $trendDates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $trendDates->push(Carbon::now()->subDays($i)->format('Y-m-d'));
        }

        $trendsData = RMARequest::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', clone $thirtyDaysAgo)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('count', 'date');

        $trendLine = $trendDates->map(function ($date) use ($trendsData) {
            return [
                'date' => $date,
                'count' => $trendsData->get($date, 0)
            ];
        });

        // 4. Average Processing Time (Completed/Rejected RMAs)
        $completedStatuses = [RMAStatus::COMPLETED->value, RMAStatus::REJECTED->value];
        $completedRmas = RMARequest::whereIn('status', $completedStatuses)->get();
        if ($completedRmas->count() > 0) {
            $totalDays = $completedRmas->sum(function ($rma) {
                return $rma->created_at->diffInDays($rma->updated_at);
            });
            $avgProcessingTime = round($totalDays / $completedRmas->count(), 1);
        } else {
            $avgProcessingTime = 0;
        }

        // 5. Top 5 Reasons
        $topReasons = RMARequest::select('reason', DB::raw('count(*) as count'))
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // 6. Top 5 Returned Products
        $topProducts = RMARequest::select('product_id', DB::raw('count(*) as count'))
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product ? $item->product->name : 'Unknown Product',
                    'count' => $item->count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'totals' => $totals,
                'status_breakdown' => $statusBreakdown,
                'trend_line' => $trendLine,
                'avg_processing_time_days' => $avgProcessingTime,
                'top_reasons' => $topReasons,
                'top_products' => $topProducts,
            ]
        ]);
    }

    public function exportRmasToCsv(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = RMARequest::with(['customer', 'product']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $rmas = $query->latest()->get();

        $filename = "rma_report_" . date('Y-m-d_H-i-s') . ".csv";

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = [
            'RMA Number',
            'Status',
            'Customer Name',
            'Customer Email',
            'Product Name',
            'RMA Type',
            'Reason',
            'Priority',
            'Created At',
            'Updated At'
        ];

        $callback = function () use ($rmas, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($rmas as $rma) {
                $row = [
                    $rma->rma_number,
                    $rma->status ? $rma->status->value : 'N/A',
                    $rma->customer ? ($rma->customer->first_name . ' ' . $rma->customer->last_name) : 'N/A',
                    $rma->customer ? $rma->customer->email : 'N/A',
                    $rma->product ? $rma->product->name : 'N/A',
                    $rma->rma_type ? $rma->rma_type->value : 'N/A',
                    $rma->reason ? $rma->reason->value : 'N/A',
                    $rma->priority ? $rma->priority->value : 'N/A',
                    $rma->created_at->format('Y-m-d H:i:s'),
                    $rma->updated_at->format('Y-m-d H:i:s'),
                ];
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
