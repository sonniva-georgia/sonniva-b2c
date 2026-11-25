<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\RolePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-permissions') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (RolePermission::EDITABLE_ROLES as $role) {
            $rules["granted.{$role}"] = ['present', 'array'];
            $rules["granted.{$role}.*"] = ['distinct', Rule::enum(Permission::class)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'granted.*.*.distinct' => 'A permission is selected twice for the same role.',
            'granted.*.*.enum' => 'Unknown permission.',
        ];
    }
}
