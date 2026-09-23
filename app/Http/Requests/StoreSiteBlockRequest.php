<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSiteBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->sites()->whereKey($this->route('site'))->exists()) {
            abort(404);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['about', 'plain_text', 'heading_text', 'hero', 'service_times', 'contact'])],
            'site_id' => ['prohibited'],
            'position' => ['prohibited'],
            'content' => ['prohibited'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['type', '_token', '_method'], true)) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }
        }];
    }
}
