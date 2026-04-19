<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthGuardAllRoutesTest extends TestCase
{
    public function test_every_api_v1_route_requires_auth_except_allowlist(): void
    {
        $allow = [
            'POST api/v1/login',
            // add here only if a new public endpoint is intentional
        ];

        $unguarded = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            $methods = array_diff($route->methods(), ['HEAD', 'OPTIONS']);
            foreach ($methods as $method) {
                $key = $method . ' ' . $uri;
                if (in_array($key, $allow, true)) {
                    continue;
                }
                $middleware = $route->gatherMiddleware();
                if (!in_array('auth:sanctum', $middleware, true)) {
                    $unguarded[] = $key;
                }
            }
        }

        $this->assertSame([], $unguarded, 'Unguarded v1 routes: ' . implode(', ', $unguarded));
    }
}
