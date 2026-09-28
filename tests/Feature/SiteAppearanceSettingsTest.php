<?php

use App\Http\Controllers\SiteController;
use App\Http\Requests\SiteSettingsRequest;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteAppearance;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('sites default to the warm theme and empty footer', function () {
    $site = Site::factory()->create();

    expect($site->theme_key)->toBe('warm')
        ->and($site->footer)->toBe(['text' => '']);

    $this->actingAs($site->user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Settings')
            ->where('site.theme_key', 'warm')
            ->where('site.footer', ['text' => '']));
});

test('an owner can save theme and footer settings with trimmed footer text', function () {
    $site = Site::factory()->create();

    $this->actingAs($site->user)
        ->patch(route('sites.update', $site), [
            'theme_key' => 'bold',
            'footer' => ['text' => '  Sunday worship at 10 AM  '],
        ])
        ->assertRedirect(route('sites.show', $site));

    expect($site->refresh()->theme_key)->toBe('bold')
        ->and($site->footer)->toBe(['text' => 'Sunday worship at 10 AM']);

    $this->get(route('sites.show', $site))
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.theme_key', 'bold')
            ->where('site.footer', ['text' => 'Sunday worship at 10 AM']));
});

test('invalid theme and footer updates preserve all saved settings', function (array $input, string $error) {
    $site = Site::factory()->create([
        'name' => 'Saved Church',
        'theme_key' => 'warm',
        'footer' => ['text' => 'Saved footer'],
    ]);

    $this->actingAs($site->user)
        ->patch(route('sites.update', $site), $input)
        ->assertSessionHasErrors($error);

    expect($site->refresh()->name)->toBe('Saved Church')
        ->and($site->theme_key)->toBe('warm')
        ->and($site->footer)->toBe(['text' => 'Saved footer']);
})->with([
    'unknown theme' => [['theme_key' => 'sepia', 'footer' => ['text' => 'Changed']], 'theme_key'],
    'non-array footer' => [['theme_key' => 'clean', 'footer' => 'Changed'], 'footer'],
    'missing footer text' => [['theme_key' => 'clean', 'footer' => []], 'footer'],
    'extra footer key' => [['theme_key' => 'clean', 'footer' => ['text' => 'Changed', 'url' => 'https://example.test']], 'footer'],
    'multiline footer' => [['theme_key' => 'clean', 'footer' => ['text' => "First line\nSecond line"]], 'footer.text'],
]);

test('an empty footer text can be saved to clear the footer', function () {
    $site = Site::factory()->create(['footer' => ['text' => 'Saved footer']]);

    $this->actingAs($site->user)
        ->patch(route('sites.update', $site), ['footer' => ['text' => '']])
        ->assertRedirect(route('sites.show', $site));

    expect($site->refresh()->footer)->toBe(['text' => '']);
});

test('a slug claimed after validation returns a field error instead of a server error', function () {
    $site = Site::factory()->create(['slug' => null]);
    Site::factory()->create(['slug' => 'grace-church']);
    $request = Mockery::mock(SiteSettingsRequest::class);
    $request->shouldReceive('validated')->once()->andReturn(['slug' => 'grace-church']);
    $request->shouldReceive('user')->once()->andReturn($site->user);

    try {
        app(SiteController::class)->update($request, $site->id);
        test()->fail('Expected the late slug collision to become a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('slug');
    }

    expect($site->fresh()->slug)->toBeNull();
});

test('appearance overrides save normalize reload and preserve on unrelated updates', function () {
    $site = Site::factory()->create();
    $appearance = ['font_pairing' => 'modern', 'accent_color' => '#abcdef', 'button_shape' => 'pill'];
    $this->actingAs($site->user)->patch(route('sites.update', $site), [
        'appearance' => [...$appearance, 'accent_color' => ' #ABCDEF '],
    ])->assertRedirect();
    expect($site->fresh()->appearance)->toBe($appearance);
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page->where('site.appearance', $appearance));
    $this->patch(route('sites.update', $site), ['theme_key' => 'clean'])->assertRedirect();
    expect($site->fresh()->appearance)->toBe($appearance);
    $this->patch(route('sites.update', $site), ['appearance' => [...$appearance, 'accent_color' => '  ']])->assertRedirect();
    expect($site->fresh()->appearance['accent_color'])->toBeNull();
    $this->patch(route('sites.update', $site), ['appearance' => null])->assertRedirect();
    expect($site->fresh()->appearance)->toBeNull();
});

test('appearance rejects invalid data without changing saved settings', function (mixed $appearance, string $error) {
    $site = Site::factory()->create();
    $this->actingAs($site->user)->patch(route('sites.update', $site), ['appearance' => $appearance])
        ->assertSessionHasErrors($error);
    expect($site->fresh()->appearance)->toBeNull();
})->with([
    ['invalid', 'appearance'],
    [['font_pairing' => 'unknown'], 'appearance.font_pairing'],
    [['font_pairing' => ['modern']], 'appearance.font_pairing'],
    [['button_shape' => 'triangle'], 'appearance.button_shape'],
    [['accent_color' => 'red; background: url(https://example.test)'], 'appearance.accent_color'],
    [['accent_color' => '#12345678'], 'appearance.accent_color'],
    [['accent_color' => ['#123456']], 'appearance.accent_color'],
    [['custom_css' => 'body {}'], 'appearance'],
]);

test('appearance is owner scoped and changes only in the next publication', function () {
    $site = Site::factory()->create(['slug' => 'appearance-church']);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $snapshot = $site->fresh()->published_snapshot;
    expect($snapshot['site'])->not->toHaveKey('appearance');
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page->where('site.has_unpublished_changes', false));
    $appearance = ['font_pairing' => 'classy', 'accent_color' => '#123456', 'button_shape' => 'square'];
    $this->patch(route('sites.update', $site), ['appearance' => $appearance])->assertRedirect();
    expect($site->fresh()->published_snapshot)->toBe($snapshot);
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page->where('site.has_unpublished_changes', true));
    $this->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))
        ->assertInertia(fn (Assert $page) => $page->where('site.appearance', $appearance));
    $this->post(route('sites.publish', $site))->assertRedirect();
    expect($site->fresh()->published_snapshot['site']['appearance'])->toBe($appearance);
    $this->get(route('sites.published.show', $site->slug))->assertOk();
    $this->actingAs(User::factory()->create());
    $this->get(route('sites.show', $site))->assertNotFound();
    $this->patch(route('sites.update', $site), ['appearance' => null])->assertNotFound();
    expect($site->fresh()->appearance)->toBe($appearance);
});

test('font pairing and button shape render from publication on every page', function (string $pairing, string $shape) {
    $site = Site::factory()->create(['slug' => 'styled-site']);
    $site->pages()->create(['name' => 'Visit', 'path' => 'visit', 'position' => 1, 'is_home' => false]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $this->patch(route('sites.update', $site), ['appearance' => [
        'font_pairing' => $pairing, 'button_shape' => $shape, 'accent_color' => null,
    ]])->assertRedirect();
    $this->get(route('sites.published.show', $site->slug))->assertSee('data-font-pairing="theme"', false);
    $this->post(route('sites.publish', $site))->assertRedirect();
    foreach ([route('sites.published.show', $site->slug), route('sites.published.pages.show', [$site->slug, 'visit'])] as $url) {
        $this->get($url)->assertOk()
            ->assertSee('data-font-pairing="'.$pairing.'"', false)
            ->assertSee('data-button-shape="'.$shape.'"', false);
    }
})->with([
    ['traditional', 'rounded'], ['modern', 'pill'], ['editorial', 'square'], ['classy', 'theme'],
]);

test('malformed legacy appearance values safely use theme defaults', function () {
    $site = Site::factory()->create(['slug' => 'legacy-appearance']);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $snapshot = $site->fresh()->published_snapshot;
    $snapshot['site']['appearance'] = ['font_pairing' => ['unsafe'], 'button_shape' => 'unknown', 'accent_color' => 'url(evil)'];
    $site->update(['published_snapshot' => $snapshot]);
    $this->get(route('sites.published.show', $site->slug))->assertOk()
        ->assertSee('data-font-pairing="theme"', false)
        ->assertSee('data-button-shape="theme"', false);
});

test('preview and publication share readable derived colors without changing the saved accent', function () {
    $site = Site::factory()->create(['slug' => 'accent-test', 'theme_key' => 'warm']);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $this->patch(route('sites.update', $site), ['appearance' => ['accent_color' => '#ffffff']])->assertRedirect();
    $colors = SiteAppearance::colors($site->fresh()->appearance, 'warm');
    expect($colors['--site-preview-accent'])->not->toBe('#ffffff');
    expect($site->fresh()->appearance['accent_color'])->toBe('#ffffff');
    $this->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))
        ->assertInertia(fn (Assert $page) => $page->where('site.appearance_colors', $colors));
    $this->get(route('sites.published.show', $site->slug))->assertDontSee('--site-preview-accent:', false);
    $this->post(route('sites.publish', $site))->assertRedirect();
    $this->get(route('sites.published.show', $site->slug))->assertSee('--site-preview-accent:'.$colors['--site-preview-accent'], false);
    $this->patch(route('sites.update', $site), ['appearance' => ['accent_color' => null]])->assertRedirect();
    $this->post(route('sites.publish', $site))->assertRedirect();
    $this->get(route('sites.published.show', $site->slug))->assertDontSee('--site-preview-accent:', false);
});
