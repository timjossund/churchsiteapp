<?php

use App\Models\Site;
use App\Models\User;

test('a user owns multiple sites and each site has one owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $first = $owner->sites()->create(['name' => 'First Church']);
    $second = $owner->sites()->create(['name' => 'Second Church']);
    $otherSite = $other->sites()->create(['name' => 'Other Church']);

    expect($owner->sites()->pluck('id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
    expect($other->sites()->pluck('id')->all())->toBe([$otherSite->id]);
    expect($first->user->is($owner))->toBeTrue();
    expect($second->user->is($owner))->toBeTrue();
});

test('deleting an owner removes their sites without affecting other owners', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $site = Site::factory()->for($owner)->create();
    $otherSite = Site::factory()->for($other)->create();

    $owner->delete();

    $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    $this->assertDatabaseHas('sites', ['id' => $otherSite->id]);
});
