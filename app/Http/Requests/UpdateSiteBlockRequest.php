<?php

namespace App\Http\Requests;

use App\Models\SiteBlock;
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
        $this->ownedBlock = $ownedSite->blocks()->whereKey($this->route('block'))->firstOrFail();

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
        $fields = match ($type) {
            'plain_text' => ['body'],
            'about', 'heading_text' => ['heading', 'body'],
            'hero' => ['heading', 'body', 'button_label', 'link_type', 'target_block_id', 'external_url'],
            'service_times' => ['heading', 'entries'],
            'contact' => ['heading', 'email', 'phone'],
            'image' => ['media_asset_id'],
            'text_image' => ['heading', 'body', 'media_asset_id'],
            'video' => ['url'],
            default => throw new LogicException('Unsupported block type.'),
        };

        $rules = [
            'site_id' => ['prohibited'],
            'type' => ['prohibited'],
            'position' => ['prohibited'],
            'content' => ['required', 'array:'.implode(',', $fields)],
        ];

        foreach ($fields as $field) {
            if (in_array($field, ['entries', 'target_block_id', 'media_asset_id'], true)) {
                continue;
            }

            $rules['content.'.$field] = ['present', 'string'];
        }

        if ($type === 'hero') {
            $linkType = $this->input('content.link_type');
            $rules['content.link_type'] = ['required', 'string', Rule::in(['none', 'section', 'external'])];
            $rules['content.target_block_id'] = ['present', 'nullable', 'integer'];

            if ($linkType === 'section') {
                $rules['content.button_label'][] = 'required';
                $rules['content.target_block_id'][] = 'required';
                $rules['content.target_block_id'][] = Rule::notIn([$this->ownedBlock()->id]);
                $rules['content.target_block_id'][] = Rule::exists('site_blocks', 'id')
                    ->where('site_id', $this->ownedBlock()->site_id);
                $rules['content.external_url'][] = Rule::in(['']);
            } elseif ($linkType === 'external') {
                $rules['content.button_label'][] = 'required';
                $rules['content.external_url'][] = 'required';
                $rules['content.external_url'][] = 'url:http,https';
            }

            if ($linkType !== 'section') {
                $rules['content.target_block_id'][] = Rule::in([null]);
            }

            if ($linkType === 'none') {
                $rules['content.button_label'][] = Rule::in(['']);
                $rules['content.external_url'][] = Rule::in(['']);
            }
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

        if (in_array($type, ['image', 'text_image'], true)) {
            $rules['content.media_asset_id'] = [
                'present',
                'nullable',
                'integer',
                Rule::exists('media_assets', 'id')->where('site_id', $this->ownedBlock()->site_id),
            ];
        }

        return $rules;
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

            if (in_array($type, ['image', 'text_image'], true)
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
        $url = trim($url);

        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $query = $this->parseVideoQuery($parts['query'] ?? '');

        if ($query === null) {
            return false;
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com'], true) && $path === '/watch') {
            return ! array_key_exists('list', $query)
                && is_string($query['v'] ?? null)
                && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $query['v']) === 1;
        }

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            return ! array_key_exists('list', $query)
                && preg_match('/\A\/[A-Za-z0-9_-]{11}\z/', $path) === 1;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)) {
            return preg_match('/\A\/[0-9]+\z/', $path) === 1;
        }

        return false;
    }

    /** @return array<string, string>|null */
    private function parseVideoQuery(string $query): ?array
    {
        if ($query === '') {
            return [];
        }

        $parameters = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                return null;
            }

            [$encodedKey, $encodedValue] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($encodedKey);
            $value = urldecode($encodedValue);

            if (preg_match('/\A[A-Za-z0-9_-]+\z/', $key) !== 1 || array_key_exists($key, $parameters)) {
                return null;
            }

            $parameters[$key] = $value;
        }

        return $parameters;
    }
}
