<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048'],
            'secret' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:24'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_merge(config('bolt.webhooks.events'), ['*']))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
