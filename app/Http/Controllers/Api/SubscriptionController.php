<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpgradeSubscriptionRequest;
use App\Models\Subscription;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    private const PLAN_QUOTAS = [
        'FREE' => 50,
        'STARTER' => 500,
        'BUSINESS' => 2000,
        'PRO' => 10000,
    ];

    /**
     * Get current business subscription plan and usage metrics.
     */
    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        $subscription = Subscription::where('business_id', $business->id)->first();

        if (! $subscription) {
            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan_name' => 'FREE',
                'max_verifications_per_month' => 50,
                'current_month_usage' => 0,
                'period_starts_at' => now(),
                'period_ends_at' => now()->addMonth(),
            ]);
        }

        $usagePercentage = $subscription->max_verifications_per_month > 0
            ? round(($subscription->current_month_usage / $subscription->max_verifications_per_month) * 100, 2)
            : 100.0;

        return response()->json([
            'subscription' => $subscription,
            'usage' => [
                'current' => $subscription->current_month_usage,
                'limit' => $subscription->max_verifications_per_month,
                'percentage' => $usagePercentage,
                'is_limit_reached' => $subscription->current_month_usage >= $subscription->max_verifications_per_month,
            ],
        ]);
    }

    /**
     * Upgrade subscription plan for the business tenant.
     */
    public function upgrade(UpgradeSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $business = $request->user()->business;

        $planName = $validated['plan_name'];
        $newQuota = self::PLAN_QUOTAS[$planName] ?? 50;

        $subscription = Subscription::where('business_id', $business->id)->first();

        if ($subscription) {
            $subscription->update([
                'plan_name' => $planName,
                'max_verifications_per_month' => $newQuota,
            ]);
        } else {
            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan_name' => $planName,
                'max_verifications_per_month' => $newQuota,
                'current_month_usage' => 0,
                'period_starts_at' => now(),
                'period_ends_at' => now()->addMonth(),
            ]);
        }

        AuditLoggerService::log(
            action: 'subscription.upgraded',
            resourceType: Subscription::class,
            resourceId: (string) $subscription->id,
            metadata: [
                'plan_name' => $planName,
                'max_verifications_per_month' => $newQuota,
            ]
        );

        return response()->json([
            'message' => 'Subscription upgraded successfully to '.$planName.' plan.',
            'subscription' => $subscription,
        ]);
    }

    /**
     * Get catalog of available SaaS plans and pricing limits.
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => [
                [
                    'name' => 'FREE',
                    'limit' => 50,
                    'description' => 'Ideal for small businesses testing the platform.',
                ],
                [
                    'name' => 'STARTER',
                    'limit' => 500,
                    'description' => 'For growing businesses requiring regular verification.',
                ],
                [
                    'name' => 'BUSINESS',
                    'limit' => 2000,
                    'description' => 'For established merchants with high daily transaction volumes.',
                ],
                [
                    'name' => 'PRO',
                    'limit' => 10000,
                    'description' => 'Enterprise-tier high-volume verification.',
                ],
            ],
        ]);
    }
}
