<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel 12 removed withoutCookies(). Provide a shim that also
     * clears the web/session guard so Sanctum's fallback guard check
     * returns null and auth:sanctum middleware returns 401.
     */
    public function withoutCookies(): static
    {
        // Sanctum's guard falls back to the 'web' guard. Clear it so
        // the next request is truly unauthenticated.
        foreach ((array) config('sanctum.guard', 'web') as $guard) {
            $this->app['auth']->guard($guard)->forgetUser();
        }

        $this->app['auth']->guard('sanctum')->forgetUser();

        return $this;
    }
}
