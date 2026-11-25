<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\Order;
use App\Models\RolePermission;
use App\Models\StockNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user?->role === 'admin';
        $isCashier = in_array(auth()?->user()?->role, ['cashier']);

        $categories = fn () => Cache::rememberForever('nav_categories_'.app()->getLocale(), fn () => Category::navTree());

        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
            'categories' => $categories,

            'recaptcha_site_key' => config('services.google_recaptcha.site_key'),

            'isLoggedIn' => Auth::check(),
            'isAdmin' => $isAdmin,
            'isCashier' => $isCashier,
            'isStaff' => in_array($user?->role, User::STAFF_ROLES, true),
            'can' => fn () => match (true) {
                $isAdmin => array_column(Permission::cases(), 'value'),
                in_array($user?->role, RolePermission::EDITABLE_ROLES, true) => RolePermission::permissionsOf($user->role),
                default => [],
            },
            'user' => Auth::user(),

            // ── Wishlist IDs shared to every page ──────────────────────────
            // Loaded only once per request; the Vue composable merges these
            // with the localStorage guest list on the client side.
            'wishlist' => [
                'ids' => $request->user()
                    ? $request->user()->wishlists()->pluck('item_id')
                    : [],
            ],

            'cart' => function () use ($request) {
                $carts = $request->user()
                    ? $request->user()->carts()->get(['id', 'item_id', 'quantity', 'selected_uom', 'with_service'])
                    : collect();

                // Use composite key "itemId__uom" for package items so same item with
                // different UOMs get separate state entries in the frontend.
                $key = fn ($c) => $c->selected_uom
                    ? $c->item_id.'__'.$c->selected_uom
                    : (string) $c->item_id;

                return [
                    'items' => $carts->mapWithKeys(fn ($c) => [$key($c) => $c->quantity]),
                    'uoms' => $carts->filter(fn ($c) => $c->selected_uom)
                        ->mapWithKeys(fn ($c) => [$key($c) => $c->selected_uom]),
                    'services' => $carts->filter(fn ($c) => $c->with_service)
                        ->mapWithKeys(fn ($c) => [$key($c) => true]),
                ];
            },

            'unseenStockCount' => $user?->can(Permission::ManageStockNotifications->value)
                ? fn () => StockNotification::whereNull('seen_at')->count()
                : null,

            'unseenOrdersCount' => $user?->can(Permission::ViewOrders->value)
                ? fn () => Order::whereNull('seen_at')->whereIn('status', ['pending', 'limit', 'paid'])->count()
                : null,

            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
