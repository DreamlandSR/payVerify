<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_staff_to_their_business(): void
    {
        $business = Business::create(['name' => 'Business Alpha', 'slug' => 'business-alpha']);
        $owner = User::create([
            'business_id' => $business->id,
            'name' => 'Alpha Owner',
            'email' => 'owner@alpha.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $token = $owner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/business/users', [
                'name' => 'Alpha Staff',
                'email' => 'staff@alpha.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('staff.role', 'staff')
            ->assertJsonPath('staff.business_id', $business->id);

        $this->assertDatabaseHas('users', [
            'email' => 'staff@alpha.com',
            'business_id' => $business->id,
            'role' => 'staff',
        ]);
    }

    public function test_user_from_business_a_only_sees_business_a_users(): void
    {
        $bizA = Business::create(['name' => 'Business A', 'slug' => 'biz-a']);
        $userA = User::create([
            'business_id' => $bizA->id,
            'name' => 'User A',
            'email' => 'user@a.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $bizB = Business::create(['name' => 'Business B', 'slug' => 'biz-b']);
        $userB = User::create([
            'business_id' => $bizB->id,
            'name' => 'User B',
            'email' => 'user@b.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $tokenA = $userA->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/business/users');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.email', 'user@a.com');
    }
}
