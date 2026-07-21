<?php

namespace App\Http\Requests\Api;

use App\Enums\KnowledgeArticleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:220'],
            'body_markdown' => ['required', 'string', 'max:2097152'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'status' => ['nullable', Rule::enum(KnowledgeArticleStatus::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:80'],
        ];
    }
}
