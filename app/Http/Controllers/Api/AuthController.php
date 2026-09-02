<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new business owner and their business tenant.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $slug = Str::slug($validated['business_name']);
        if (Business::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $business = Business::create([
            'name' => $validated['business_name'],
            'slug' => $slug,
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan_name' => 'FREE',
            'max_verifications_per_month' => 50,
            'current_month_usage' => 0,
            'period_starts_at' => now(),
            'period_ends_at' => now()->addMonth(),
        ]);

        $user = User::create([
            'business_id' => $business->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'owner',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        AuditLoggerService::log(
            action: 'user.registered',
            resourceType: User::class,
            resourceId: (string) $user->id,
            metadata: ['business_name' => $business->name]
        );

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $token,
            'user' => $user->load('business.subscription'),
        ], 201);
    }

    /**
     * Log in user and generate token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        AuditLoggerService::log(
            action: 'user.login',
            resourceType: User::class,
            resourceId: (string) $user->id
        );

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user->load('business.subscription'),
        ]);
    }

    /**
     * Log out current user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        AuditLoggerService::log(
            action: 'user.logout',
            resourceType: User::class,
            resourceId: (string) $request->user()->id
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    /**
     * Get current user profile with business details.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('business.subscription'),
        ]);
    }
}
