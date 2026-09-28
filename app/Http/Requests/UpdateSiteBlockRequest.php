<?php

namespace App\Http\Requests;

use App\Models\SiteBlock;
use App\Support\VideoEmbedUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class UpdateSiteBlockRequest extends FormRequest
{
    private ?SiteBlock $ownedBlock = null;

    public function authorize(): bool
    {
        $ownedSite = $this->user()->sites()->whereKey($this->route('site'))->firstOrFail();
        $this->ownedBlock = $ownedSite->editorPage($this->route('page'))->blocks()->whereKey($this->route('block'))->firstOrFail();

        return true;
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');

        if (is_array($content) && is_string($content['url'] ?? null)) {
            $content['url'] = trim($content['url']);
            $this->merge(['content' => $content]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->ownedBlock()->type;
        $hasImagePresentation = in_array($type, ['image', 'text_image'], true);
        $hasTextButton = in_array($type, ['about', 'heading_text', 'plain_text', 'text_image'], true);
        $fields = match ($type) {
            'plain_text' => ['body'],
            'about', 'heading_text' => ['heading', 'body'],
            'hero' => ['heading', 'body', 'button_label', 'link_type', 'target_block_id', 'external_url', 'welcome_label', 'media_asset_id', 'target_page_id', 'secondary_button'],
            'service_times' => ['heading', 'entries'],
            'contact' => ['heading', 'email', 'phone', 'address'],
            'image' => ['media_asset_id', 'caption'],
            'text_image' => ['heading', 'body', 'media_asset_id', 'caption'],
            'video' => ['url'],
            default => throw new LogicException('Unsupported block type.'),
        };
        if ($hasTextButton) {
            $fields = [...$fields, 'button_label', 'link_type', 'target_block_id', 'target_page_id', 'external_url'];
        }

        $rules = [
            'site_id' => ['prohibited'],
            'type' => ['prohibited'],
            'position' => ['prohibited'],
            'content' => ['required', 'array:'.implode(',', [...$fields, 'style'])],
            'content.style' => ['sometimes', $type === 'hero'
                ? 'array:alignment,background,layout,spacing,content_width,heading_size,height,overlay,motion'
                : 'array:alignment,background,layout,spacing,content_width,heading_size'.($hasImagePresentation ? ',image_ratio,crop_position,corner_style' : '')],
            'content.style.alignment' => ['sometimes', 'string', Rule::in(['left', 'center'])],
            'content.style.background' => ['sometimes', 'string', Rule::in(['theme', 'soft', 'accent', 'contrast'])],
            'content.style.spacing' => ['sometimes', 'string', Rule::in(['compact', 'current', 'spacious'])],
            'content.style.content_width' => ['sometimes', 'string', Rule::in(['narrow', 'current', 'full'])],
            'content.style.heading_size' => in_array('heading', $fields, true)
                ? ['sometimes', 'string', Rule::in(['small', 'current', 'large'])]
                : ['missing'],
            'content.style.layout' => match ($type) {
                'text_image' => ['sometimes', 'string', Rule::in(['image_left', 'image_right'])],
                'service_times' => ['sometimes', 'string', Rule::in(['list', 'grid'])],
                default => ['prohibited'],
            },
            'content.style.image_ratio' => $hasImagePresentation
                ? ['sometimes', 'string', Rule::in(['original', 'landscape', 'square', 'portrait'])]
                : ['prohibited'],
            'content.style.crop_position' => $hasImagePresentation
                ? ['sometimes', 'string', Rule::in(['top', 'center', 'bottom'])]
                : ['prohibited'],
            'content.style.corner_style' => $hasImagePresentation
                ? ['sometimes', 'string', Rule::in(['current', 'square', 'rounded'])]
                : ['prohibited'],
            'content.caption' => $hasImagePresentation
                ? ['sometimes', 'string']
                : ['prohibited'],
            'content.address' => $type === 'contact'
                ? ['sometimes', 'string']
                : ['prohibited'],
        ];

        foreach ($fields as $field) {
            if (in_array($field, ['entries', 'target_block_id', 'media_asset_id', 'caption', 'address', 'welcome_label', 'target_page_id', 'secondary_button'], true)
                || ($hasTextButton && in_array($field, ['button_label', 'link_type', 'external_url'], true))) {
                continue;
            }

            $rules['content.'.$field] = ['present', 'string'];
        }

        if ($type === 'hero') {
            $rules['content.welcome_label'] = ['sometimes', 'string'];
            $rules['content.style.height'] = ['sometimes', 'string', Rule::in(['current', 'medium', 'full'])];
            $rules['content.style.overlay'] = ['sometimes', 'string', Rule::in(['light', 'medium', 'dark'])];
            $rules['content.style.motion'] = ['sometimes', 'string', Rule::in(['normal', 'fixed', 'half'])];
            $rules = array_merge($rules, $this->buttonRules('content', false));
            $rules['content.secondary_button'] = ['sometimes', 'array:button_label,link_type,target_block_id,target_page_id,external_url'];
            if ($this->has('content.secondary_button')) {
                $rules = array_merge($rules, $this->buttonRules('content.secondary_button', true));
            }
        }

        if ($hasTextButton && collect(['button_label', 'link_type', 'target_block_id', 'target_page_id', 'external_url'])
            ->contains(fn (string $field): bool => $this->has('content.'.$field))) {
            $rules = array_merge($rules, $this->buttonRules('content', false));
        }

        if ($type === 'service_times') {
            $rules['content.entries'] = ['present', 'array', 'list'];
            $rules['content.entries.*'] = ['required', 'array:day,time,label'];
            $rules['content.entries.*.day'] = ['required', 'string', Rule::in([
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            ])];
            $rules['content.entries.*.time'] = ['required', 'string', 'regex:/\A(?:[01]\d|2[0-3]):[0-5]\d\z/'];
            $rules['content.entries.*.label'] = ['present', 'string'];
        }

        if ($type === 'contact') {
            $rules['content.email'][] = 'email:rfc';
            $rules['content.phone'][] = 'regex:/\A\+?[0-9().\- ]+\z/';
            $rules['content.phone'][] = 'regex:/[0-9]/';
        }

        if (in_array($type, ['image', 'text_image', 'hero'], true)) {
            $rules['content.media_asset_id'] = [
                $type === 'hero' ? 'sometimes' : 'present',
                'nullable',
                'integer',
                Rule::exists('media_assets', 'id')->where('site_id', $this->ownedBlock()->site_id),
            ];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    private function buttonRules(string $prefix, bool $secondary): array
    {
        $type = $this->input($prefix.'.link_type');

        return [
            $prefix.'.link_type' => ['required', 'string', Rule::in(['none', 'section', 'page', 'external'])],
            $prefix.'.button_label' => [$type === 'none' ? 'present' : 'required', 'string'],
            $prefix.'.external_url' => $type === 'external' ? ['required', 'string', 'url:http,https'] : ['present', 'string'],
            $prefix.'.target_block_id' => $type === 'section'
                ? ['required', 'integer', Rule::notIn([$this->ownedBlock()->id]), Rule::exists('site_blocks', 'id')->where('site_id', $this->ownedBlock()->site_id)->where('page_id', $this->ownedBlock()->page_id)]
                : ['present', 'nullable', 'integer'],
            $prefix.'.target_page_id' => $type === 'page'
                ? ['required', 'integer', Rule::exists('site_pages', 'id')->where('site_id', $this->ownedBlock()->site_id)]
                : [$secondary ? 'present' : 'sometimes', 'nullable', 'integer'],
        ];
    }

    public function ownedBlock(): SiteBlock
    {
        return $this->ownedBlock ?? throw new LogicException('Block ownership was not checked.');
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['content', '_token', '_method'], true)) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }

            $type = $this->ownedBlock()->type;
            $content = $this->input('content');

            if (in_array($type, ['image', 'text_image', 'hero'], true)
                && is_array($content)
                && array_key_exists('media_asset_id', $content)
                && $content['media_asset_id'] === '') {
                $validator->errors()->add('content.media_asset_id', 'Choose an image or clear the current image.');
            }

            if ($type === 'video') {
                $url = $this->input('content.url');

                if (is_string($url) && $url !== '' && ! $this->isSupportedVideoUrl($url)) {
                    $validator->errors()->add('content.url', 'Enter an HTTPS link to a single YouTube or Vimeo video.');
                }
            }
        }];
    }

    private function isSupportedVideoUrl(string $url): bool
    {
        return VideoEmbedUrl::from($url) !== null;
    }
}
