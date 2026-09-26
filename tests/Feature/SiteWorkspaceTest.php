<?php

use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard lists only the signed-in user sites', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $first = Site::factory()->for($owner)->create(['name' => 'First Church']);
    $second = Site::factory()->for($owner)->create(['name' => 'Second Church']);
    Site::factory()->for($other)->create(['name' => 'Private Church']);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('sites', 2)
            ->where('sites.0.id', $second->id)
            ->where('sites.1.id', $first->id)
            ->missing('sites.2'));
});

test('a new account sees an empty site list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('sites', 0));
});

test('a user can create multiple blank sites and open the new site', function () {
    $owner = User::factory()->create();

    $other = User::factory()->create();
    $firstResponse = $this->actingAs($owner)
        ->post(route('sites.store'), ['name' => '  First Church  ', 'user_id' => $other->id]);
    $first = $owner->sites()->firstOrFail();
    $firstResponse->assertRedirect(route('sites.show', $first));
    $this->assertDatabaseHas('sites', ['id' => $first->id, 'user_id' => $owner->id, 'name' => 'First Church']);

    $secondResponse = $this->post(route('sites.store'), ['name' => 'Second Church']);
    $second = $owner->sites()->orderByDesc('id')->firstOrFail();
    $secondResponse->assertRedirect(route('sites.show', $second));
    $this->assertDatabaseHas('sites', ['id' => $second->id, 'user_id' => $owner->id]);

    expect($owner->sites()->pluck('name')->all())->toBe(['First Church', 'Second Church']);

    $this->get(route('sites.show', $first))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Settings')
            ->where('site.id', $first->id)
            ->where('site.name', 'First Church'));
});

test('invalid names do not create a site', function ($name) {
    $this->actingAs(User::factory()->create())
        ->post(route('sites.store'), ['name' => $name])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('sites', 0);
})->with(['', '    ', "\u{2003}", str_repeat('a', 256)]);

test('guests cannot create or open sites and owners cannot open other sites', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.store'), ['name' => 'Guest Church'])
        ->assertRedirect(route('login'));
    $this->get(route('sites.show', $site))
        ->assertRedirect(route('login'));
    $this->patch(route('sites.update', $site), ['name' => 'Changed'])
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('sites.show', $site))
        ->assertNotFound();
    $this->patch(route('sites.update', $site), ['name' => 'Changed'])
        ->assertNotFound();
    $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => $site->name]);
});

test('an owner can rename a site and see the new name in the dashboard', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->for($owner)->create(['name' => 'Old Name']);

    $this->actingAs($owner)
        ->patch(route('sites.update', $site), ['name' => '  New Name  ', 'user_id' => User::factory()->create()->id])
        ->assertRedirect(route('sites.show', $site));

    $this->assertDatabaseHas('sites', ['id' => $site->id, 'user_id' => $owner->id, 'name' => 'New Name']);
    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('sites.0.name', 'New Name'));
});

test('an invalid rename preserves the saved name', function ($name) {
    $site = Site::factory()->create(['name' => 'Saved Name']);

    $this->actingAs($site->user)
        ->patch(route('sites.update', $site), ['name' => $name])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'Saved Name']);
})->with(['', '  ', "\u{2003}", str_repeat('a', 256)]);

test('missing and malformed site IDs are not found', function () {
    $this->actingAs(User::factory()->create())
        ->get('/sites/nope')
        ->assertNotFound();
    $this->patch('/sites/nope', ['name' => 'Changed'])
        ->assertNotFound();
    $this->get('/sites/999999')
        ->assertNotFound();
});
