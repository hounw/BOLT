<?php

namespace App\Http\Requests\Api;

use App\Models\AssetEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(AssetEvent::TYPES))],
            'occurred_at' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'condition' => ['nullable', 'string', 'max:120'],
            'notes' => ['required', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
