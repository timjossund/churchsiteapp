<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class StoreSiteFaviconRequest extends StoreSiteImageRequest
{
    public function authorize(): bool
    {
        $this->user()->sites()->whereNull('deletion_requested_at')->findOrFail($this->route('site'));

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:png', 'mimetypes:image/png', 'extensions:png', 'dimensions:ratio=1', 'max:5120'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['image', '_token', '_method'], true)) {
                    $validator->errors()->add('image', 'Only a PNG favicon file is allowed.');
                }
            }
        }];
    }
}
