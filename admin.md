# Admin Panel & Admin API

The **Admin panel** (`/admin`) is the bundled Vue 3 + TypeScript SPA for staff; it talks to the **Admin API** (`/api/v1/admin/*`). Shared rules (domain, roles, backend architecture) are in `CLAUDE.md`; the Storefront API is in `frontstore.md`.

## Staff Auth

- **Sanctum cookie sessions** for the bundled admin SPA (same domain, no tokens in localStorage). The Admin API requires the `access-admin-panel` permission and rejects users without a staff role.
- Staff log in like customers (phone + password, or phone → OTP) on a separate `/admin` login page. `last_login_at` is recorded on every login.
- Endpoints `admin/auth/*`: `login`, `login/otp`, `login/otp/verify`, `me`, `logout`.
- SPA flow: `GET /sanctum/csrf-cookie` first, then send the `X-XSRF-TOKEN` header (axios does this automatically).
- The admin `me` endpoint returns **effective** permissions (the full list for a super admin); the frontend checks them with `useAdminAuthStore().can(Permission.X)` from `resources/js/admin/permissions.ts`. The UI only hides what the API also refuses.

## User Management (admin panel)
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
- `{customer}` / `{staffMember}` route bindings (AppServiceProvider) only resolve users of that kind — anything else is 404.
- Rule violations return `AccountActionException` codes: `cannot_change_own_account`, `last_super_admin`, `customer_anonymised` (409), `already_staff`, `name_required` (422).

## Admin API (`/api/v1/admin`, all behind `auth:sanctum` + `active` + `access-admin-panel`)
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
| `GET comments` (`q`, `status` default `pending` or `all`, `type` post/product, `item_id`, `per_page`; `meta.status_counts`) · `GET comments/pending-count` (`pending { post, product }`) · `PUT comments/{comment}/status` (`status` approved/rejected/spam, `reason` with rejected only) · `POST comments/status` (bulk: `ids` ≤ 100, `status`, `reason`) · `PUT`/`DELETE comments/{comment}/reply` (`reply`) · `DELETE comments/{comment}` · `POST comments/delete` (bulk: `ids` ≤ 100) | `comments.moderate` |
| `GET promotions` | `discounts.view` |
| `PUT promotions` (`promotions`, `deleted`) · `GET promotions/variants` (product filters) | `discounts.manage` |
| `GET sale-events` · `GET sale-events/{saleEvent}` · `POST sale-events/{saleEvent}/preview` | `discounts.view` |
| `POST sale-events` · `PUT sale-events/{saleEvent}` · `DELETE sale-events/{saleEvent}` · `PUT sale-events/order` · `POST sale-events/variants` (`product_ids` ≤ 500: their enabled variants, as items show them) | `discounts.manage` |
| `GET coupons` (`q`, `status`, `per_page`; `meta.status_counts`) · `GET coupons/{coupon}` | `discounts.view` |
| `POST coupons` · `PUT coupons/{coupon}` · `DELETE coupons/{coupon}` · `POST coupons/simulate` (`coupon`, `items`) | `discounts.manage` |
| `GET shipping-methods` (every method in the checkout order, with `rates` and `cities`) · `GET shipping-methods/{shippingMethod}` · `GET provinces` (with cities) | `shipping.view` |
| `POST shipping-methods` · `PUT shipping-methods/{shippingMethod}` · `DELETE shipping-methods/{shippingMethod}` · `PUT shipping-methods/order` (`ids`: every method) · `POST shipping-methods/simulate` (`method`, `items`, `city_id`, `coupon_code`) | `shipping.manage` |
| `GET byline` · `PUT byline` (`author_name`, `slug`, `author_bio`, `avatar_id`) | any staff (their own) |
| `GET settings` | any staff |
| `PUT settings` | `settings.manage` (super admin) |

- Category rule violations return `CategoryException` codes: `category_has_children`, `category_invalid_parent`, `category_tree_changed` (409: the `ids` sent don't include every current child, reload and retry).

## Frontend Structure (Vue 3 + TypeScript, feature modules)

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
- Laravel serves the admin Blade shell (`admin.blade.php`) via a catch-all `/admin/*` web route. Every other web address serves the storefront (`frontstore.md`).

## Frontend Conventions
- **Localization & theming:** Farsi/English with **RTL** layout switching, **Dark** and **Light** mode. Language and theme preferences are stored in `localStorage` and applied before first paint by `resources/views/partials/spa-head.blade.php`.
- **Colors:** use the semantic Tailwind tokens from `resources/css/app.css` (`bg-bg`, `bg-surface`, `text-fg`, `text-fg-muted`, `border-line`, `bg-primary`, `text-on-primary`, `text-danger`, ...). They switch automatically in dark mode, so components don't repeat `dark:` variants. Never use raw hex colors in components.
- **RTL:** use logical utilities (`ps-*`, `pe-*`, `text-start`, `rtl:*`); phone numbers and codes are always `dir="ltr"`. Text, number and rich-text inputs follow the page direction (right-to-left in Farsi); only Latin-only values (slug, SKU, URL, email, phone, codes) set `dir="ltr"`.
- **Digits:** v-models hold Latin digits; show Persian digits in Farsi with `useLocale().localizeDigits()` (inputs: `PhoneField`, `OtpInput`).
- **API calls:** go through `@/shared/http/client` (`http`), which handles the CSRF cookie, `Accept-Language` and turns every failure into `ApiError`. Forms use `useApiForm([...fields])` for loading state and inline/general errors.
- **`defineModel` is one step behind:** after `model.value = next`, reading `model.value` in the same function still returns the old value until the parent re-renders. Compute the new value into a local variable and use that (see `VariantsEditor` `choose()`, `CategoriesField` `remove()`).
- **Text:** every UI string lives in `resources/js/shared/i18n/{fa,en}.ts` (`en` is typed against `fa`, so a missing key fails typecheck).
- **Shared UI kit** (`resources/js/shared/ui`): `AppButton` (variants primary/secondary/danger/danger-outline, sizes lg/md), `TextField`, `TextAreaField` (optional character counter), `NumberField` (whole numbers typed with any digits, grouped when not editing; `factor` converts shown ↔ stored, e.g. Toman ↔ Rial; `decimals` allows decimal places in what is shown, e.g. kilos stored as grams; `compact` for table cells), `PhoneField`, `OtpInput`, `SegmentedControl`, `SelectField` (native select), `SearchInput` (debounced), `AppDialog` (focus trap, Esc, bottom sheet on phones), `ActionMenu` (row "⋯" menu, keyboard navigable), `AppPagination`, `AppBadge`, `AppAvatar`, `EmptyState`, `FormAlert`, `ToastHost` + `useToast()`, `PreferenceSwitches`, `BrandMark`, `AppIcon`, `AppSpinner`. Reuse these before creating new ones.
- **Formatting:** `useLocale()` gives `formatNumber`, `formatDate` (Solar Hijri in Farsi), `formatDateTime`, `formatRelativeTime`, `localizeDigits`.
- **Admin app:** `AdminLayout`: on desktop (`lg`+) the sidebar is open by default and the menu button collapses it to an icon rail (choice remembered in `localStorage`, content widens); below `lg` it is a drawer over the page. Menu items live in `admin/navigation.ts` with the permission they need; pages declare `meta.permission` and the router redirects users who lack it. Show actions only when `useAdminAuthStore().can(...)` allows them. Lists keep their filters in the URL query (customers). Destructive actions go through a confirmation dialog; irreversible ones (anonymise) require typing the customer's phone number.

## Admin UI by module

### Categories
- An expandable tree. Drag and drop (`vue-draggable-plus`) on desktop, plus "move up / move down / move to…" actions on every row for phones and keyboards.
- Slug suggestion while creating: Persian is transliterated to Finglish by `suggestSlug()` in `admin/modules/categories/tree.ts` (e.g. «ایران ۱۴۰۳» → `iran-1403`); editing the slug stops the suggestion.
- **Product / Blog** tabs (`?type=blog`; staff see the tabs they may use; the menu item and route accept either permission via `canAny()` / a permission list in `meta.permission`); the form has no Specifications section for blog categories and its SEO panel uses `blog_category`. The admin tree shows categories hidden by a parent as "hidden by parent" (`is_visible = false`).

### Brands
- The admin list returns **all** brands (no pagination, as the order is managed by hand); search and filters run in the browser. Drag and drop plus "move up / move down" on every row. The form suggests a PNG logo with a transparent background.

### Attributes & Options
- Catalog › Attributes (tabs Attributes / Options, drag-and-drop order, form dialog with a values editor) and an "Attributes" section in the category form.

### Products admin UI (phase 3)
- **Money in the admin:** `useMoney()` (`admin/modules/settings/store.ts`) formats Rials in the display currency from the settings store (loaded once); price inputs are `NumberField`s with `factor = rialsPerUnit` (10 for Toman), so the API always gets Rials.
- **Product list** (`/admin/products`): filters in the URL query (`q`, `status`, `category`, `brand`, `stock`, `needs_seo`, `trashed`, `tag`, `shipping`, `page`), row selection with bulk publish / draft / archive, restore for deleted products.
- **Product editor** (`/admin/products/new`, `/admin/products/:id`): one page with section links (General, Categories/brand/tags, Price/stock/variants, Images, Specifications, Shipping, SEO) and a sticky save bar (status, publish date, Save). Everything, the gallery included, is saved by one Save. The gallery (`ImagesManager`) adds pictures with the media picker (several at once, or uploaded from the picker), drag to reorder, "make main", remove; images without alt text get a badge. Removing a picture from the gallery clears it from variants using it; description images are inserted from the gallery (with their alt text). Leaving with unsaved changes asks first (route guard + `beforeunload`). An "Allow comments" switch and a "View this product's comments" link.
  - Variants (`products/variants.ts`, tested by `tests/js/variants.test.mjs`): the grid is the combinations of the chosen option values; regenerating keeps existing variants (id, price, stock) and prices new ones like the first row. SKUs are suggested from the slug and value slugs until typed. Stock is sent only when changed, with `stock_quantity_was`.
  - Specifications are fetched for the chosen categories (`attributes/applicable`); only filled-in ones are sent; their errors are placed by attribute id.
  - Short description and description: TinyMCE (`admin/modules/editor`, `RichTextEditor` with `variant` basic/full). The full toolbar: undo/redo, blocks (paragraph, H2–H4), bold/italic/underline/strikethrough, text and background colour, alignment, RTL/LTR, lists, quote, horizontal line, link, table, **library image**, **library video** (media picker, upload included), **Aparat/YouTube** (a link turned into the player address by `embedUrl()`), clear formatting, HTML source, fullscreen. The editor follows the panel's language (Farsi strings in `editor/fa.ts`, as the npm package ships none), direction and light/dark theme, with the Vazirmatn font; Persian half-spaces are kept as characters (`entity_encoding: raw`). TinyMCE tidies the HTML it loads, and the page takes that tidied text as its unsaved-changes baseline (`normalized` event). The SEO panel analyses `htmlToText()` of the description, matching the server's `RichText::toPlainText()`. The live duplicate-keyphrase check is not available for products (the server still checks on save).
  - Errors: fields with their own error display show it in place; any other error goes in the alert at the top (never hidden).
- **Tags page** (`/admin/tags`): rename, merge, delete; "N products" / "N posts" open the filtered lists.
- **Settings page** (`/admin/settings`): visible to all staff, editable by the super admin (`settings.manage`); includes "Default product weight" (`default_weight_grams`).
- Sale and publish dates use the browser's `datetime-local` picker (Gregorian); a Jalali date picker would need an extra package.

### Bulk edit page (`/admin/products/bulk-edit`, `products.manage`)
- **Inline editing like a seller panel, one Save for the whole page.** Opened from the menu ("Bulk edit") and the product list header. A card per product (image, name, status, summary: variants, price range, total stock; variable products can be folded) with a row per variant: image (the variant's own, else the product's), label, SKU, and editable **price, sale price, stock, active**. Sale dates are not edited here.
- Filters live in the URL: search, quick-filter chips with counts (`quick`: all / out of stock / low stock / on sale / inactive variant), and behind "Filters" status, category, brand and products per page (20 / 50 / 100); paginated.
- Fields read like text until hovered or focused. A changed field turns amber with the old value crossed out under it; the row gets an accent bar, the card a "N changes" pill. Empty sale = "+ Add sale"; a sale shows its discount pill and end date; × removes it. Stock has − / + buttons and a dot (out / low / in stock). Checks while typing: price and stock required, sale below the price; Save is disabled while any field is invalid.
- **Save = `PUT admin/products/bulk`** with `variants[i]`: `id` + only changed `price`, `sale_price`, `is_enabled`, `stock_quantity` (+ `stock_quantity_was`). **All or nothing**: errors come back as `variants.{i}.{field}` and are shown on the row; stock that changed since the page loaded → the row shows "now X", the page reloads its rows keeping the changes, and the next Save compares against the new number (409 `stock_changed` if it changes between validation and saving). A published product keeps ≥1 enabled variant; variants of deleted products and stock of untracked products are refused.
- Saving: a sale added here starts now with no end date (old dates are cleared); changing an existing sale price keeps its dates; removing a sale clears them. Stock goes through `AdjustStockAction` with reason `bulk_edit`. Each changed product gets one `product.updated` audit entry (`fields: ['variants']`, `bulk: true`).
- Unsaved changes: changing a filter or page, or leaving, asks **Save and continue / Discard / Stay**; reloading the tab warns. Enter moves to the same field in the next row. The floating save bar ("N unsaved changes · Undo · Save all") appears only with changes.
- Below `xl` the variant fields wrap into a grid (2 per row on phones). Logic in `products/bulkEdit.ts` (tested by `tests/js/bulkEdit.test.mjs`); the list's filters are the `Product::matching()` scope, shared with the product list.

### Media
- **Media page** (`/admin/media`, `admin/modules/media`): folders on the side (chips on smaller screens; rename/delete in each folder's menu on desktop) with All files, No folder and Trash; search, type chips (all / images / videos / PDFs), used/unused and sort; a grid of cards (picture, the video's cover with a play badge, a PDF tile; name, size, dimensions, "in use" badge) with "Load more" (40 per page). Filters and the open file live in the URL (`folder`, `q`, `type`, `used`, `sort`, `file`); the audit log links media entries to `?file=`.
- **Uploading:** the button or files dropped anywhere on the page (into the folder being viewed). The queue (`useMediaUploadStore`, shown by `MediaUploadQueue` in the admin layout) keeps going while staff move around the panel: two files at a time, 1 MB chunks, a failed chunk retried with back-off, an out-of-order chunk resumes from the server's `received`, "Retry" continues where the upload stopped, and a refused file shows the server's message. `ApiError.body` carries extra error fields such as `received`.
- **Details drawer** (slides in from the side, Esc closes): preview (image, video player with its cover, PDF link), title and alt text (upload permission), a video's cover chosen with `MediaPickerDialog`, type/size/dimensions/uploader, folder (manage permission: changing it moves the file), "where it's used" with links, copy address, download, delete (disabled, with the reason, for a file in use); in the trash: when it will be removed, and restore.
- **Selection** (manage permission; checkbox on hover, always on touch screens, Shift+click for a range): move to a folder, delete (files in use are skipped and reported), restore in the trash.
- **Picker** (`MediaPickerDialog`): search, "Load more", single or multiple choice (numbered in click order; files already used are greyed out) and an "Upload" button whose files are chosen as soon as they finish. `MediaImageField` is the one-picture form field (category image, brand logo, featured images).

### SEO panel
- The shared `SeoPanel` (`admin/modules/seo`) in every content type's form: live analysis (`admin/modules/seo/analysis.ts`), focus keyphrase, AI suggestions (pick or edit; nothing saved). Lists show the score as a dot with a "Needs SEO work" filter.

### Pages (`admin/modules/pages`)
- **List** `/admin/pages` (filters in the URL: `q`, `status` draft/published/scheduled, `sort`, `needs_seo`, `trashed`, `page`; rows show the image, SEO dot, slug, state, last edited and by whom; restore in the trash).
- **Editor** `/admin/pages/new`, `/admin/pages/:id` (Content card: title, slug suggested via Finglish until typed, TinyMCE full editor; side card: featured image (`MediaImageField`) and excerpt; a "Social & indexing" card; the shared `SeoPanel` with type `page`; sticky save bar with Draft/Published, publish date and Save; unsaved-changes guard; a page in the trash shows when it will be removed and only offers Restore).
- **History** (`VersionHistoryDialog`, shared with posts): versions newest first (the newest marked "current"), a preview of the chosen one (rendered content, image, excerpt, SEO fields) and "Restore this version", which loads it into the form (status and publish date untouched).
- **Autosave** every 30 seconds while an existing page has unsaved changes; on opening, a newer autosave offers **Restore** or **Discard**. **Preview** autosaves first, then opens the storefront preview address or copies the token.
- The audit log and the media library's "where it's used" link to the page editor.

### Menus (`/admin/menus`, `admin/modules/menus`)
- Header / Footer tabs, the tree with drag and drop (`vue-draggable-plus`) plus move up / down for phones and keyboards, an item dialog (page, category or custom link; label, new tab; the link is checked like on the server), "Not shown" badges with the reason, indent / outdent from each row's menu, one Save for the whole menu (Undo reloads it), and Save and continue / Discard / Stay when switching menus or leaving with changes. Tree helpers in `menus/tree.ts` (tested by `tests/js/menus.test.mjs`).
- `GET admin/menus/targets` lists every page not in the trash with its state and the category tree flattened with depth and visibility, so staff who manage menus need no pages or categories permission. `GET admin/menus/{location}` returns each item with `is_visible` + `hidden_reason` (`page_draft`, `page_scheduled`, `page_trashed`, `category_hidden`).

### Blog (`admin/modules/posts`, routes `/admin/blog`, `/admin/blog/new`, `/admin/blog/:id`)
- Content › Blog. **List**: search, status, blog category (sub-categories included), author, sort, Needs SEO work, Trash, and a removable tag chip (`?tag=`, set by the Tags page's "N posts" link); rows show the primary category and the author's byline (else account name).
- **Editor**: the Pages editor (content, excerpt and featured image, social & indexing, SEO panel with type `post`, autosave with Restore / Discard, Preview, History) plus a "Categories, tags & author" card: `CategoriesField` with the blog tree (primary), `TagsInput` (shared tags), the author picker (`GET admin/posts/authors`; staff without a byline are marked) and "Allow comments". The History dialog is shared with pages (`pages/components/VersionHistoryDialog.vue`, given `loadRevisions` / `loadRevision`).
- **Byline**: a pen button in the sidebar's account area opens `account/components/BylineDialog.vue` (name, slug suggested via Finglish, bio, picture; an empty name removes it).
- The audit log links post entries to the editor; the media library's "where it's used" links posts to the editor and bylines to Staff.

### Comments (`admin/modules/comments`)
- **Two separate pages**, one `CommentsPage` with a `type` prop: **Content › Blog comments** (`/admin/blog/comments`, route `post-comments`) and **Catalog › Product comments** (`/admin/products/comments`, route `product-comments`), each with its own pending count in the menu (`GET comments/pending-count` → `pending { post, product }`; `useCommentsStore`, `NavItem.badge`; refreshed on every page change, every minute while the tab is visible, when the tab comes back and right after a moderation).
- Each page: status tabs with counts (Pending default, Approved, Rejected, Spam, All), search and one item (`?item=5`, from the post or product editor's "View this … comments" link) in the URL; each comment shows the name (linked to the customer for staff with `customers.view`), the post or product (linked to its editor), the time, the status, who moderated it and when, the rejection reason and the store's reply; actions Approve / Reject (optional reason dialog) / Spam (the statuses it can move to), Edit reason, Reply / Edit reply / Remove reply, Delete (confirmation). Row selection ("Select this page") → bulk Approve, Reject, Spam, Delete. Logic in `comments/comments.ts` (tested by `tests/js/comments.test.mjs`).

### Discounts (Sales › Discounts tabs, `DiscountTabs`)
Promotions (`/admin/discounts`), Sale events (`/admin/discounts/sale-events`), Coupons (`/admin/discounts/coupons`).

- **Promotions** (`discounts/pages/PromotionsPage.vue`; design approved by the user): search and status chips with counts (all / active / scheduled / sold out / ended / problems) in the URL, 50 rows per page, rows grouped by product. Each row: variant and SKU, price, state, remove, then six fields: **off price** and **percentage** (linked; the typed one is outlined), **max in cart**, **discounted quantity** (with "N of M sold"), **start** and **end** (`PromotionDatesDialog`). Selection: a row, a product, the page, or **"Select all"** (every matching row of every product) → **bulk edit** (`PromotionBulkDialog`: plain inputs, **an empty input changes nothing** — discount as percent / amount off / off price (only when the rows share a price), max in cart, quantity, start, end; rows an amount can't apply to are skipped and reported; a bulk edit can't clear a limit, that is done per row) and remove. **"Add product"** (`PromotionVariantsDialog`): every SKU grouped by product with search, status, availability, category and brand filters, **published products and in-stock variants by default**, selection across pages (a product, the page, or **"Select all N matching"**: every matching product with all its variants, loaded 100 products per request), starting values (percent, max in cart, quantity, start/end = now + 7 days). Changes are kept until **Save all** (floating bar with Undo, like the bulk edit page); leaving or reloading with changes asks first; problems block saving and server errors go to their row. Logic in `discounts/promotions.ts` (tested by `tests/js/promotions.test.mjs`), sharing the linked-field helpers of `discounts/saleEvents.ts` (`tsconfig` allows `.ts` import extensions so Node's test runner can load them).
- **Sale events — list** `/admin/discounts/sale-events`: the whole list in priority order; search and status filter in the browser and live in the URL. Rows: drag handle, priority number, name, product / variant counts, landing page chip, dates, status. Drag and drop (`vue-draggable-plus`, off while filtering) and the row menu's Move up / Move down save the order (`PUT sale-events/order`); the menu also has edit, preview landing page, enable / disable (loads the event and saves it whole) and delete.
- **Sale events — editor** `/admin/discounts/sale-events/new`, `/:id`: header with status, "starts / ends in…", "Priority N of M", Preview of the saved landing page (`useLandingPreview`: opens the storefront preview address or copies the token) and Delete. Cards: General (name, start, end, active); Products (`EventItemsSection`); Landing page (switch, upcoming / running / ended timeline, title, slug suggested from the name until typed, banner, TinyMCE description), followed by Social & indexing and the shared `SeoPanel` (type `sale_event`) while the landing page is on. Sticky Save, unsaved-changes guard.
  - **Products card:** rows grouped by product with linked %, amount off and final price fields (the typed one outlined; typing one fills the other two; a percentage is saved if the % was typed, otherwise a fixed amount off), an order limit, a dates button and remove per row, and notes for deleted / disabled variants, higher-priority events (`overridden_by`) and a lower own sale or promotion price. Filter chips All / Overridden / Own dates / Problems. Selected rows get a bulk bar: set discount (`SetDiscountDialog`, shows the result and skipped variants), set order limit (`SetLimitDialog`), set dates (`SetDatesDialog`), use event dates, remove. `AddProductsDialog` wraps the shared product picker (`products/components/ProductPickerDialog.vue`: every product, 20 per page by name, with search (name or SKU) and brand, category (sub-categories included), status and availability (in / out of stock) filters; the selection is kept across pages and filters ("Select this page", and "Select all N matching" via `GET products/matching`, up to 500); `locked` products are marked and can't be chosen, `initial` ones start ticked; a slot for extra fields); products already in the event are locked. Chosen products are added with all their enabled variants (`POST sale-events/variants`, in chunks of 500) and an optional starting % or amount; "Add more variants" adds a product's missing enabled variants the same way.
  - Save is refused in the browser while a row has a problem (the list switches to Problems); server errors `items.N.*` are shown on their row (`mapItemErrors`). Row logic (linked fields, rounding like `Pricing::eventDiscount()`, checks, bulk apply, payload) lives in `discounts/saleEvents.ts`, tested by `tests/js/saleEvents.test.mjs`. The audit log and the media library's "where it's used" link sale events to the editor.
- **Coupons** (design approved by the user): **list** `discounts/pages/CouponsPage.vue` (search and status chips in the URL, counts from `meta.status_counts`, paginated; rows: code with copy, name, discount, applies to / condition, usage bar, dates, status; menu: edit, copy code, make a copy (`coupon-create?from=id`), enable / disable (loads and saves the whole coupon), delete, refused with an explanation once used). **Editor** `/admin/discounts/coupons/new`, `/:id` (`CouponEditorPage.vue`): cards Code and name (code kept as uppercase Latin letters and digits, "Generate"), Discount (percent + optional cap / fixed amount / free shipping), Applies to (all or chosen: `CouponTargetsDialog` with Categories / Brands / Tags / Products tabs, a chosen category covering its sub-categories; excluded products via the product picker; "leave out discounted items"), Who can use it (all or chosen: `CouponCustomersDialog`, search active customers), Conditions and limits, Schedule (+ active); beside it the **sample order** (`CouponSampleOrder`: sample products from the product picker, variant and quantity per line, results from the server via `POST admin/coupons/simulate`) and usage. Sticky Save, unsaved-changes guard, client checks in `discounts/coupons.ts` (tested by `tests/js/coupons.test.mjs`) repeated by the server. The pickers reuse `GET categories`, `brands`, `tags`, `products`, `customers` (the Admin role has those permissions).

### Shipping (`admin/modules/shipping`, Sales › Shipping, `shipping.view`; editing `shipping.manage`; design approved by the user)
- **List** `/admin/shipping` (`ShippingMethodsPage`): every method in checkout order with drag and drop (`vue-draggable-plus`) and Move up / Move down (`PUT shipping-methods/order`); search, status (all / active / inactive) and pricing type in the URL (filtered in the browser, dragging off while filtering). Rows: position, name and delivery days, type chip, fee range and ranges/cities summary, where it delivers, "paid with the order" / "paid on delivery" chip, free-from and minimum order, an active switch (saves the whole method) and a menu (edit, make a copy `shipping-method-create?from=id`, move, delete with confirmation). A warning shows the products shipping can't measure (`meta.unmeasured_products` from `GET shipping-methods`: products without a weight, or missing a dimension) with links to the product list filtered by them (`shipping=missing_weight|missing_dimensions`, a removable chip there; `Product::missingWeight()` / `missingDimensions()` scopes).
- **New method** (`NewShippingMethodDialog`): name and type (weight / volume / courier cards) → the editor (`/admin/shipping/new?type=&name=`); new methods start switched off.
- **Editor** `/admin/shipping/new`, `/admin/shipping/:id` (`ShippingMethodEditorPage`): cards Method details (name, delivery days from/to, description for customers), Pricing (type switch; changing it clears the other type's ranges, cities, default fee or packaging), Who pays the shipping fee (with the order / on delivery, every type), Conditions and free shipping, Status; sticky Save, unsaved-changes guard, delete. **Ranges** (`RatesEditor`): kilos or litres (`NumberField` `decimals` 3, `factor` 1000), each row starts at the previous end (only the end and fee are typed), add / remove keep them contiguous; weight methods also packaging weight (grams) and a note on the store's default weight. **Courier**: default fee and the city list with a fee per city (empty = default), added through `CourierCitiesDialog` (provinces on the side, tick cities or a whole province, search every province; `GET admin/provinces`). **Test box** (`ShippingTestBox`): sample products (product picker, variant and quantity), destination city and coupon code → `POST shipping-methods/simulate`: each line's weight or volume (default weight marked), order weight/volume, the matched range, items' total after the coupon, why the method isn't offered, free shipping and how much more is needed, the fee, what the carrier collects, and a "what customers see" preview. Logic in `shipping/shipping.ts` (tested by `tests/js/shipping.test.mjs`).
- The audit log lists `shipping_method.*` entries and links them to the editor.
