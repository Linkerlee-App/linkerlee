<?php

namespace App\Http\Requests;

use App\Enums\LinkSource;
use App\Models\Group;
use App\Models\Link;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rules\Enum;

class StoreLinkApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Request::user()->tokenCan('create');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->title ?: null,
            'source' => $this->source ?: null,
            'link' => filter_var($this->link, FILTER_VALIDATE_URL) ? $this->link : "https://$this->link",
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return array_merge(Link::rules($this->request->get('link')), [
            'groups' => 'nullable|array',
            'groups.*' => [
                'integer',
                'exists:groups,id',
                function (string $attribute, int $value, Closure $fail) {
                    $group = Group::find($value);
                    if ($group && $group->user_id !== auth()->id()) {
                        $fail('You do not own this group.');
                    }
                },
            ],
            'tags' => 'nullable|array',
            'tags.*' => ['integer', 'exists:tags,id'],
            'newTags' => 'nullable|array',
            'newTags.*' => 'string|min:1|max:50',
            'source' => ['nullable', (new Enum(LinkSource::class))->only(LinkSource::apiClientSources())],
        ]);
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors(),
        ], 422));
    }
}
