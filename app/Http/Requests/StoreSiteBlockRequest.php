<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type' => ['required', 'string', Rule::in(['about', 'plain_text', 'heading_text'])],
        ];
    }
}
