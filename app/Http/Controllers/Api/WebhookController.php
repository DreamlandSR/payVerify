<?php

namespace App\Http\Controllers\Api;

use App\Domain\PaymentProviders\Services\PaymentProviderService;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Handle incoming payment provider webhook callbacks.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerService = new PaymentProviderService;
        $providerInstance = $providerService->getProvider();

        $result = $providerInstance->processWebhook($request);

        if (! $result->validSignature) {
            WebhookEvent::create([
                'provider' => $provider,
                'event_type' => 'unauthorized',
                'payload' => $request->json()->all() ?: ['raw' => $request->getContent()],
                'status' => 'FAILED',
            ]);

            return response()->json([
                'message' => 'Invalid webhook signature.',
                'error' => $result->errorMessage,
            ], 401);
        }

        $payment = null;
        if ($result->paymentNumber) {
            $payment = Payment::where('payment_number', $result->paymentNumber)->first();
        }

        $webhookEvent = WebhookEvent::create([
            'business_id' => $payment?->business_id,
            'provider' => $provider,
            'event_type' => $result->eventType ?? 'payment.notification',
            'payload' => $request->json()->all(),
            'status' => 'PROCESSED',
            'processed_at' => now(),
        ]);

        AuditLoggerService::log(
            action: 'webhook.received',
            resourceType: WebhookEvent::class,
            resourceId: (string) $webhookEvent->id,
            metadata: [
                'provider' => $provider,
                'event_type' => $result->eventType,
                'payment_number' => $result->paymentNumber,
            ]
        );

        return response()->json([
            'message' => 'Webhook payload processed successfully.',
            'event_id' => $webhookEvent->id,
            'payment_found' => $payment !== null,
        ]);
    }
}
