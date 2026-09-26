<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner publishes an atomic snapshot of saved site content and referenced media', function () {
    Storage::fake('s3');
    $site = Site::factory()->create([
        'name' => 'Grace Church',
        'slug' => 'grace-church',
        'theme_key' => 'bold',
        'footer' => ['text' => 'Sunday worship'],
        'seo_title' => 'Grace Church Home',
        'seo_description' => 'Welcome to Grace Church.',
    ]);
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/published-image",
        'mime_type' => 'image/png',
        'alt_text' => 'Congregation gathered',
    ]);
    Storage::disk('s3')->put($asset->storage_key, 'image bytes', ['visibility' => 'private']);
    $site->update([
        'logo_media_asset_id' => $asset->id,
        'social_image_id' => $asset->id,
    ]);
    $site->blocks()->create([
        'type' => 'image',
        'position' => 0,
        'content' => ['media_asset_id' => $asset->id],
    ]);
    $site->blocks()->create([
        'type' => 'contact',
        'position' => 1,
        'content' => ['heading' => 'Visit us', 'email' => 'hello@example.test', 'phone' => '+1 555 123 4567'],
    ]);

    $this->actingAs($site->user)
        ->post(route('sites.publish', $site))
        ->assertRedirect(route('sites.show', $site));

    $published = $site->fresh();
    expect($published->published_at)->not->toBeNull()
        ->and($published->published_snapshot['version'])->toBe(1)
        ->and($published->published_snapshot['site'])->toBe([
            'name' => 'Grace Church',
            'slug' => 'grace-church',
            'theme_key' => 'bold',
            'footer' => ['text' => 'Sunday worship'],
            'seo_title' => 'Grace Church Home',
            'seo_description' => 'Welcome to Grace Church.',
            'logo_media_asset_id' => $asset->id,
            'social_image_id' => $asset->id,
        ])
        ->and($published->published_snapshot['blocks'])->toHaveCount(2)
        ->and($published->published_snapshot['blocks'][0]['id'])->toBe($site->blocks()->first()->id)
        ->and($published->published_snapshot['media'])->toBe([[
            'id' => $asset->id,
            'storage_key' => $asset->storage_key,
            'mime_type' => 'image/png',
            'alt_text' => 'Congregation gathered',
        ]])
        ->and($published->published_snapshot['draft_fingerprint'])
        ->toBe(app(BuildSitePublicationSnapshot::class)->fingerprint(
            collect($published->published_snapshot)->except('draft_fingerprint')->all(),
        ));
});

test('publishing requires a saved address and leaves publication fields empty on failure', function () {
    $site = Site::factory()->create();

    $this->actingAs($site->user)
        ->postJson(route('sites.publish', $site))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    expect($site->fresh()->published_snapshot)->toBeNull()
        ->and($site->published_at)->toBeNull();
});

test('only the owner can publish and the endpoint ignores client snapshot data', function () {
    $site = Site::factory()->create(['slug' => 'grace-church']);
    $this->actingAs(User::factory()->create())
        ->post(route('sites.publish', $site), ['published_snapshot' => ['private' => 'injected']])
        ->assertNotFound();

    $this->actingAs($site->user)
        ->post(route('sites.publish', $site), ['published_snapshot' => ['private' => 'injected']])
        ->assertRedirect(route('sites.show', $site));

    expect($site->fresh()->published_snapshot)->not->toHaveKey('private');
});

test('draft edits leave the published snapshot stable and are reported to the owner', function () {
    $site = Site::factory()->create([
        'name' => 'Grace Church',
        'slug' => 'grace-church',
    ]);
    $block = $site->blocks()->create([
        'type' => 'about',
        'position' => 0,
        'content' => ['heading' => 'Welcome', 'body' => 'Join us.'],
    ]);

    $this->actingAs($site->user)->post(route('sites.publish', $site));
    $originalSnapshot = $site->fresh()->published_snapshot;

    $this->patch(route('sites.update', $site), [
        'name' => 'Grace Community Church',
        'seo_title' => 'A new title',
    ])->assertRedirect(route('sites.show', $site));
    $this->patch(route('sites.blocks.update', [$site, $block]), [
        'content' => ['heading' => 'New heading', 'body' => 'New copy.'],
    ])->assertRedirect(route('sites.show', $site));

    expect($site->fresh()->published_snapshot)->toBe($originalSnapshot);
    $this->get(route('sites.show', $site))
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.has_unpublished_changes', true)
            ->where('site.published_url', route('sites.published.show', ['slug' => 'grace-church'])));
});

test('a failed publication preserves its previous snapshot and timestamp', function () {
    $site = Site::factory()->create([
        'slug' => 'grace-church',
        'published_snapshot' => ['version' => 1, 'site' => ['name' => 'Earlier publication']],
        'published_at' => now()->subDay(),
    ]);
    $oldSnapshot = $site->published_snapshot;
    $oldPublishedAt = $site->published_at;

    Site::updating(fn () => throw new RuntimeException('Simulated publication write failure.'));

    $this->actingAs($site->user)
        ->post(route('sites.publish', $site))
        ->assertServerError();

    expect($site->fresh()->published_snapshot)->toBe($oldSnapshot)
        ->and($site->published_at->equalTo($oldPublishedAt))->toBeTrue();
});
