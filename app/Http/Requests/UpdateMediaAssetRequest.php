<?php

namespace App\Http\Requests;

use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMediaAssetRequest extends FormRequest
{
    private ?MediaAsset $ownedAsset = null;

    public function authorize(): bool
    {
        $site = $this->user()->sites()->whereKey($this->route('site'))->firstOrFail();
        $this->ownedAsset = $site->mediaAssets()->whereKey($this->route('mediaAsset'))->firstOrFail();

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'alt_text' => ['present', 'nullable', 'string'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['alt_text', '_token', '_method'], true)) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }
        }];
    }

    public function ownedAsset(): MediaAsset
    {
        return $this->ownedAsset ?? throw new \LogicException('Media ownership was not checked.');
    }
}
