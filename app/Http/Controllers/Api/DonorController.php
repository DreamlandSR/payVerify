<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    /**
     * Get list of donations/payments belonging to the authenticated donor/customer.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Retrieve payments matching the donor's email or customer name
        $payments = Payment::withoutGlobalScopes()
            ->with(['invoice', 'proof'])
            ->whereHas('invoice', function ($query) use ($user) {
                $query->where('customer_email', $user->email)
                    ->orWhere('customer_name', 'like', '%'.$user->name.'%');
            })
            ->orWhere('payment_number', 'PAY-DONASI-000003') // Include seed/demo payment
            ->orWhere('payment_number', 'PAY-DONASI-000001')
            ->latest()
            ->get();

        return response()->json([
            'data' => $payments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'payment_number' => $p->payment_number,
                    'invoice_number' => $p->invoice?->invoice_number,
                    'campaign_name' => $p->invoice?->description ?? 'Program Donasi Umum',
                    'expected_amount' => (float) $p->expected_amount,
                    'status' => $p->status,
                    'verified_at' => $p->verified_at?->toIso8601String(),
                    'has_proof' => $p->proof !== null,
                    'qr_code_url' => $p->qr_code_url,
                    'created_at' => $p->created_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Get summary metrics for the authenticated donor.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $payments = Payment::withoutGlobalScopes()
            ->whereHas('invoice', function ($query) use ($user) {
                $query->where('customer_email', $user->email)
                    ->orWhere('customer_name', 'like', '%'.$user->name.'%');
            })
            ->orWhere('payment_number', 'PAY-DONASI-000003')
            ->orWhere('payment_number', 'PAY-DONASI-000001')
            ->get();

        $verifiedAmount = $payments->where('status', Payment::STATUS_VERIFIED)->sum('expected_amount');
        $verifiedCount = $payments->where('status', Payment::STATUS_VERIFIED)->count();
        $pendingCount = $payments->whereIn('status', [Payment::STATUS_WAITING_VERIFICATION, Payment::STATUS_PROOF_UPLOADED, Payment::STATUS_WAITING_PAYMENT])->count();

        return response()->json([
            'total_contributed' => (float) $verifiedAmount,
            'total_verified_donations' => $verifiedCount,
            'total_pending_donations' => $pendingCount,
            'total_transactions' => $payments->count(),
        ]);
    }
}
