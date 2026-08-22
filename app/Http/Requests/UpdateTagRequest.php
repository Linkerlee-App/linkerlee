<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is checked in the controller rather than here, so that a tag
     * belonging to someone else is indistinguishable from one that does not
     * exist — a 403 would confirm the id is real.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tagName' => ['required', 'string', 'min:2', 'max:50'],
        ];
    }
}
