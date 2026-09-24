<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];
        $name = $this->input('name');
        $footer = $this->input('footer');

        if (is_string($name)) {
            $data['name'] = preg_replace('/^\s+|\s+$/u', '', $name);
        }

        if (is_array($footer) && array_key_exists('text', $footer) && ($footer['text'] === null || is_string($footer['text']))) {
            $text = $footer['text'] ?? '';
            $footer['text'] = preg_replace('/^\s+|\s+$/u', '', $text);
            $data['footer'] = $footer;
        }

        $this->merge($data);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'theme_key' => ['sometimes', 'required', 'string', 'in:warm,clean,bold'],
            'footer' => ['sometimes', 'required', 'array:text', 'required_array_keys:text'],
            'footer.text' => ['string', 'not_regex:/[\r\n\x{0085}\x{2028}\x{2029}]/u'],
        ];
    }
}
