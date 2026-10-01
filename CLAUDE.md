# Ecommerce CMS

Laravel coding guidelines (Boost) live in AGENTS.md and must be followed:

@AGENTS.md

## Project Overview

- A **reusable, headless e-commerce CMS**: a standalone backend/API platform for a **single store** (one business sells its own products) that any external storefront can be connected to.
- **This project contains no storefront UI.** Never build product listing, product details, cart, checkout, account or other public shopping pages in Vue (or Blade). External storefronts (Vue, Nuxt, React, Next.js, mobile apps, ...) live in their own projects and consume the **Storefront API**.
- **This project contains:**
  - The **backend** (domain logic, database, integrations).
  - The **Storefront API** (`/api/v1/*`): everything a storefront needs, including the customer/account features (registration, login, password reset, profile, later cart/checkout/orders). Customer features are built as API only.
  - The **Admin panel** (`/admin`): a fully implemented Vue 3 SPA for staff.
- The backend and API must stay **decoupled from any specific storefront**: no storefront URLs, page structure or framework assumptions in backend code. Content that a storefront renders (slugs, SEO title/description, images, translations) is provided as **data through the API**; rendering, routing, SEO tags and sitemaps are the storefront's job.
- Commerce and content management are equally important: catalog, orders, payments, shipping plus CMS content (pages, blog, menus, ...), all managed in the admin panel and exposed via the API.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3, Pest 4, Pint.
- **Admin panel:** Vue 3 + TypeScript SPA, served at `/admin` (`/` redirects there). The only frontend in this repo.
- **Styling:** Tailwind CSS 4.
- **API:** JSON REST API using Eloquent API Resources, versioned (`/api/v1`), authenticated with Sanctum. Two areas:
  - **Storefront API** (`/api/v1/*`): public endpoints + customer endpoints authenticated with **Bearer tokens** (Sanctum personal access tokens). Usable cross-origin (CORS) by any client; no cookies.
  - **Admin API** (`/api/v1/admin/*`): used only by the bundled admin SPA, authenticated with **Sanctum cookie sessions** (same domain).
- **Database:** MySQL / MariaDB, in local development and production.
- **Font:** Vazirmatn (self-hosted via the `vazirmatn` npm package, no CDN).

### Approved Packages
- **PHP:** `laravel/sanctum`, `spatie/laravel-permission`, `spatie/laravel-activitylog` (audit log; do not build a custom one), `larastan/larastan` (dev).
- **JS:** `vue`, `@vitejs/plugin-vue`, `vue-router`, `pinia`, `axios`, `vue-i18n`, `vazirmatn`.
- **JS dev:** `typescript`, `vue-tsc`, `eslint`, `eslint-plugin-vue`, `typescript-eslint`, `prettier`, `eslint-config-prettier`, `prettier-plugin-tailwindcss`.
- Any other package requires the user's approval first.

## Requirements

### Users, Auth & Permissions
- All users (customers and staff) live in **one `users` table**; access is controlled by **roles & permissions** (spatie/laravel-permission), not by a type column.
- **Every user has exactly one role.** Fixed roles, **no role editor** (don't build one until asked):
  - **`super-admin`**: everything (a `Gate::before` rule grants all permissions). Only role that can anonymise customers, manage staff and view the audit log.
  - **`admin`**: view, edit and deactivate customers; view staff.
  - **`author`**: admin-panel access only for now; content permissions are added with the content (pages/blog) module.
  - **`customer`**: assigned on registration; no admin access.
- Permissions live in `App\Enums\Permission`; each role's permissions in `UserRole::permissions()`; `RolesAndPermissionsSeeder` syncs them. Adding a permission = add the enum case + list it on the roles that get it + re-run the seeder.
- **Phone number is the identifier**: required and unique for every user (only an anonymised customer has none); email is optional.
- **Registration (customers, API only):** form with name, phone number, password (required) and optional email → phone verified by OTP → account active and an API token is returned.
- **Login (customers and staff), two methods:**
  - Phone number + password.
  - Phone number → OTP verification code.
- **Staff** log in the same way on a separate `/admin` login page; the admin API rejects users without a staff role.
- **Password reset:** phone number → OTP → set a new password.
- **No user enumeration:** requesting a login/password-reset OTP for an unregistered phone returns the same generic success response ("if this number is registered, a code was sent"); password login with an unknown phone returns the generic `invalid_credentials`.
- **Phone numbers:** Iranian mobile only, normalized to `09xxxxxxxxx` (Persian/Arabic digits and `+98` / `0098` / `9...` prefixes accepted as input).
- **OTP rules** (configurable in `config/otp.php`): 5 digits, stored hashed, 2-minute lifetime, max 5 wrong attempts per code, 60s resend cooldown, one active code per phone + purpose, rate-limited per phone and per IP.
- **Unverified registrations** are deleted automatically after 24 hours (scheduled task).
- **Customer auth (Storefront API): Bearer tokens.** Every successful customer login, registration verification or password reset returns `{ data: { token, token_type: "Bearer", expires_at, customer } }`. The client sends `Authorization: Bearer <token>`. Tokens carry the `customer` ability, are named by the optional `device_name` field (default `storefront`), expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 30 days) and are pruned daily. Logout revokes only the token used; a password reset or deactivation revokes all of the customer's tokens. How a storefront stores the token is up to that storefront.
- **Staff auth (Admin API): Sanctum cookie sessions** for the bundled admin SPA (no tokens in localStorage). Admin API requires the `access-admin-panel` permission.
- **CORS:** `config/cors.php` allows `api/*` from the origins in `CORS_ALLOWED_ORIGINS` (comma-separated; `*` by default, set real storefront domains in production). No credentials (cookies) are used cross-origin. `Retry-After` is exposed.
- **Login is required to checkout** (no guest checkout).
- **Inactive accounts** can't log in, and an account deactivated while logged in is rejected on its next request (`active` middleware on every authenticated route). `last_login_at` is recorded on every login.

### User Management (admin panel)
- **Customers** (`customers.*` permissions):
  - List/search (name, phone, email; Persian digits accepted), filter by status (active/inactive/anonymised) and registration (completed/pending); view; edit name, email, language. **Staff cannot change a customer's phone number** (it needs OTP verification by the customer).
  - **Deactivate:** keeps all data, logs the customer out everywhere, blocks login. Reversible.
  - **Anonymise** (super admin only, irreversible, for data-deletion requests): removes name, phone, email and password and their OTP codes, ends all sessions; the account row and id stay so records such as orders keep pointing at it. Anonymised customers can't be edited or reactivated.
  - Customers are never hard-deleted.
- **Staff** (`staff.view` for admins; `staff.manage` super admin only):
  - **Add staff = phone number + role only.** An existing customer with that number is promoted (one account); otherwise a verified account is created with **no usable password** — staff log in with an SMS code. Never set passwords for others.
  - Change role, activate/deactivate (deactivating ends sessions), remove staff access (the account becomes a customer).
  - **Safety rules** (server-enforced): nobody can change their own role/status or remove themselves; the last active super admin can't be demoted, deactivated or removed.
- **Audit log** (`spatie/laravel-activitylog`, log name `admin`, viewable by super admin): every customer/staff change is logged with causer, subject and event (`customer.updated|activated|deactivated|anonymised`, `staff.added|role_changed|activated|deactivated|removed`). **Properties never contain personal data** — only changed field names and roles — so anonymisation leaves no personal data in the log.

### Localization & Theming
- Two languages: **Farsi (fa)** and **English (en)**. The API localizes messages from the `Accept-Language` header; translatable content (e.g. category/product names) will be returned per locale.
- Admin panel: Farsi/English with **RTL** layout switching, **Dark** and **Light** mode.

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

- **Actions, Models, Data, Enums, Contracts and Services are shared** by the admin and storefront APIs. Only the HTTP layer (controllers, requests, resources) is split per area.

### API Conventions
- Versioned under `/api/v1`, authenticated with Sanctum.
- Two API areas:
  - `/api/v1/*`: **Storefront API** for external storefronts (public endpoints + customer endpoints behind a Bearer token). It is the product's public contract: keep it stable, generic and client-agnostic; breaking changes need a new version.
  - `/api/v1/admin/*`: **admin** (authenticated staff only, every endpoint permission-checked).
- Admin and storefront have separate Resources; storefront resources must never expose admin-only fields. A resource with no account data (e.g. `OtpChallengeResource`) may live at `Http/Resources/V1/` and be shared.
- Errors are rendered by `App\Exceptions\ApiExceptionRenderer` (framework errors) and `App\Exceptions\AuthException` (auth error codes). Add new domain error codes as named constructors on a dedicated exception, with messages in `lang/{fa,en}`.
- `SetLocaleFromHeader` runs before authentication/throttling so every error is translated. Clients (the admin SPA and storefronts) should send `Accept-Language` with the user's chosen locale.

### Auth API (`/api/v1`)
- Storefront `auth/*`: `register`, `register/otp` (resend), `register/verify`, `login`, `login/otp`, `login/otp/verify`, `password/otp`, `password/reset`, `me`, `logout`. `register/verify`, `login`, `login/otp/verify` and `password/reset` accept an optional `device_name` and return a token (`CustomerTokenResource`); `me` and `logout` need the Bearer token.
- Admin `admin/auth/*`: `login`, `login/otp`, `login/otp/verify`, `me`, `logout`.
- Admin SPA flow: `GET /sanctum/csrf-cookie` first, then send the `X-XSRF-TOKEN` header (axios does this automatically).
- Tests: call the Storefront API like an external client with `asStorefront($token, $method, $uri)`; check a login response with `assertIssuedTokenFor($response, $user)` and failures with `assertNoTokenIssued()` (`tests/Pest.php`).
- Form requests share phone/code normalization and rules via `App\Http\Requests\Concerns\HasAuthFields`.
- SMS drivers: `log` (local dev, codes appear in `storage/logs/laravel.log`), `array` (tests; read codes with the `sms()` Pest helper), `faraz` (production).

### User Management API (`/api/v1/admin`, all behind `auth:sanctum` + `active` + `access-admin-panel`)
| Method & path | Permission |
|---|---|
| `GET customers` (`q`, `status`, `registration`, `page`, `per_page`) | `customers.view` |
| `GET customers/{customer}` | `customers.view` |
| `PUT customers/{customer}` (`name`, `email`, `locale`) | `customers.update` |
| `PUT customers/{customer}/status` (`is_active`) | `customers.deactivate` |
| `POST customers/{customer}/anonymise` | `customers.anonymise` (super admin) |
| `GET staff` (`q`, `role`, `status`, `page`, `per_page`) | `staff.view` |
| `POST staff` (`phone_number`, `role`, `name` for new accounts) | `staff.manage` (super admin) |
| `PUT staff/{staffMember}/role` · `PUT staff/{staffMember}/status` · `DELETE staff/{staffMember}` | `staff.manage` (super admin) |
| `GET activity-log` (`subject_id`, `causer_id`, `event`) | `activity-log.view` (super admin) |

- `{customer}` / `{staffMember}` route bindings (AppServiceProvider) only resolve users of that kind — anything else is 404.
- Rule violations return `AccountActionException` codes: `cannot_change_own_account`, `last_super_admin`, `customer_anonymised` (409), `already_staff`, `name_required` (422).
- The admin `me` endpoint returns **effective** permissions (the full list for a super admin); the frontend checks them with `useAdminAuthStore().can(Permission.X)` from `resources/js/admin/permissions.ts`. The UI only hides what the API also refuses.
- Success responses use the default Laravel Resource shape (`{ data, meta, links }`).
- One error shape for every error, messages localized from the `Accept-Language` header (`fa` / `en`):

```json
{ "message": "...", "code": "otp_invalid", "errors": { "field": ["..."] } }
```

### Frontend Structure (admin panel only: Vue 3 + TypeScript, feature modules)

```
resources/js/
  admin/                       staff app, served at /admin
    app.ts  router/  layouts/
    permissions.ts             Permission / StaffRole constants mirroring the backend enums
    modules/{feature}/         auth, customers, staff, activity-log, products, orders, content, settings, ...
  shared/                      generic code only, no feature logic, never imports from admin/
    ui/                        reusable UI kit (Button, Input, Modal, ...)
    composables/               useTheme, useLocale, ...
    http/                      axios client
    i18n/                      shared translations (fa, en)
    types/                     shared API types
```

- Each feature module contains `pages/`, `components/`, `api.ts`, `store.ts` (Pinia), `types.ts`.
- Laravel serves the admin Blade shell (`admin.blade.php`) via a catch-all `/admin/*` web route; `/` redirects to `/admin`. There is no other web page.

### Frontend Conventions
- **Colors:** use the semantic Tailwind tokens from `resources/css/app.css` (`bg-bg`, `bg-surface`, `text-fg`, `text-fg-muted`, `border-line`, `bg-primary`, `text-on-primary`, `text-danger`, ...). They switch automatically in dark mode, so components don't repeat `dark:` variants. Never use raw hex colors in components.
- **RTL:** use logical utilities (`ps-*`, `pe-*`, `text-start`, `rtl:*`); phone numbers and codes are always `dir="ltr"`.
- **Digits:** v-models hold Latin digits; show Persian digits in Farsi with `useLocale().localizeDigits()` (inputs: `PhoneField`, `OtpInput`).
- **API calls:** go through `@/shared/http/client` (`http`), which handles the CSRF cookie, `Accept-Language` and turns every failure into `ApiError`. Forms use `useApiForm([...fields])` for loading state and inline/general errors.
- **Text:** every UI string lives in `resources/js/shared/i18n/{fa,en}.ts` (`en` is typed against `fa`, so a missing key fails typecheck).
- **Shared UI kit** (`resources/js/shared/ui`): `AppButton` (variants primary/secondary/danger/danger-outline, sizes lg/md), `TextField`, `PhoneField`, `OtpInput`, `SegmentedControl`, `SelectField` (native select), `SearchInput` (debounced), `AppDialog` (focus trap, Esc, bottom sheet on phones), `ActionMenu` (row "⋯" menu, keyboard navigable), `AppPagination`, `AppBadge`, `AppAvatar`, `EmptyState`, `FormAlert`, `ToastHost` + `useToast()`, `PreferenceSwitches`, `BrandMark`, `AppIcon`, `AppSpinner`. Reuse these before creating new ones.
- **Formatting:** `useLocale()` gives `formatNumber`, `formatDate` (Solar Hijri in Farsi), `formatDateTime`, `formatRelativeTime`, `localizeDigits`.
- **Admin app:** `AdminLayout`: on desktop (`lg`+) the sidebar is open by default and the menu button collapses it to an icon rail (choice remembered in `localStorage`, content widens); below `lg` it is a drawer over the page. Menu items live in `admin/navigation.ts` with the permission they need; pages declare `meta.permission` and the router redirects users who lack it. Show actions only when `useAdminAuthStore().can(...)` allows them. Lists keep their filters in the URL query (customers). Destructive actions go through a confirmation dialog; irreversible ones (anonymise) require typing the customer's phone number.
- Language and theme preferences are stored in `localStorage` and applied before first paint by `resources/views/partials/spa-head.blade.php`.

### Storefront Data (SEO, URLs, media)
- Public content (products, categories, pages, blog posts) has a **slug** and **SEO fields** (meta title, meta description, image) that admins edit and the Storefront API returns. Storefronts build their own URLs, meta tags, Open Graph, JSON-LD and sitemaps from this data.
- The CMS renders no public pages, `sitemap.xml` or storefront meta tags. If needed later, provide data endpoints (e.g. a list of all public slugs with `updated_at`) rather than storefront markup.

### Code Quality
- **Pint** for PHP formatting.
- **Larastan (PHPStan)** starting at level 6.
- **Pest architecture tests** that enforce only the agreed architecture above. No extra restrictions or abstractions.
- **ESLint + Prettier** for Vue/TypeScript.

### Commands
- PHP: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G`, `php artisan test --compact`.
- JS: `npm run typecheck`, `npm run lint`, `npm run format`, `npm run build` (or `npm run dev`).
- Local DB: MariaDB via WAMP, database `ecommerce-cms`; tests use `ecommerce_cms_test` (set in `phpunit.xml`).
- Env: `CORS_ALLOWED_ORIGINS` (storefront domains), `SANCTUM_TOKEN_EXPIRATION` (customer token lifetime in minutes).

### Git
- A single `main` branch; commit directly to `main`.

## Open Questions (not yet decided)

Ask the user before implementing anything related to these:

- Discounts / coupons / sale prices and tax (VAT).
- CMS scope: pages, blog, menus, media library, SEO fields.
- API documentation for storefront developers (e.g. OpenAPI).
- Categories: translations package, image handling, slug language, max depth, which roles manage them.
- Order lifecycle: statuses, refunds, returns, invoices, notifications.
- Extras: search, reviews, wishlists, reports/dashboards, import/export.
- Further packages beyond the approved list (e.g. media library, translatable content).
- Deployment target (Laravel Cloud, VPS, shared hosting).
- Testing expectations (coverage level).
- Build order / first milestone.

## Working Agreement

- Do not implement a section until the user asks for it.
- Ask questions one by one when requirements are unclear.
