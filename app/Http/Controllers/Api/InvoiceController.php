<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with('payments');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%");
            });
        }

        $invoices = $query->latest()->paginate(15);

        return response()->json($invoices);
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $invoiceNumber = Invoice::generateInvoiceNumber($user->business_id);

        $invoice = Invoice::create([
            'business_id' => $user->business_id,
            'invoice_number' => $invoiceNumber,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'IDR',
            'status' => 'WAITING_PAYMENT',
            'due_date' => $validated['due_date'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        AuditLoggerService::log(
            action: 'invoice.created',
            resourceType: Invoice::class,
            resourceId: (string) $invoice->id,
            metadata: [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
            ]
        );

        return response()->json([
            'message' => 'Invoice created successfully.',
            'invoice' => $invoice,
        ], 201);
    }

    /**
     * Display the specified invoice.
     */
    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::with('payments')->findOrFail($id);

        return response()->json([
            'invoice' => $invoice,
        ]);
    }
}
