<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_business_and_owner_account(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'business_name' => 'Toko Barokah',
            'name' => 'Budi Santoso',
            'email' => 'budi@barokah.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'business' => [
                        'id',
                        'name',
                        'slug',
                        'subscription',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('businesses', [
            'name' => 'Toko Barokah',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'budi@barokah.com',
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.registered',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $business = Business::create(['name' => 'Store A', 'slug' => 'store-a']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner A',
            'email' => 'owner@a.com',
            'password' => bcrypt('secret123'),
            'role' => 'owner',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'owner@a.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'token', 'user']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@email.com',
            'password' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_get_profile_and_logout(): void
    {
        $business = Business::create(['name' => 'Store B', 'slug' => 'store-b']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner B',
            'email' => 'owner@b.com',
            'password' => bcrypt('secret123'),
            'role' => 'owner',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('user.email', 'owner@b.com');

        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200);
    }
}
