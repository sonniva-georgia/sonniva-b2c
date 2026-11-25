<?php

use App\Models\Order;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Once;

function grantPermission(string $role, string $permission): void
{
    RolePermission::create(['role' => $role, 'permission' => $permission]);
    Once::flush();
}

it('seeds defaults that match the previous role access', function () {
    expect(RolePermission::permissionsOf('manager'))->toEqualCanonicalizing([
        'orders.view', 'orders.process', 'orders.send-onway', 'orders.delete',
        'items.manage', 'items.sync', 'categories.manage', 'home-page.manage',
        'users.view', 'payments.view', 'stock-notifications.manage', 'analytics.view',
    ])->and(RolePermission::permissionsOf('pricing_manager'))->toBe(['items.manage']);
});

it('lets a pricing manager sync items once granted', function () {
    Queue::fake();
    $pricingManager = User::factory()->create(['role' => 'pricing_manager']);

    $this->actingAs($pricingManager)->post(route('admin.items.sync'))->assertForbidden();

    grantPermission('pricing_manager', 'items.sync');

    $this->actingAs($pricingManager)->post(route('admin.items.sync'))->assertRedirect();
    $this->actingAs($pricingManager)->post(route('admin.items.sync-inventory'))->assertRedirect();
});

it('blocks a manager from actions not granted by default', function () {
    $manager = User::factory()->create(['role' => 'manager']);
    $customer = User::factory()->create();

    $this->actingAs($manager);

    $this->get(route('admin.users.get-user', $customer))->assertForbidden();
    $this->delete(route('admin.users.delete', $customer))->assertForbidden();
    $this->getJson(route('admin.customers.index', ['q' => 'x']))->assertForbidden();
    $this->get(route('admin.permissions.index'))->assertForbidden();

    expect($customer->fresh())->not->toBeNull();
});

it('removes access when a permission is revoked', function () {
    $manager = User::factory()->create(['role' => 'manager']);
    $order = Order::factory()->create(['status' => 'pending']);

    RolePermission::where('role', 'manager')->where('permission', 'orders.delete')->delete();
    Once::flush();

    $this->actingAs($manager)->delete(route('admin.orders.destroy', $order))->assertForbidden();
    expect($order->fresh())->not->toBeNull();
});

it('never lets a non-admin edit or delete staff accounts', function () {
    grantPermission('manager', 'users.edit');
    grantPermission('manager', 'users.delete');
    $manager = User::factory()->create(['role' => 'manager']);
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create();

    $this->actingAs($manager);

    $this->get(route('admin.users.get-user', $customer))->assertOk();
    $this->get(route('admin.users.get-user', $admin))->assertForbidden();
    $this->delete(route('admin.users.delete', $admin))->assertForbidden();

    expect($admin->fresh())->not->toBeNull();
});

it('shares the granted permissions with the frontend', function () {
    $this->actingAs(User::factory()->create(['role' => 'pricing_manager']))
        ->get(route('admin.items.index'))
        ->assertInertia(fn ($page) => $page->where('can', ['items.manage']));
});

it('lets only admin change permissions', function () {
    $granted = ['manager' => ['orders.view'], 'pricing_manager' => ['items.manage', 'items.sync']];

    $this->actingAs(User::factory()->create(['role' => 'manager']))
        ->put(route('admin.permissions.update'), ['granted' => $granted])
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->put(route('admin.permissions.update'), ['granted' => $granted])
        ->assertSessionHasNoErrors();

    Once::flush();

    expect(RolePermission::permissionsOf('manager'))->toBe(['orders.view'])
        ->and(RolePermission::permissionsOf('pricing_manager'))->toEqualCanonicalizing(['items.manage', 'items.sync']);
});

it('rejects unknown or duplicate permissions', function (array $managerPermissions) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->put(route('admin.permissions.update'), ['granted' => ['manager' => $managerPermissions, 'pricing_manager' => []]])
        ->assertSessionHasErrors('granted.manager.0');
})->with([
    'unknown' => [['orders.fly']],
    'duplicate' => [['orders.view', 'orders.view']],
]);
