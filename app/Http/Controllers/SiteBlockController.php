<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderSiteBlocksRequest;
use App\Http\Requests\StoreSiteBlockRequest;
use App\Http\Requests\UpdateSiteBlockRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class SiteBlockController extends Controller
{
    public function store(StoreSiteBlockRequest $request, int $site): RedirectResponse
    {
        DB::transaction(function () use ($request, $site): void {
            $ownedSite = $request->user()->sites()->lockForUpdate()->findOrFail($site);
            $ownedPage = $ownedSite->editorPage($request->route('page'));
            $type = $request->validated('type');

            $ownedPage->blocks()->create([
                'type' => $type,
                'position' => ($ownedPage->blocks()->max('position') ?? -1) + 1,
                'content' => match ($type) {
                    'about', 'heading_text' => ['heading' => '', 'body' => ''],
                    'plain_text' => ['body' => ''],
                    'hero' => [
                        'heading' => '', 'body' => '', 'button_label' => '',
                        'link_type' => 'none', 'target_block_id' => null, 'external_url' => '',
                    ],
                    'service_times' => ['heading' => '', 'entries' => []],
                    'contact' => ['heading' => '', 'email' => '', 'phone' => ''],
                    'image' => ['media_asset_id' => null],
                    'text_image' => ['heading' => '', 'body' => '', 'media_asset_id' => null],
                    'video' => ['url' => ''],
                    default => throw new LogicException('Unsupported validated block type.'),
                },
            ]);
        });

        return $request->route('page') === null
            ? to_route('sites.show', $site)
            : to_route('sites.pages.show', [$site, $request->route('page')]);
    }

    public function update(UpdateSiteBlockRequest $request, int $site): RedirectResponse
    {
        $block = (int) $request->route('block');
        DB::transaction(function () use ($request, $site, $block): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();
            $ownedPage = $ownedSite->editorPage($request->route('page'));
            $ownedBlock = $ownedPage->blocks()->whereKey($block)->firstOrFail();
            $content = $request->validated('content');

            if ($ownedBlock->type === 'hero' && $content['link_type'] === 'section') {
                $content['target_block_id'] = (int) $content['target_block_id'];

                if (! $ownedPage->blocks()->whereKey($content['target_block_id'])->exists()) {
                    throw ValidationException::withMessages([
                        'content.target_block_id' => 'This section is no longer available. Choose another block.',
                    ]);
                }
            }

            $ownedPage->blocks()->whereKey($block)->firstOrFail()->update([
                'content' => $content,
            ]);
        });

        return $request->route('page') === null
            ? to_route('sites.show', $site)
            : to_route('sites.pages.show', [$site, $request->route('page')]);
    }

    public function destroy(Request $request, int $site): RedirectResponse
    {
        $block = (int) $request->route('block');
        DB::transaction(function () use ($request, $site, $block): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();
            $ownedPage = $ownedSite->editorPage($request->route('page'));
            $ownedPage->blocks()->whereKey($block)->firstOrFail()->delete();

            foreach ($ownedPage->blocks()->where('type', 'hero')->get() as $hero) {
                if ($hero->content['link_type'] !== 'section' || (int) $hero->content['target_block_id'] !== $block) {
                    continue;
                }

                $content = $hero->content;
                $content['button_label'] = '';
                $content['link_type'] = 'none';
                $content['target_block_id'] = null;
                $content['external_url'] = '';
                $hero->update(['content' => $content]);
            }

            $remaining = $ownedPage->blocks()->orderBy('position')->orderBy('id')->get(['id']);
            foreach ($remaining as $position => $remainingBlock) {
                $remainingBlock->update(['position' => $position]);
            }
        });

        return $request->route('page') === null
            ? to_route('sites.show', $site)
            : to_route('sites.pages.show', [$site, $request->route('page')]);
    }

    public function order(OrderSiteBlocksRequest $request, int $site): RedirectResponse
    {
        DB::transaction(function () use ($request, $site): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();
            $ownedPage = $ownedSite->editorPage($request->route('page'));
            $current = $ownedPage->blocks()->orderBy('position')->orderBy('id')->pluck('id')->all();
            $expected = $request->validated('expected_order');
            $desired = $request->validated('order');

            $sameMembers = count($desired) === count($current)
                && count(array_diff($desired, $current)) === 0;

            if ($expected !== $current || ! $sameMembers) {
                throw ValidationException::withMessages([
                    'order' => 'The page changed since you loaded it. Refresh and try again.',
                ]);
            }

            foreach ($desired as $position => $blockId) {
                $ownedPage->blocks()->whereKey($blockId)->update(['position' => $position]);
            }
        });

        return $request->route('page') === null
            ? to_route('sites.show', $site)
            : to_route('sites.pages.show', [$site, $request->route('page')]);
    }
}
