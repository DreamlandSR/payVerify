<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddStaffRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BusinessController extends Controller
{
    /**
     * Display the authenticated user's business profile.
     */
    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business->load(['subscription', 'users']);

        return response()->json([
            'business' => $business,
        ]);
    }

    /**
     * Update business details.
     */
    public function update(UpdateBusinessRequest $request): JsonResponse
    {
        $business = $request->user()->business;

        $business->update($request->validated());

        AuditLoggerService::log(
            action: 'business.updated',
            resourceType: get_class($business),
            resourceId: (string) $business->id,
            metadata: $request->validated()
        );

        return response()->json([
            'message' => 'Business details updated successfully.',
            'business' => $business->fresh(['subscription']),
        ]);
    }

    /**
     * List all users associated with the business tenant.
     */
    public function users(Request $request): JsonResponse
    {
        $users = User::where('business_id', $request->user()->business_id)->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Add a staff user to the business.
     */
    public function addStaff(AddStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        $staff = User::create([
            'business_id' => $currentUser->business_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'staff',
        ]);

        AuditLoggerService::log(
            action: 'staff.added',
            resourceType: User::class,
            resourceId: (string) $staff->id,
            metadata: ['email' => $staff->email]
        );

        return response()->json([
            'message' => 'Staff member added successfully.',
            'staff' => $staff,
        ], 201);
    }
}
