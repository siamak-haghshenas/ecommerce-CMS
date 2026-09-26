# Ecommerce CMS

Laravel coding guidelines (Boost) live in AGENTS.md and must be followed:

@AGENTS.md

## Project Overview

- A **single-store** ecommerce platform: one business sells its own products.
- **Commerce and content management are equally important**: a full store (products, cart, checkout, orders) plus CMS features.
- The current state is a fresh Laravel skeleton; no domain features are implemented yet.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3, Pest 4, Pint.
- **Two frontends, both core parts of the project, both in this repo:**
  - **Storefront** (customer-facing): Vue 3 + TypeScript SPA, served at `/`.
  - **Admin panel** (staff): Vue 3 + TypeScript SPA, served at `/admin`.
  - Each is a separate Vite entry, sharing only generic code (UI kit, http client, i18n, theme/locale, API types).
- **Styling:** Tailwind CSS 4.
- **API:** JSON REST API using Eloquent API Resources, versioned (`/api/v1`), authenticated with Sanctum (cookie-based SPA auth on the same domain). Split into a storefront area and an admin area.
- **Database:** MySQL / MariaDB, in local development and production.
- **Font:** Vazirmatn (self-hosted via the `vazirmatn` npm package, no CDN).

### Approved Packages
- **PHP:** `laravel/sanctum`, `spatie/laravel-permission`, `larastan/larastan` (dev).
- **JS:** `vue`, `@vitejs/plugin-vue`, `vue-router`, `pinia`, `axios`, `vue-i18n`, `vazirmatn`.
- **JS dev:** `typescript`, `vue-tsc`, `eslint`, `eslint-plugin-vue`, `typescript-eslint`, `prettier`, `eslint-config-prettier`, `prettier-plugin-tailwindcss`.
- Any other package requires the user's approval first.

## Requirements

### Users, Auth & Permissions
- All users (customers and staff) live in **one `users` table**; access is controlled by **roles & permissions** (spatie/laravel-permission), not by a type column.
- Roles seeded now: **`super-admin`** (bypasses all permission checks, manages admins/roles; created by seeder only), **`admin`** (full admin panel except managing roles/admins), **`customer`** (assigned on registration, no admin access).
- Planned later, when their features exist: `content-editor`, `product-manager`, `order-manager`.
- **Phone number is the identifier**: required and unique for every user; email is optional.
- **Registration (customers):** form with name, phone number, password (required) and optional email → phone verified by OTP → account active and logged in.
- **Login (customers and staff), two methods:**
  - Phone number + password.
  - Phone number → OTP verification code.
- **Staff** log in the same way on a separate `/admin` login page; the admin API rejects users without a staff role.
- **Password reset:** phone number → OTP → set a new password.
- **No user enumeration:** requesting a login/password-reset OTP for an unregistered phone returns the same generic success response ("if this number is registered, a code was sent"); password login with an unknown phone returns the generic `invalid_credentials`.
- **Phone numbers:** Iranian mobile only, normalized to `09xxxxxxxxx` (Persian/Arabic digits and `+98` / `0098` / `9...` prefixes accepted as input).
- **OTP rules** (configurable in `config/otp.php`): 5 digits, stored hashed, 2-minute lifetime, max 5 wrong attempts per code, 60s resend cooldown, one active code per phone + purpose, rate-limited per phone and per IP.
- **Unverified registrations** are deleted automatically after 24 hours (scheduled task).
- **SPA auth:** Sanctum cookie sessions for both apps (no tokens in localStorage). Admin API requires the `access-admin-panel` permission.
- **Login is required to checkout** (no guest checkout).

### Localization & Theming
- Two languages: **Farsi (fa)** and **English (en)**, with **RTL** layout switching for Farsi.
- Two themes: **Dark mode** and **Light mode**.

### Products
- Products support **variants** (e.g. size/color), each with its own SKU, price, and stock.
- Filterable **attributes**, **categories**, and **tags**.

### Pricing & Currency
- **One store currency**, selected by the admin in settings. All prices are stored and displayed in that currency.

### Payments
- First gateway: **Zarinpal** (Iranian gateway).
- Must be built behind a **pluggable gateway interface/driver** so gateways can be swapped or added easily.

### SMS
- Provider: **Faraz SMS** (used for OTP).
- Must also be behind a **pluggable driver interface**, so the provider can be changed easily.

### Shipping
- **Admin-defined shipping methods** (e.g. post, courier, free over X) with configurable rates.

## Architecture & Clean Code

### Backend Structure (Layered + Actions)
Standard Laravel folders, grouped by domain inside each layer:

```
app/
  Actions/{Domain}/            one class per use case (e.g. Actions/Auth/SendOtpAction)
  Contracts/                   interfaces for swappable integrations (SmsSender, PaymentGateway)
  Data/{Domain}/               final readonly data objects passed into Actions
  Enums/                       UserRole, OtpPurpose, OrderStatus, ...
  Events/  Listeners/          side effects (notifications, SMS) via queued listeners
  Http/Controllers/Api/V1/Admin/{Domain}/
  Http/Controllers/Api/V1/Storefront/{Domain}/
  Http/Controllers/Web/        serves the two SPA shells (+ SEO meta for storefront)
  Http/Requests/Api/V1/{Admin|Storefront}/{Domain}/
  Http/Resources/V1/{Admin|Storefront}/
  Models/
  Services/{Integration}/      managers + drivers (e.g. Services/Sms/Drivers/FarazSmsDriver)
```

### Rules
- **Thin controllers:** validate via FormRequest → build a data object → call one Action → return an API Resource.
- **Actions:** single-purpose, `final`, one public `handle()` method, reusable from controllers, jobs and commands.
- **Data objects:** plain `final readonly` PHP classes (no spatie/laravel-data), typically built from the FormRequest (e.g. `$request->toData()`).
- **No repository pattern.** Use Eloquent directly; put reusable queries in model scopes.
- **Swappable integrations** (payment, SMS, later shipping) sit behind a contract in `app/Contracts`, with drivers chosen via config (Laravel Manager pattern). Adding a provider = adding a driver class.
- **Enums** for fixed value sets; no magic strings.
- **Events + queued listeners** for side effects, to keep the main flow clean.
- **`declare(strict_types=1);`** in every PHP file.

- **Actions, Models, Data, Enums, Contracts and Services are shared** by the admin and storefront. Only the HTTP layer (controllers, requests, resources) is split per area.

### API Conventions
- Versioned under `/api/v1`, authenticated with Sanctum.
- Two API areas:
  - `/api/v1/*`: **storefront** (public endpoints + authenticated customer endpoints).
  - `/api/v1/admin/*`: **admin** (authenticated staff only, every endpoint permission-checked).
- Admin and storefront have separate Resources; storefront resources must never expose admin-only fields. A resource with no account data (e.g. `OtpChallengeResource`) may live at `Http/Resources/V1/` and be shared.
- Errors are rendered by `App\Exceptions\ApiExceptionRenderer` (framework errors) and `App\Exceptions\AuthException` (auth error codes). Add new domain error codes as named constructors on a dedicated exception, with messages in `lang/{fa,en}`.
- `SetLocaleFromHeader` runs before authentication/throttling so every error is translated. The SPAs must always send `Accept-Language` with the user's chosen locale.

### Auth API (`/api/v1`)
- Storefront `auth/*`: `register`, `register/otp` (resend), `register/verify`, `login`, `login/otp`, `login/otp/verify`, `password/otp`, `password/reset`, `me`, `logout`.
- Admin `admin/auth/*`: `login`, `login/otp`, `login/otp/verify`, `me`, `logout`.
- SPA flow: `GET /sanctum/csrf-cookie` first, then send the `X-XSRF-TOKEN` header (axios does this automatically).
- Form requests share phone/code normalization and rules via `App\Http\Requests\Concerns\HasAuthFields`.
- SMS drivers: `log` (local dev, codes appear in `storage/logs/laravel.log`), `array` (tests; read codes with the `sms()` Pest helper), `faraz` (production).
- Success responses use the default Laravel Resource shape (`{ data, meta, links }`).
- One error shape for every error, messages localized from the `Accept-Language` header (`fa` / `en`):

```json
{ "message": "...", "code": "otp_invalid", "errors": { "field": ["..."] } }
```

### Frontend Structure (two Vue 3 + TypeScript apps, feature modules)

```
resources/js/
  storefront/                  customer-facing app, served at /
    app.ts  router/  layouts/
    modules/{feature}/         auth, catalog, cart, checkout, account, pages, blog, ...
  admin/                       staff app, served at /admin
    app.ts  router/  layouts/
    modules/{feature}/         auth, products, orders, customers, users, content, settings, ...
  shared/                      generic code only, no feature logic
    ui/                        reusable UI kit (Button, Input, Modal, ...)
    composables/               useTheme, useLocale, ...
    http/                      axios client
    i18n/                      shared translations (fa, en)
    types/                     shared API types
```

- Each feature module contains `pages/`, `components/`, `api.ts`, `store.ts` (Pinia), `types.ts`.
- `storefront/` and `admin/` must **never import from each other**; common code goes in `shared/`.
- Both apps support Farsi/English with RTL switching and Dark/Light mode (via `shared/`).
- Laravel serves one Blade shell per app (`storefront.blade.php`, `admin.blade.php`) via catch-all web routes.

### Storefront SEO
- Laravel web routes for public storefront URLs (products, categories, CMS pages, blog posts) render the storefront shell with server-side **title, meta description, Open Graph tags, canonical URL, hreflang (fa/en) and JSON-LD**. The Vue SPA then mounts and takes over.
- Provide `sitemap.xml` and `robots.txt`.

### Code Quality
- **Pint** for PHP formatting.
- **Larastan (PHPStan)** starting at level 6.
- **Pest architecture tests** that enforce only the agreed architecture above. No extra restrictions or abstractions.
- **ESLint + Prettier** for Vue/TypeScript.

### Commands
- PHP: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G`, `php artisan test --compact`.
- JS: `npm run typecheck`, `npm run lint`, `npm run format`, `npm run build` (or `npm run dev`).
- Local DB: MariaDB via WAMP, database `ecommerce-cms`; tests use `ecommerce_cms_test` (set in `phpunit.xml`).

### Git
- A single `main` branch; commit directly to `main`.

## Open Questions (not yet decided)

Ask the user before implementing anything related to these:

- Discounts / coupons / sale prices and tax (VAT).
- CMS scope: pages, blog, menus, media library, SEO fields.
- Order lifecycle: statuses, refunds, returns, invoices, notifications.
- Extras: search, reviews, wishlists, reports/dashboards, import/export.
- Further packages beyond the approved list (e.g. media library, translatable content).
- Deployment target (Laravel Cloud, VPS, shared hosting).
- Testing expectations (coverage level).
- Build order / first milestone.

## Working Agreement

- Do not implement a section until the user asks for it.
- Ask questions one by one when requirements are unclear.
