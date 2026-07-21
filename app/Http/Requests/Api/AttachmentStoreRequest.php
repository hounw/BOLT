<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string', Rule::in(['employees', 'knowledge_articles', 'assets', 'asset_events'])],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:20480'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
