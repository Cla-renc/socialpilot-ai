<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['nullable', 'string', 'in:owner,admin,editor,reviewer,viewer'],
            'status' => ['nullable', 'string', 'in:active,pending'],
        ];
    }
}
