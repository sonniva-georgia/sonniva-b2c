<?php

namespace App\Enums;

enum Permission: string
{
    case ViewOrders = 'orders.view';
    case ProcessOrders = 'orders.process';
    case SendOrdersToOnway = 'orders.send-onway';
    case DeleteOrders = 'orders.delete';
    case PlaceOrdersForCustomers = 'orders.place-for-customers';
    case ManageItems = 'items.manage';
    case SyncItems = 'items.sync';
    case ManageCategories = 'categories.manage';
    case ManageHomePage = 'home-page.manage';
    case ViewUsers = 'users.view';
    case EditUsers = 'users.edit';
    case EditUserBenefits = 'users.edit-benefits';
    case DeleteUsers = 'users.delete';
    case ViewPayments = 'payments.view';
    case ManageStockNotifications = 'stock-notifications.manage';
    case ViewAnalytics = 'analytics.view';

    public function label(): string
    {
        return match ($this) {
            self::ViewOrders => 'View orders',
            self::ProcessOrders => 'Approve, dispatch, deliver, cancel orders and send PDF',
            self::SendOrdersToOnway => 'Send orders to Onway courier',
            self::DeleteOrders => 'Delete orders',
            self::PlaceOrdersForCustomers => 'Place orders on a customer\'s behalf at checkout (incl. cutting service, delivery cost edit)',
            self::ManageItems => 'Manage items (prices, discounts, details, liquidation)',
            self::SyncItems => 'Sync items from Business Central (new items, attributes, inventory, missing images)',
            self::ManageCategories => 'Sync categories, category images and keywords',
            self::ManageHomePage => 'Manage home page banners and sections',
            self::ViewUsers => 'View and export users',
            self::EditUsers => 'Edit user details',
            self::EditUserBenefits => 'Edit user benefits (VIP, wholesale, free delivery, cash payment, BC customer no.)',
            self::DeleteUsers => 'Delete users',
            self::ViewPayments => 'View and export payments',
            self::ManageStockNotifications => 'Manage stock requests',
            self::ViewAnalytics => 'View analytics',
        };
    }
}
