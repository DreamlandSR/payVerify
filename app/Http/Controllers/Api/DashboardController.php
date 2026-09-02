<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentExtraction;
use App\Models\PaymentRiskAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get aggregated dashboard analytics and key statistics for the business tenant.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->business_id;

        // Base query automatically scoped to tenant
        $totalTransactions = Payment::count();
        $totalRevenue = (float) Payment::where('status', Payment::STATUS_VERIFIED)->sum('expected_amount');

        $pendingVerification = Payment::whereIn('status', [
            Payment::STATUS_WAITING_VERIFICATION,
            Payment::STATUS_PROOF_UPLOADED,
            Payment::STATUS_AI_PROCESSING,
        ])->count();

        $verifiedPayments = Payment::where('status', Payment::STATUS_VERIFIED)->count();
        $rejectedPayments = Payment::where('status', Payment::STATUS_REJECTED)->count();

        $todayTransactions = Payment::whereDate('created_at', now()->toDateString())->count();
        $todayRevenue = (float) Payment::where('status', Payment::STATUS_VERIFIED)
            ->whereDate('created_at', now()->toDateString())
            ->sum('expected_amount');

        $monthlyTransactions = Payment::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $monthlyRevenue = (float) Payment::where('status', Payment::STATUS_VERIFIED)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('expected_amount');

        $verificationRate = $totalTransactions > 0
            ? round(($verifiedPayments / $totalTransactions) * 100, 2)
            : 0.0;

        $highRiskCount = PaymentRiskAssessment::where('risk_level', 'HIGH')->count();

        // AI Statistics
        $totalExtractions = PaymentExtraction::where('status', 'COMPLETED')->count();
        $highConfidenceExtractions = PaymentExtraction::where('status', 'COMPLETED')
            ->where('confidence_score', '>=', 0.85)
            ->count();

        $aiAccuracyRate = $totalExtractions > 0
            ? round(($highConfidenceExtractions / $totalExtractions) * 100, 2)
            : 0.0;

        return response()->json([
            'summary' => [
                'total_transactions' => $totalTransactions,
                'total_revenue' => $totalRevenue,
                'pending_verification' => $pendingVerification,
                'verified_payments' => $verifiedPayments,
                'rejected_payments' => $rejectedPayments,
                'verification_rate_percentage' => $verificationRate,
                'high_risk_payments' => $highRiskCount,
            ],
            'period' => [
                'today_transactions' => $todayTransactions,
                'today_revenue' => $todayRevenue,
                'monthly_transactions' => $monthlyTransactions,
                'monthly_revenue' => $monthlyRevenue,
            ],
            'ai_performance' => [
                'total_extractions' => $totalExtractions,
                'high_confidence_extractions' => $highConfidenceExtractions,
                'accuracy_rate_percentage' => $aiAccuracyRate,
            ],
        ]);
    }
}
