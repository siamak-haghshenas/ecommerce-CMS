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
- **PHP:** `laravel/sanctum`, `spatie/laravel-permission`, `spatie/laravel-activitylog` (audit log; do not build a custom one), `spatie/laravel-medialibrary` (all uploaded images), `larastan/larastan` (dev).
- **JS:** `vue`, `@vitejs/plugin-vue`, `vue-router`, `pinia`, `axios`, `vue-i18n`, `vazirmatn`, `vue-draggable-plus` (drag-and-drop sorting).
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
- Farsi (fa) / English (en) translations are **only for the CMS interface itself**: the admin panel UI and API messages (errors, validation), chosen by the `Accept-Language` header.
- **Store content is not translated.** Categories, products, pages etc. have one name/description/SEO text, stored and returned exactly as the admin typed it. No translations package.
- Admin panel: Farsi/English with **RTL** layout switching, **Dark** and **Light** mode.

### Products (built in 4 phases, each reviewed and committed: 1 attributes & options ✅, 2 products core, 3 admin UI, 4 storefront catalog API)
- **Variants** are generated from **options** (e.g. Size × Color) into a grid; unwanted combinations can be disabled. Each variant (SKU) has its own price, sale price and stock. A simple product has one default variant.
- **Specifications** use global **attributes** assigned to categories (inherited by sub-categories). A product in several categories gets the combined attributes of all its categories and their ancestors. Filterable attributes become storefront filters.
- **Categories:** several per product, one primary (breadcrumbs, URLs). **Brand:** one, optional. **Tags:** typed freely with suggestions, plus a Tags page (rename, merge, delete); tags have slugs for storefront filtering.
- **Money:** whole **Rials** everywhere (`unsigned bigint`). A store setting chooses Toman or Rial display; the API also returns raw Rials.
- **Sale price** per SKU with optional start/end dates; storefronts get current price, regular price and discount %. Coupons and tax come later with checkout.
- **Stock:** quantity per SKU; per product "allow backorders" or "don't track stock"; low-stock threshold (store default, overridable per product); every change logged in `stock_movements` (who, reason, before → after).
- **Description:** Tiptap (`@tiptap/vue-3`) in the admin; the server cleans the HTML with `symfony/html-sanitizer` (both approved, install in phase 2/3).
- **Search:** a normalized `search_text` column (name, brand names, SKUs, tags, categories) using the SEO text normalization, so Arabic/Persian letters, half-spaces and digits match.
- **Status:** draft / published / archived (+ scheduled `published_at`). Products and variants are **soft-deleted** (orders will point at them). Deleting a category or brand with products is refused.
- **Who manages:** Super Admin and Admin (`products.view`, `products.manage`).
- **Planned tables (phase 2):** `products`, `category_product` (is_primary), `product_options`, `product_variants`, `option_value_product_variant`, `product_attribute_values` (value_text / value_number / value_boolean / attribute_value_id), `tags`, `product_tag`, `stock_movements`, `settings` (key-value: price display currency, low-stock default). The gallery uses medialibrary.

### Attributes & Options (products phase 1)
- **Attributes** (`attributes`, `attribute_values`): name, Latin slug (unique; used in storefront filter URLs), `type` (`text`, `number` + optional `unit`, `boolean`, `select` with ordered values), `is_filterable`, manual `position`. Only `select` attributes have values; values have a Latin slug unique within the attribute. Saving an attribute syncs its values (update by id, create new, delete missing, order = array order).
- **Options** (`options`, `option_values`): variant dimensions with `display_type` `text` (buttons) or `color` (swatches; each value then needs `color_hex`), manual `position`, values synced the same way.
- **Category attributes** (`attribute_category`, ordered): `PUT admin/categories/{category}/attributes` sets a category's own attributes; `GET` returns own + inherited (from ancestors, with the category they come from). Products (phase 2) get the union over all their categories.
- **Who manages:** Super Admin and Admin (`attributes.view`, `attributes.manage`; assigning attributes to a category needs `categories.manage`).
- Reordering (`PUT attributes/order`, `PUT options/order`) takes every id of the list (409 `catalog_list_changed` otherwise), like brands.
- Deleting an attribute, option or value is free for now; phase 2 adds "refused while products use it".
- **Audit log:** `attribute.created|updated|deleted|reordered`, `option.created|updated|deleted|reordered`, `category.attributes_changed`.
- Admin UI: Catalog › Attributes (tabs Attributes / Options, drag-and-drop order, form dialog with a values editor) and an "Attributes" section in the category form.

### Categories
- Fields: `parent_id`, `name`, `slug`, `description`, `meta_title`, `meta_description`, `position` (order among siblings), `is_active`, plus one image (medialibrary collection `image`).
- **Unlimited depth.** Stored as `parent_id` only; `App\Support\CategoryTree` loads all categories in one query, caches the tree as plain arrays (cleared on every category save/delete) and answers ancestors, descendants, visibility and "can move" questions. No tree package.
- **Slug:** Latin only (`a-z`, `0-9`, single hyphens), unique. While creating, the admin UI suggests one from the name (Persian is transliterated to Finglish by `suggestSlug()` in `admin/modules/categories/tree.ts`, e.g. «ایران ۱۴۰۳» → `iran-1403`); the admin can edit it, and editing stops the suggestion.
- **Image:** `spatie/laravel-medialibrary`, one image per category (jpg/png/webp), conversions `thumb` and `medium` (webp) generated immediately (not queued). The API returns `{ original, thumb, medium }` URLs.
- **Moving:** a category can never move under itself or one of its descendants (`category_invalid_parent`).
- **Delete** is refused while the category has sub-categories (`category_has_children`) or products (`category_has_products`, added with the products module). Nothing is moved or unlinked automatically; the admin moves them first.
- **Hiding:** `is_active = false` hides the category **and its whole branch** from the Storefront API. Each sub-category keeps its own flag, so showing the parent restores the branch as it was; the admin tree shows them as "hidden by parent" (`is_visible = false`).
- **Who manages:** Super Admin and Admin (`categories.view`, `categories.manage`). Author has no access.
- **Admin UI:** an expandable tree. Drag and drop (`vue-draggable-plus`) on desktop, plus "move up / move down / move to…" actions on every row for phones and keyboards.
- **Audit log:** `category.created`, `category.updated` (changed field names, incl. `image`), `category.moved` (from/to parent ids), `category.reordered`, `category.deleted` (name and slug kept in properties, as category data is not personal).

### Brands
- **Flat list** with a **manual order** (`position`): drag and drop (`vue-draggable-plus`) plus "move up / move down" on every row. New brands go last. `PUT admin/brands/order` takes the complete list of ids (409 `brand_list_changed` when it doesn't match the current brands).
- Fields: `name` (unique; as the brand writes it, e.g. Adidas), `persian_name` (optional, unique when set, e.g. آدیداس; searchable in the admin, returned by the Storefront API, given to the AI as `alternate_name`), `slug` (Latin, unique, suggested via Finglish like categories), `description`, `website` (optional http/https URL), `is_active`, `position`, the shared SEO fields, and one **logo** (medialibrary collection `logo`): **JPG, PNG or WebP only** (no SVG: it can carry scripts); conversions `thumb` (240px, fitted without cropping) and `medium` (600px), webp. The form suggests a PNG with a transparent background.
- **Products:** one brand per product (`brand_id`, added with products). Delete is refused while a brand has products (`brand_has_products`, added with the products module); hiding is the alternative.
- **Who manages:** Super Admin and Admin (`brands.view`, `brands.manage`). Author has no access.
- The admin list returns **all** brands (no pagination, as the order is managed by hand); search and filters run in the browser.
- **Storefront API:** `GET /api/v1/brands` (visible brands in position order), `GET /api/v1/brands/{slug}` (logo, description, website, SEO fields). Hidden or unknown → 404.
- **Audit log:** `brand.created`, `brand.updated` (changed field names, incl. `logo`), `brand.reordered`, `brand.deleted` (name kept).

### SEO Analysis & AI Suggestions (shared: categories and brands)
- **One shared SEO module** for every content type with a public page (now categories and brands; later products, pages, blog). A content type plugs in with: the SEO columns (`meta_title`, `meta_description`, `focus_keyphrase`, `seo_score`, `readability_score`), the `App\Models\Concerns\HasSeo` trait (+ `seoImageCollection()`), a case in `App\Enums\SeoContentType`, and the shared `SeoPanel` (`admin/modules/seo`) in its admin form.
- **Focus keyphrase:** stored `focus_keyphrase` per item (admin-only, never in the Storefront API). Duplicate keyphrases are flagged by the `keyphrase_unique` check, compared among items of the same content type.
- **Analysis** (colored indicators like WordPress SEO plugins): every check is rated `good` 🟢 / `ok` 🟠 / `bad` 🔴; score = average of good 1, ok 0.5, bad 0 (×100). Overall color: ≥70 good, ≥40 ok, else bad; null = not analysed (grey).
  - **SEO checks:** `keyphrase_set`, `keyphrase_in_title`, `keyphrase_in_meta_description`, `keyphrase_in_slug` (Finglish-slugified keyphrase words in the slug), `keyphrase_in_description` (density over 3% = stuffing), `keyphrase_unique`, `title_length` (30–60 chars), `meta_description_length` (120–160), `description_length` (≥50 words), `image`. Keyphrase checks are skipped while no keyphrase is set.
  - **Readability checks** (only once the description has ≥50 words; otherwise no readability score): `sentence_length` (≤25% of sentences over 20 words), `paragraph_length` (≤100 words), `transition_words` (≥30% of sentences, Persian + English lists), `consecutive_sentences` (no 3+ sentences starting with the same word). No English reading-ease formulas: they don't apply to Persian.
  - Text is normalized before matching: Arabic ي/ك → ی/ک, Persian/Arabic digits → Latin, diacritics and tatweel removed, half-space (ZWNJ) → space, lowercase.
- **Runs twice from the same rules:** live in the browser (`admin/modules/seo/analysis.ts`, self-contained, no imports) and on the server on every save (`ScoreSeoAction` with `App\Support\Seo\SeoAnalyzer`), which stores `seo_score` and `readability_score` (shown as a dot in the admin lists, with a "Needs SEO work" filter = score < 70). Both implementations run the shared cases in `tests/Fixtures/seo-analysis.json` (Pest + `npm test`, Node's built-in test runner); **change a rule in both and add a fixture case**. Re-score everything with `php artisan seo:score` (`--type=category|brand`).
- **AI suggestions:** `POST /api/v1/admin/seo-suggestions` with `type` (`category` needs `categories.manage`, `brand` needs `brands.manage`), the current, possibly unsaved, name/description/keyphrase (+ `parent_id` for categories) and an optional `subject_id`; returns **3 keyphrases, 3 SEO titles and 3 meta descriptions** in the content's language (Persian if the name or the optional `alternate_name`, e.g. a brand's Persian name, contains Persian letters). Nothing is saved; the admin picks or edits.
  - Provider behind `App\Contracts\AiTextGenerator` (`AiManager`, drivers chosen by `AI_DRIVER`): `claude` (Anthropic Messages API via Laravel's HTTP client, forced tool use for structured JSON, model `AI_MODEL`, default `claude-sonnet-5`, key `ANTHROPIC_API_KEY`) and `fake` (tests). The key never reaches the browser. Anthropic does not serve Iranian IPs: the server must be hosted outside Iran.
  - Limits (rate limiter `ai`): `AI_LIMIT_PER_USER_PER_HOUR` (20) per user and `AI_LIMIT_PER_DAY` (200) store-wide → 429 `too_many_requests` with `retry_after`.
  - Every call is recorded in `ai_requests` (user, feature `{type}_seo`, subject, model, tokens, status, error code); never the prompt text.
  - Errors use `AiException` codes: `ai_not_configured` (503), `ai_unavailable` (503), `ai_invalid_response` (502). The rest of the SEO section keeps working.

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
| `GET categories` (whole tree, nested) · `GET categories/{category}` | `categories.view` |
| `POST categories` · `PUT categories/{category}` · `DELETE categories/{category}` | `categories.manage` |
| `PUT categories/order` (`parent_id`, `ids`: the full new child order of that parent) | `categories.manage` |
| `POST categories/{category}/image` (multipart `image`) · `DELETE categories/{category}/image` | `categories.manage` |
| `GET brands` (all, in order, not paginated) · `GET brands/{brand}` | `brands.view` |
| `POST brands` · `PUT brands/{brand}` · `DELETE brands/{brand}` · `PUT brands/order` (`ids`: every brand, new order) | `brands.manage` |
| `POST brands/{brand}/logo` (multipart `logo`) · `DELETE brands/{brand}/logo` | `brands.manage` |
| `POST seo-suggestions` (`type`, `name`, …; rate limiter `ai`) | the type's manage permission |
| `GET attributes` · `GET options` (all, in order, with values) | `attributes.view` |
| `POST`/`PUT`/`DELETE attributes[/{attribute}]` · `PUT attributes/order` · the same for `options` | `attributes.manage` |
| `GET categories/{category}/attributes` (own + inherited) | `categories.view` |
| `PUT categories/{category}/attributes` (`ids`, ordered) | `categories.manage` |

- Category rule violations return `CategoryException` codes: `category_has_children`, `category_invalid_parent`, `category_tree_changed` (409: the `ids` sent don't include every current child, reload and retry).
- **Storefront catalog API** (public): `GET /api/v1/categories` (nested tree of visible categories) and `GET /api/v1/categories/{slug}` (detail with description, SEO fields, image, breadcrumb and visible children; hidden or unknown → 404).

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
- **Shared UI kit** (`resources/js/shared/ui`): `AppButton` (variants primary/secondary/danger/danger-outline, sizes lg/md), `TextField`, `TextAreaField` (optional character counter), `PhoneField`, `OtpInput`, `SegmentedControl`, `SelectField` (native select), `SearchInput` (debounced), `AppDialog` (focus trap, Esc, bottom sheet on phones), `ActionMenu` (row "⋯" menu, keyboard navigable), `AppPagination`, `AppBadge`, `AppAvatar`, `EmptyState`, `FormAlert`, `ToastHost` + `useToast()`, `PreferenceSwitches`, `BrandMark`, `AppIcon`, `AppSpinner`. Reuse these before creating new ones.
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
- Env: `CORS_ALLOWED_ORIGINS` (storefront domains), `SANCTUM_TOKEN_EXPIRATION` (customer token lifetime in minutes), `AI_DRIVER`, `ANTHROPIC_API_KEY`, `AI_MODEL`, `AI_LIMIT_PER_USER_PER_HOUR`, `AI_LIMIT_PER_DAY`.
- JS tests: `npm test` (Node's built-in runner, `tests/js`; no test framework installed).

### Git
- A single `main` branch; commit directly to `main`.

## Open Questions (not yet decided)

Ask the user before implementing anything related to these:

- Discounts / coupons / sale prices and tax (VAT).
- CMS scope: pages, blog, menus, media library, SEO fields.
- API documentation for storefront developers (e.g. OpenAPI).
- Order lifecycle: statuses, refunds, returns, invoices, notifications.
- Extras: search, reviews, wishlists, reports/dashboards, import/export.
- Further packages beyond the approved list.
- Deployment target (Laravel Cloud, VPS, shared hosting).
- Testing expectations (coverage level).
- Build order / first milestone.

## Working Agreement

- Do not implement a section until the user asks for it.
- Ask questions one by one when requirements are unclear.
