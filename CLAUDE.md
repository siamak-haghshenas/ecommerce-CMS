# Ecommerce CMS

Laravel coding guidelines (Boost) live in AGENTS.md and must be followed:

@AGENTS.md

The two API areas have their own docs:

- **`admin.md`**: the admin panel (Vue SPA), staff auth, user management, the Admin API table and every module's admin UI.
- **`frontstore.md`**: the Storefront API contract (customer auth, catalog, content, comments, previews, coupon check, shipping quote) for storefront builders, plus the storefront **design template** (Farsi-only, RTL; pages, tokens, components, API mapping and gaps).

@admin.md
@frontstore.md

## Project Overview

- A **reusable, headless e-commerce CMS**: a standalone backend/API platform for a **single store** (one business sells its own products) that any external storefront can be connected to.
- **The bundled storefront** (decided 2026-10-20, replacing "no storefront UI in this repo"): our own shop (`resources/js/storefront`, a Vue 3 SPA served at `/`, built to the design template in `frontstore.md`) lives in this repo, but it is **just another Storefront API client**: it uses only `/api/v1/*` (Bearer token, `X-Cart-Token`, no cookies, no Blade data), never imports from `admin/`, and nothing in the backend may depend on it. Other storefronts (Nuxt, React, mobile apps, ...) can still connect the same way.
- **This project contains:**
  - The **backend** (domain logic, database, integrations).
  - The **Storefront API** (`/api/v1/*`): everything a storefront needs, including the customer/account features (registration, login, password reset, profile, later cart/checkout/orders).
  - The **Admin panel** (`/admin`): a fully implemented Vue 3 SPA for staff (`admin.md`).
  - The **storefront** (`/`): the Farsi shop (`frontstore.md` › Bundled storefront).
- The backend and API must stay **decoupled from any specific storefront**: no storefront URLs, page structure or framework assumptions in backend code. Content that a storefront renders (slugs, SEO title/description, images, translations) is provided as **data through the API**; rendering, routing, SEO tags and sitemaps are the storefront's job.
- Commerce and content management are equally important: catalog, orders, payments, shipping plus CMS content (pages, blog, menus, ...), all managed in the admin panel and exposed via the API.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3, Pest 4, Pint.
- **Admin panel:** Vue 3 + TypeScript SPA, served at `/admin`.
- **Storefront:** Vue 3 + TypeScript SPA (`resources/js/storefront`, own entry and `resources/css/storefront.css`), served at every address outside `/admin`, `/api`, `/sanctum`, `/storage` and `/up`.
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
  - **`admin`**: view, edit and deactivate customers; view staff; catalog, discounts and shipping.
  - **`author`**: admin-panel access, viewing the media library and uploading / editing alt text and titles, managing pages and menus (`pages.*`, `menus.*`), the blog and comment moderation (`comments.moderate`).
  - **`customer`**: assigned on registration; no admin access.
- Permissions live in `App\Enums\Permission`; each role's permissions in `UserRole::permissions()`; `RolesAndPermissionsSeeder` syncs them. Adding a permission = add the enum case + list it on the roles that get it + re-run the seeder.
- **Phone number is the identifier**: required and unique for every user (only an anonymised customer has none); email is optional.
- **Registration** (customers, API only), **login** (phone + password, or phone → OTP; customers and staff), **password reset** and the **no user enumeration** rule: see `frontstore.md` › Customer Auth (staff: `admin.md` › Staff Auth).
- **Phone numbers:** Iranian mobile only, normalized to `09xxxxxxxxx` (Persian/Arabic digits and `+98` / `0098` / `9...` prefixes accepted as input).
- **OTP rules** (configurable in `config/otp.php`): 5 digits, stored hashed, 2-minute lifetime, max 5 wrong attempts per code, 60s resend cooldown, one active code per phone + purpose, rate-limited per phone and per IP.
- **Unverified registrations** are deleted automatically after 24 hours (scheduled task).
- **Customer auth: Bearer tokens** (details in `frontstore.md`); **staff auth: Sanctum cookie sessions** (details in `admin.md`).
- **Login is required to checkout** (no guest checkout).
- **Inactive accounts** can't log in, and an account deactivated while logged in is rejected on its next request (`active` middleware on every authenticated route). `last_login_at` is recorded on every login.
- User management (customers, staff, audit log): `admin.md`.

### Localization & Theming
- Farsi (fa) / English (en) translations are **only for the CMS interface itself**: the admin panel UI and API messages (errors, validation), chosen by the `Accept-Language` header.
- **Store content is not translated.** Categories, products, pages etc. have one name/description/SEO text, stored and returned exactly as the admin typed it. No translations package.
- Admin panel: Farsi/English with **RTL** layout switching, **Dark** and **Light** mode.

### Products (built in 4 phases, each reviewed and committed: 1 attributes & options ✅, 2 products core backend ✅, 3 admin UI ✅ (`admin.md`), 4 storefront catalog API ✅ (`frontstore.md`))
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
- **Store settings** (`App\Support\Settings`, cached): `price_display_currency` (`IRT` default / `IRR`), `low_stock_threshold` (default 5) `storefront_preview_url` (null default; an http/https address containing `{token}`) and `default_weight_grams` (null default; the weight shipping uses for products without one). `GET admin/settings` for all staff; `PUT` needs `settings.manage` (no role lists it: super admin only).
- **Protections now active:** deleting a category or brand with products (deleted ones included) → 409 `category_has_products` / `brand_has_products`; deleting an attribute or option products use → 409 `catalog_item_in_use`; removing a value products use → 409 `catalog_value_in_use`; changing the type of an attribute products use → 422 `attribute_type_locked`.
- **Audit log:** `product.created|updated|deleted|restored` (updated lists changed parts: fields, `categories`, `tags`, `options`, `variants`, `specifications`, `images`), `tag.renamed|merged|deleted`, `settings.updated`. Stock changes are in `stock_movements`, not the audit log.
- The SEO system covers products too (`SeoContentType::Product`, AI page description "a product page").
- Admin UI and the bulk edit page (`PUT admin/products/bulk`): `admin.md`. Storefront catalog API: `frontstore.md`.

### Attributes & Options (products phase 1)
- **Attributes** (`attributes`, `attribute_values`): name, Latin slug (unique; used in storefront filter URLs), `type` (`text`, `number` + optional `unit`, `boolean`, `select` with ordered values), `is_filterable`, manual `position`. Only `select` attributes have values; values have a Latin slug unique within the attribute. Saving an attribute syncs its values (update by id, create new, delete missing, order = array order).
- **Options** (`options`, `option_values`): variant dimensions with `display_type` `text` (buttons) or `color` (swatches; each value then needs `color_hex`), manual `position`, values synced the same way.
- **Category attributes** (`attribute_category`, ordered): `PUT admin/categories/{category}/attributes` sets a category's own attributes; `GET` returns own + inherited (from ancestors, with the category they come from). Products (phase 2) get the union over all their categories.
- **Who manages:** Super Admin and Admin (`attributes.view`, `attributes.manage`; assigning attributes to a category needs `categories.manage`).
- Reordering (`PUT attributes/order`, `PUT options/order`) takes every id of the list (409 `catalog_list_changed` otherwise), like brands.
- Deleting an attribute, option or value products use is refused (see Products backend), as is changing a used attribute's type.
- **Audit log:** `attribute.created|updated|deleted|reordered`, `option.created|updated|deleted|reordered`, `category.attributes_changed`.

### Categories
- Fields: `parent_id`, `name`, `slug`, `description`, `meta_title`, `meta_description`, `position` (order among siblings), `is_active`, plus one image from the media library.
- **Unlimited depth.** Stored as `parent_id` only; `App\Support\CategoryTree` loads all categories in one query, caches the tree as plain arrays (cleared on every category save/delete) and answers ancestors, descendants, visibility and "can move" questions. No tree package.
- **Slug:** Latin only (`a-z`, `0-9`, single hyphens), unique per type. The admin UI suggests one from the name via Finglish (`admin.md`).
- **Image:** one media library image (`image_id` in the save; `media_usages` role `image`). The API returns `{ id, original, thumb, medium, large, alt }`.
- **Moving:** a category can never move under itself or one of its descendants (`category_invalid_parent`).
- **Delete** is refused while the category has sub-categories (`category_has_children`) or products (`category_has_products`, added with the products module). Nothing is moved or unlinked automatically; the admin moves them first.
- **Hiding:** `is_active = false` hides the category **and its whole branch** from the Storefront API. Each sub-category keeps its own flag, so showing the parent restores the branch as it was.
- **Who manages:** Super Admin and Admin (`categories.view`, `categories.manage`). Author has no access to product categories (blog categories: see Blog).
- **Two types in one table** (see Blog › Categories): `type` = `product` (default) or `blog`.
- **Audit log:** `category.created`, `category.updated` (changed field names, incl. `image`), `category.moved` (from/to parent ids), `category.reordered`, `category.deleted` (name and slug kept in properties, as category data is not personal).

### Brands
- **Flat list** with a **manual order** (`position`). New brands go last. `PUT admin/brands/order` takes the complete list of ids (409 `brand_list_changed` when it doesn't match the current brands).
- Fields: `name` (unique; as the brand writes it, e.g. Adidas), `persian_name` (optional, unique when set, e.g. آدیداس; searchable in the admin, returned by the Storefront API, given to the AI as `alternate_name`), `slug` (Latin, unique, suggested via Finglish like categories), `description`, `website` (optional http/https URL), `is_active`, `position`, the shared SEO fields, and one **logo**: a media library image (`logo_id` in the save; role `logo`; returned as `{ id, original, thumb, medium, large, alt }`).
- **Products:** one brand per product (`brand_id`, added with products). Delete is refused while a brand has products (`brand_has_products`, added with the products module); hiding is the alternative.
- **Who manages:** Super Admin and Admin (`brands.view`, `brands.manage`). Author has no access.
- **Audit log:** `brand.created`, `brand.updated` (changed field names, incl. `logo`), `brand.reordered`, `brand.deleted` (name kept).

### Media Library (built in 3 phases, each reviewed and committed: 1 backend & API ✅, 2 Media page ✅, 3 picker + moving products/categories/brands onto the library ✅; UI in `admin.md`)
- **One shared library:** every file is stored once as a `MediaAsset` (`media_assets`; the file itself is its single spatie media in the `file` collection) and items point to it through `media_usages` (`media_asset_id`, `usable` morph, `role`: `gallery`, `variant_image`, `image`, `logo`, `content`; `position`). "Where it's used" and the in-use checks read that table (plus video covers). Phase 3 moves product galleries, variant images, category images and brand logos onto it with a one-time migration; storefront responses keep their shape and gain `alt`.
- **Files** (`config/media.php`, `MediaType`): images JPG/PNG/WebP/AVIF/GIF ≤ 10 MB (no SVG), PDF ≤ 20 MB, video MP4/WebM ≤ 200 MB. The **real content type is read from the file** on completion (not the name or the announced type). Images are turned upright (EXIF orientation), scaled down to 2560px on the longest side and re-saved, which drops location and camera details (animated GIFs kept as they are); they get webp versions `thumb` (320, fitted), `medium` (800), `large` (1600). PDFs and videos are kept as uploaded. A video can have a **cover picture** (`cover_id`: an image of the library). Columns: `type`, `name`, `mime_type`, `size`, `width`, `height`, `title`, `alt`, `folder_id`, `cover_id`, `uploaded_by`.
- **Chunked uploads only** (no request reaches PHP's 2 MB upload limit): `POST media/uploads` announces `name`, `mime_type`, `size`, `folder_id` (type and size checked up front) → `POST media/uploads/{id}/chunks` (multipart `chunk` ≤ `chunk_kb` = 1 MB, `offset` must equal `received`, else 409 `media_chunk_out_of_order` with `received` to resume) → `POST media/uploads/{id}/complete` → the file. Uploads are private to their uploader (`mediaUpload` binding) and pruned with their temp file (`storage/app/private/media-uploads`) after 24 h.
- **Folders:** one level (`media_folders`, unique name); deleting a folder moves its files to "no folder". Moving files: `POST media/move` (`ids`, `folder_id` or null).
- **Delete = trash:** refused while in use (409 `media_in_use`); otherwise soft-deleted, restorable, and removed for good (file and versions) by `model:prune` after `trash_days` = 30 (files in use are never pruned).
- **Permissions:** `media.view` (browse), `media.upload` (upload, edit title/alt/cover), `media.manage` (move, delete, restore, folders). Admin has all three; Author has view + upload.
- **Audit log:** `media.uploaded|updated|moved|deleted|restored`, `media_folder.created|renamed|deleted` (file and folder names, field names; no personal data).
- Error codes (`MediaException`): `media_in_use`, `media_type_not_allowed`, `media_too_large`, `media_chunk_out_of_order`, `media_chunk_too_large`, `media_upload_incomplete`.
- **Items use the library** (`App\Models\Concerns\UsesMediaLibrary`): products (`galleryAssets`, role `gallery`), categories (`imageAssets`, role `image`) and brands (`logoAssets`, role `logo`) link library files through `media_usages`; `syncMediaAssets($role, $ids)` saves a role in order and reports whether it changed (audit fields `images` / `image` / `logo`). A hard-deleted item (category, brand) frees its files; a soft-deleted product keeps them in use (it can be restored). Saves validate ids as library **images not in the trash** (`HasMediaFields`). The SEO `image` check asks `hasSeoImage()`. A variant's picture is `image_asset_id`, one of its product's gallery files. Editing a file's alt text clears the cached category tree.
- **Moving existing images** (migration `move_item_images_to_media_library`): each category image, brand logo and gallery image became a library file (its media row now belongs to the file; the stored file did not move) used by its item in the same role, gallery order kept; variant pictures moved from `image_media_id` to `image_asset_id`. **After deploying, run `php artisan media-library:regenerate "App\Models\MediaAsset" --only-missing`** so moved files get the library's versions (`large`, etc.).

### SEO Analysis & AI Suggestions (shared by every content type)
- **One shared SEO module** for every content type with a public page (categories, brands, products, pages, posts, sale events). A content type plugs in with: the SEO columns (`meta_title`, `meta_description`, `focus_keyphrase`, `seo_score`, `readability_score`), the `App\Models\Concerns\HasSeo` trait (+ `seoImageCollection()`), a case in `App\Enums\SeoContentType`, and the shared `SeoPanel` (`admin/modules/seo`) in its admin form.
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
- **Audit log:** `page.created|updated|deleted|restored` (changed field names only; restoring a revision is a normal save).
- **Backend:** `Page` (`state()`: draft / scheduled / published; `visible()` and `matching()` scopes; `seoName()` = title, since `SeoSubject::seoName()` lets content types name their title column), `PageRevision`, `SavePageAction` (one action for create and update: sanitizes via the request, syncs the image and `content` usages, scores SEO, keeps a revision unless the saved fields equal the newest one, e.g. a status-only change), `DeletePageAction` (trash/restore), `SearchPagesAction`. Admin list: `q` (title, slug), `status` (`draft`/`published`/`scheduled`), `needs_seo`, `trashed`, `sort` (`updated` default, `newest`, `title`).
- **SEO & social fields** (besides the shared SEO fields): `robots_noindex`, `canonical_url` (absolute http/https), `og_title`, `og_description` and an **OG image** (media library image, role `og_image`). Kept in revisions and autosaves. The Storefront API resolves them into a `seo` block (`frontstore.md`).
- **Preview** of drafts, scheduled pages and unsaved changes: `POST admin/pages/{page}/preview` (`pages.view`) returns `{ token, url, expires_at }`. The token (`App\Support\PreviewToken`) is signed with the app key and carries the page, the staff member and an expiry (60 minutes); `url` is the store setting `storefront_preview_url` with `{token}` filled in, or null when not set (the editor then copies the token). Storefronts call `GET /api/v1/pages/preview/{token}` (`frontstore.md`). The editor autosaves right before asking for a token, so Preview shows the form as it is.
- **Autosave** (`page_autosaves`, one per page and staff member): while an existing page has unsaved changes the editor sends `PUT admin/pages/{page}/autosave` (`pages.manage`) every 30 seconds with the form's fields (lengths checked, content sanitized; no unique-slug or publishing checks). It never changes the page, the version history or the audit log, and records no media usages. `GET admin/pages/{page}` includes the current staff member's `autosave` when it is newer than the page; the editor then offers **Restore** or **Discard** (`DELETE admin/pages/{page}/autosave`). A normal save deletes the saver's autosave; autosaves go with their page. New pages autosave after their first Save.
- Not included: nested pages, page-builder blocks, diffs between revisions and the blog (separate module).

### Menus
- **Two fixed locations** (`App\Enums\MenuLocation`: `header`, `footer`), each an ordered list of items with optional **sub-items (two levels)**. Table `menu_items`: `location`, `parent_id`, `position`, `type` (`MenuItemType`: `page`, `category`, `url`), `page_id` (a page removed for good removes its items), `category_id`, `url` (absolute http/https, or a path starting with `/`), `label` (required for links; page and category items fall back to the title / name), `open_in_new_tab`.
- **Who manages:** Super Admin, Admin and Author (`menus.view`, `menus.manage`).
- **Saving:** `PUT admin/menus/{location}` (`items`: the whole tree, saved in one go: updated by id, new ones created, missing ones deleted, order = array order). Audit log `menu.updated` (`location`).
- **Unavailable targets** are hidden, never silently removed: the menu editor marks them "Not visible" with the reason, the Storefront API leaves them out. Deleting a category a menu links to is refused (409 `category_in_menu`); remove the menu item first.

### Blog (4 phases, each reviewed and committed: 1 category types ✅, 2 posts backend & API + author bylines ✅, 3 posts admin UI ✅, 4 comments ✅)
- **Categories (one table):** `categories.type` (`App\Enums\CategoryType`: `product` default, `blog`). A tree never mixes types (a blog category's parent is a blog category; checked on save and move). Products only go in product categories, posts only in blog categories; attributes only on product categories. **Slugs are unique per type** (unique index `type + slug`). `CategoryTree` keeps one cached tree and answers per type (`visibleIds(type)`, `children(parent, type)`, ...). Deleting refuses categories with products **or posts** (`category_has_products` / `category_has_posts`). Admin endpoints take `type` (`GET categories?type=blog`, `PUT categories/order` with `type`; `POST categories` with `type`, ignored on update) and check the type's permission themselves (`CategoryType::viewPermission()` / `managePermission()`); specifications endpoints are 404 for blog categories. SEO: `SeoContentType::BlogCategory` (`blog_category`, AI suggestions need `blog_categories.manage`); duplicate keyphrases are compared within the same type (`SeoSubject::seoPeerConstraints()`). `GET /api/v1/categories` stays product-only; blog categories have their own storefront endpoints. Menu items can link to either type (the storefront response gives `category.type`).
- **Who manages:** Super Admin, Admin and Author: `posts.view`, `posts.manage` and `blog_categories.manage` (blog categories only; the Product tab still needs `categories.*`). Comment moderation: `comments.moderate` (same roles).
- **Posts** (`posts`): `title`, `slug` (Latin, unique), `excerpt`, `content` (full rich text: library images/videos, Aparat/YouTube; usages role `content`), featured image (role `image`), `status` draft / published + `published_at` (scheduling), `author_id` (a staff member), `comments_enabled` (default true), the shared SEO fields (`SeoContentType::Post`) and the social & indexing fields (`robots_noindex`, `canonical_url`, `og_*`, OG image role `og_image`), `created_by` / `updated_by`. **Categories:** several, one primary (`category_post.is_primary`, breadcrumbs). **Tags:** typed freely with suggestions, **the same `tags` table as products** (`post_tag`); the Tags page shows product and post counts, rename / merge / delete apply to both, and refresh affected products' search text. **Reused from Pages:** trash (30 days, `model:prune`), revisions (last 20), autosave (per post and staff member), preview tokens (the same storefront preview address), media usages. Revisions and autosaves keep the same fields as pages (not categories or tags). Audit log `post.created|updated|deleted|restored` (updated lists changed fields plus `categories`, `tags`, `image`, `og_image`). **Backend:** `Post` (`state()`, `visible()`, `matching()`, `primaryCategory()`), `PostRevision`, `PostAutosave`, `SavePostAction` (one action for create and update; the author defaults to the writer; tags through `ResolveTagsAction`), `DeletePostAction`, `SearchPostsAction`, `SavePostAutosaveAction`, `PreviewPostAction` / `ResolvePostPreviewAction`, `SearchStorefrontPostsAction`. Preview tokens name what they show (`PreviewToken::PAGE` / `POST`; tokens made before carry no kind and count as pages), so a page token never opens a post. Tags: `GET admin/tags` needs `products.view` **or** `posts.view` (suggestions in both editors); rename / merge / delete stay with `products.manage`.
- **Author bylines:** staff may fill an optional public **byline** on their own profile (`GET`/`PUT admin/byline`, any staff member, only their own; an empty name removes it): `author_name`, `author_slug` (Latin, unique; sent as `slug`), `author_bio`, avatar (library image, `media_usages` role `avatar`, "where it's used" shows the byline). A post exposes the byline only (never the account's name, phone or email).
- **Comments** (`comments`, `Comment`, on **blog posts and products**): one table with a polymorphic `commentable` link (`commentable_type` stores the model class like the project's other morph links; the API names the kind with `App\Enums\CommentableType`: `post`, `product`). Posts and products use `App\Models\Concerns\HasComments` (`comments()`, `acceptsComments()` = `comments_enabled`, default on for both; removed for good, the item takes its comments, in the trash it keeps them). Only **logged-in customers** (Storefront API Bearer token, active account) comment, on visible items with comments on (else 409 `comments_closed`). Migration `move_comments_to_commentable` turned the first `post_comments` table into `comments`. Columns: `user_id` (the customer; linked for moderation only), `display_name` (≤ 60), `body` (plain text ≤ 2000; line breaks kept, control characters dropped), `status` (`App\Enums\CommentStatus`), `moderated_by` / `moderated_at` (the last status change), `rejection_reason` (≤ 500, **staff only**, kept only while rejected), `reply` / `replied_by` / `replied_at`.
  - **Workflow:** new comments are always `pending` (`AddCommentAction` reads only `display_name` and `body`, so a `status` sent by the client changes nothing). Staff move a comment to `approved`, `rejected` or `spam`, and between those three; **nothing goes back to `pending`**. Only **approved** comments are public. Delete is separate and permanent. Nobody moderates their own comment (403 `comment_own`; staff can't comment anyway, the endpoint is for customer tokens).
  - **Storefront** (`Storefront\Comments\CommentController`, the route default `type` says which kind; `FindCommentableAction` finds the visible post or product): endpoints in `frontstore.md`.
  - **Admin API** (`comments.moderate`): `ModerateCommentsAction` (one comment, strict: the same status and reason again → 409 `comment_status_unchanged`; or a bulk selection ≤ 100, all or nothing, where comments already in the status are left as they are), `ReplyToCommentAction` (one reply per comment, the store's answer, shown once approved), `DeleteCommentsAction`, `SearchCommentsAction` (`q` in text and name, `status` default `pending` or `all`, `type` post/product, `item_id` with `type`; each row has `item { type, id, title, slug, is_deleted }`), `CountCommentStatesAction` (`meta.status_counts`, the menu badge). Errors: `CommentException`, messages in `lang/{fa,en}/comments.php`.
  - **Anonymising a customer deletes their comments.** Audit log `comment.approved|rejected|spammed|replied|reply_deleted|deleted`: the comment id, the item's `type` and `item_id` and the statuses only (never the text, name or reason).

### Discounts (promotions per SKU ✅, sale events ✅, coupons ✅ (definitions, admin, eligible items, sample order, check endpoint); applying coupons and counting usage come with checkout & orders)
- **Kinds:** **promotions** (one row per SKU, see below), time-limited **sale events** (see Sale events) and **coupons** (one code per order). The first promotions (a discount over chosen products) were removed on 2026-10-11 at the user's request (migration `remove_promotions`) and rebuilt per SKU the same day (`create_sku_promotions_table`). Buy X get Y, tiered and cart-level automatic rules are not included.
- **Who manages:** Super Admin and Admin (`discounts.view`, `discounts.manage`). Author has no access.
- **Best price wins, never stacked:** a variant's current price is the lowest of its active sale price, the sale event chosen for it and its running promotion row, never above its regular price. Amounts are whole Rials, rounded down (to whole Toman when the store displays Toman).
- **One pricing service** (`App\Support\Pricing\Pricing::price($variant, $at, $eventItems, $promotions)` → `VariantPrice`) computes prices for the admin, the Storefront API and later the cart. The current price is stored per variant (`product_variants.current_price`, `discount_source` `sale|sale_event|promotion|null`, `sale_event_id`, `promotion_id`, `max_per_order`, `price_recalc_at`) by `RefreshPricesAction` (with `SaleEventSet` and `PromotionSet`) so storefront filters and sorting by price stay SQL; it runs after a product save, a bulk edit, sale event and promotion saves, and `php artisan prices:refresh --due` runs every minute. The variant's `saving` hook keeps the stored price in line with its own sale until then.
- **Order limit:** `max_per_order` (1–10,000, null = none) on each promotion row and sale event item: the most units of that variant **one order** may hold at that price. Stored on the variant with the price it belongs to (null when a sale or the regular price gives it) and returned by the Storefront API as `variants[].max_per_order`. **The cart (not built yet) must refuse more than this.**
- **Promotions** (`promotions`, `Promotion`): **one row per SKU** (`product_variant_id`, `product_id`), `discount_type` percent (1–90) | fixed (Rials off each unit, below the price), `discount_value`, `max_per_order`, `quantity_limit` (discounted units in all; null = unlimited), `sold_count` (**counted by orders later**; the row stops once it reaches the limit), `starts_at` / `ends_at` (both required). A variant may have several rows **whose dates don't overlap** (checked on save, saved rows included). `state()`: scheduled / active / sold_out / ended. Hard delete. Audit log `promotion.created|updated|deleted` (subject the row; properties `sku`, changed `fields`).
  - **Admin API:** `GET promotions` (every row with its variant and product, grouped by product name; `discounts.view`) · `PUT promotions` (`promotions`: new rows without `id` and changed ones, `deleted`: ids; all or nothing, errors `promotions.N.*`; returns every row; `discounts.manage`) · `GET promotions/variants` (the product list's filters: products by name, 20 per page, with their enabled variants (only available ones with `stock=in`), each with price, stock and `has_promotion`; `discounts.manage`). `SavePromotionsRequest` (percent ≤ 90, fixed < price, no new row on a deleted variant, no overlap), `SavePromotionsAction`, `ListPromotionsAction`, `FindPromotionVariantsAction`.
- **Coupons** (`coupons` + `coupon_targets`): one **shared code** per coupon (Latin letters and digits, unique, case-insensitive; typed or generated), `name` (admin), `type` `percent` | `fixed` (off the order) | `free_shipping`, `value`, `max_discount`, `min_order_amount`, eligible items (all, or chosen products / categories / brands / tags, with excluded products), **`exclude_discounted`** (items whose price comes from a sale, sale event or promotion don't count), `usage_limit` (total), `per_customer_limit` (default 1), `first_order_only`, `starts_at` (null = from saving), `ends_at` (null = no end), `is_active`; percent 1–90 (`MAX_PERCENT`, like promotions). **Who may use it** (`customer_scope`, `App\Enums\CouponCustomerScope`, from the approved design): all logged-in customers, or **chosen customers** (`coupon_customer` pivot, `Coupon::customers()` / `User::coupons()`, at most `Coupon::MAX_CUSTOMERS`; others get "this code isn't for your account", `Coupon::isFor($customer)`; `AnonymiseCustomerAction` detaches them). **Eligible items** (`coupon_targets`: `coupon_id`, `target` morph, `is_excluded`; `Coupon::categories()` / `brands()` / `tags()` / `products()` / `excludedProducts()`, at most `Coupon::MAX_TARGETS` per list): `applies_to` (`CouponAppliesTo`) `all` or `selected` (≥1 category, brand, tag or product; product categories only; a chosen category covers its sub-categories); excluded products apply either way and can't also be chosen. Deleting a category, brand or tag removes it from coupons (`IsCouponTarget`); merging a tag moves its coupons. Validated by `HasCouponTermsFields` (also used by the sample order, prefix `coupon.`), saved by `SyncCouponTargetsAction` (audit fields `targets`, `excluded_products`). **Calculation** (`App\Support\Pricing\CouponCalculator::quote(CouponTerms, items)` → `CouponQuote`): lines at the variant's current price; a line is ineligible as `excluded` › `not_targeted` › `discounted` (`CouponLineReason`); no eligible line → `no_eligible_items`, eligible lines below `min_order_amount` → `min_order` (`CouponProblem`, nothing taken off); percent rounded down to whole Rials / Toman (display currency) then capped by `max_discount`; fixed never more than the eligible lines; free shipping takes nothing off the items. **Built (admin, chosen customers included):** `SaveCouponRequest` (code uppercased with Latin digits, `^[A-Z0-9]+$`, 3–50, unique incl. deleted coupons; `value` required unless free shipping; percent ≤ 90, end after start in `after()`; the terms checks of `HasCouponTermsFields`) → `CouponData` (with `CouponTerms`) → `SaveCouponAction` (one action for create and update; no start = now; customers through `SyncCouponCustomersAction`; audit `coupon.created|updated|deleted` with `code` and changed `fields`, incl. `customers`), `DeleteCouponAction` (soft delete, 409 `coupon_in_use` once `used_count` > 0, `CouponException`), `SearchCouponsAction` (`Coupon::matching()` + `inState()`, newest first, paginated) + `CountCouponStatesAction` (`meta.status_counts`), `CouponResource` (list: `targets_count`, `excluded_count`, `customers_count`; detail adds `targets`, `excluded_products` (id, name) and `customers`). Migration `align_coupons_table_with_spec` renamed the first draft's columns. The customer rules live in `HasCouponCustomerFields` (`couponCustomerRules()` + `checkCouponCustomers()` in `after()`: customer accounts that aren't anonymised; deactivated ones may stay). **One coupon per order**, taken off the already-discounted prices. `state()`: active / scheduled / used_up / expired / disabled. A coupon used by an order can't be deleted (409 `coupon_in_use`), only disabled.
- **Checking a code** (until a cart exists): `POST /api/v1/coupons/check` (`CheckCouponRequest` → `CheckCouponAction` → `CouponCheckResource`; contract and error order in `frontstore.md`, messages in `lang/{fa,en}/coupons.php`). **Not yet** (need orders, phase 4: `coupon_redemptions`): `coupon_customer_limit_reached`, `coupon_first_order_only`, counting `used_count`.
- **Sample order** (admin): `POST admin/coupons/simulate` (`coupon` = the save payload, `items` `[{ variant_id, quantity }]` → lines with `eligible` / `reason` `not_targeted|excluded|discounted`, `subtotal`, `eligible_subtotal`, `uncapped_discount`, `discount`, `free_shipping`, `problem` `min_order|no_eligible_items`, `total`; nothing saved; `discounts.manage`).

### Sale events (2 phases, each reviewed and committed: 1 backend, pricing & Storefront API ✅, 2 admin UI ✅)
- **Time-limited sales on chosen variants** (weekend sale, end-of-year sale, ...), managed under Discounts (`discounts.view`, `discounts.manage`). Design preview approved by the user (list with drag-and-drop priority, editor with per-variant rows and bulk actions, landing page card, dialogs).
- **Tables:** `sale_events` (`name` shown to storefronts as the label, `starts_at` and `ends_at` both required, `is_active`, `position` = priority (0 = highest; new events go last), landing page: `has_landing_page`, `slug` (Latin, unique, required with a landing page), `landing_title`, `landing_description` (full rich text, library files as `media_usages` role `content`), banner (library image, role `banner`), the shared SEO fields (`SeoContentType::SaleEvent`) and the social & indexing fields like pages (OG image role `og_image`), `created_by` / `updated_by`) and `sale_event_items`, **one row per variant** (`sale_event_id`, `product_id`, `product_variant_id` unique per event, `discount_type` `percent` (1–90) | `fixed` (Rials off each unit, less than the variant's price), `discount_value`, `max_per_order`, optional `starts_at` / `ends_at` inside the event's dates). `product_variants.sale_event_id` + `discount_source` `sale_event`.
- **Price rule (user's choice A):** priority picks **one event per variant**: of the events that run for it now (active, inside the event's and the item's dates), the one highest in the list. Its price then competes with the variant's own sale price and promotion rows under the lowest-price rule; nothing stacks. Discounts are rounded down to whole display units; an item whose discount is not below the price (the price was lowered later) is skipped. Prices are refreshed on save, delete and reorder (the products concerned) and by `prices:refresh --due` when an event or item starts or ends.
- **Landing page (data only):** `landingStatus()` `upcoming` / `running` / `ended`; storefront endpoints in `frontstore.md`. Staff preview `POST admin/sale-events/{saleEvent}/preview` (`PreviewToken::SALE_EVENT`, any state).
- **Admin API:** `GET sale-events` (`q`, `status`; the whole list in priority order, not paginated) · `GET sale-events/{saleEvent}` (items with their variant, product, current price and `overridden_by`: higher-priority events sharing the variant and overlapping dates) · `POST` / `PUT` / `DELETE` (one save for the event and all its items; items updated by variant, missing ones removed) · `PUT sale-events/order` (`ids`: every event; 409 `sale_event_list_changed` otherwise) · `POST sale-events/{saleEvent}/preview`. Delete is a hard delete (frees its library files).
- **Audit log:** `sale_event.created|updated|deleted|reordered` (name and changed field names; `items`, `banner`, `og_image`).
- **Backend (phase 1):** `SaleEvent` (`state()`: disabled / scheduled / active / expired, `landingStatus()`, scopes `relevant()`, `withVisibleLanding()`, `inState()`; `HasSeo` with `seoName()` = landing title or name) + `SaleEventItem` (`startsAt()` / `endsAt()` / `runsAt()`: the item's window, falling back to the event's). `SaleEventSet` loads the items prices depend on, by variant, highest priority first; `Pricing::price($variant, $at, $eventItems, $promotions)` picks the event and returns `saleEventId`; `Pricing::eventDiscount()`. Actions `SaveSaleEventAction` (items synced by variant, `product_id` taken from the variant; reprices the products it had or has), `DeleteSaleEventAction`, `ReorderSaleEventsAction` (reprices products of events that haven't ended), `SearchSaleEventsAction`, `FindOverridingEventsAction`, `PreviewSaleEventAction` / `ResolveSaleEventPreviewAction`. Validation in `SaveSaleEventRequest::after()` (percent ≤ 90, fixed < the variant's price, item dates inside the event's, a deleted variant only if already in the event); `SaleEventException::listChanged()`. The admin detail lists each item's variant (label in option order, price, current price, source) and product (name, thumbnail). The media library shows banners and OG images as used by `sale_event`.

### Cart (backend & Storefront API ✅; checkout comes next)
- **Server carts for guests and customers** (`carts`, `cart_items`; `Cart`, `CartItem`). A customer has one cart (`user_id`, unique); a guest cart is found by the sha256 of the token the storefront sends in `X-Cart-Token` (`token_hash`; the plain token is returned once, in `meta.cart_token`, when the first add opens the cart). Cart routes use the `auth.optional` middleware (`AuthenticateIfTokenSent`: a Bearer token is authenticated like `auth:sanctum`, an invalid one → 401; none = guest) + `active` + rate limiter `cart` (60/min per customer or IP). Endpoints and response: `frontstore.md` › Cart.
- **Lines store only the variant, the quantity, `unit_price_seen` and `limited_from`.** Everything else is worked out on every read (`SummarizeCartAction` → `CartSummary` / `CartLine` / `CartCoupon`): current prices from `ProductVariant::currentPrice()`, availability and limits from `App\Support\Cart\CartCatalog` (unavailable = missing, deleted or disabled variant or a product `StorefrontProductQuery::visible()` doesn't return; out of stock = `! Product::isAvailable()`; line limit = min(`cart.max_quantity_per_line`, `max_per_order`, stock when tracked without backorders)). **Nothing is reserved** (stock is taken at order placement, later).
- **Problems** (`App\Enums\CartLineProblem`): `unavailable`, `out_of_stock`, `exceeds_limit`, `price_changed`. Lines are never changed or removed silently; unavailable / out-of-stock lines count in no total. `can_checkout` = ≥1 line and none unavailable, out of stock or above its limit. A changed price is reported once (the read stores the new `unit_price_seen`).
- **Writes** (`AddCartItemAction`, `SetCartItemQuantityAction`, `RemoveCartItemAction`, `ClearCartAction`; adds and sets lock the cart row): refused with `CartException` codes `cart_item_unavailable`, `cart_item_out_of_stock`, `cart_quantity_exceeded` (+ `max_quantity`), `cart_full` (+ `max_lines`, `cart.max_lines`), `cart_login_required`; messages in `lang/{fa,en}/cart.php`. A quantity above the limit can always be lowered to it.
- **Coupons (customers only):** `ApplyCartCouponAction` checks the code like `coupons/check` against the lines that can be bought (its `CouponException` is thrown, nothing saved) and stores `coupon_code`; a guest gets 422 `cart_login_required`. Every read checks it again; one that no longer applies keeps its code and reports the coupon error as `coupon.problem` (takes nothing off, doesn't block checkout). `RemoveCartCouponAction`.
- **Merge** (`POST cart/merge`, `MergeCartsAction`): the guest cart's lines move into the customer's (quantities added; a purchasable line over its limit is cut to it and keeps `limited_from`, reported as `exceeds_limit` until the customer sets the quantity); the customer cart's coupon stays, else the guest's; the guest cart is deleted. Unknown token = no change.
- **Shipping quote** without `items` uses the customer's cart and its coupon when it applies (`QuoteShippingAction`).
- `config/cart.php`: `max_quantity_per_line` (100), `max_lines` (100), `prune_after_days` guest 30 / customer 90 (`Cart::prunable()`, `model:prune`; a read touches `last_activity_at` at most hourly). `AnonymiseCustomerAction` deletes the customer's cart. No audit log, no admin UI.

### Pricing & Currency
- **One store currency**, selected by the admin in settings. All prices are stored and displayed in that currency.

### Payments
- First gateway: **Zarinpal** (Iranian gateway).
- Must be built behind a **pluggable gateway interface/driver** so gateways can be swapped or added easily.

### SMS
- Provider: **Faraz SMS** (used for OTP).
- Must also be behind a **pluggable driver interface**, so the provider can be changed easily.
- SMS drivers: `log` (local dev, codes appear in `storage/logs/laravel.log`), `array` (tests; read codes with the `sms()` Pest helper), `faraz` (production).

### Shipping (3 phases: 1 backend & API ✅, 2 admin UI ✅ (design approved by the user), 3 with checkout & orders)
- **Shipping methods** (`shipping_methods`, `ShippingMethod`, soft-deleted so orders can keep pointing at them), each with **one pricing `type`** (`App\Enums\ShippingMethodType`): `weight` (ranges of the order's weight in grams), `volume` (ranges of the order's volume in cm³) or `courier` (chosen cities). Fields: `name`, `description` (shown to customers), `fee_on_delivery` (**any type**: the carrier collects the fee on delivery (پس‌کرایه); it isn't part of the online payment and the order records it separately), `default_fee` (courier only), `packaging_weight_grams` (weight only, added once per order), `min_order_amount` (hidden below it), `free_over_amount`, `delivery_days_min` / `max`, `is_active`, `position` (the order customers see; new methods go last), `created_by` / `updated_by`. Volumetric weight is not used (v1).
- **Ranges** (`shipping_rates`: `range_start` included, `range_end` excluded, null = no upper limit, `fee`): start at 0 and follow each other without gaps or overlaps; only the last may be open (`HasShippingMethodFields::checkShippingMethod()`, messages in `lang/{fa,en}/shipping.php`). Above the last closed range the method isn't offered. **Courier cities** (`city_shipping_method`, `fee` null = the default fee; ≤ `MAX_CITIES`). Fields of another type must be null or left out; saving clears them, so changing the type leaves nothing behind.
- **Provinces and cities** (`provinces`, `cities`): fixed data, Iran's 31 provinces and their main cities from `IranLocationsSeeder` (in `DatabaseSeeder`; safe to run again: **run `php artisan db:seed --class=IranLocationsSeeder` on deploy**). No admin editor.
- **Calculation** (`App\Support\Pricing\ShippingCalculator`, one service for the admin test box, the storefront quote and later checkout): `basket(items, couponQuote)` measures the cart (each line at the variant's current price; weight = product `weight_grams` × quantity, or the store setting **`default_weight_grams`** (null default: products without a weight then hide weight methods); volume = L×W×H mm → cm³ rounded up × quantity, a missing or 0 dimension hides volume methods); `quote(ShippingTerms, basket, cityId)` → `ShippingOption`. Unavailable reasons (`ShippingUnavailableReason`, first applicable): `inactive`, `city_not_served`, `below_min_order`, `missing_weight`, `missing_dimensions`, `out_of_range`. **Minimum order and "free over" compare the items' total after the coupon discount.** Free shipping (`ShippingFreeReason`): `free_over` (the order reached the amount; reported first) or `coupon` (a `free_shipping` coupon, on every method); a paid-on-delivery fee then leaves nothing to collect. `remaining_for_free` = how much more the order needs. Weights and dimensions are per product (shared by its variants).
- **Who manages:** Super Admin and Admin (`shipping.view`, `shipping.manage`). Audit log `shipping_method.created|updated|deleted|reordered` (name and changed fields incl. `rates`, `cities`).
- **Admin API:** see the table in `admin.md`. `SaveShippingMethodAction` (one save for the method with its ranges or cities), `DeleteShippingMethodAction`, `ReorderShippingMethodsAction` (every method's id, else 409 `shipping_method_list_changed`, `ShippingException`), `SimulateShippingAction` (the test box: the unsaved method, priced even while switched off, sample items, optional city and coupon code (terms only); returns each line's price, weight (and whether it is the default) and volume, the totals and the option with its matched range).
- **Storefront API:** `GET /api/v1/provinces` and `POST /api/v1/shipping/quote` (`QuoteShippingRequest` → `QuoteShippingAction` → `ShippingQuoteResource`; contract in `frontstore.md`).
- **Checkout (phase 3):** the order sends the chosen method; the server recomputes with the same calculator (never trusting a client fee) and copies name, type, fee, fee on delivery and weight/volume onto the order.

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
  - `/api/v1/*`: **Storefront API** for external storefronts (`frontstore.md`). It is the product's public contract: keep it stable, generic and client-agnostic; breaking changes need a new version.
  - `/api/v1/admin/*`: **admin** (authenticated staff only, every endpoint permission-checked; `admin.md`).
- Admin and storefront have separate Resources; storefront resources must never expose admin-only fields. A resource with no account data (e.g. `OtpChallengeResource`) may live at `Http/Resources/V1/` and be shared.
- Errors are rendered by `App\Exceptions\ApiExceptionRenderer` (framework errors) and `App\Exceptions\AuthException` (auth error codes). Add new domain error codes as named constructors on a dedicated exception, with messages in `lang/{fa,en}`.
- `SetLocaleFromHeader` runs before authentication/throttling so every error is translated. Clients (the admin SPA and storefronts) should send `Accept-Language` with the user's chosen locale.
- Success responses use the default Laravel Resource shape (`{ data, meta, links }`).
- One error shape for every error, messages localized from the `Accept-Language` header (`fa` / `en`):

```json
{ "message": "...", "code": "otp_invalid", "errors": { "field": ["..."] } }
```

- Form requests share phone/code normalization and rules via `App\Http\Requests\Concerns\HasAuthFields`.

### Code Quality
- **Pint** for PHP formatting.
- **Larastan (PHPStan)** starting at level 6.
- **Pest architecture tests** that enforce only the agreed architecture above. No extra restrictions or abstractions.
- **ESLint + Prettier** for Vue/TypeScript.

### Commands
- PHP: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --memory-limit=1G`, `php artisan test --compact`.
- JS: `npm run typecheck`, `npm run lint`, `npm run format`, `npm run build` (or `npm run dev`).
- Local DB: MariaDB via WAMP, database `ecommerce-cms`; tests use `ecommerce_cms_test` (set in `phpunit.xml`).
- Env: `CORS_ALLOWED_ORIGINS` (storefront domains), `SANCTUM_TOKEN_EXPIRATION` (customer token lifetime in minutes), `AI_DRIVER`, `ANTHROPIC_API_KEY`, `AI_MODEL`, `AI_LIMIT_PER_USER_PER_HOUR`, `AI_LIMIT_PER_DAY`, `CART_MAX_QUANTITY_PER_LINE`, `CART_MAX_LINES`, `CART_GUEST_DAYS`, `CART_CUSTOMER_DAYS`.
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
