<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->unique(['role', 'permission']);
        });

        $defaults = [
            'manager' => [
                'orders.view', 'orders.process', 'orders.send-onway', 'orders.delete',
                'items.manage', 'items.sync', 'categories.manage', 'home-page.manage',
                'users.view', 'payments.view', 'stock-notifications.manage', 'analytics.view',
            ],
            'pricing_manager' => ['items.manage'],
        ];

        foreach ($defaults as $role => $permissions) {
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert(['role' => $role, 'permission' => $permission]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
