<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_draft_id' => ['nullable', 'integer', 'exists:brand_content_drafts,id'],
            'provider' => ['nullable', 'string', 'in:internal,openai,gemini,groq,openrouter,ollama'],
            'platform' => ['required', 'string', 'in:linkedin,x,instagram,tiktok,facebook'],
            'content' => ['required', 'string', 'min:10', 'max:5000'],
            'status' => ['nullable', 'string', 'in:queued,scheduled,published,failed'],
            'scheduled_for' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
