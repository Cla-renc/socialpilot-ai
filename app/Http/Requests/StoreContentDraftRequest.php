<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:linkedin,x,instagram,tiktok,facebook'],
            'content_type' => ['nullable', 'string', 'in:caption,carousel,thread,video'],
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
            'status' => ['nullable', 'string', 'in:draft,approved,scheduled,published'],
            'scheduled_for' => ['nullable', 'date'],
            'ai_provider' => ['nullable', 'string', 'max:100'],
            'ai_model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
