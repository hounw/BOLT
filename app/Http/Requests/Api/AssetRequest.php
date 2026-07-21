<?php

namespace App\Http\Requests\Api;

use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $asset = $this->route('asset');

        return [
            'asset_tag' => ['nullable', 'string', 'max:80', Rule::unique('assets')->ignore($asset)],
            'name' => ['required', 'string', 'max:180'],
            'tags' => ['nullable'],
            'tags.*' => ['string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:180', Rule::unique('assets')->ignore($asset)],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'vendor' => ['nullable', 'string', 'max:180'],
            'warranty_expires_on' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
