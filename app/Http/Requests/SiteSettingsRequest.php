<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SiteSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];
        $name = $this->input('name');
        $footer = $this->input('footer');
        $slug = $this->input('slug');
        $title = $this->input('seo_title');
        $description = $this->input('seo_description');

        if (is_string($name)) {
            $data['name'] = preg_replace('/^\s+|\s+$/u', '', $name);
        }

        if (is_array($footer) && array_key_exists('text', $footer) && ($footer['text'] === null || is_string($footer['text']))) {
            $text = $footer['text'] ?? '';
            $footer['text'] = preg_replace('/^\s+|\s+$/u', '', $text);
            $data['footer'] = $footer;
        }

        if (is_string($slug)) {
            $normalizedSlug = strtolower(trim($slug));
            $data['slug'] = $normalizedSlug === '' ? null : $normalizedSlug;
        }

        if (is_string($title)) {
            $data['seo_title'] = trim($title) === '' ? null : trim($title);
        }

        if (is_string($description)) {
            $data['seo_description'] = trim($description) === '' ? null : trim($description);
        }

        $this->merge($data);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $site = $this->user()->sites()->whereKey($this->route('site'))->firstOrFail();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'theme_key' => ['sometimes', 'required', 'string', 'in:warm,clean,bold'],
            'footer' => ['sometimes', 'required', 'array:text', 'required_array_keys:text'],
            'footer.text' => ['string', 'not_regex:/[\r\n\x{0085}\x{2028}\x{2029}]/u'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('sites', 'slug')->ignore($site->getKey())],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $slug = $this->input('slug');
            if (array_key_exists('slug', $this->all())) {
                $site = $this->user()->sites()->whereKey($this->route('site'))->firstOrFail();
                if ($site->published_at !== null && $slug !== $site->slug) {
                    $validator->errors()->add('slug', 'The address cannot change after the first publication.');
                }
            }

        }];
    }
}
