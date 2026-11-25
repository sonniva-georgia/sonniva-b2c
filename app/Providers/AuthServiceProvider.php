<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->role === 'admin' ? true : null);

        Gate::define('access-admin', fn (User $user) => in_array($user->role, RolePermission::EDITABLE_ROLES));

        Gate::define('manage-permissions', fn (User $user) => false);

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => in_array($user->role, RolePermission::EDITABLE_ROLES)
                && in_array($permission->value, RolePermission::permissionsOf($user->role)));
        }

        Gate::define('edit-user', fn (User $user, User $target) => $user->is($target)
            || ($user->can(Permission::EditUsers->value) && ! in_array($target->role, User::STAFF_ROLES)));

        Gate::define('edit-user-benefits', fn (User $user, User $target) => $user->can(Permission::EditUserBenefits->value)
            && ! in_array($target->role, User::STAFF_ROLES));

        Gate::define('delete-user', fn (User $user, User $target) => $user->can(Permission::DeleteUsers->value)
            && ! in_array($target->role, User::STAFF_ROLES));
    }
}
