<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSiteImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->user()->sites()->whereKey($this->route('site'))->firstOrFail();
        $blockId = $this->route('block');

        if ($blockId === null) {
            return true;
        }

        $block = $site->blocks()->whereKey($blockId)->firstOrFail();

        return in_array($block->type, ['image', 'text_image'], true);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'alt_text' => ['nullable', 'string'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['image', 'alt_text', '_token', '_method'], true)) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }
        }];
    }
}
