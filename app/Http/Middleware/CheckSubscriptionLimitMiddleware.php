<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->business_id) {
            $subscription = Subscription::where('business_id', $user->business_id)->first();

            if ($subscription && $subscription->current_month_usage >= $subscription->max_verifications_per_month) {
                return response()->json([
                    'message' => "Subscription monthly verification limit reached ({$subscription->current_month_usage}/{$subscription->max_verifications_per_month}). Please upgrade your plan to continue.",
                    'error_code' => 'SUBSCRIPTION_LIMIT_EXCEEDED',
                    'current_usage' => $subscription->current_month_usage,
                    'limit' => $subscription->max_verifications_per_month,
                ], 403);
            }
        }

        return $next($request);
    }
}
