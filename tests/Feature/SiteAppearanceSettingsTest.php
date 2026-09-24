<?php

use App\Models\Site;
use Inertia\Testing\AssertableInertia as Assert;

test('sites default to the warm theme and empty footer', function () {
    $site = Site::factory()->create();

    expect($site->theme_key)->toBe('warm')
        ->and($site->footer)->toBe(['text' => '']);

    $this->actingAs($site->user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
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
