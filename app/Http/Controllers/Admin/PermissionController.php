<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Models\RolePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $granted = [];

        foreach (RolePermission::EDITABLE_ROLES as $role) {
            $granted[$role] = RolePermission::permissionsOf($role);
        }

        return Inertia::render('Admin/permissions/Index', [
            'roles' => RolePermission::EDITABLE_ROLES,
            'permissions' => array_map(fn (Permission $permission) => [
                'value' => $permission->value,
                'label' => $permission->label(),
            ], Permission::cases()),
            'granted' => $granted,
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request): RedirectResponse
    {
        $rows = [];

        foreach (RolePermission::EDITABLE_ROLES as $role) {
            foreach ($request->validated("granted.{$role}") as $permission) {
                $rows[] = ['role' => $role, 'permission' => $permission];
            }
        }

        DB::transaction(function () use ($rows) {
            RolePermission::whereIn('role', RolePermission::EDITABLE_ROLES)->delete();
            RolePermission::insert($rows);
        });

        return back()->with('message', 'Permissions updated successfully.');
    }
}
