<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class SitePageRequest extends SiteNameRequest
{
    public function authorize(): bool
    {
        $site = $this->user()->sites()->whereKey($this->route('site'))->firstOrFail();
        if ($this->route('page') !== null) {
            $site->editorPage($this->route('page'));
        }

        return true;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['name', '_token', '_method'], true)) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }
        }];
    }
}
