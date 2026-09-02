<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadProofRequest;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentProofController extends Controller
{
    /**
     * Upload payment proof image securely.
     */
    public function upload(UploadProofRequest $request, string $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);

        $file = $request->file('proof');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $fileSize = $file->getSize();
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Secure file storage in private directory using UUID
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::uuid().'.'.$extension;
        $path = $file->storeAs('private/payment_proofs', $fileName);

        // Delete previous proof file if replacing
        if ($payment->proof) {
            Storage::delete($payment->proof->file_path);
            $payment->proof->delete();
        }

        $proof = PaymentProof::create([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'file_path' => $path,
            'file_name' => $originalName,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
        ]);

        $payment->update([
            'status' => Payment::STATUS_PROOF_UPLOADED,
        ]);

        AuditLoggerService::log(
            action: 'payment_proof.uploaded',
            resourceType: PaymentProof::class,
            resourceId: (string) $proof->id,
            metadata: [
                'payment_number' => $payment->payment_number,
                'file_name' => $originalName,
                'file_hash' => $fileHash,
            ]
        );

        return response()->json([
            'message' => 'Payment proof uploaded successfully.',
            'proof' => $proof,
            'payment' => $payment->fresh(),
        ], 201);
    }

    /**
     * Securely stream/view the payment proof image.
     */
    public function show(Request $request, string $paymentId): BinaryFileResponse|JsonResponse
    {
        $payment = Payment::with('proof')->findOrFail($paymentId);

        if (! $payment->proof) {
            return response()->json([
                'message' => 'No payment proof uploaded for this payment.',
            ], 404);
        }

        $filePath = storage_path('app/'.$payment->proof->file_path);

        if (! file_exists($filePath)) {
            return response()->json([
                'message' => 'Payment proof file not found on disk.',
            ], 404);
        }

        return response()->file($filePath, [
            'Content-Type' => $payment->proof->mime_type,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
