<?php

namespace App\Http\Controllers\Api;

use App\Domain\PaymentProviders\Services\PaymentProviderService;
use App\Domain\Reconciliation\Services\ReconciliationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with('invoice');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $payments = $query->latest()->paginate(15);

        return response()->json($payments);
    }

    /**
     * Store a newly created payment attempt for an invoice.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        $paymentNumber = Payment::generatePaymentNumber();

        $payment = Payment::create([
            'business_id' => $user->business_id,
            'invoice_id' => $invoice->id,
            'payment_number' => $paymentNumber,
            'expected_amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => Payment::STATUS_WAITING_PAYMENT,
            'payment_method' => $validated['payment_method'] ?? 'QRIS',
            'expires_at' => now()->addHours(24),
        ]);

        // Generate QRIS details from provider
        $providerService = new PaymentProviderService;
        $qrisData = $providerService->getProvider()->createQrisPayment($payment);

        $payment->update([
            'qr_code_url' => $qrisData->qrCodeUrl,
        ]);

        AuditLoggerService::log(
            action: 'payment.created',
            resourceType: Payment::class,
            resourceId: (string) $payment->id,
            metadata: [
                'payment_number' => $payment->payment_number,
                'invoice_number' => $invoice->invoice_number,
                'expected_amount' => $payment->expected_amount,
                'qr_code_url' => $qrisData->qrCodeUrl,
            ]
        );

        return response()->json([
            'message' => 'Payment record initialized.',
            'qris' => $qrisData->toArray(),
            'payment' => $payment->fresh(['invoice']),
        ], 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(string $id): JsonResponse
    {
        $payment = Payment::with(['invoice', 'verifier'])->findOrFail($id);

        return response()->json([
            'payment' => $payment,
        ]);
    }

    /**
     * Get complete AI extraction, validation results, and risk assessment findings for a payment.
     */
    public function analysis(string $id): JsonResponse
    {
        $payment = Payment::with([
            'invoice',
            'proof.extraction',
            'validationResult',
            'riskAssessment.duplicateProof',
        ])->findOrFail($id);

        return response()->json([
            'payment' => $payment,
            'expected' => [
                'amount' => (float) $payment->expected_amount,
                'currency' => $payment->currency,
            ],
            'extraction' => $payment->proof?->extraction,
            'validation' => $payment->validationResult,
            'risk' => $payment->riskAssessment,
        ]);
    }

    /**
     * Perform 3-way reconciliation for a payment.
     */
    public function reconciliation(string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $reconciliationService = new ReconciliationService;
        $report = $reconciliationService->reconcile($payment);

        return response()->json($report);
    }
}
