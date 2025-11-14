<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_demo_users_and_transactions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'charlie@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'demo@example.com']);

        $this->assertTrue(User::query()->count() >= 4, 'Expected at least 4 demo users');
        $this->assertTrue(Transaction::query()->count() > 0, 'Expected some transactions to be seeded');
    }
}
