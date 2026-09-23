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
            default => throw new LogicException('Unsupported block type.'),
        };

        $rules = [
            'site_id' => ['prohibited'],
            'type' => ['prohibited'],
            'position' => ['prohibited'],
            'content' => ['required', 'array:'.implode(',', $fields)],
        ];

        foreach ($fields as $field) {
            if (in_array($field, ['entries', 'target_block_id'], true)) {
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
        }];
    }
}
