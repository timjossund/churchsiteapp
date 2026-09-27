<?php

namespace App\Http\Requests;

use App\Support\CustomerPagePath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SitePageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->sites()->whereKey($this->route('site'))->firstOrFail()->editorPage($this->route('page'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['path', 'seo_title', 'seo_description'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = preg_replace('/^\s+|\s+$/u', '', $value) ?? $value;
                $this->merge([$field => $value === '' ? null : ($field === 'path' ? strtolower($value) : $value)]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $page = $this->user()->sites()->whereKey($this->route('site'))->firstOrFail()->editorPage($this->route('page'));

        return [
            'path' => $page->is_home ? ['prohibited'] : [
                'sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(CustomerPagePath::RESERVED),
                Rule::unique('site_pages', 'path')->where('site_id', $page->site_id)->ignore($page->id),
            ],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['path', 'seo_title', 'seo_description', '_token', '_method'], true)) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }
        }];
    }
}
