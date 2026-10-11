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
- **PHP:** `laravel/sanctum`, `spatie/laravel-permission`, `spatie/laravel-activitylog` (audit log; do not build a custom one), `spatie/laravel-medialibrary` (stores the media library's files), `symfony/html-sanitizer` (rich text), `larastan/larastan` (dev).
- **JS:** `vue`, `@vitejs/plugin-vue`, `vue-router`, `pinia`, `axios`, `vue-i18n`, `vazirmatn`, `vue-draggable-plus` (drag-and-drop sorting), `tinymce` + `@tinymce/tinymce-vue` (rich text editor; self-hosted under the **GPL**, `license_key: 'gpl'`, nothing loaded from Tiny's cloud; a commercial key is needed if the CMS is ever distributed as closed source).
- **JS dev:** `typescript`, `vue-tsc`, `eslint`, `eslint-plugin-vue`, `typescript-eslint`, `prettier`, `eslint-config-prettier`, `prettier-plugin-tailwindcss`.
- Any other package requires the user's approval first.

## Requirements

### Users, Auth & Permissions
- All users (customers and staff) live in **one `users` table**; access is controlled by **roles & permissions** (spatie/laravel-permission), not by a type column.
- **Every user has exactly one role.** Fixed roles, **no role editor** (don't build one until asked):
  - **`super-admin`**: everything (a `Gate::before` rule grants all permissions). Only role that can anonymise customers, manage staff and view the audit log.
  - **`admin`**: view, edit and deactivate customers; view staff.
  - **`author`**: admin-panel access, viewing the media library and uploading / editing alt text and titles, and managing pages and menus (`pages.*`, `menus.*`).
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

### Products (built in 4 phases, each reviewed and committed: 1 attributes & options ✅, 2 products core backend ✅, 3 admin UI ✅, 4 storefront catalog API ✅)
- **Variants** are generated from **options** (e.g. Size × Color) into a grid; unwanted combinations can be disabled. Each variant (SKU) has its own price, sale price and stock. A simple product has one default variant.
- **Specifications** use global **attributes** assigned to categories (inherited by sub-categories). A product in several categories gets the combined attributes of all its categories and their ancestors. Filterable attributes become storefront filters.
- **Categories:** several per product, one primary (breadcrumbs, URLs). **Brand:** one, optional. **Tags:** typed freely with suggestions, plus a Tags page (rename, merge, delete); tags have slugs for storefront filtering.
- **Money:** whole **Rials** everywhere (`unsigned bigint`). A store setting chooses Toman or Rial display; the API also returns raw Rials.
- **Sale price** per SKU with optional start/end dates; storefronts get current price, regular price and discount %. Coupons and tax come later with checkout.
- **Stock:** quantity per SKU; per product "allow backorders" or "don't track stock"; low-stock threshold (store default, overridable per product); every change logged in `stock_movements` (who, reason, before → after).
- **Descriptions:** TinyMCE in the admin (short description: basic editor; description: full editor with library images/videos and Aparat/YouTube players); the server cleans the HTML with `symfony/html-sanitizer` plus its own pass (`App\Support\RichText`).
- **Search:** a normalized `search_text` column (name, brand names, SKUs, tags, categories) using the SEO text normalization, so Arabic/Persian letters, half-spaces and digits match.
- **Status:** draft / published / archived (+ scheduled `published_at`). Products and variants are **soft-deleted** (orders will point at them). Deleting a category or brand with products is refused.
- **Who manages:** Super Admin and Admin (`products.view`, `products.manage`).
- **Tables:** `products`, `category_product` (is_primary), `product_options`, `product_variants`, `option_value_product_variant`, `product_attribute_values` (value_text / value_number / value_boolean / attribute_value_id), `tags`, `product_tag`, `stock_movements`, `settings`. The gallery is media library files (`media_usages` role `gallery`, ordered; max 30; the first is the main image).

#### Products backend (phase 2)
- **One save endpoint for the whole form** (`POST`/`PUT admin/products`, `SaveProductRequest` → `SaveProductAction`): fields, `category_ids` + `primary_category_id` (default: first), `tags` (names), `option_ids`, `variants` (in order), `specifications` and `image_ids` (the gallery: library images in order). Structure errors are standard validation errors with field keys like `variants.2.sku`; cross-field checks live in the request's `after()` (SKU unique across all variants incl. deleted, case-insensitive; sale price < price and end > start; a variant's `image_id` must be one of `image_ids`; a variable product's variant has exactly one value of each chosen option, no duplicate combinations; a simple product has exactly one variant; publishing needs ≥1 category and ≥1 enabled variant; specifications only for attributes applicable to the categories, with a value of the right kind).
- **Variants:** updated by id, new ones created, missing ones **soft-deleted** (their SKU stays taken). `ProductVariant::currentPrice()` / `isOnSale()` / `discountPercent()` apply the optional sale window.
- **Stock** only changes through `AdjustStockAction` (row lock + `stock_movements` row). New variants: `stock_quantity` becomes an `initial` movement. Existing variants: the form sends `stock_quantity` with `stock_quantity_was` (the number the admin saw); if stock moved meanwhile → 409 `stock_changed` and nothing is saved, otherwise an `adjustment` movement. History: `GET admin/products/{product}/stock-movements`.
- **Rich text** is cleaned by `App\Support\RichText::sanitize($html, full: bool)`:
  - `full` (description): headings h2–h4, inline formatting, lists, quotes, tables, `hr`, `figure`, `dir`; `style` keeps only `color`, `background-color`, `text-align`, `width` (px/%) and `border-collapse` with safe values; **images and videos must carry `data-media-id` of a library file of that type** (else removed), and their `src` / video `poster` (the cover) / default `alt` are taken from the library, so pasted or forged addresses never survive; **iframes only for exact Aparat (`https://www.aparat.com/video/video/embed/videohash/{hash}/vt/frame`) and YouTube (`https://www.youtube(-nocookie).com/embed/{id}`) addresses**.
  - basic (short description): `p`, `br`, bold/italic/underline, links, lists; pasted headings and blocks become paragraphs; other markup is removed but its text kept.
  - Everywhere: scripts, event handlers and unknown elements are dropped; links get `rel="noopener noreferrer"`. The HTML source view in the editor can't get around any of this.
- **Short description:** rich text (basic); at most 1000 characters of **text** (`short_description` HTML ≤ 5000). The Storefront API returns `short_description` (HTML) and `short_description_text` (plain, for cards and meta tags). A migration turned the old plain-text ones into paragraphs.
- **Library files in the description** are recorded as used (`media_usages` role `content`, "Text" in "where it's used") on every save (`RichText::mediaIds()`), so they can't be deleted while the description shows them; removing them from the description frees them. The SEO analysis reads `RichText::toPlainText()` (`Product::seoDescription()`); the admin analyses the same text (`htmlToText()` in `admin/modules/editor/richText.ts`).
- **Tags** typed in the form are matched by normalized name («هديه» = «هدیه») or created with a Finglish slug (`ResolveTagsAction`). Tags page API: list (`q`, product counts), rename, merge into another, delete.
- **Search text** (`RefreshProductSearchTextAction`) is rebuilt on save, and for affected products when a brand, category or tag is renamed/merged/deleted. Admin list search: every normalized word must appear.
- **Admin list filters:** `q`, `status`, `category_id` (includes sub-categories), `brand_id`, `tag_id`, `stock` (`in` = an enabled variant with something to sell: untracked stock, backorders or a quantity left; `low` = an enabled variant ≤ threshold; `out` = no enabled variant > 0 and no backorders), `needs_seo`, `trashed`, `sort` (`newest`, `name`, `updated`). Rows include price range (current prices of enabled variants), stock summary, main image, primary category.
- **Delete** is a soft delete (`DELETE`, `POST {product}/restore`; `{product}` resolves deleted products for show/restore). **Bulk status** `POST admin/products/status` (`ids`, `status`) skips products that can't be published.
- **Store settings** (`App\Support\Settings`, cached): `price_display_currency` (`IRT` default / `IRR`), `low_stock_threshold` (default 5) and `storefront_preview_url` (null default; an http/https address containing `{token}`). `GET admin/settings` for all staff; `PUT` needs `settings.manage` (no role lists it: super admin only).
- **Protections now active:** deleting a category or brand with products (deleted ones included) → 409 `category_has_products` / `brand_has_products`; deleting an attribute or option products use → 409 `catalog_item_in_use`; removing a value products use → 409 `catalog_value_in_use`; changing the type of an attribute products use → 422 `attribute_type_locked`.
- **Audit log:** `product.created|updated|deleted|restored` (updated lists changed parts: fields, `categories`, `tags`, `options`, `variants`, `specifications`, `images`), `tag.renamed|merged|deleted`, `settings.updated`. Stock changes are in `stock_movements`, not the audit log.
- The SEO system covers products too (`SeoContentType::Product`, AI page description "a product page").

#### Products admin UI (phase 3)
- **Money in the admin:** `useMoney()` (`admin/modules/settings/store.ts`) formats Rials in the display currency from the settings store (loaded once); price inputs are `NumberField`s with `factor = rialsPerUnit` (10 for Toman), so the API always gets Rials.
- **Product list** (`/admin/products`): filters in the URL query (`q`, `status`, `category`, `brand`, `stock`, `needs_seo`, `trashed`, `tag`, `page`), row selection with bulk publish / draft / archive, restore for deleted products.
- **Product editor** (`/admin/products/new`, `/admin/products/:id`): one page with section links (General, Categories/brand/tags, Price/stock/variants, Images, Specifications, Shipping, SEO) and a sticky save bar (status, publish date, Save). Everything, the gallery included, is saved by one Save. The gallery (`ImagesManager`) adds pictures with the media picker (several at once, or uploaded from the picker), drag to reorder, "make main", remove; images without alt text get a badge. Removing a picture from the gallery clears it from variants using it; description images are inserted from the gallery (with their alt text). Leaving with unsaved changes asks first (route guard + `beforeunload`).
  - Variants (`products/variants.ts`, tested by `tests/js/variants.test.mjs`): the grid is the combinations of the chosen option values; regenerating keeps existing variants (id, price, stock) and prices new ones like the first row. SKUs are suggested from the slug and value slugs until typed. Stock is sent only when changed, with `stock_quantity_was`.
  - Specifications are fetched for the chosen categories (`attributes/applicable`); only filled-in ones are sent; their errors are placed by attribute id.
  - Short description and description: TinyMCE (`admin/modules/editor`, `RichTextEditor` with `variant` basic/full). The full toolbar: undo/redo, blocks (paragraph, H2–H4), bold/italic/underline/strikethrough, text and background colour, alignment, RTL/LTR, lists, quote, horizontal line, link, table, **library image**, **library video** (media picker, upload included), **Aparat/YouTube** (a link turned into the player address by `embedUrl()`), clear formatting, HTML source, fullscreen. The editor follows the panel's language (Farsi strings in `editor/fa.ts`, as the npm package ships none), direction and light/dark theme, with the Vazirmatn font; Persian half-spaces are kept as characters (`entity_encoding: raw`). TinyMCE tidies the HTML it loads, and the page takes that tidied text as its unsaved-changes baseline (`normalized` event). The SEO panel analyses `htmlToText()` of the description, matching the server's `RichText::toPlainText()`. The live duplicate-keyphrase check is not available for products (the server still checks on save).
  - Errors: fields with their own error display show it in place; any other error goes in the alert at the top (never hidden).
- **Tags page** (`/admin/tags`): rename, merge, delete; "N products" opens the filtered product list.
- **Settings page** (`/admin/settings`): visible to all staff, editable by the super admin (`settings.manage`).
- Sale and publish dates use the browser's `datetime-local` picker (Gregorian); a Jalali date picker would need an extra package.

#### Bulk edit page (`/admin/products/bulk-edit`, `products.manage`)
- **Inline editing like a seller panel, one Save for the whole page.** Opened from the menu ("Bulk edit") and the product list header. A card per product (image, name, status, summary: variants, price range, total stock; variable products can be folded) with a row per variant: image (the variant's own, else the product's), label, SKU, and editable **price, sale price, stock, active**. Sale dates are not edited here.
- Filters live in the URL: search, quick-filter chips with counts (`quick`: all / out of stock / low stock / on sale / inactive variant), and behind "Filters" status, category, brand and products per page (20 / 50 / 100); paginated.
- Fields read like text until hovered or focused. A changed field turns amber with the old value crossed out under it; the row gets an accent bar, the card a "N changes" pill. Empty sale = "+ Add sale"; a sale shows its discount pill and end date; × removes it. Stock has − / + buttons and a dot (out / low / in stock). Checks while typing: price and stock required, sale below the price; Save is disabled while any field is invalid.
- **Save = `PUT admin/products/bulk`** with `variants[i]`: `id` + only changed `price`, `sale_price`, `is_enabled`, `stock_quantity` (+ `stock_quantity_was`). **All or nothing**: errors come back as `variants.{i}.{field}` and are shown on the row; stock that changed since the page loaded → the row shows "now X", the page reloads its rows keeping the changes, and the next Save compares against the new number (409 `stock_changed` if it changes between validation and saving). A published product keeps ≥1 enabled variant; variants of deleted products and stock of untracked products are refused.
- Saving: a sale added here starts now with no end date (old dates are cleared); changing an existing sale price keeps its dates; removing a sale clears them. Stock goes through `AdjustStockAction` with reason `bulk_edit`. Each changed product gets one `product.updated` audit entry (`fields: ['variants']`, `bulk: true`).
- Unsaved changes: changing a filter or page, or leaving, asks **Save and continue / Discard / Stay**; reloading the tab warns. Enter moves to the same field in the next row. The floating save bar ("N unsaved changes · Undo · Save all") appears only with changes.
- Below `xl` the variant fields wrap into a grid (2 per row on phones). Logic in `products/bulkEdit.ts` (tested by `tests/js/bulkEdit.test.mjs`); the list's filters are the `Product::matching()` scope, shared with the product list.

#### Storefront catalog API (phase 4, public)
- **Visible product** (`StorefrontProductQuery::visible()`): published, `published_at` passed, not deleted, ≥1 enabled variant, and ≥1 category that is visible (active with all ancestors active, `CategoryTree::visibleIds()`). Anything else is 404 on the detail endpoint.
- `GET /api/v1/products`: `q` (every normalized word in `search_text`), `category` (slug, includes visible sub-categories), `brand` (slug, active only), `tag` (slug), `filter[attribute-slug]` (`a,b` select values · `13..16`, `13..` or `..16` number range · `1`/`0` boolean), `option[option-slug]=red,blue` (**one enabled variant must match every chosen option**), `min_price` / `max_price` (Rials, on the current price), `in_stock`, `on_sale`, `sort` (`newest` default, `price_asc`, `price_desc`, `name`), `page`, `per_page` (≤48, default 24). An unknown or non-filterable slug matches nothing (never silently ignored).
- `GET /api/v1/products/filters` (same scope parameters): `total`, `price {min,max}`, brands, filterable attributes (on a category: that category's own and inherited ones; values products have, with product counts; number min/max; boolean yes/no counts) and option values used by enabled variants. Counts ignore the refining filters (`filter`, `option`, price, stock, sale).
- `GET /api/v1/products/{slug}`: description (sanitized HTML), brand, visible categories with `is_primary`, breadcrumb (from the primary category), tags, images, options (only values enabled variants use), variants (`option_value_ids`, `price`, `regular_price`, `discount_percent`, `sale_ends_at`, `in_stock`, `low_stock`, `image_id`), specifications (display text), shipping, SEO fields.
- **Prices** are Rials and sale-aware (`price` = current, `regular_price`); every response has `meta.currency {display: IRT|IRR, rials_per_unit}` for display.
- **Stock numbers are never exposed:** only `in_stock` (untracked stock or backorders count as in stock) and `low_stock`. Inactive brands are left off (`brand: null`); admin-only fields (focus keyphrase, scores) are never included.

### Attributes & Options (products phase 1)
- **Attributes** (`attributes`, `attribute_values`): name, Latin slug (unique; used in storefront filter URLs), `type` (`text`, `number` + optional `unit`, `boolean`, `select` with ordered values), `is_filterable`, manual `position`. Only `select` attributes have values; values have a Latin slug unique within the attribute. Saving an attribute syncs its values (update by id, create new, delete missing, order = array order).
- **Options** (`options`, `option_values`): variant dimensions with `display_type` `text` (buttons) or `color` (swatches; each value then needs `color_hex`), manual `position`, values synced the same way.
- **Category attributes** (`attribute_category`, ordered): `PUT admin/categories/{category}/attributes` sets a category's own attributes; `GET` returns own + inherited (from ancestors, with the category they come from). Products (phase 2) get the union over all their categories.
- **Who manages:** Super Admin and Admin (`attributes.view`, `attributes.manage`; assigning attributes to a category needs `categories.manage`).
- Reordering (`PUT attributes/order`, `PUT options/order`) takes every id of the list (409 `catalog_list_changed` otherwise), like brands.
- Deleting an attribute, option or value products use is refused (see Products backend), as is changing a used attribute's type.
- **Audit log:** `attribute.created|updated|deleted|reordered`, `option.created|updated|deleted|reordered`, `category.attributes_changed`.
- Admin UI: Catalog › Attributes (tabs Attributes / Options, drag-and-drop order, form dialog with a values editor) and an "Attributes" section in the category form.

### Categories
- Fields: `parent_id`, `name`, `slug`, `description`, `meta_title`, `meta_description`, `position` (order among siblings), `is_active`, plus one image from the media library.
- **Unlimited depth.** Stored as `parent_id` only; `App\Support\CategoryTree` loads all categories in one query, caches the tree as plain arrays (cleared on every category save/delete) and answers ancestors, descendants, visibility and "can move" questions. No tree package.
- **Slug:** Latin only (`a-z`, `0-9`, single hyphens), unique. While creating, the admin UI suggests one from the name (Persian is transliterated to Finglish by `suggestSlug()` in `admin/modules/categories/tree.ts`, e.g. «ایران ۱۴۰۳» → `iran-1403`); the admin can edit it, and editing stops the suggestion.
- **Image:** one media library image (`image_id` in the save; `media_usages` role `image`). The API returns `{ id, original, thumb, medium, large, alt }`.
- **Moving:** a category can never move under itself or one of its descendants (`category_invalid_parent`).
- **Delete** is refused while the category has sub-categories (`category_has_children`) or products (`category_has_products`, added with the products module). Nothing is moved or unlinked automatically; the admin moves them first.
- **Hiding:** `is_active = false` hides the category **and its whole branch** from the Storefront API. Each sub-category keeps its own flag, so showing the parent restores the branch as it was; the admin tree shows them as "hidden by parent" (`is_visible = false`).
- **Who manages:** Super Admin and Admin (`categories.view`, `categories.manage`). Author has no access to product categories (blog categories: see Blog).
- **Two types in one table** (see Blog › Categories): `type` = `product` (default) or `blog`.
- **Admin UI:** an expandable tree. Drag and drop (`vue-draggable-plus`) on desktop, plus "move up / move down / move to…" actions on every row for phones and keyboards.
- **Audit log:** `category.created`, `category.updated` (changed field names, incl. `image`), `category.moved` (from/to parent ids), `category.reordered`, `category.deleted` (name and slug kept in properties, as category data is not personal).

### Brands
- **Flat list** with a **manual order** (`position`): drag and drop (`vue-draggable-plus`) plus "move up / move down" on every row. New brands go last. `PUT admin/brands/order` takes the complete list of ids (409 `brand_list_changed` when it doesn't match the current brands).
- Fields: `name` (unique; as the brand writes it, e.g. Adidas), `persian_name` (optional, unique when set, e.g. آدیداس; searchable in the admin, returned by the Storefront API, given to the AI as `alternate_name`), `slug` (Latin, unique, suggested via Finglish like categories), `description`, `website` (optional http/https URL), `is_active`, `position`, the shared SEO fields, and one **logo**: a media library image (`logo_id` in the save; role `logo`; returned as `{ id, original, thumb, medium, large, alt }`). The form suggests a PNG with a transparent background.
- **Products:** one brand per product (`brand_id`, added with products). Delete is refused while a brand has products (`brand_has_products`, added with the products module); hiding is the alternative.
- **Who manages:** Super Admin and Admin (`brands.view`, `brands.manage`). Author has no access.
- The admin list returns **all** brands (no pagination, as the order is managed by hand); search and filters run in the browser.
- **Storefront API:** `GET /api/v1/brands` (visible brands in position order), `GET /api/v1/brands/{slug}` (logo, description, website, SEO fields). Hidden or unknown → 404.
- **Audit log:** `brand.created`, `brand.updated` (changed field names, incl. `logo`), `brand.reordered`, `brand.deleted` (name kept).

### Media Library (built in 3 phases, each reviewed and committed: 1 backend & API ✅, 2 Media page ✅, 3 picker + moving products/categories/brands onto the library ✅)
- **One shared library:** every file is stored once as a `MediaAsset` (`media_assets`; the file itself is its single spatie media in the `file` collection) and items point to it through `media_usages` (`media_asset_id`, `usable` morph, `role`: `gallery`, `variant_image`, `image`, `logo`, `content`; `position`). "Where it's used" and the in-use checks read that table (plus video covers). Phase 3 moves product galleries, variant images, category images and brand logos onto it with a one-time migration; storefront responses keep their shape and gain `alt`.
- **Files** (`config/media.php`, `MediaType`): images JPG/PNG/WebP/AVIF/GIF ≤ 10 MB (no SVG), PDF ≤ 20 MB, video MP4/WebM ≤ 200 MB. The **real content type is read from the file** on completion (not the name or the announced type). Images are turned upright (EXIF orientation), scaled down to 2560px on the longest side and re-saved, which drops location and camera details (animated GIFs kept as they are); they get webp versions `thumb` (320, fitted), `medium` (800), `large` (1600). PDFs and videos are kept as uploaded. A video can have a **cover picture** (`cover_id`: an image of the library). Columns: `type`, `name`, `mime_type`, `size`, `width`, `height`, `title`, `alt`, `folder_id`, `cover_id`, `uploaded_by`.
- **Chunked uploads only** (no request reaches PHP's 2 MB upload limit): `POST media/uploads` announces `name`, `mime_type`, `size`, `folder_id` (type and size checked up front) → `POST media/uploads/{id}/chunks` (multipart `chunk` ≤ `chunk_kb` = 1 MB, `offset` must equal `received`, else 409 `media_chunk_out_of_order` with `received` to resume) → `POST media/uploads/{id}/complete` → the file. Uploads are private to their uploader (`mediaUpload` binding) and pruned with their temp file (`storage/app/private/media-uploads`) after 24 h.
- **Folders:** one level (`media_folders`, unique name); deleting a folder moves its files to "no folder". Moving files: `POST media/move` (`ids`, `folder_id` or null).
- **Delete = trash:** refused while in use (409 `media_in_use`); otherwise soft-deleted, restorable, and removed for good (file and versions) by `model:prune` after `trash_days` = 30 (files in use are never pruned).
- **Permissions:** `media.view` (browse), `media.upload` (upload, edit title/alt/cover), `media.manage` (move, delete, restore, folders). Admin has all three; Author has view + upload.
- **Audit log:** `media.uploaded|updated|moved|deleted|restored`, `media_folder.created|renamed|deleted` (file and folder names, field names; no personal data).
- Error codes (`MediaException`): `media_in_use`, `media_type_not_allowed`, `media_too_large`, `media_chunk_out_of_order`, `media_chunk_too_large`, `media_upload_incomplete`.
- **Media page** (`/admin/media`, `admin/modules/media`): folders on the side (chips on smaller screens; rename/delete in each folder's menu on desktop) with All files, No folder and Trash; search, type chips (all / images / videos / PDFs), used/unused and sort; a grid of cards (picture, the video's cover with a play badge, a PDF tile; name, size, dimensions, "in use" badge) with "Load more" (40 per page). Filters and the open file live in the URL (`folder`, `q`, `type`, `used`, `sort`, `file`); the audit log links media entries to `?file=`.
- **Uploading:** the button or files dropped anywhere on the page (into the folder being viewed). The queue (`useMediaUploadStore`, shown by `MediaUploadQueue` in the admin layout) keeps going while staff move around the panel: two files at a time, 1 MB chunks, a failed chunk retried with back-off, an out-of-order chunk resumes from the server's `received`, "Retry" continues where the upload stopped, and a refused file shows the server's message. `ApiError.body` carries extra error fields such as `received`.
- **Details drawer** (slides in from the side, Esc closes): preview (image, video player with its cover, PDF link), title and alt text (upload permission), a video's cover chosen with `MediaPickerDialog` (library images; phase 3 adds uploading and uses it everywhere), type/size/dimensions/uploader, folder (manage permission: changing it moves the file), "where it's used" with links, copy address, download, delete; in the trash: when it will be removed, and restore.
- **Selection** (manage permission; checkbox on hover, always on touch screens, Shift+click for a range): move to a folder, delete (files in use are skipped and reported), restore in the trash.
- **Items use the library** (`App\Models\Concerns\UsesMediaLibrary`): products (`galleryAssets`, role `gallery`), categories (`imageAssets`, role `image`) and brands (`logoAssets`, role `logo`) link library files through `media_usages`; `syncMediaAssets($role, $ids)` saves a role in order and reports whether it changed (audit fields `images` / `image` / `logo`). A hard-deleted item (category, brand) frees its files; a soft-deleted product keeps them in use (it can be restored). Saves validate ids as library **images not in the trash** (`HasMediaFields`). The SEO `image` check asks `hasSeoImage()`. A variant's picture is `image_asset_id`, one of its product's gallery files. Editing a file's alt text clears the cached category tree.
- **Picker** (`MediaPickerDialog`): search, "Load more", single or multiple choice (numbered in click order; files already used are greyed out) and an "Upload" button whose files are chosen as soon as they finish. `MediaImageField` is the one-picture form field (category image, brand logo). The details drawer disables Delete for a file in use and says why.
- **Moving existing images** (migration `move_item_images_to_media_library`): each category image, brand logo and gallery image became a library file (its media row now belongs to the file; the stored file did not move) used by its item in the same role, gallery order kept; variant pictures moved from `image_media_id` to `image_asset_id`. **After deploying, run `php artisan media-library:regenerate "App\Models\MediaAsset" --only-missing`** so moved files get the library's versions (`large`, etc.).

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

### Pages (built in 2 phases, each reviewed and committed: 1 backend & API ✅, 2 admin UI ✅)
- **Standalone content pages** (About us, Terms, Privacy, Shipping & returns, FAQ, ...). **Flat list** (no parent pages); storefronts build their own URLs from the slug. No pages are created by default.
- **Fields** (`pages`): `title`, `slug` (Latin, unique, suggested from the title via Finglish like categories; stays taken while the page is in the trash), `content` (rich text, full profile of `App\Support\RichText`: the same TinyMCE editor, library images/videos and Aparat/YouTube players as product descriptions; library files recorded as `media_usages` role `content`), `excerpt` (optional plain text for cards and meta tags), one **featured image** (media library image, role `image`), `status` draft / published + `published_at` (scheduling), the shared SEO fields (`meta_title`, `meta_description`, `focus_keyphrase`, `seo_score`, `readability_score`; `SeoContentType::Page`, live analysis, AI suggestions), `created_by` / `updated_by`.
- **Delete = trash:** soft delete, a Trash filter in the list with restore; removed for good (with its revisions, freeing its library files) by `model:prune` after 30 days.
- **Revisions** (`page_revisions`): every save that changes the page keeps a snapshot of the saved version (title, slug, excerpt, content, featured image, SEO fields) with who saved it and when; the **last 20 per page** are kept. The editor's **History** panel lists them; choosing one shows a **preview** (no diff) and **Restore**, which loads it into the editor as unsaved changes; saving it creates a new revision, so a restore can be undone. Library files referenced only by old revisions are not counted as in use.
- **Who manages:** Super Admin, Admin and **Author** (`pages.view`, `pages.manage`), publishing and deleting included.
- **Admin UI** (`admin/modules/pages`): **list** `/admin/pages` (filters in the URL: `q`, `status` draft/published/scheduled, `sort`, `needs_seo`, `trashed`, `page`; rows show the image, SEO dot, slug, state, last edited and by whom; restore in the trash) and **editor** `/admin/pages/new`, `/admin/pages/:id` (Content card: title, slug suggested via Finglish until typed, TinyMCE full editor; side card: featured image (`MediaImageField`) and excerpt; the shared `SeoPanel` with type `page`; sticky save bar with Draft/Published, publish date and Save; unsaved-changes guard; a page in the trash shows when it will be removed and only offers Restore). **History** (`VersionHistoryDialog`, shared with posts): versions newest first (the newest marked "current"), a preview of the chosen one (rendered content, image, excerpt, SEO fields) and "Restore this version", which loads it into the form (status and publish date untouched). The audit log and the media library's "where it's used" link to the page editor.
- **Storefront API** (public): `GET /api/v1/pages` (published pages: slug, title, excerpt, `updated_at`; for footer links and sitemaps) and `GET /api/v1/pages/{slug}` (content, excerpt, featured image, SEO fields, `published_at`, `updated_at`). Drafts, scheduled, trashed or unknown → 404. Admin-only fields (focus keyphrase, scores, authors, revisions) never included.
- **Audit log:** `page.created|updated|deleted|restored` (changed field names only; restoring a revision is a normal save).
- **Backend:** `Page` (`state()`: draft / scheduled / published; `visible()` and `matching()` scopes; `seoName()` = title, since `SeoSubject::seoName()` lets content types name their title column), `PageRevision`, `SavePageAction` (one action for create and update: sanitizes via the request, syncs the image and `content` usages, scores SEO, keeps a revision unless the saved fields equal the newest one, e.g. a status-only change), `DeletePageAction` (trash/restore), `SearchPagesAction`. Admin list: `q` (title, slug), `status` (`draft`/`published`/`scheduled`), `needs_seo`, `trashed`, `sort` (`updated` default, `newest`, `title`).
- **SEO & social fields** (besides the shared SEO fields): `robots_noindex`, `canonical_url` (absolute http/https), `og_title`, `og_description` and an **OG image** (media library image, role `og_image`). Kept in revisions and autosaves. The Storefront API returns them as typed plus a resolved `seo` block: `title` (meta title → title), `description` (meta description → excerpt), `canonical_url`, `robots_noindex`, `og { title (OG title → SEO title → title), description (OG description → meta description → excerpt), image (OG image → featured image) }`. `GET /api/v1/pages` includes `robots_noindex` (a storefront may link a noindex page but leave it out of its sitemap). The editor has a "Social & indexing" card.
- **Preview** of drafts, scheduled pages and unsaved changes: `POST admin/pages/{page}/preview` (`pages.view`) returns `{ token, url, expires_at }`. The token (`App\Support\PreviewToken`) is signed with the app key and carries the page, the staff member and an expiry (60 minutes); `url` is the store setting `storefront_preview_url` (e.g. `https://shop.example/preview?token={token}`, set by the super admin) with `{token}` filled in, or null when not set (the editor then copies the token). Storefronts call `GET /api/v1/pages/preview/{token}`: the page in the same shape as `pages/{slug}` whatever its status (not when in the trash), with that staff member's autosave applied when newer than the page; sent with `Cache-Control: no-store` and `X-Robots-Tag: noindex`. Invalid or expired tokens → 404. The editor autosaves right before asking for a token, so Preview shows the form as it is.
- **Autosave** (`page_autosaves`, one per page and staff member): while an existing page has unsaved changes the editor sends `PUT admin/pages/{page}/autosave` (`pages.manage`) every 30 seconds with the form's fields (lengths checked, content sanitized; no unique-slug or publishing checks). It never changes the page, the version history or the audit log, and records no media usages. `GET admin/pages/{page}` includes the current staff member's `autosave` when it is newer than the page; the editor then offers **Restore** (loads it as unsaved changes) or **Discard** (`DELETE admin/pages/{page}/autosave`). A normal save deletes the saver's autosave; autosaves go with their page. New pages autosave after their first Save.
- Not included: nested pages, page-builder blocks, diffs between revisions and the blog (separate module).

### Menus
- **Two fixed locations** (`App\Enums\MenuLocation`: `header`, `footer`), each an ordered list of items with optional **sub-items (two levels)**. Table `menu_items`: `location`, `parent_id`, `position`, `type` (`MenuItemType`: `page`, `category`, `url`), `page_id` (a page removed for good removes its items), `category_id`, `url` (absolute http/https, or a path starting with `/`), `label` (required for links; page and category items fall back to the title / name), `open_in_new_tab`.
- **Who manages:** Super Admin, Admin and Author (`menus.view`, `menus.manage`).
- **Admin API:** `GET admin/menus/targets` (`menus.manage`: every page not in the trash with its state, and the category tree flattened with depth and visibility, so staff who manage menus need no pages or categories permission), `GET admin/menus/{location}` (the tree, each item with its target and `is_visible` + `hidden_reason`: `page_draft`, `page_scheduled`, `page_trashed`, `category_hidden`) and `PUT admin/menus/{location}` (`items`: the whole tree, saved in one go: updated by id, new ones created, missing ones deleted, order = array order). Audit log `menu.updated` (`location`).
- **Storefront API** (public): `GET /api/v1/menus/{location}` → items `{ label, type, url (links), page { slug, title } | category { slug, name }, open_in_new_tab, children }`. Items whose page or category isn't visible are left out, with their sub-items. Unknown location → 404.
- **Unavailable targets** are hidden, never silently removed: the menu editor marks them "Not visible" with the reason. Deleting a category a menu links to is refused (409 `category_in_menu`); remove the menu item first.
- **Admin UI** (`/admin/menus`, `admin/modules/menus`): Header / Footer tabs, the tree with drag and drop (`vue-draggable-plus`) plus move up / down for phones and keyboards, an item dialog (page, category or custom link; label, new tab; the link is checked like on the server), "Not shown" badges with the reason, indent / outdent from each row's menu, one Save for the whole menu (Undo reloads it), and Save and continue / Discard / Stay when switching menus or leaving with changes. Tree helpers in `menus/tree.ts` (tested by `tests/js/menus.test.mjs`).

### Blog (4 phases, each reviewed and committed: 1 category types ✅, 2 posts backend & API + author bylines ✅, 3 posts admin UI ✅, 4 comments)
- **Categories (one table):** `categories.type` (`App\Enums\CategoryType`: `product` default, `blog`). A tree never mixes types (a blog category's parent is a blog category; checked on save and move). Products only go in product categories, posts only in blog categories; attributes only on product categories. **Slugs are unique per type** (unique index `type + slug`). `CategoryTree` keeps one cached tree and answers per type (`visibleIds(type)`, `children(parent, type)`, ...). Deleting refuses categories with products **or posts** (`category_has_products` / `category_has_posts`). Admin: the Categories page has **Product / Blog** tabs (`?type=blog`; staff see the tabs they may use; the menu item and route accept either permission via `canAny()` / a permission list in `meta.permission`); the form has no Specifications section for blog categories and its SEO panel uses `blog_category`. Admin endpoints take `type` (`GET categories?type=blog`, `PUT categories/order` with `type`; `POST categories` with `type`, ignored on update) and check the type's permission themselves (`CategoryType::viewPermission()` / `managePermission()`); specifications endpoints are 404 for blog categories. SEO: `SeoContentType::BlogCategory` (`blog_category`, AI suggestions need `blog_categories.manage`); duplicate keyphrases are compared within the same type (`SeoSubject::seoPeerConstraints()`). `GET /api/v1/categories` stays product-only; blog categories: `GET /api/v1/blog/categories` (tree) and `GET /api/v1/blog/categories/{slug}`. Menu items can link to either type (the storefront response gives `category.type`).
- **Who manages:** Super Admin, Admin and Author: `posts.view`, `posts.manage` and `blog_categories.manage` (blog categories only; the Product tab still needs `categories.*`). Comment moderation: `comments.moderate` (same roles).
- **Posts** (`posts`): `title`, `slug` (Latin, unique), `excerpt`, `content` (full rich text: library images/videos, Aparat/YouTube; usages role `content`), featured image (role `image`), `status` draft / published + `published_at` (scheduling), `author_id` (a staff member), `comments_enabled` (default true), the shared SEO fields (`SeoContentType::Post`) and the social & indexing fields (`robots_noindex`, `canonical_url`, `og_*`, OG image role `og_image`), `created_by` / `updated_by`. **Categories:** several, one primary (`category_post.is_primary`, breadcrumbs). **Tags:** typed freely with suggestions, **the same `tags` table as products** (`post_tag`); the Tags page shows product and post counts, rename / merge / delete apply to both, and refresh affected products' search text. **Reused from Pages:** trash (30 days, `model:prune`), revisions (last 20), autosave (per post and staff member), preview tokens (`GET /api/v1/blog/posts/preview/{token}`, the same storefront preview address), media usages. Revisions and autosaves keep the same fields as pages (not categories or tags). Audit log `post.created|updated|deleted|restored` (updated lists changed fields plus `categories`, `tags`, `image`, `og_image`). **Backend:** `Post` (`state()`, `visible()`, `matching()`, `primaryCategory()`), `PostRevision`, `PostAutosave`, `SavePostAction` (one action for create and update; the author defaults to the writer; tags through `ResolveTagsAction`), `DeletePostAction`, `SearchPostsAction`, `SavePostAutosaveAction`, `PreviewPostAction` / `ResolvePostPreviewAction`, `SearchStorefrontPostsAction`. Preview tokens name what they show (`PreviewToken::PAGE` / `POST`; tokens made before carry no kind and count as pages), so a page token never opens a post. Tags: `GET admin/tags` needs `products.view` **or** `posts.view` (suggestions in both editors); rename / merge / delete stay with `products.manage`.
- **Author bylines:** staff may fill an optional public **byline** on their own profile (`GET`/`PUT admin/byline`, any staff member, only their own; an empty name removes it): `author_name`, `author_slug` (Latin, unique; sent as `slug`), `author_bio`, avatar (library image, `media_usages` role `avatar`, "where it's used" shows the byline). A post shows `author { name, slug, bio, avatar }` only when its author has a byline, else `author: null`; the account name, phone and email are never exposed. Storefront filter `author` = byline slug.
- **Storefront API:** `GET /api/v1/blog/posts` (published, newest first, paginated; `category` slug incl. visible sub-categories, `tag` slug, `author` byline slug, `q`), `GET /api/v1/blog/posts/{slug}` (content, categories with breadcrumb from the primary, tags, author, comment count, the resolved `seo` block). Same visibility rules as pages (draft, scheduled, trashed → 404). Categories are optional for posts: a post without a visible blog category is still listed.
- **Admin UI** (`admin/modules/posts`, routes `/admin/blog`, `/admin/blog/new`, `/admin/blog/:id`): Content › Blog. **List**: search, status, blog category (sub-categories included), author, sort, Needs SEO work, Trash, and a removable tag chip (`?tag=`, set by the Tags page's "N posts" link); rows show the primary category and the author's byline (else account name). **Editor**: the Pages editor (content, excerpt and featured image, social & indexing, SEO panel with type `post`, autosave with Restore / Discard, Preview, History) plus a "Categories, tags & author" card: `CategoriesField` with the blog tree (primary), `TagsInput` (shared tags), the author picker (`GET admin/posts/authors`; staff without a byline are marked) and "Allow comments". The History dialog is shared with pages (`pages/components/VersionHistoryDialog.vue`, given `loadRevisions` / `loadRevision`). **Byline**: a pen button in the sidebar's account area opens `account/components/BylineDialog.vue` (name, slug suggested via Finglish, bio, picture; an empty name removes it). The audit log links post entries to the editor; the media library's "where it's used" links posts to the editor and bylines to Staff.
- **Comments** (`post_comments`): only **logged-in customers** (Storefront API Bearer token, active account) on posts with `comments_enabled`. `POST /api/v1/blog/posts/{slug}/comments` (`display_name`: prefilled by storefronts with the customer's name, editable; `body`: plain text ≤ 2000 characters; rate limited per customer) → status `pending`. **Moderation:** staff approve, reject or delete; only **approved** comments are shown (`GET /api/v1/blog/posts/{slug}/comments`, paginated, oldest first). **Replies: staff only**, one per comment, shown under it as the **store's answer** (never a staff name). Customers never see others' phone or email; the account is linked only for moderation. **Anonymising a customer deletes their comments.** Admin: a Comments page (filter by status / post, pending count in the menu, approve / reject / delete / reply). Audit log `comment.approved|rejected|deleted|replied` (comment and post ids only, no comment text or names).

### Discounts (promotions per SKU ✅, sale events ✅; coupons later: definitions, admin, check endpoint, then applying them and counting usage with checkout & orders)
- **Kinds:** **promotions** (one row per SKU, see below), time-limited **sale events** (see Sale events) and, later, **coupons** (one code per order). The first promotions (a discount over chosen products) were removed on 2026-10-11 at the user's request (migration `remove_promotions`) and rebuilt per SKU the same day (`create_sku_promotions_table`). Buy X get Y, tiered and cart-level automatic rules are not included.
- **Who manages:** Super Admin and Admin (`discounts.view`, `discounts.manage`). Author has no access.
- **Best price wins, never stacked:** a variant's current price is the lowest of its active sale price, the sale event chosen for it and its running promotion row, never above its regular price. Amounts are whole Rials, rounded down (to whole Toman when the store displays Toman).
- **One pricing service** (`AppSupportPricingPricing::price($variant, $at, $eventItems, $promotions)` → `VariantPrice`) computes prices for the admin, the Storefront API and later the cart. The current price is stored per variant (`product_variants.current_price`, `discount_source` `sale|sale_event|promotion|null`, `sale_event_id`, `promotion_id`, `max_per_order`, `price_recalc_at`) by `RefreshPricesAction` (with `SaleEventSet` and `PromotionSet`) so storefront filters and sorting by price stay SQL; it runs after a product save, a bulk edit, sale event and promotion saves, and `php artisan prices:refresh --due` runs every minute. The variant's `saving` hook keeps the stored price in line with its own sale until then.
- **Order limit:** `max_per_order` (1–10,000, null = none) on each promotion row and sale event item: the most units of that variant **one order** may hold at that price. Stored on the variant with the price it belongs to (null when a sale or the regular price gives it) and returned by the Storefront API as `variants[].max_per_order`. **The cart (not built yet) must refuse more than this.**
- **Promotions** (`promotions`, `Promotion`): **one row per SKU** (`product_variant_id`, `product_id`), `discount_type` percent (1–90) | fixed (Rials off each unit, below the price), `discount_value`, `max_per_order`, `quantity_limit` (discounted units in all; null = unlimited), `sold_count` (**counted by orders later**; the row stops once it reaches the limit), `starts_at` / `ends_at` (both required). A variant may have several rows **whose dates don't overlap** (checked on save, saved rows included). `state()`: scheduled / active / sold_out / ended. Hard delete. Audit log `promotion.created|updated|deleted` (subject the row; properties `sku`, changed `fields`).
  - **Admin API:** `GET promotions` (every row with its variant and product, grouped by product name; `discounts.view`) · `PUT promotions` (`promotions`: new rows without `id` and changed ones, `deleted`: ids; all or nothing, errors `promotions.N.*`; returns every row; `discounts.manage`) · `GET promotions/variants` (the product list's filters: products by name, 20 per page, with their enabled variants (only available ones with `stock=in`), each with price, stock and `has_promotion`; `discounts.manage`). `SavePromotionsRequest` (percent ≤ 90, fixed < price, no new row on a deleted variant, no overlap), `SavePromotionsAction`, `ListPromotionsAction`, `FindPromotionVariantsAction`.
  - **Storefront API:** variants priced by a promotion have `discount_source: promotion` and `promotion { ends_at, remaining }` (`remaining` null = unlimited).
  - **Admin UI** (`/admin/discounts`, the first tab of Sales › Discounts; design approved by the user): `discounts/pages/PromotionsPage.vue`. Search and status chips with counts (all / active / scheduled / sold out / ended / problems) in the URL, 50 rows per page, rows grouped by product. Each row: variant and SKU, price, state, remove, then six fields: **off price** and **percentage** (linked; the typed one is outlined), **max in cart**, **discounted quantity** (with "N of M sold"), **start** and **end** (`PromotionDatesDialog`). Selection: a row, a product, the page, or **"Select all"** (every matching row of every product) → **bulk edit** (`PromotionBulkDialog`: plain inputs, **an empty input changes nothing** — discount as percent / amount off / off price (only when the rows share a price), max in cart, quantity, start, end; rows an amount can't apply to are skipped and reported; a bulk edit can't clear a limit, that is done per row) and remove. **"Add product"** (`PromotionVariantsDialog`): every SKU grouped by product with search, status, availability, category and brand filters, **published products and in-stock variants by default**, selection across pages (a product, the page, or **"Select all N matching"**: every matching product with all its variants, loaded 100 products per request), starting values (percent, max in cart, quantity, start/end = now + 7 days). Changes are kept until **Save all** (floating bar with Undo, like the bulk edit page); leaving or reloading with changes asks first; problems block saving and server errors go to their row. Logic in `discounts/promotions.ts` (tested by `tests/js/promotions.test.mjs`), sharing the linked-field helpers of `discounts/saleEvents.ts` (`tsconfig` allows `.ts` import extensions so Node's test runner can load them).
- **Coupons** (later; `coupons` + `coupon_targets`): one **shared code** per coupon (Latin letters and digits, unique, case-insensitive; typed or generated), `name` (admin), `type` `percent` | `fixed` (off the order) | `free_shipping`, `value`, `max_discount`, `min_order_amount`, eligible items (all, or chosen products / categories / brands / tags, with excluded products), **`exclude_discounted`** (items whose price comes from a sale or sale event don't count), `usage_limit` (total), `per_customer_limit` (default 1), `first_order_only`, `starts_at`, `ends_at`, `is_active`. **One coupon per order**, taken off the already-discounted prices. Any logged-in customer may use one (no per-customer lists).
- **Checking a code** (until a cart exists): `POST /api/v1/coupons/check` (customer Bearer token) with `code` and `items` `[{ variant_id, quantity }]` → the discount in Rials, the eligible items and `free_shipping`, or an error code (`CouponException`): `coupon_not_found`, `coupon_inactive`, `coupon_not_started`, `coupon_expired`, `coupon_min_order`, `coupon_no_eligible_items`, `coupon_limit_reached`, `coupon_customer_limit_reached`, `coupon_first_order_only`. The last three count real orders once they exist (phase 4: `coupon_redemptions`).
- **Admin UI:** Sales › Discounts tabs (`DiscountTabs`): Promotions (`/admin/discounts`), Sale events (`/admin/discounts/sale-events`), Coupons marked soon.

### Sale events (2 phases, each reviewed and committed: 1 backend, pricing & Storefront API ✅, 2 admin UI ✅)
- **Time-limited sales on chosen variants** (weekend sale, end-of-year sale, ...), managed under Discounts (`discounts.view`, `discounts.manage`). Design preview approved by the user (list with drag-and-drop priority, editor with per-variant rows and bulk actions, landing page card, dialogs).
- **Tables:** `sale_events` (`name` shown to storefronts as the label, `starts_at` and `ends_at` both required, `is_active`, `position` = priority (0 = highest; new events go last), landing page: `has_landing_page`, `slug` (Latin, unique, required with a landing page), `landing_title`, `landing_description` (full rich text, library files as `media_usages` role `content`), banner (library image, role `banner`), the shared SEO fields (`SeoContentType::SaleEvent`) and the social & indexing fields like pages (OG image role `og_image`), `created_by` / `updated_by`) and `sale_event_items`, **one row per variant** (`sale_event_id`, `product_id`, `product_variant_id` unique per event, `discount_type` `percent` (1–90) | `fixed` (Rials off each unit, less than the variant's price), `discount_value`, optional `starts_at` / `ends_at` inside the event's dates). `product_variants.sale_event_id` + `discount_source` `sale_event`.
- **Price rule (user's choice A):** priority picks **one event per variant**: of the events that run for it now (active, inside the event's and the item's dates), the one highest in the list. Its price then competes with the variant's own sale price and promotion rows under the lowest-price rule; nothing stacks. Discounts are rounded down to whole display units; an item whose discount is not below the price (the price was lowered later) is skipped. Prices are refreshed on save, delete and reorder (the products concerned) and by `prices:refresh --due` when an event or item starts or ends.
- **Admin editor fields:** each variant row shows linked **%, amount off and final price**; typing one fills the other two, and what is saved is a percentage if the % was typed, otherwise a fixed amount off (final price → amount off = price − final price). Adding a product adds all its enabled variants. Each row also has an **order limit** field. Bulk actions on selected variants: set discount, set order limit (`SetLimitDialog`), set dates, use event dates, remove.
- **Landing page (data only):** visible as soon as its switch is on and the event is active, before the start included; `status` `upcoming` (countdown to `starts_at`, normal prices) → `running` (event prices, countdown to `ends_at`) → `ended` (stays visible with normal prices until switched off). Switched off, disabled or deleted → 404. `GET /api/v1/sale-events` (events with a landing page that haven't ended, in priority order) · `GET /api/v1/sale-events/{slug}` (title, description, banner, dates, status, SEO fields and the resolved `seo` block) · products through `GET /api/v1/products?sale_event={slug}` (the usual filters and sorting) · staff preview `POST admin/sale-events/{saleEvent}/preview` → `GET /api/v1/sale-events/preview/{token}` (`PreviewToken::SALE_EVENT`, any state). On product variants: `discount_source: sale_event` and `sale_event { name, slug (null without a visible landing page), ends_at }`.
- **Admin API:** `GET sale-events` (`q`, `status`; the whole list in priority order, not paginated) · `GET sale-events/{saleEvent}` (items with their variant, product, current price and `overridden_by`: higher-priority events sharing the variant and overlapping dates) · `POST` / `PUT` / `DELETE` (one save for the event and all its items; items updated by variant, missing ones removed) · `PUT sale-events/order` (`ids`: every event; 409 `sale_event_list_changed` otherwise) · `POST sale-events/{saleEvent}/preview`. Delete is a hard delete (frees its library files).
- **Audit log:** `sale_event.created|updated|deleted|reordered` (name and changed field names; `items`, `banner`, `og_image`).
- **Backend (phase 1):** `SaleEvent` (`state()`: disabled / scheduled / active / expired, `landingStatus()`, scopes `relevant()`, `withVisibleLanding()`, `inState()`; `HasSeo` with `seoName()` = landing title or name) + `SaleEventItem` (`startsAt()` / `endsAt()` / `runsAt()`: the item's window, falling back to the event's). `SaleEventSet` loads the items prices depend on, by variant, highest priority first; `Pricing::price($variant, $at, $eventItems, $promotions)` picks the event and returns `saleEventId`; `Pricing::eventDiscount()`. Actions `SaveSaleEventAction` (items synced by variant, `product_id` taken from the variant; reprices the products it had or has), `DeleteSaleEventAction`, `ReorderSaleEventsAction` (reprices products of events that haven't ended), `SearchSaleEventsAction`, `FindOverridingEventsAction`, `PreviewSaleEventAction` / `ResolveSaleEventPreviewAction`. Validation in `SaveSaleEventRequest::after()` (percent ≤ 90, fixed < the variant's price, item dates inside the event's, a deleted variant only if already in the event); `SaleEventException::listChanged()`. The admin detail lists each item's variant (label in option order, price, current price, source) and product (name, thumbnail). The media library shows banners and OG images as used by `sale_event`.
- **Admin UI (phase 2, `admin/modules/discounts`):**
  - **List** `/admin/discounts/sale-events` (Sale events tab): the whole list in priority order; search and status filter in the browser and live in the URL. Rows: drag handle, priority number, name, product / variant counts, landing page chip, dates, status. Drag and drop (`vue-draggable-plus`, off while filtering) and the row menu's Move up / Move down save the order (`PUT sale-events/order`); the menu also has edit, preview landing page, enable / disable (loads the event and saves it whole) and delete.
  - **Editor** `/admin/discounts/sale-events/new`, `/:id`: header with status, "starts / ends in…", "Priority N of M", Preview of the saved landing page (`useLandingPreview`: opens the storefront preview address or copies the token) and Delete. Cards: General (name, start, end, active); Products (`EventItemsSection`); Landing page (switch, upcoming / running / ended timeline, title, slug suggested from the name until typed, banner, TinyMCE description), followed by Social & indexing and the shared `SeoPanel` (type `sale_event`) while the landing page is on. Sticky Save, unsaved-changes guard.
  - **Products card:** rows grouped by product with linked %, amount off and final price fields (the typed one outlined), a dates button and remove per row, and notes for deleted / disabled variants, higher-priority events (`overridden_by`) and a lower own sale or promotion price. Filter chips All / Overridden / Own dates / Problems. Selected rows get a bulk bar: set discount (`SetDiscountDialog`, shows the result and skipped variants), set dates (`SetDatesDialog`), use event dates, remove. `AddProductsDialog` wraps the shared product picker (`products/components/ProductPickerDialog.vue`: every product, 20 per page by name, with search (name or SKU) and brand, category (sub-categories included), status and availability (in / out of stock) filters; the selection is kept across pages and filters ("Select this page", and "Select all N matching" via `GET products/matching`, up to 500); `locked` products are marked and can't be chosen, `initial` ones start ticked; a slot for extra fields); products already in the event are locked. Chosen products are added with all their enabled variants (`POST sale-events/variants`, in chunks of 500) and an optional starting % or amount; "Add more variants" adds a product's missing enabled variants the same way.
  - Save is refused in the browser while a row has a problem (the list switches to Problems); server errors `items.N.*` are shown on their row (`mapItemErrors`). Row logic (linked fields, rounding like `Pricing::eventDiscount()`, checks, bulk apply, payload) lives in `discounts/saleEvents.ts`, tested by `tests/js/saleEvents.test.mjs`. The audit log and the media library's "where it's used" link sale events to the editor.

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
| `GET categories` (`type`: one type's whole tree, nested) · `GET categories/{category}` | `categories.view` (product) / `blog_categories.manage` (blog) |
| `POST categories` · `PUT categories/{category}` · `DELETE categories/{category}` | `categories.manage` (product) / `blog_categories.manage` (blog) |
| `PUT categories/order` (`type`, `parent_id`, `ids`: the full new child order of that parent) | the type's manage permission |
| `GET brands` (all, in order, not paginated) · `GET brands/{brand}` | `brands.view` |
| `POST brands` · `PUT brands/{brand}` · `DELETE brands/{brand}` · `PUT brands/order` (`ids`: every brand, new order) | `brands.manage` |
| `POST seo-suggestions` (`type`, `name`, …; rate limiter `ai`) | the type's manage permission |
| `GET attributes` · `GET options` (all, in order, with values) | `attributes.view` |
| `POST`/`PUT`/`DELETE attributes[/{attribute}]` · `PUT attributes/order` · the same for `options` | `attributes.manage` |
| `GET categories/{category}/attributes` (own + inherited) | `categories.view` |
| `PUT categories/{category}/attributes` (`ids`, ordered) | `categories.manage` |
| `GET products` (filters) · `GET products/matching` (the same filters: every matching product's `id` and `name`, up to 500, plus `total` and `limit`; none above the limit) · `GET products/{product}` · `GET products/{product}/stock-movements` · `GET attributes/applicable?category_ids[]=` · `GET tags` | `products.view` |
| `POST products` · `PUT products/{product}` · `DELETE products/{product}` · `POST products/{product}/restore` · `POST products/status` | `products.manage` |
| `PUT tags/{tag}` · `POST tags/{tag}/merge` · `DELETE tags/{tag}` | `products.manage` |
| `GET products/bulk` (the list's filters + `quick`; `meta.quick_counts`) · `PUT products/bulk` (`variants`, all or nothing) | `products.manage` |
| `GET media` (`q`, `type`, `folder` id or `none`, `used`, `trashed`, `sort` newest/oldest/name/size, `per_page`) · `GET media/{id}` (with usages; trash too) · `GET media/folders` | `media.view` |
| `POST media/uploads` · `POST media/uploads/{id}/chunks` · `POST media/uploads/{id}/complete` · `DELETE media/uploads/{id}` · `PUT media/{id}` (`title`, `alt`, `cover_id`) | `media.upload` |
| `POST media/move` · `DELETE media/{id}` · `POST media/{id}/restore` · `POST`/`PUT`/`DELETE media/folders[/{id}]` | `media.manage` |
| `GET pages` (`q`, `status`, `needs_seo`, `trashed`, `sort`, `per_page`) · `GET pages/{page}` · `GET pages/{page}/revisions` · `GET pages/{page}/revisions/{revision}` (trash included) | `pages.view` |
| `POST pages` · `PUT pages/{page}` · `DELETE pages/{page}` · `POST pages/{page}/restore` | `pages.manage` |
| `POST pages/{page}/preview` | `pages.view` |
| `PUT pages/{page}/autosave` · `DELETE pages/{page}/autosave` | `pages.manage` |
| `GET menus/{location}` | `menus.view` |
| `GET menus/targets` | `menus.manage` |
| `PUT menus/{location}` (`items`, the whole tree) | `menus.manage` |
| `GET posts` (`q`, `status`, `category_id`, `tag_id`, `author_id`, `needs_seo`, `trashed`, `sort`, `per_page`) · `GET posts/authors` · `GET posts/{post}` · `GET posts/{post}/revisions[/{revision}]` · `POST posts/{post}/preview` | `posts.view` |
| `POST posts` · `PUT posts/{post}` · `DELETE posts/{post}` · `POST posts/{post}/restore` · `PUT`/`DELETE posts/{post}/autosave` | `posts.manage` |
| `GET promotions` | `discounts.view` |
| `PUT promotions` (`promotions`, `deleted`) · `GET promotions/variants` (product filters) | `discounts.manage` |
| `GET sale-events` · `GET sale-events/{saleEvent}` · `POST sale-events/{saleEvent}/preview` | `discounts.view` |
| `POST sale-events` · `PUT sale-events/{saleEvent}` · `DELETE sale-events/{saleEvent}` · `PUT sale-events/order` · `POST sale-events/variants` (`product_ids` ≤ 500: their enabled variants, as items show them) | `discounts.manage` |
| `GET byline` · `PUT byline` (`author_name`, `slug`, `author_bio`, `avatar_id`) | any staff (their own) |
| `GET settings` | any staff |
| `PUT settings` | `settings.manage` (super admin) |

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
- **RTL:** use logical utilities (`ps-*`, `pe-*`, `text-start`, `rtl:*`); phone numbers and codes are always `dir="ltr"`. Text, number and rich-text inputs follow the page direction (right-to-left in Farsi); only Latin-only values (slug, SKU, URL, email, phone, codes) set `dir="ltr"`.
- **Digits:** v-models hold Latin digits; show Persian digits in Farsi with `useLocale().localizeDigits()` (inputs: `PhoneField`, `OtpInput`).
- **API calls:** go through `@/shared/http/client` (`http`), which handles the CSRF cookie, `Accept-Language` and turns every failure into `ApiError`. Forms use `useApiForm([...fields])` for loading state and inline/general errors.
- **`defineModel` is one step behind:** after `model.value = next`, reading `model.value` in the same function still returns the old value until the parent re-renders. Compute the new value into a local variable and use that (see `VariantsEditor` `choose()`, `CategoriesField` `remove()`).
- **Text:** every UI string lives in `resources/js/shared/i18n/{fa,en}.ts` (`en` is typed against `fa`, so a missing key fails typecheck).
- **Shared UI kit** (`resources/js/shared/ui`): `AppButton` (variants primary/secondary/danger/danger-outline, sizes lg/md), `TextField`, `TextAreaField` (optional character counter), `NumberField` (whole numbers typed with any digits, grouped when not editing; `factor` converts shown ↔ stored, e.g. Toman ↔ Rial; `compact` for table cells), `PhoneField`, `OtpInput`, `SegmentedControl`, `SelectField` (native select), `SearchInput` (debounced), `AppDialog` (focus trap, Esc, bottom sheet on phones), `ActionMenu` (row "⋯" menu, keyboard navigable), `AppPagination`, `AppBadge`, `AppAvatar`, `EmptyState`, `FormAlert`, `ToastHost` + `useToast()`, `PreferenceSwitches`, `BrandMark`, `AppIcon`, `AppSpinner`. Reuse these before creating new ones.
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

- Tax (VAT).
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

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
