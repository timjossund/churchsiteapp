<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSiteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->user()->sites()->findOrFail($this->route('site'));
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['name' => 'required|string|max:255'];
    }
}
