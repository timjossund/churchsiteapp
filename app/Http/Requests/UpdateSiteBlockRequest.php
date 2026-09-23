<?php

namespace App\Http\Requests;

use App\Models\SiteBlock;
use Illuminate\Foundation\Http\FormRequest;
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
        $fields = $this->ownedBlock()->type === 'plain_text'
            ? ['body']
            : ['heading', 'body'];

        $rules = [
            'site_id' => ['prohibited'],
            'type' => ['prohibited'],
            'position' => ['prohibited'],
            'content' => ['required', 'array:'.implode(',', $fields)],
        ];

        foreach ($fields as $field) {
            $rules['content.'.$field] = ['present', 'string'];
        }

        return $rules;
    }

    public function ownedBlock(): SiteBlock
    {
        return $this->ownedBlock ?? throw new LogicException('Block ownership was not checked.');
    }
}
