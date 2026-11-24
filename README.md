# Sonniva B2C

The online store of **Sonniva Georgia** — [sonniva.ge](https://sonniva.ge).

Customers browse the catalog, add items to the cart and pay online or by invoice. Products, prices and stock come from **Microsoft Dynamics 365 Business Central (BC)**, the company's ERP, and every paid order is sent back to BC as a sales order. The same backend also serves the **Sonniva mobile app** (`/api/v1`) and the **Sonniva HMS** site (`/api/hms/items`).

---

## Contents

1. [Tech stack](#1-tech-stack)
2. [Run it locally (macOS)](#2-run-it-locally-macos)
3. [Environment variables](#3-environment-variables)
4. [How the project is organised](#4-how-the-project-is-organised)
5. [External services](#5-external-services)
6. [Background work: scheduler & queues](#6-background-work-scheduler--queues)
7. [Business rules you must know](#7-business-rules-you-must-know)
8. [Localization](#8-localization)
9. [Users & roles](#9-users--roles)
10. [Artisan commands](#10-artisan-commands)
11. [Testing & code style](#11-testing--code-style)
12. [Deployment](#12-deployment)
13. [Related projects](#13-related-projects)
14. [Ownership & access](#14-ownership--access)

---

## 1. Tech stack

| Layer | Technology |
|---|---|
| Language | PHP **8.4** |
| Framework | Laravel **13** |
| Frontend | Inertia **3** + Vue **3** (with SSR), Tailwind CSS **4**, PrimeVue **4** |
| State | Pinia (+ `pinia-plugin-persistedstate`) |
| Translations (UI) | `vue-i18n` — `resources/js/locales/*.json` |
| Routing in JS | Ziggy (`route()` in Vue) |
| Database | MySQL |
| Cache & queues | Redis, Laravel Horizon |
| API auth (mobile) | Laravel Sanctum |
| PDF invoices | `spatie/laravel-pdf` (headless Chrome via Puppeteer) |
| Tests | Pest **4** |
| Code style | Laravel Pint (PHP), Prettier (JS/Vue) |

> Always check the real installed versions in `composer.json` / `package.json` before relying on an API — the table above was correct when written.

---

## 2. Run it locally (macOS)

### 2.1 Get the code

```bash
git clone git@github.com:sonniva-georgia/sonniva-sales.git
cd sonniva-sales

composer install
npm install            # also downloads the Chrome build Puppeteer uses for PDF invoices
```

### 2.2 Configure

```bash
cp .env.example .env
php artisan key:generate
```

Fill in the empty values in `.env`. See [Environment variables](#3-environment-variables) for what each one does and who to ask for it. **Never commit `.env`.**

Run `php artisan ziggy:generate` before the first start, and again after adding or renaming routes. `resources/js/ziggy.js` is generated and git-ignored, so pages fail with an import error until it exists.

### 2.3 Database

```bash
mysql -u root -e "CREATE DATABASE sales_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan storage:link
```

Now load data. There are two ways:

**A. Database dump from production (recommended).** Ask the Director for a recent dump with personal customer data removed, then:

```bash
mysql -u root sales_db < dump.sql
```

Product images live in `storage/app/public` (not in git). Ask for a copy of that folder too, or fetch them from BC (step B, last command).

**B. Pull the catalog from Business Central.** Needs the `BC_*` keys in `.env`. Run in this order:

```bash
php artisan items:sync-category          # categories
php artisan items:sync                   # items
php artisan items:sync-data              # names, prices, wholesale tiers
php artisan items:sync-weights           # weights (needs prices first)
php artisan items:sync-attributes        # attributes (brand, length, …)
php artisan inventory:sync               # stock
php artisan services:sync                # paid services (setup, cutting)
php artisan items:fetch-missing-images   # product images

# translations of categories, item names and descriptions
php artisan localize:import database/localization/categories.json
php artisan localize:import database/localization/dictionary.json
php artisan localize:import database/localization/item_descriptions.json
php artisan localize:import database/localization/items_name_batch1.json   # repeat for every items_name_batch*.json
```

---

## 3. Environment variables

`.env.example` lists every key the app reads. Non-secret defaults are filled in; secrets are empty. **Credentials are never stored in git or in this README** — ask the Director (see [Ownership & access](#14-ownership--access)).

| Keys | Used for | Needed locally? |
|---|---|---|
| `APP_*`, `DB_*`, `REDIS_*` | core app, MySQL, Redis | yes |
| `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` | queues and cache run on Redis | yes |
| `BC_*` | Business Central API (OAuth client credentials) | for syncing / placing real orders |
| `ONWAY_*` | OnWay courier API (regions, delivery orders) | for regional delivery |
| `FLITT_*`, `TBC_IBAN` | TBC Bank card payments (through Flitt) | for card payments |
| `BOG_*` | Bank of Georgia payments. `BOG_CLIENT_ID/SECRET` on production, `BOG_API_KEY/SECRET` for local testing | for card payments |
| `PCB_TEST_URL`, `PCB_TEST_MERCHANT_ID` | ProCredit Bank payments. Also needs certificate files in `storage/app/private/pcb-certs/` (`ca.pem`, `cert.pem`, `key.pem`) | for card payments |
| `SMS_OFFICE_*` | SMS (registration OTP, password reset, order SMS) | for registration / login by phone |
| `GOOGLE_RECAPTCHA_*` | reCAPTCHA on public forms | yes, or forms fail |
| `ANALYTICS_PROPERTY_ID` | admin analytics page (Google Analytics 4). Also needs `storage/app/analytics/service-account-credentials.json` | optional |
| `MAIL_*` | SMTP. `MAIL_TO_ADDRESS` = company inbox that receives every order PDF | use `MAIL_MAILER=log` locally |
| `VITE_*` | values baked into the frontend build (app name, Google Maps key, company phone / WhatsApp) | yes |
| `MOBILE_APP_DEEPLINK_SCHEME` | returns the mobile app user to the app after paying. Must match the mobile app's `NATIVEPHP_DEEPLINK_SCHEME` | only for mobile testing |

> **Payment callbacks** (`/payment/callback`) must be reachable from the internet, so card payments can't be fully tested on `127.0.0.1`. Use a tunnel (e.g. Expose / ngrok) and point `*_CALLBACK_URL` at it.

---

## 4. How the project is organised

```
app/
├── Console/Commands/     BC sync, localization import/export, sitemap, maintenance commands
├── Http/Controllers/     web (Inertia) controllers
│   ├── Admin/            admin panel
│   ├── Api/              mobile app + HMS endpoints (routes/api.php)
│   └── Payment/          card payments, invoices, bank callbacks
├── Jobs/                 queued work: send order to BC, order emails/SMS, syncs
├── Models/               Eloquent models (Item, Category, Order, Cart, User, …)
├── Providers/            gates (AuthServiceProvider), Horizon access
└── Services/
    ├── BusinessCentralService.php   every call to BC goes through here
    ├── ItemPricingService.php       which price a customer sees and pays
    ├── Payments/                    OrderCalculatorService (totals + delivery), OrderPlacementService, bank services
    ├── Auth/, Account/              login, registration, password reset, account deletion
    ├── PDFGeneratorService.php      invoice PDFs
    └── SmsService.php
resources/js/
├── Pages/                Inertia pages (one folder per section; Admin/ = admin panel)
├── Shared/               layout, menus, shared components
├── composables/          useCart, useWishlist, usePricing, useReorder, useGtag
├── PiniaStore/           Pinia stores
├── locales/              UI translations: ka, en, ru, tr
└── utils/apiRoute.js     use this for every routes/api.php call (see Localization)
routes/
├── web.php               website (Inertia) + admin
├── api.php               mobile app (/api/v1) + HMS (/api/hms)
└── console.php           scheduled tasks
database/localization/    translation source files imported with localize:import
```

**Where to look first**

- Prices → `ItemPricingService` (backend) and `composables/usePricing.js` (frontend).
- Order totals and delivery → `Services/Payments/OrderCalculatorService.php`.
- Placing an order → `OrderPlacementService` → `Payment/PaymentController` → jobs `SendOrderToBCJob` + `SendOrderEmailJob`.
- Anything BC → `BusinessCentralService`.

---

## 5. External services

| Service | What we use it for | Where in code |
|---|---|---|
| **Business Central** | source of items, categories, prices, stock, services, customers; receives every order | `BusinessCentralService`, sync commands, `SendOrderToBCJob` |
| **OnWay** (onway.ge) | courier: regions/villages list at checkout; an admin sends the delivery order to OnWay from the admin order page | `config/onway.php`, `CheckoutController`, `Admin/AdminOrderController` |
| **TBC Bank** (via Flitt) | card payments | `Payments/TBCPaymentService.php` |
| **Bank of Georgia** | card payments | `Payments/BOGPaymentService.php` |
| **ProCredit Bank** | card payments (mutual-TLS with client certificates) | `Payments/PCBService.php` |
| **SMS Office** | OTP codes and order SMS | `SmsService` |
| **Google** | reCAPTCHA, Maps (checkout), Analytics (admin + gtag) | `config/services.php`, `config/analytics.php`, `app.blade.php` |
| **SMTP mail** | order PDFs to the company inbox, customer emails | `Mail/`, `SendOrderEmailJob` |

**BC access token** is cached in the `business_central_tokens` table and refreshed automatically before it expires.

### Business Central endpoints

Two kinds of BC URL are used, both on top of `BC_API_BASE_URL` (`https://api.businesscentral.dynamics.com/v2.0/`):

- **Custom API**: `Production/api/smart/sonniva/v1.0/companies(dc29e11b-78aa-ee11-be38-000d3ab8f033)/<endpoint>`
- **OData web services**: `904668f4-6aa7-44ce-8285-5c27b33faeeb/Production/ODataV4/Company('SONNIVA')/<endpoint>`

| Endpoint | Type | Used for | Where |
|---|---|---|---|
| `items` | Custom API | item list: numbers, categories, names, prices, attributes, stock, services | `items:sync*`, `inventory:sync`, `services:sync`, `getItemsInventory()` |
| `itemsDetailed` | Custom API | one item with wholesale price tiers (`itemUnitPrices`) and picture | `items:sync`, `items:sync-data`, `items:fetch-missing-images` |
| `itemCategories` | Custom API | category tree and category images | `getItemCategoryByCode()` (admin), `CategorySeeder` |
| `ItemUnitOfMeasureAPI` | OData | item weights | `items:sync-weights` |
| `Item_Ledger_Entries_Excel` | OData | live stock of one item (item page, checkout check) | `calcInventory()` |
| `Price_List_Lines_Excel` | OData | price list lines | `PriceListSeeder` |
| `Customers` | OData | create / update / read a customer, credit limit by tax ID | `addCustomer()`, `updateCustomer()`, `getCustomerCreditInfo()` |
| `SalesOrders` | OData | order header | `addSalesOrders()` via `SendOrderToBCJob` |
| `SalesOrderLines` | OData | order lines (items, delivery, services) | `addSalesOrderLines()` |
| `ShipToAddress` | OData | delivery address of the order | `addShipToAddress()` |

---

## 6. Background work: scheduler & queues

### Scheduled tasks (`routes/console.php`)

All times are **Asia/Tbilisi**. They only run when the scheduler is running (`* * * * * php artisan schedule:run` on the server).

| Task | When | What it does |
|---|---|---|
| `inventory:sync` | every 5 min, 09:00–18:00 | stock levels from BC |
| `items:sync-data` | every 30 min, 09:00–18:00 | names, prices, wholesale tiers from BC |
| `services:sync` | every 30 min, 09:00–18:00 | service names / prices from BC |
| `SyncItemFromBcJob` | daily 03:00 | imports new items, then `items:sync-data`, then weights for items missing them |

Locally the scheduler does not run by itself. Run a task by hand when you need fresh data, or `php artisan schedule:work`.

### Queues

Queues run on **Redis**, managed by **Horizon** on the server (queues: `high`, `default`, `low`). Important queued jobs:

| Job | Triggered by | What it does |
|---|---|---|
| `SendOrderToBCJob` | successful payment / invoice order | creates the sales order + lines (+ ship-to address, service lines, delivery line) in BC |
| `SendOrderEmailJob` | successful payment / invoice order | generates the invoice PDF, emails it to the company and the customer, sends SMS |
| `UpdateBusinessCentralCustomer` | customer edits their account (web or mobile) | keeps the BC customer card in sync |

> If the queue worker is stopped, orders are still saved, but **BC never receives them and nobody gets an email**. Check `/horizon` first when "orders are missing in BC".

---

## 7. Business rules you must know

These rules come from the company and are not obvious from the code. Change them only when the company asks.

### Prices

- BC is the source of every price. `items:sync-data` overwrites `unit_price` every 30 minutes.
- **`unit_price_override`**: an admin can set a higher price than BC's. It survives the sync because it's applied through an accessor on `Item`, not written over `unit_price`.
- **`fake_price`**: a marketing "was" price. Strikethrough rule everywhere: strike `fake_price` if present, otherwise `unit_price`. The bold price is always what the customer is actually charged.
- **Wholesale tiers** (`prices` JSON): quantity-based prices. Customers with `can_view_vip` / `can_view_wholesales` see extra tiers. Category `1201-08` shows wholesale prices only to users with `can_view_wholesales`.
- Pricing logic exists on **both** sides: `ItemPricingService` (authoritative, used for the real charge) and `usePricing.js` (display). Keep them in sync.

### Delivery (`OrderCalculatorService`)

| Case | Price |
|---|---|
| Pickup from a Sonniva branch | free |
| Tbilisi, order total ≥ **500 ₾** | free |
| Tbilisi, cart **< 50 kg** | OnWay weight tariff (`DELIVERY_RATES`, `tbilisi` column) |
| Tbilisi, cart **≥ 50 kg**, or an oversized item | flat **zone rate** by district (`TBILISI_ZONE_RATES`): 40 / 50 / 90 / 100 ₾ |
| Regions / villages / OnWay branch | OnWay weight tariff (`region` / `village` / `office` column) |
| User with `has_free_delivery` | free |

"Oversized" = item number in `TBILISI_ZONE_ONLY_ITEM_NOS`, category in `TBILISI_ZONE_ONLY_CATEGORY_CODES`, or a `სიგრძე` (length) attribute over 2 m.

The rate tables are duplicated in three places that **must stay identical**: `OrderCalculatorService`, `resources/js/Pages/Checkout/Index.vue`, and `resources/js/Pages/Policies/DeliveryRates.vue` (plus district lists in `locales/*.json`). The backend number is the one that's charged.

### Services

- **Setup service** (door locks, category `2300-02`) and **cutting service** are rows in the `services` table, synced from BC (`NONINV331`, `NONINV261`) and sent to BC as extra order lines.

### Payment methods

Card (TBC / BOG / PCB), bank transfer by invoice, purchase on credit limit for contract customers (limit read from BC), and cash for users with `allow_cash_payment`. Admins can also place an order **on behalf of a customer** from checkout (`placed_by_id` = admin, `user_id` = customer).

### Stock

Stock comes from BC. At checkout the app re-checks live stock in BC; if BC is unreachable it falls back to the synced value so an outage doesn't block orders.

---

## 8. Localization

- Languages: **Georgian (`ka`, default)**, English, Russian, Turkish.
- URLs: Georgian has no prefix (`/…`); the others are `/en/…`, `/ru/…`, `/tr/…`. The prefixed routes are cloned automatically in `bootstrap/app.php` — don't add them by hand. Admin routes are not cloned.
- **UI text** → `resources/js/locales/{ka,en,ru,tr}.json` (vue-i18n). Add every new key to **all four** files.
- **Database content** (category names, item names, descriptions) → locale columns, filled with `php artisan localize:import`.
- **Calling `routes/api.php` from Vue:** use `apiRoute('api.…')` from `utils/apiRoute.js`, never plain `route('api.…')`. On `/en` pages `route()` adds the locale prefix and the API call 404s.
- The **admin panel is English only**. Customer-facing pages, invoices and emails are Georgian.

---

## 9. Users & roles

| `users.role` | Access |
|---|---|
| `user` | normal customer |
| `pricing_manager` | admin item pricing pages only |
| `manager` | admin panel |
| `admin` | everything: admin panel, Horizon, placing orders for customers |

Gates are in `app/Providers/AuthServiceProvider.php`. Extra per-user switches (set by an admin): `can_view_wholesales`, `can_view_vip`, `can_view_inventory`, `allow_cash_payment`, `has_free_delivery`.

---

## 10. Artisan commands

| Command | What it does |
|---|---|
| `items:sync {no?} {--all}` | import new items from BC (or refresh one / all) |
| `items:sync-data {no?}` | names, prices, wholesale tiers |
| `items:sync-weights {no?} {--missing}` | weights |
| `items:sync-attributes` | attributes |
| `items:sync-category` | categories + each item's category |
| `inventory:sync` | stock |
| `services:sync` | services |
| `items:fetch-missing-images` | images from BC for in-stock items without one |
| `items:import-sales-rank {path}` | per-category sort order from a BC sales CSV |
| `localize:export {type}` / `localize:import {file}` | export untranslated strings / import translations |
| `sitemap:generate` | writes `public/sitemap.xml` |
| `icons:optimize` | resizes category icons in `public/categories-icons/` to 200×200 |
| `app:fix-item-slugs` | repairs item slugs with invalid URL characters |

> Category icons are named `{code}-{version}.png`. When you replace one, bump `CATEGORY_ICON_VERSION` in `resources/js/constants/categoryIcons.js` and rename the file, or browsers keep the old one cached.

---

## 11. Testing & code style

```bash
php artisan test                         # whole suite (in-memory SQLite, no MySQL/Redis needed)
php artisan test --filter=CheckoutPreview
vendor/bin/pint --dirty                  # format changed PHP files
npm run format                           # format resources/ with Prettier
```

Tests never call BC, banks or SMS for real — those are faked. Delivery pricing, checkout, BC sync, localization routing and pricing tiers have feature tests in `tests/Feature/`; update them when you change those rules.

---

## 12. Deployment

**Push to `main` = deploy to production.** There is no staging for this project, so test locally first.

The GitHub Actions workflow `.github/workflows/deploy.yml`:

1. If frontend files changed: installs dependencies and runs `npm run build` (client + SSR) **in CI**.
2. SSHes into the server → `git pull` → `composer install --no-dev` → `php artisan migrate --force`.
3. Uploads the built `public/build/` and `bootstrap/ssr/` to the server.
4. `php artisan optimize:clear && php artisan optimize`, restarts Horizon and the SSR server.

**Rules**

- **Never build assets on the server** and never commit `public/build` or `bootstrap/ssr`. CI builds and uploads them.
- Migrations run automatically on deploy. Never edit a migration that has already run on production; add a new one.
- Every `VITE_*` value used in production must also be set in the workflow (`Build assets` step), because the build runs in CI, not on the server.

**GitHub secrets** (repo → Settings → Secrets and variables → Actions):
`DEPLOY_KEY` (SSH key the workflow uses to log into the server), `PRODUCTION_HOST`, `PRODUCTION_USER`, `PROJECT_PATH`, `VITE_GOOGLE_API_KEY`.

**Server side**

- The server pulls code with a **read-only deploy key** (repo → Settings → Deploy keys), through the SSH host alias `github-sonniva-sales` in the deploy user's `~/.ssh/config`. Never use a personal GitHub key on a server.
- Processes that must always run on the server: the scheduler (cron `schedule:run`), **Horizon**, and the **Inertia SSR** server (`php artisan inertia:start-ssr`). The deploy restarts the last two; their process manager starts them again.
- Production `.env` lives only on the server.

**If a deploy fails** at "Pull code" with *divergent branches* or *local changes would be overwritten*: someone changed files on the server or rewrote `main`. SSH in, check `git status`, and bring the server back in line with `origin/main` without losing anything you need (`git reset --keep origin/main`). Never force-push to `main`.

---

## 13. Related projects

All in the [sonniva-georgia](https://github.com/sonniva-georgia) GitHub organization.

| Repo | Relation to this project |
|---|---|
| `sonniva-b2c-mobile` | NativePHP mobile app. Uses only `routes/api.php` (`/api/v1`, Sanctum tokens) of this project. Any new mobile endpoint goes in `api.php`, never `web.php`. |
| `sonniva-hms` | HMS brand site. Reads HMS / HMSFIX items from `GET /api/hms/items`. |
| `sonniva-frame` | Separate Sonniva Frame app on its own server. Its users were migrated here once (`migrate:frames-users`, uses the `FRAMES_DB_*` connection; not needed for daily work). |

---

## 14. Ownership & access

| What | Owner / who to ask |
|---|---|
| GitHub organization `sonniva-georgia` | Director |
| Production server (DigitalOcean) | Director |
| Domain & DNS (`sonniva.ge`) | Director |
| Business Central API app (Azure) | Director |
| Bank merchant accounts (TBC / BOG / PCB) | Director |
| OnWay, SMS Office, Google accounts | Director |
| Production `.env` and database backups | Director |
