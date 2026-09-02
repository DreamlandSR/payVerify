<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Generate filterable transaction reports for business owners and staff.
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = Payment::query()->with([
            'invoice',
            'proof.extraction',
            'validationResult',
            'riskAssessment',
            'verifier',
        ]);

        // Apply filters
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->query('end_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        if ($request->filled('verified_by_user_id')) {
            $query->where('verified_by_user_id', $request->query('verified_by_user_id'));
        }

        if ($request->filled('min_amount')) {
            $query->where('expected_amount', '>=', (float) $request->query('min_amount'));
        }

        if ($request->filled('max_amount')) {
            $query->where('expected_amount', '<=', (float) $request->query('max_amount'));
        }

        if ($request->filled('risk_level')) {
            $riskLevel = $request->query('risk_level');
            $query->whereHas('riskAssessment', function ($q) use ($riskLevel) {
                $q->where('risk_level', $riskLevel);
            });
        }

        // Clone query for overall summary totals before pagination
        $summaryQuery = clone $query;
        $totalRecords = $summaryQuery->count();
        $totalSumAmount = (float) $summaryQuery->sum('expected_amount');

        $statusBreakdown = (clone $summaryQuery)
            ->selectRaw('status, count(*) as count, sum(expected_amount) as total_amount')
            ->groupBy('status')
            ->get();

        $payments = $query->latest()->paginate($request->query('per_page', 15));

        return response()->json([
            'report_summary' => [
                'total_records' => $totalRecords,
                'total_amount' => $totalSumAmount,
                'status_breakdown' => $statusBreakdown,
            ],
            'transactions' => $payments,
        ]);
    }
}
