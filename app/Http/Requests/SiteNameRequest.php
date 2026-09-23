<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteNameRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => preg_replace('/^\s+|\s+$/u', '', $name)]);
        }
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}
