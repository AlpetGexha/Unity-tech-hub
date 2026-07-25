<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->demoUsers() as $demoUser) {
            User::factory()->create($demoUser);
        }
    }

    /**
     * Get the demo accounts used for local development and product demos.
     *
     * @return array<int, array{name: string, email: string}>
     */
    protected function demoUsers(): array
    {
        return [
            [
                'name' => 'Olivia Carter',
                'email' => 'olivia.carter@example.com',
            ],
            [
                'name' => 'Marcus Reed',
                'email' => 'marcus.reed@example.com',
            ],
            [
                'name' => 'Sophia Bennett',
                'email' => 'sophia.bennett@example.com',
            ],
        ];
    }
}
