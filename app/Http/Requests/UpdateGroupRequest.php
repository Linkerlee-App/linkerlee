<?php

namespace App\Http\Requests;

use App\Helpers\PermissionHelper;
use App\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return PermissionHelper::userIsOwnerOfModal($this->route('group'), $this->user()->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Group $group */
        $group = $this->route('group');

        $rules = Group::rules();

        // Nesting a collection under itself or under one of its own children
        // would make the hierarchy a loop, and walking a loop never ends.
        $rules['parentGroupId'][] = Rule::notIn([$group->id, ...$group->descendantIds()]);

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parentGroupId.not_in' => 'A collection cannot be nested inside itself.',
        ];
    }
}
