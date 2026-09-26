<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderSitePagesRequest;
use App\Http\Requests\SitePageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SitePageController extends Controller
{
    public function store(SitePageRequest $request, int $site): RedirectResponse
    {
        DB::transaction(function () use ($request, $site): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();

            $ownedSite->pages()->create([
                'name' => $request->validated('name'),
                'position' => ($ownedSite->pages()->max('position') ?? -1) + 1,
                'is_home' => false,
            ]);
        });

        return to_route('sites.show', $site);
    }

    public function update(SitePageRequest $request, int $site, int $page): RedirectResponse
    {
        DB::transaction(function () use ($request, $site, $page): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();
            $ownedSite->editorPage($page)->update($request->validated());
        });

        return to_route('sites.show', $site);
    }

    public function order(OrderSitePagesRequest $request, int $site): RedirectResponse
    {
        DB::transaction(function () use ($request, $site): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();
            $current = $ownedSite->pages()->orderBy('position')->orderBy('id')->pluck('id')->all();
            $expected = $request->validated('expected_order');
            $desired = $request->validated('order');
            if ($expected !== $current || count($desired) !== count($current) || array_diff($desired, $current) !== []) {
                throw ValidationException::withMessages(['order' => 'The pages changed since you loaded them. Refresh and try again.']);
            }
            foreach ($desired as $position => $pageId) {
                $ownedSite->pages()->whereKey($pageId)->update(['position' => $position]);
            }
        });

        return to_route('sites.show', $site);
    }

    public function destroy(Request $request, int $site, int $page): RedirectResponse
    {
        DB::transaction(function () use ($request, $site, $page): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();
            $ownedPage = $ownedSite->editorPage($page);
            if ($ownedPage->is_home) {
                throw ValidationException::withMessages(['page' => 'The Home page cannot be deleted.']);
            }
            $ownedPage->delete();
            foreach ($ownedSite->pages()->orderBy('position')->orderBy('id')->get() as $position => $remaining) {
                $remaining->update(['position' => $position]);
            }
        });

        return to_route('sites.show', $site);
    }
}
