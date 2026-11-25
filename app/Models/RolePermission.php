<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    public const array EDITABLE_ROLES = ['manager', 'pricing_manager'];

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return list<string>
     */
    public static function permissionsOf(string $role): array
    {
        return once(fn () => self::where('role', $role)->pluck('permission')->all());
    }
}
