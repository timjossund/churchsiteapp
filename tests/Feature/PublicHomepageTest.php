<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can view the public homepage and reach account entry points', function () {
    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('auth.user', null));
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();
});

test('signed in users can still view the homepage with their dashboard session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('auth.user.id', $user->id));
    $this->get(route('dashboard'))->assertOk();
});
