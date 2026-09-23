<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderSiteBlocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->sites()->whereKey($this->route('site'))->firstOrFail();

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_order' => ['present', 'array', 'list'],
            'expected_order.*' => ['integer', 'distinct'],
            'order' => ['present', 'array', 'list'],
            'order.*' => ['integer', 'distinct'],
        ];
    }
}
