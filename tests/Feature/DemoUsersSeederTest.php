<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_users_are_seeded_with_login_details(): void
    {
        $this->seed(DemoUsersSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', [
            'name' => 'Olivia Carter',
            'email' => 'olivia.carter@example.com',
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'Marcus Reed',
            'email' => 'marcus.reed@example.com',
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'Sophia Bennett',
            'email' => 'sophia.bennett@example.com',
        ]);

        $this->assertSame(3, User::whereNotNull('email_verified_at')->count());
        $this->assertTrue(Hash::check('password', User::query()->firstOrFail()->password));
    }
}
