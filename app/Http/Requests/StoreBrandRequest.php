<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'industry' => ['nullable', 'string', 'max:120'],
            'niche' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:2000'],
            'target_age_group' => ['nullable', 'string', 'max:100'],
            'audience_interests' => ['nullable', 'array', 'max:30'],
            'audience_interests.*' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:50'],
            'tone' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused'])],
            'brand_voice' => ['nullable', 'string', 'max:1000'],
            'writing_style' => ['nullable', 'string', 'max:255'],
            'brand_personality' => ['nullable', 'array', 'max:20'],
            'brand_personality.*' => ['string', 'max:100'],
            'brand_values' => ['nullable', 'array', 'max:30'],
            'brand_values.*' => ['string', 'max:100'],
            'brand_keywords' => ['nullable', 'array', 'max:50'],
            'brand_keywords.*' => ['string', 'max:100'],
            'topics_to_avoid' => ['nullable', 'array', 'max:50'],
            'topics_to_avoid.*' => ['string', 'max:100'],
            'content_restrictions' => ['nullable', 'array', 'max:50'],
            'content_restrictions.*' => ['string', 'max:200'],
            'hashtag_preferences' => ['nullable', 'array', 'max:30'],
            'hashtag_preferences.*' => ['string', 'max:100'],
            'preferred_publishing_times' => ['nullable', 'array', 'max:20'],
            'preferred_publishing_times.*' => ['string', 'max:100'],
            'marketing_objectives' => ['nullable', 'array', 'max:8'],
            'marketing_objectives.*' => ['nullable', 'string', Rule::in(array_keys(config('brand.objectives')))],
            'content_preferences' => ['nullable', 'array:educational,entertaining,promotional,inspirational,community'],
            'content_preferences.*' => ['nullable', 'integer', 'between:1,5'],
            'content_formats' => ['nullable'],
            'brand_colours' => ['nullable'],
            'publishing_frequency' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'timezone'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'audience_interests',
            'brand_personality',
            'brand_values',
            'brand_keywords',
            'topics_to_avoid',
            'content_restrictions',
            'hashtag_preferences',
            'preferred_publishing_times',
            'content_formats',
            'brand_colours',
        ] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = array_values(array_filter(array_map('trim', preg_split('/[,\n]/', $value) ?: [])));
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
