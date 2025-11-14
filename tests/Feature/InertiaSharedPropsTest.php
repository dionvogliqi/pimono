<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_share_includes_csrf_token(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('database.sqlite'),
            'session.driver' => 'array',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('transfers'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('csrfToken', fn ($token) => is_string($token) && strlen($token) > 0)
                ->etc());
    }
}
