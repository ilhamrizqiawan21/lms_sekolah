<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * AuthenticateSession pins the session to the signed-in user's password
     * hash. A real user switch goes through the login request, which re-pins
     * the hash; actingAs() swaps the user on the same test session, so drop
     * the pinned hash when the actor changes.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $guardName = $guard ?? config('auth.defaults.guard');
        $current = $this->app['auth']->guard($guardName)->user();

        if ($current && $current->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            $this->app['session.store']->forget('password_hash_'.$guardName);
        }

        return parent::actingAs($user, $guard);
    }
}
