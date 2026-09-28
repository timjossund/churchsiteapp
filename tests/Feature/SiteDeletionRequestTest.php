<?php

use App\Actions\CancelSiteBillingCommitments;
use App\Actions\FinalizeSiteDeletion;
use App\Models\Site;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\Exception\ApiConnectionException;

test('site deletion requires a verified owner before validating confirmation', function () {
    $site = Site::factory()->create();
    $url = route('sites.destroy', $site);
    $this->delete($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->delete($url)->assertNotFound();
    $site->user->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($site->user)->delete($url, ['name' => $site->name])->assertRedirect(route('verification.notice'));
    expect($site->fresh()->deletion_requested_at)->toBeNull();
});

test('invalid site deletion names preserve the site and report a name error', function (mixed $name) {
    $site = Site::factory()->create(['name' => 'Grace Church']);
    $this->actingAs($site->user)->from(route('sites.show', $site))->delete(route('sites.destroy', $site), ['name' => $name])
        ->assertRedirect(route('sites.show', $site))->assertSessionHasErrors('name');
    expect($site->fresh()->deletion_requested_at)->toBeNull()->and($site->pages()->count())->toBe(1);
})->with(['wrong', 'grace church', '', null, ['array']]);

test('an owner can delete a free site with trimmed confirmation and see completion', function () {
    $site = Site::factory()->create(['name' => ' Grace Church ']);
    $user = $site->user;
    $other = Site::factory()->for($user)->create();
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/photo.png", 'mime_type' => 'image/png']);
    $disk = Storage::fake('s3');
    $disk->put($asset->storage_key, 'image');
    $this->actingAs($user)->delete(route('sites.destroy', $site), ['name' => ' Grace Church '])
        ->assertRedirect(route('dashboard'))->assertSessionHas('site_deletion_status', 'completed');
    expect($site->fresh())->toBeNull()->and($other->fresh())->not->toBeNull()->and($user->fresh())->not->toBeNull();
    $disk->assertMissing($asset->storage_key);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('deletionStatus', 'completed')->has('sites', 1)->where('sites.0.id', $other->id));
});

test('paid site deletion acknowledges pending cleanup and repeats without changing the request time', function () {
    $this->freezeTime();
    $site = Site::factory()->create();
    $this->mock(CancelSiteBillingCommitments::class)->shouldReceive('handle')->twice()->andReturn([now()->toImmutable()->addMonth(), true]);
    $this->actingAs($site->user)->delete(route('sites.destroy', $site), ['name' => $site->name])
        ->assertRedirect(route('dashboard'))->assertSessionHas('site_deletion_status', 'pending');
    $requestedAt = $site->fresh()->deletion_requested_at;
    $this->travel(1)->hour();
    $this->delete(route('sites.destroy', $site), ['name' => $site->name])->assertRedirect(route('dashboard'));
    expect($site->fresh()->deletion_requested_at->equalTo($requestedAt))->toBeTrue();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('deletionStatus', 'pending')->where('sites.0.deletion_requested_at', $requestedAt->toJSON()));
    $this->get(route('sites.show', $site))->assertNotFound();
});

test('a billing outage still accepts deletion with pending feedback', function () {
    $site = Site::factory()->create();
    $this->mock(CancelSiteBillingCommitments::class)->shouldReceive('handle')->once()->andThrow(new ApiConnectionException('outage'));
    $this->actingAs($site->user)->delete(route('sites.destroy', $site), ['name' => $site->name])
        ->assertRedirect(route('dashboard'))->assertSessionHas('site_deletion_status', 'pending');
    expect($site->fresh()->deletion_requested_at)->not->toBeNull()->and($site->pages()->count())->toBe(0);
});

test('failed file cleanup never reverses an accepted deletion request', function () {
    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/photo.png", 'mime_type' => 'image/png']);
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $disk->shouldReceive('exists')->with($asset->storage_key)->andReturnTrue();
    $disk->shouldReceive('delete')->with($asset->storage_key)->andReturnFalse();
    $this->actingAs($site->user)->delete(route('sites.destroy', $site), ['name' => $site->name])
        ->assertRedirect(route('dashboard'))->assertSessionHas('site_deletion_status', 'pending');
    expect($site->fresh()->deletion_requested_at)->not->toBeNull()->and($asset->fresh())->not->toBeNull();
});

test('unexpected cleanup failures propagate after the offline request has committed', function () {
    $site = Site::factory()->create();
    $this->withoutExceptionHandling();
    $this->mock(FinalizeSiteDeletion::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Unexpected cleanup failure'));
    try {
        $this->actingAs($site->user)->delete(route('sites.destroy', $site), ['name' => $site->name]);
        $this->fail('Unexpected failures must remain visible');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Unexpected cleanup failure')
            ->and($site->fresh()->deletion_requested_at)->not->toBeNull()->and($site->pages()->count())->toBe(0);
    }
});
