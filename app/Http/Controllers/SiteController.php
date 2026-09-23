<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteNameRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'sites' => $request->user()->sites()
                ->orderByDesc('id')
                ->get(['id', 'name']),
        ]);
    }

    public function store(SiteNameRequest $request): RedirectResponse
    {
        $site = $request->user()->sites()->create($request->validated());

        return to_route('sites.show', $site);
    }

    public function show(Request $request, int $site): Response
    {
        $ownedSite = $request->user()->sites()->findOrFail($site);

        return Inertia::render('Sites/Show', [
            'site' => $ownedSite->only('id', 'name'),
        ]);
    }

    public function update(SiteNameRequest $request, int $site): RedirectResponse
    {
        $ownedSite = $request->user()->sites()->findOrFail($site);
        $ownedSite->update($request->validated());

        return to_route('sites.show', $ownedSite);
    }
}
