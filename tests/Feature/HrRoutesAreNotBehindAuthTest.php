<?php

namespace Tests\Feature;

use App\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The starter ships Fortify, and `routes/web.php` has a `['auth', 'verified']` group.
 * An HrHire route that lands inside it dies at a login screen during the demo, so this
 * is asserted structurally rather than left to a reviewer noticing the `require` moved.
 */
class HrRoutesAreNotBehindAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_hr_route_carries_auth_or_verified_middleware(): void
    {
        $hrRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'hr.'));

        $this->assertGreaterThan(0, $hrRoutes->count(), 'No hr.* routes are registered at all.');

        foreach ($hrRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertNotContains('auth', $middleware, "Route [{$route->getName()}] is behind auth.");
            $this->assertNotContains('verified', $middleware, "Route [{$route->getName()}] is behind verified.");
        }
    }

    public function test_a_guest_can_open_every_hr_page(): void
    {
        $candidate = Candidate::factory()->create();

        $this->get('/hr/jobs')->assertOk();
        $this->get('/hr/candidates/create')->assertOk();
        $this->get('/hr/candidates/'.$candidate->id)->assertOk();
    }
}
