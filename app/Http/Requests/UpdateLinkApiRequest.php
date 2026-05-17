<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Request;

class UpdateLinkApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Request::user()->tokenCan('create');
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|min:2',
            'tags' => 'nullable|array',
            'tags.*' => ['integer', 'exists:tags,id'],
            'newTags' => 'nullable|array',
            'newTags.*' => 'string|min:1|max:50',
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors(),
        ], 422));
    }
}
