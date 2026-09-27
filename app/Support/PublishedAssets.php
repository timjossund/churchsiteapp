<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

final class PublishedAssets
{
    /** @return array{styles: list<string>, scripts: list<string>, files: list<string>} */
    public function manifest(): array
    {
        $manifest = json_decode(File::get(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $files = [];
        $styles = [];
        $scripts = [];
        $visit = function (string $key) use (&$visit, &$files, &$styles, $manifest): void {
            $entry = $manifest[$key];
            $file = $entry['file'];
            if (in_array($file, $files, true)) {
                return;
            }
            $files[] = $file;
            if (str_ends_with($file, '.css')) {
                $styles[] = '/build/'.$file;
            }
            foreach ($entry['css'] ?? [] as $css) {
                $files[] = $css;
                $styles[] = '/build/'.$css;
            }
            foreach ($entry['imports'] ?? [] as $import) {
                $visit($import);
            }
        };
        $visit('resources/css/app.css');
        $visit('resources/js/published.ts');
        $scripts[] = '/build/'.$manifest['resources/js/published.ts']['file'];
        // The installed font plugin emits a separate stylesheet and font entries.
        foreach ($manifest as $key => $entry) {
            if (preg_match('/\.(?:woff2?|ttf)$/', $entry['file']) === 1 || str_starts_with($key, '_fonts-')) {
                $visit($key);
            }
        }

        return ['styles' => array_values(array_unique($styles)), 'scripts' => $scripts, 'files' => array_values(array_unique($files))];
    }

    public function response(string $path): Response
    {
        abort_unless(preg_match('#\A/build/(assets/[a-zA-Z0-9_-]+\.(?:css|js|woff2?|ttf))\z#', $path, $matches) === 1, 404);
        abort_unless(in_array($matches[1], $this->manifest()['files'], true), 404);
        $file = public_path('build/'.$matches[1]);
        abort_unless(is_file($file), 404);
        $type = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'css' => 'text/css', 'js' => 'application/javascript', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
            default => abort(404),
        };

        return response(File::get($file), 200, ['Content-Type' => $type]);
    }
}
