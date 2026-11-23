# Storefront API

The **Storefront API** (`/api/v1/*`) is the product's public contract: everything an external storefront (Vue, Nuxt, React, Next.js, mobile apps, ...) needs. Storefronts live in their own projects; this CMS renders no public pages. Keep the API stable, generic and client-agnostic; breaking changes need a new version.

Shared rules (domain, roles, OTP, pricing, architecture) are in `CLAUDE.md`; the admin panel and Admin API are in `admin.md`.

## Basics

- **Base URL:** `/api/v1`. JSON REST, Eloquent API Resources; success responses use the default Laravel Resource shape (`{ data, meta, links }`).
- **Locale:** send `Accept-Language` (`fa` / `en`); every message (errors, validation) is translated from it. `SetLocaleFromHeader` runs before authentication/throttling so every error is translated. **Our storefront is Farsi only:** it always sends `Accept-Language: fa` and has no language switch.
- **Errors:** one shape for every error:

```json
{ "message": "...", "code": "otp_invalid", "errors": { "field": ["..."] } }
```

- **Rate limits:** 429 `too_many_requests` with `retry_after`; the `Retry-After` header is exposed.
- **CORS:** `config/cors.php` allows `api/*` from the origins in `CORS_ALLOWED_ORIGINS` (comma-separated; `*` by default, set real storefront domains in production). No credentials (cookies) are used cross-origin.
- **Storefront resources must never expose admin-only fields** (focus keyphrase, SEO scores, authors' accounts, revisions, stock numbers, limits, coupon names/customers, ...).
- **Money:** whole **Rials** everywhere, sale-aware. Every catalog response has `meta.currency {display: IRT|IRR, rials_per_unit}` so storefronts can display Toman or Rial as the store chose.
- **Store content is not translated:** names, descriptions and SEO text are returned exactly as the admin typed them.

## Storefront Data (SEO, URLs, media)

- Public content (products, categories, brands, pages, blog posts, sale events) has a **slug** and **SEO fields** (meta title, meta description, image) that admins edit and the Storefront API returns. Storefronts build their own URLs, meta tags, Open Graph, JSON-LD and sitemaps from this data.
- The CMS renders no public pages, `sitemap.xml` or storefront meta tags. If needed later, provide data endpoints (e.g. a list of all public slugs with `updated_at`) rather than storefront markup.
- Images are returned as `{ id, original, thumb, medium, large, alt }` (webp versions: `thumb` 320 fitted, `medium` 800, `large` 1600).
- Rich text (descriptions, page/post content, landing descriptions) is **sanitized HTML** (see `App\Support\RichText` in `CLAUDE.md`): library images/videos with their real addresses, Aparat/YouTube iframes only, links with `rel="noopener noreferrer"`.
- **Resolved `seo` block** (pages, posts, sale events): `title` (meta title → title), `description` (meta description → excerpt), `canonical_url`, `robots_noindex`, `og { title (OG title → SEO title → title), description (OG description → meta description → excerpt), image (OG image → featured image) }`. The raw fields (`robots_noindex`, `canonical_url`, `og_title`, `og_description`, OG image) are returned as typed too.

## Customer Auth

- **Registration:** name, phone number, password (required) and optional email → phone verified by OTP → account active and an API token is returned. Unverified registrations are deleted after 24 hours.
- **Login, two methods:** phone number + password, or phone number → OTP code.
- **Password reset:** phone number → OTP → set a new password.
- **Phone numbers:** Iranian mobile only, normalized to `09xxxxxxxxx` (Persian/Arabic digits and `+98` / `0098` / `9...` prefixes accepted).
- **OTP:** 5 digits, 2-minute lifetime, max 5 wrong attempts per code, 60s resend cooldown, rate-limited per phone and per IP.
- **No user enumeration:** requesting a login/password-reset OTP for an unregistered phone returns the same generic success response; password login with an unknown phone returns the generic `invalid_credentials`.
- **Endpoints** (`auth/*`): `register`, `register/otp` (resend), `register/verify`, `login`, `login/otp`, `login/otp/verify`, `password/otp`, `password/reset`, `me`, `logout`.
- **Bearer tokens:** `register/verify`, `login`, `login/otp/verify` and `password/reset` accept an optional `device_name` and return `{ data: { token, token_type: "Bearer", expires_at, customer } }` (`CustomerTokenResource`). The client sends `Authorization: Bearer <token>`. Tokens carry the `customer` ability, are named by `device_name` (default `storefront`), expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 30 days) and are pruned daily. Logout revokes only the token used; a password reset or deactivation revokes all of the customer's tokens. How a storefront stores the token is up to that storefront.
- **Inactive accounts** can't log in, and an account deactivated while logged in is rejected on its next request (`active` middleware on every authenticated route).
- **Login is required to checkout** (no guest checkout).
- Tests: call the Storefront API like an external client with `asStorefront($token, $method, $uri)`; check a login response with `assertIssuedTokenFor($response, $user)` and failures with `assertNoTokenIssued()` (`tests/Pest.php`).

## Catalog (public)

### Categories
- `GET /api/v1/categories` (nested tree of visible product categories) and `GET /api/v1/categories/{slug}` (detail with description, SEO fields, image, breadcrumb and visible children; hidden or unknown → 404).
- A category is visible when it and all its ancestors are active (`is_active = false` hides the whole branch).

### Brands
- `GET /api/v1/brands` (visible brands in position order), `GET /api/v1/brands/{slug}` (`persian_name`, logo, description, website, SEO fields). Hidden or unknown → 404.

### Products
- **Visible product** (`StorefrontProductQuery::visible()`): published, `published_at` passed, not deleted, ≥1 enabled variant, and ≥1 category that is visible (`CategoryTree::visibleIds()`). Anything else is 404 on the detail endpoint.
- `GET /api/v1/products`: `q` (every normalized word in `search_text`), `category` (slug, includes visible sub-categories), `brand` (slug, active only), `tag` (slug), `sale_event` (slug of an event with a visible landing page), `filter[attribute-slug]` (`a,b` select values · `13..16`, `13..` or `..16` number range · `1`/`0` boolean), `option[option-slug]=red,blue` (**one enabled variant must match every chosen option**), `min_price` / `max_price` (Rials, on the current price), `in_stock`, `on_sale`, `sort` (`newest` default, `price_asc`, `price_desc`, `name`), `page`, `per_page` (≤48, default 24). An unknown or non-filterable slug matches nothing (never silently ignored). Cards: `price` / `regular_price` `{min,max}`, `discount_percent`, `in_stock`, `has_variants`, `variant_id` (the only variant of a simple product, for add-to-cart from a card; null with several) and `discount_ends_at` (the soonest end of a discount giving a variant its price, for deal countdowns; null when none ends).
- `GET /api/v1/products/filters` (same scope parameters): `total`, `price {min,max}`, brands, filterable attributes (on a category: that category's own and inherited ones; values products have, with product counts; number min/max; boolean yes/no counts) and option values used by enabled variants. Counts ignore the refining filters (`filter`, `option`, price, stock, sale).
- `GET /api/v1/products/{slug}`: description (sanitized HTML), `short_description` (HTML) and `short_description_text` (plain, for cards and meta tags), brand, visible categories with `is_primary`, breadcrumb (from the primary category), tags, images (the first is the main image), options (only values enabled variants use), variants, specifications (display text), shipping, SEO fields, `comments_enabled`, `comments_count` (approved only).
- **Variants:** `option_value_ids`, `price` (current), `regular_price`, `discount_percent`, `sale_ends_at`, `in_stock`, `low_stock`, `image_id`, `max_per_order`, `discount_source` (`sale|sale_event|promotion|null`); priced by a promotion: `promotion { ends_at, remaining }` (`remaining` null = unlimited); priced by a sale event: `sale_event { name, slug (null without a visible landing page), ends_at }`.
- **Prices:** a variant's current price is the lowest of its sale price, its sale event and its promotion, never stacked. `max_per_order` = the most units one order may hold at that price (null = no limit); the cart must refuse more.
- **Stock numbers are never exposed:** only `in_stock` (untracked stock or backorders count as in stock) and `low_stock`. Inactive brands are left off (`brand: null`).

### Sale events (landing pages)
- A landing page is visible as soon as its switch is on and the event is active, before the start included; `status` `upcoming` (countdown to `starts_at`, normal prices) → `running` (event prices, countdown to `ends_at`) → `ended` (stays visible with normal prices until switched off). Switched off, disabled or deleted → 404.
- `GET /api/v1/sale-events` (events with a landing page that haven't ended, in priority order) · `GET /api/v1/sale-events/{slug}` (title, description, banner, dates, status, SEO fields and the resolved `seo` block) · products through `GET /api/v1/products?sale_event={slug}` (the usual filters and sorting).
- Staff preview: `GET /api/v1/sale-events/preview/{token}` (any state; see Previews).

## Content (public)

### Pages
- `GET /api/v1/pages` (published pages: slug, title, excerpt, `updated_at`, `robots_noindex`; for footer links and sitemaps: a storefront may link a noindex page but leave it out of its sitemap).
- `GET /api/v1/pages/{slug}` (content, excerpt, featured image, SEO fields, the social & indexing fields, the resolved `seo` block, `published_at`, `updated_at`). Drafts, scheduled, trashed or unknown → 404.
- Flat list (no parent pages); storefronts build their own URLs from the slug.

### Menus
- `GET /api/v1/menus/{location}` (`header`, `footer`) → items `{ label, type, url (links), page { slug, title } | category { slug, name, type }, open_in_new_tab, children }` (two levels). Items whose page or category isn't visible are left out, with their sub-items. Unknown location → 404.

### Blog
- `GET /api/v1/blog/categories` (tree of visible blog categories) and `GET /api/v1/blog/categories/{slug}`.
- `GET /api/v1/blog/posts` (published, newest first, paginated; `category` slug incl. visible sub-categories, `tag` slug, `author` byline slug, `q`).
- `GET /api/v1/blog/posts/{slug}` (content, categories with breadcrumb from the primary, tags, author, `comments_enabled`, `comments_count`, the resolved `seo` block). Draft, scheduled, trashed → 404. A post without a visible blog category is still listed.
- **Author:** `author { name, slug, bio, avatar }` only when the post's author has a public byline, else `author: null`; the account name, phone and email are never exposed.

### Comments (blog posts and products)
- `GET /api/v1/blog/posts/{slug}/comments` and `GET /api/v1/products/{slug}/comments`: approved comments, oldest first, paginated (`per_page` ≤ 50): `id`, `display_name`, `body`, `created_at`, `reply { body, created_at }` (the store's answer); never the account, status or a staff name.
- `POST` to the same addresses (customer Bearer token, active account): `display_name` (≤ 60; prefill with the customer's name, editable) and `body` (plain text ≤ 2000, line breaks kept) → 201 with `meta.status: pending`. New comments are always pending until staff approve them. Rate limiter `comments`: 3 a minute and 30 a day per customer.
- Comments closed on the item → 409 `comments_closed`. Items the storefront doesn't show (draft, scheduled, trashed, unknown; products also need an enabled variant and a visible category) → 404.

## Previews (staff → storefront)

- Staff ask for a preview in the admin; the storefront receives a signed token (60 minutes) at the store setting `storefront_preview_url` (e.g. `https://shop.example/preview?token={token}`), or the staff member copies it.
- `GET /api/v1/pages/preview/{token}`, `GET /api/v1/blog/posts/preview/{token}`, `GET /api/v1/sale-events/preview/{token}`: the item in the same shape as its normal detail endpoint whatever its status (not when in the trash), with that staff member's autosave applied when newer (pages, posts). Sent with `Cache-Control: no-store` and `X-Robots-Tag: noindex`. Invalid or expired tokens → 404. Tokens name what they show, so a page token never opens a post.

## Cart (guests and customers)

- **Whose cart:** with a customer Bearer token, the customer's cart (one per account). Without one, a **guest cart** found by the `X-Cart-Token` header. The first add-to-cart of a guest without a cart opens one and returns its token **once** in `meta.cart_token`: the storefront keeps it and sends it on every cart call (only a hash is stored). An unknown token counts as no cart; reading never opens one. An invalid or expired Bearer token → 401 (it is never treated as a guest); a deactivated account → 403 `account_inactive`.
- **After login:** `POST /api/v1/cart/merge` (customer token) with `cart_token`. The guest cart moves into the customer's and is deleted. Quantities of a variant in both carts are added; a line that can be bought is cut to its limit and gets `limited_from` (what it asked for) and the `exceeds_limit` problem until the customer changes it. The customer cart's coupon stays (else the guest cart's). Safe to repeat: an unknown token changes nothing.
- **Endpoints** (rate limiter `cart`: 60/min per customer, or per IP for guests). Every one returns the whole cart:

| Method & path | What |
|---|---|
| `GET /api/v1/cart` | the cart (empty when there is none) |
| `POST /api/v1/cart/items` | `variant_id`, `quantity` (default 1): adds to the line, or creates it |
| `PUT /api/v1/cart/items/{variant_id}` | `quantity` (≥ 1): sets it; 404 when the variant isn't in the cart |
| `DELETE /api/v1/cart/items/{variant_id}` | removes the line |
| `DELETE /api/v1/cart` | empties the cart (lines and coupon) |
| `PUT /api/v1/cart/coupon` · `DELETE /api/v1/cart/coupon` | `code` (normalized like `coupons/check`): applies (replacing the cart's coupon) / removes; **customers only** |
| `POST /api/v1/cart/merge` | `cart_token` (customer token) |

- **Line limit:** the smallest of the store cap (`config('cart.max_quantity_per_line')`, 100), the order limit of the sale event or promotion giving the price (`max_per_order`) and, for tracked stock without backorders, the stock left. At most `config('cart.max_lines')` (100) lines. **Nothing is reserved:** stock is taken only when an order is placed (later).
- **Errors** (422, `CartException`, messages in `lang/{fa,en}/cart.php`): `cart_item_unavailable` (unknown, disabled or deleted variant, or a product the storefront doesn't show), `cart_item_out_of_stock`, `cart_quantity_exceeded` (+ `max_quantity`), `cart_full` (+ `max_lines`), `cart_login_required` (a guest applying a coupon). A coupon that can't be used → its coupon error code (see Coupon check), and it isn't saved.
- **Response** `data`:
  - `lines[]`: `variant_id`, `sku`, `product { id, name, slug }`, `image { thumb, medium, alt }` (the variant's picture, else the product's main one), `options [{ name, value }]` (e.g. رنگ: مشکی), `quantity`, `max_quantity` (0 when it can't be bought), `limited_from`, `unit_price`, `regular_price`, `discount_percent`, `discount_source`, `price_was`, `total`, `regular_total`, `problems`.
  - `lines_count`, `items_count` (units, every line: the header badge), `regular_subtotal`, `savings` («سود شما از خرید»), `items_subtotal`, `coupon`, `total` (items after the coupon, before shipping), `can_checkout`.
  - `coupon`: `{ code, type, discount, free_shipping, ends_at, problem }` or null. It is **checked again on every read**; one that no longer applies keeps its code, takes nothing off and gives `problem { code, message, …fields }` (e.g. `coupon_min_order` + `min_order_amount`). It doesn't block checkout.
  - `meta.currency`, `meta.cart_token`.
- **Line problems** (worked out on every read; a line is **never changed or removed silently**):
  - `unavailable`: the product is hidden or deleted, or the variant disabled or deleted.
  - `out_of_stock`.
  - `exceeds_limit`: the quantity is above `max_quantity`, or a merge cut it (`limited_from`).
  - `price_changed`: the price isn't the one the customer last saw (`price_was`). Shown once; the next read no longer reports it.

  Lines that are `unavailable` or `out_of_stock` count in no total. **`can_checkout`** is false while the cart is empty or any line is unavailable, out of stock or above its limit; a line cut by a merge and a changed price are only notices.
- Guest carts are removed after 30 days without use, customer carts after 90 (`config('cart.prune_after_days')`, `model:prune`). Anonymising a customer deletes their cart.

## Checkout helpers (customer Bearer token, `active`)

### Coupon check (without a cart)
- `POST /api/v1/coupons/check` (rate limiter `coupon-check` 10/min per customer) with `code` (normalized: uppercased, Latin digits) and `items` `[{ variant_id, quantity }]` (≤ 100, each an enabled variant of a visible product, else `items.N.variant_id` errors) → `code`, `type` (`percent|fixed|free_shipping`), `value`, `max_discount`, `min_order_amount`, `ends_at` and the quote (`lines` with `unit_price`, `total`, `eligible`, `reason` `not_targeted|excluded|discounted`; `subtotal`, `eligible_subtotal`, `uncapped_discount`, `discount`, `free_shipping`, `total`); never the name, limits or customers.
- Errors (422), checked in this order: `coupon_not_found`, `coupon_not_for_customer`, `coupon_inactive`, `coupon_expired`, `coupon_limit_reached`, `coupon_not_started` (+ `starts_at`), `coupon_no_eligible_items`, `coupon_min_order` (+ `min_order_amount`). Later with orders: `coupon_customer_limit_reached`, `coupon_first_order_only`.
- One coupon per order, taken off the already-discounted prices.

### Shipping
- `GET /api/v1/provinces` (public: provinces with cities, for address forms).
- `POST /api/v1/shipping/quote` (rate limiter `shipping-quote` 30/min per customer) with `city_id` and either `items` `[{ variant_id, quantity }]` (like `coupons/check`) with an optional `coupon` (checked like `coupons/check`; its errors are returned), or **no `items`: the customer's cart** (the lines that can be bought, and the cart's coupon when it applies; sending `coupon` without `items` is a validation error) → `items_subtotal`, `coupon_discount`, `items_total` and `methods` (**only the available ones**, in the admin's order): `id`, `name`, `description`, `type`, `delivery_days {min,max}`, `original_fee`, `fee`, `free_reason` (`free_over|coupon`), `fee_on_delivery`, `payable_now` (paid online), `collect_on_delivery` (paid to the carrier), `free_over_amount`, `remaining_for_free`.
- Checkout (later) sends the chosen method; the server recomputes the fee and never trusts a client fee.

## Not built yet

Checkout, orders, payments (Zarinpal), stock reservation, counting coupon use, wishlist and the customer's addresses / order history endpoints. Ask before designing them (order lifecycle is an open question in `CLAUDE.md`).

---

# Bundled storefront (`resources/js/storefront`)

- A Vue 3 + TypeScript SPA (vue-router, pinia, axios), served by `storefront.blade.php` at every address outside `/admin`, `/api`, `/sanctum`, `/storage`, `/up`. Farsi only, RTL, light only. It is **an ordinary Storefront API client** (`http/client.ts`: Bearer token and `X-Cart-Token` from localStorage, `Accept-Language: fa`, no cookies) and never imports from `admin/` (only `shared/utils`).
- **Styling:** Tailwind with the template's tokens in `resources/css/storefront.css` (`bg-ink`, `text-primary-ink`, `bg-primary-soft`, `max-w-store`, ...); never raw colors. Font: Vazirmatn (Readex Pro would need a new package).
- **Static content** (store name, delivery note, trust badges, support phone, hero slides, promo cards, home section sizes) lives in `content.ts` until the CMS has store-info and home-section settings. Addresses are built in `links.ts` (`paths`, `menuLink()` for CMS menu items); money in `money.ts` (Rials → display currency from `meta.currency`, Persian digits); deal countdowns in `deals.ts`.
- **Built:** layout (header with search, account and cart badge; nav bar = categories + deals + the `header` menu; footer = trust row + the `footer` menu as columns; mobile bottom tab bar), **Home** (hero slider: sale events with a banner, then `content.ts` slides; promo cards from `content.ts`, else the first two categories; category circles; deals = `products?on_sale=1&in_stock=1` with countdown bars; new arrivals = `sort=newest` with add-to-cart), 404. Every other template page is a "coming soon" route. Best sellers waits for orders; the newsletter form waits for an endpoint.

# Storefront Design Template

**Source:** the design canvas https://claude.ai/artifact/DjncsGq6GNMoMwx4p9VgAp ("آمپر، قرمز و مشکی"), 31 artboards: 27 pages, 3 shared parts and a mobile home screen. It is **our template**: build the storefront to match it. The store name «آمپر» and every product, price, person and text in it are **sample content**; real content comes from the API. Text in `[brackets]` is a placeholder the store owner supplies.

## Language & locale
- **Farsi only, RTL.** `<html lang="fa" dir="rtl">`, no language switch, every UI string in Farsi.
- **Digits:** show Persian digits everywhere (prices, counts, dates, steps, pagination, cart badge). Codes stay Latin and `dir="ltr"`: order codes (`AMP-1405-48213`), SKUs, phone numbers (`0912 *** 4567`), countdown timers (`01:24`).
- **Numbers:** group thousands with the Persian separator `٬` (e.g. `۳٬۲۳۰٬۰۰۰`).
- **Money:** shown in **Toman** with the word `تومان` in a smaller, lighter `<small>` after the number. The API gives Rials: divide by `meta.currency.rials_per_unit` and follow `meta.currency.display`. Large ranges may be written in words (`از ۳۰۰ هزار` / `تا ۴ میلیون تومان`).
- **Dates:** Solar Hijri (`۱۲ مهر ۱۴۰۵`), relative where recent (`امروز`, `امروز ۱۰:۱۲`). Countdown labels like `۱۸ ساعت مانده`.
- **Phone input:** `inputmode="tel"`, `dir="ltr"`, placeholder `09xx xxx xxxx`; accept Persian digits (the API normalizes).

## Design tokens

### Colors (red and black on light grey)
| Token | Hex | Use |
|---|---|---|
| `ink` | `#18181B` | text, dark header/footer, dark buttons and chips, selected outlines |
| `ink-2` | `#27272A` | inputs on the dark footer |
| `line-dark` | `#3F3F46` | borders and dividers on dark backgrounds |
| `muted` | `#52525B` | secondary text, icons |
| `muted-2` | `#71717A` | counts, struck-through old prices |
| `line-strong` | `#D4D4D8` | footer text, swatch borders |
| `line` | `#E4E4E7` | card and input borders, dividers |
| `bg` | `#F4F4F5` | page background, soft tiles, inner dividers |
| `bg-2` | `#FAFAFA` | order card header strip |
| `surface` | `#FFFFFF` | cards, panels |
| `primary` | `#DC2626` | primary buttons, discount badges, active tab underline, progress bars, logo tile, links on hover |
| `primary-ink` | `#991B1B` | links, red text on light red, "you save" amounts |
| `primary-soft` | `#FEF2F2` | soft red badges and panels (active sort, "in delivery" status) |
| `primary-ring` | `#FEE2E2` / `#FECACA` | current step ring, countdown track |
| `primary-on-dark` | `#F87171` | icons on the dark footer |

Links: `#991B1B`, no underline; hover `#DC2626`.

### Typography
- **Font:** `'Readex Pro', Vazirmatn, Tahoma, sans-serif` (weights 300–700; Vazirmatn 400/700 as fallback). The canvas loads them from Google Fonts; **the storefront should self-host them** (no CDN), like the admin does with Vazirmatn.
- **Base:** 15px, line-height 1.65. Long text (product description, about) line-height 2, max ~60ch.
- **Scale:** 11 (price unit `تومان`), 12–12.5 (badges, captions), 13–13.5 (breadcrumbs, meta, chips, sort), **14 (default UI text)**, 15, 16 (button labels, card prices), 17–18 (section headings), 20–22 (page titles, cart total), 24–26 (product title, big prices, logo, hero), hero headline larger.
- **Weights:** 400 body, 500 product names and labels, 600 headings, buttons, active tabs, 700 prices and the logo.

### Shape, spacing, layout
- **Radii:** 16px cards and panels · 14px buy box · 12px inner cards (order, address, wishlist, similar products) · 10px buttons, inputs, thumbnails · 8px chips and small buttons · 6px badges · 18px pill chips · 50% avatars, step dots, icon circles.
- **Container:** `max-width: 1336px`, `margin: 0 auto`, side padding 24px. Content pages (pay result) narrower: 760px; auth forms 420px.
- **Gaps:** 12–20px between cards, 28px between home sections, 16–24px card padding (28px on the product panel).
- **Heights / touch targets:** buttons and inputs 44px (main CTA 50–52px, OTP digits 60px, header search 46px); everything clickable ≥ 44px.
- **Product grids:** cards separated by 1px `#E4E4E7` lines inside one rounded white panel (category list), or separate white cards with 12px gaps (home, similar, wishlist).
- **Icons:** inline stroke SVGs (1.6–2.2 stroke, round caps), 18–24px, `aria-hidden="true"`; icon-only buttons have an `aria-label`. No emoji.
- **No dark mode** in the template.
- **Accessibility as drawn:** real `<button>`, `<a href>`, `<label>` + `<input>`, `<fieldset>/<legend>` for option groups, `aria-pressed` on toggle chips, `role="tablist"/"tab"` with `aria-selected`, `aria-current="page"|"step"`, breadcrumbs in `<nav aria-label="مسیر صفحه">`, visually-hidden labels for the search and newsletter inputs.

## Shared parts

### Header (`Header`, 2 bars)
1. **Dark bar** (`#18181B`): logo (40px red tile with a white lightning bolt + «آمپر» 26px/700) · search field (white, 46px, max 640px, search icon, placeholder «نام کالا، برند یا دسته را جستجو کنید»; on the search page it keeps the query) · on the far side: account button (outlined `#3F3F46`: person icon + «ورود | ثبت‌نام» when logged out → login; «{name}، حساب من» when logged in → profile) and the red **cart button** («سبد خرید» + white count badge with `#991B1B` number, `aria-label="سبد خرید، ۲ کالا"`).
2. **White nav bar** (50px, bottom border): «دسته‌بندی کالاها» (hamburger icon, bold → categories) · «پیشنهاد شگفت‌انگیز» (red, bold → deals) · «پرفروش‌ترین‌ها» · «مجله آمپر» (blog) · «پشتیبانی» (FAQ) · on the far side a muted note «ارسال همان روز در تهران، برای سفارش تا ساعت ۱۴».

### Footer (`Footer`, dark)
- **Trust row** (4 columns, bottom border): red-outline icon + title + note: «ارسال همان روز / برای سفارش‌های تهران», «پرداخت در محل / در تهران», «۷ روز ضمانت بازگشت / بدون پرسش», «ضمانت اصالت / با فاکتور و شماره سریال».
- **Link columns** (4): «آمپر» (about, contact, blog) · «خدمات مشتریان» (FAQ, returns, order tracking) · «راهنمای خرید» (shipping & payment, terms & privacy, compare) · «از تخفیف‌ها باخبر شوید» (email + red «ثبت» button, then «پشتیبانی: [شماره تماس]»).
- **Copyright line** centred: «حقوق این وب‌سایت برای فروشگاه آمپر محفوظ است.»

### Account side menu (`AccountNav`, 280px white card)
Avatar circle with the first letter, name, masked phone (ltr); links: خلاصه حساب · سفارش‌ها · آدرس‌ها · علاقه‌مندی‌ها · پشتیبانی · خروج از حساب; the current one marked (`aria-current`).

### Help side menu (`InfoNav`, 280px white card)
Title «راهنما و پشتیبانی»; links: سوالات متداول · ارسال و پرداخت · شرایط بازگشت کالا · قوانین و حریم خصوصی · درباره آمپر · تماس با ما (current one marked); below: «جواب‌تان را پیدا نکردید؟», «شنبه تا پنجشنبه، ۹ تا ۱۸» and a «تماس با پشتیبانی» button.

### Product card (several variants)
Image (`object-fit: contain`, 150–180px tall) · name (14px/500, 2 lines reserved: `min-height: 3.2em`) · row with a red discount badge (`۱۵٪`) and the price (16px/700 + `تومان`) at the end · struck-through old price under it when discounted. Home "new" cards add a round add-to-cart icon button. **Deal cards** add a countdown bar (4px, grey track, red fill = time left) and «N ساعت مانده». Out-of-stock (wishlist): image at 45% opacity, «ناموجود» instead of the price.

### Common patterns
- Breadcrumbs: `آمپر / … / current`, 13px muted, current item in ink.
- Section header: title (20px/600) + «مشاهده همه» link at the far end.
- Sort chips: active one `#FEF2F2` background, `#991B1B` text, bold.
- Filter chips (applied filters): white, bordered, with a × remove button.
- Pagination: 44px squares, current dark (`#18181B` / white), «بعدی» button.
- Status badges: live/in progress = `#FEF2F2` + `#991B1B`; done = `#F4F4F5` + ink.
- Steppers (checkout, tracking): numbered circles joined by a line; done = dark with ✓, current = red with a soft red ring, next = white with a grey border.

## Pages

Desktop artboards are 1440px wide; pages are fluid within the 1336px container. Every page except login has the Header and Footer.

### Shopping path
- **Home** (`Main`, «صفحه اصلی»):
  1. **Hero** (12-column grid): a big white slider card (8 columns, 400px tall): soft red badge «تا ۲۰٪ تخفیف», large headline, subtitle, red CTA, product image, slide dots and previous/next buttons; beside it (4 columns) two stacked promo cards, one dark and one red (title, subtitle, link text, image).
  2. **Category circles:** one white card, 8 columns: round image + name each.
  3. **Amazing deals strip:** dark card: a title block «پیشنهاد شگفت‌انگیز» with a short text and «همه پیشنهادها», then 5 white deal cards with countdown bars.
  4. **Best sellers of the week** («پرفروش‌ترین‌های هفته»): a ranked list in 3 columns × 3 rows (image, big rank number, name).
  5. **New arrivals** («تازه‌ها»): 5 product cards with an add-to-cart button.
  6. Footer.
- **Product list / category** (`Category`): breadcrumb · **filter sidebar** (280px white card: «فیلترها» + «حذف همه»; switches «فقط کالاهای موجود», «فقط تخفیف‌دارها»; checkbox list of sub-categories with counts; price range slider with min/max labels; brand chips as toggle buttons) · **results**: header card (title, «N کالا», sort chips پرفروش‌ترین / جدیدترین / ارزان‌ترین / گران‌ترین) · applied-filter chips · 4-column grid of product cards (discount badge, price, old price) · pagination.
- **Search, no results** (`Search`): header keeps the query · white card with a big search icon, «برای «…» کالایی پیدا نشد», help text, buttons «ثبت درخواست کالا» (→ contact) and «دیدن همه دسته‌ها», popular-search pill chips · «شاید این‌ها را بخواهید»: 5 suggested products. (Search with results uses the product list layout.)
- **Product** (`Product`): breadcrumb · one white panel in 12 columns:
  - **Gallery** (4 cols): main image 420px with a «۱۵٪ تخفیف» badge, 72px thumbnails (selected one with a dark 2px border; one may be a video).
  - **Info** (5 cols): primary category link, title (26px), «کد کالا: SKU» (ltr), option groups (`<fieldset>`: «رنگ: مشکی», buttons with a colour swatch; selected = 2px dark border), «ویژگی‌های اصلی»: 3 grey key-spec tiles.
  - **Buy box** (3 cols, bordered card): seller line («فروشنده: آمپر / فروشنده رسمی»), guarantee and delivery lines with icons, the **deal countdown** panel (soft red: «پیشنهاد شگفت‌انگیز» + «۱۸ ساعت مانده» + bar), old price struck + discount badge, current price (26px/700), red «افزودن به سبد» (52px).
  - **Tabs** معرفی / مشخصات / دیدگاه‌ها / پرسش‌ها (active: red 3px underline); under them introduction text and the specifications table (`<dl>`, name column 160px) side by side.
  - **Similar products** («کالاهای مشابه»): 5 cards.
- **Cart** (`Cart`): main column: header card («سبد خرید» + «N کالا», «خالی کردن سبد»), line items (120px image; name; chosen option; seller/delivery note; quantity stepper with + and a trash icon at 1; «انتقال به علاقه‌مندی‌ها»; at the end «… تومان تخفیف» in red and the line price), a free-shipping progress card («ارسال این سفارش رایگان است» / how much is left). Side column (340–380px): summary card (items' price, «سود شما از خرید» in red, shipping fee or «رایگان», total, red «ادامه و ثبت سفارش») and a coupon form («کد تخفیف دارید؟» + dark «اعمال»).
- **Checkout** (`Checkout`): stepper سبد خرید ✓ → **ارسال و پرداخت** → ثبت سفارش · cards: delivery address (chosen address, recipient, phone; «تغییر یا افزودن آدرس») · delivery time (radio cards: day, time window, fee) · payment method (radio cards: «پرداخت اینترنتی / با همه کارت‌های عضو شتاب», «پرداخت در محل / با کارتخوان پیک، فقط در تهران») · items in this order (thumbnails) · side summary (items, savings, shipping, «مبلغ قابل پرداخت», red «پرداخت N تومان», terms note).
- **Payment success** (`PaySuccess`, 760px card): dark circle with ✓, «سفارش شما ثبت شد», note that details were sent by SMS; grid: order code (ltr), amount paid, delivery time, address; buttons «پیگیری سفارش» (red) and «ادامه خرید».
- **Payment failed** (`PayFail`): soft red circle with !, «پرداخت انجام نشد», text (refund within 72 hours, the order stays in the cart); buttons «پرداخت دوباره» and «انتخاب پرداخت در محل»; link to support.

### Account
- **Login, phone** (`Login`, no header/footer, centred 420px card on grey): logo, «ورود یا ثبت‌نام», «شماره موبایل‌تان را وارد کنید. کد تأیید برایتان پیامک می‌شود.», phone input (52px, 2px dark border), red «دریافت کد تأیید», terms note with a link.
- **Login, code** (`LoginCode`): back button, «کد تأیید را وارد کنید», «کد ۵ رقمی به 0912 *** 4567 پیامک شد.» + «ویرایش شماره», **5 digit boxes** (ltr, 60px; filled = dark border, current = red border, empty = grey), resend countdown «ارسال دوباره کد تا 01:24», red «ورود».
- **Profile / account summary** (`Profile`, with AccountNav): «سفارش‌های من» counters (در حال ارسال / تحویل‌شده / مرجوعی, icon tiles) · «آخرین سفارش» (thumbnails, code, date and total, status badge; «همه سفارش‌ها») · «اطلاعات حساب» (name, phone, email, national ID «کد ملی», «ثبت نشده» when empty; «ویرایش»).
- **My orders** (`Orders`): tabs جاری / تحویل‌شده / مرجوعی / لغو‌شده with count badges; order cards (grey header strip: status badge, code, date, amount, «جزئیات سفارش»; then item thumbnails).
- **Order details & tracking** (`Tracking`): back link, «سفارش CODE» + status badge with the delivery window; 4-step tracker (ثبت سفارش → آماده‌سازی → تحویل به پیک → تحویل به شما, each with a time); two cards: shipping (recipient, address, courier name and phone) and payment (method, savings, amount paid); item list (image, name, quantity and option, price); buttons «دریافت فاکتور» and «درخواست مرجوعی».
- **Addresses** (`Addresses`): «افزودن آدرس» (red); address cards (pin icon, title, «آدرس پیش‌فرض» badge, full address, postal code, recipient; edit and delete icon buttons; «انتخاب به‌عنوان پیش‌فرض»); the default card has a 2px dark border.
- **Wishlist** (`Wishlist`): «علاقه‌مندی‌ها» + count; 4-column cards with a filled red heart (remove), image, name, price, red «افزودن به سبد», or for out of stock «موجود شد خبرم کن» (outlined).

### Help & trust pages (with InfoNav, except About)
- **About** (`About`): hero (headline over two lines, intro text, 4 product images) · 3 value cards (فقط کالای اصل / تحویل سریع / ۷ روز برای امتحان) · «از سفارش تا تحویل»: 4 numbered steps · a "questions?" band with «تماس با ما».
- **Contact** (`Contact`): contact info (phone, email, office address) and a form: name, mobile (ltr), subject (`<select>`: پیگیری سفارش / بازگرداندن کالا / درخواست کالای جدید / سایر), message, «ارسال پیام».
- **FAQ** (`Faq`): «چه سؤالی دارید؟» + search in the questions; groups (سفارش و ارسال / پرداخت / بازگرداندن و ضمانت) of `<details>` accordions (+ / − sign).
- **Returns** (`Returns`): intro, 3 numbered steps, two lists «قابل بازگشت است» / «قابل بازگشت نیست».
- **Shipping & payment** (`Shipping`): a table of methods (روش / مقصد / زمان تحویل / هزینه) and the two payment methods.
- **Terms & privacy** (`Terms`): title, last updated date, an in-page table of contents and numbered sections with anchors.

### Extra pages
- **Amazing deals** (`Deals`): title band and text · category tabs (همه / سلامت / خانه / …) and «مرتب‌سازی: کمترین زمان باقی‌مانده» · grid of deal cards with countdown bars.
- **Brand** (`Brand`): breadcrumb آمپر / برندها / name · brand header (Latin name ltr, Persian name as the title, description, «N کالا») · sort chips · product grid.
- **Blog** (`Blog`, «مجله آمپر»): title and subtitle · category tabs (همه / راهنمای خرید / معرفی کالا / ترفندها) · a featured post card (image, category, title, excerpt, «N دقیقه مطالعه») · grid of post cards (image, category, title, reading time).
- **Compare** (`Compare`): «مقایسه کالا», switch «فقط تفاوت‌ها را نشان بده», a table: one column per product (remove ×, image, name, price, «افزودن به سبد»), an «افزودن کالا برای مقایسه» column, rows of specifications.
- **404** (`NotFound`): a big «۴۰۴», «این صفحه پیدا نشد», explanation, buttons to home and categories.

### Mobile (`Mobile`, 390px)
- Header: logo, «ارسال به تهران» location link, full-width search («جستجو در آمپر»).
- Home sections stacked: hero card + two small promo cards, category circles in a 4×2 grid, a horizontal deals strip (3 cards), best sellers (2 columns).
- **Fixed bottom tab bar** (white, top border, 4 tabs with icon + label): خانه · دسته‌بندی · سبد خرید (count badge) · حساب من; the current tab red.
- Other pages follow the desktop ones at phone width: sidebars (filters, account and help menus) stack above or move into a sheet, grids drop to 2 columns, tables scroll inside their box.

## Template ↔ API

| Template part | Storefront API |
|---|---|
| Header nav bar, footer link columns, InfoNav | `GET menus/header`, `GET menus/footer` (two levels: footer groups → links) |
| Search field, search results / no results | `GET products?q=` (`total` 0 → the empty state) |
| Category circles, category page, breadcrumbs | `GET categories` (images), `GET categories/{slug}`, `GET products?category=` |
| Category filter sidebar | `GET products/filters` (sub-categories, brands, `price {min,max}`, attributes, options) + `in_stock`, `on_sale`, `brand`, `min_price` / `max_price`, `filter[…]`, `option[…]` |
| Sort chips | `sort` = `newest` / `price_asc` / `price_desc` (+ `name`) |
| Product page | `GET products/{slug}` (gallery, options as swatch buttons, variants, specifications, breadcrumb, `comments_count`) |
| Discount badge, old price, countdown bar | variant `price`, `regular_price`, `discount_percent`, `sale_ends_at` / `promotion.ends_at` / `sale_event.ends_at` |
| «دیدگاه‌ها» tab | `GET`/`POST products/{slug}/comments` |
| Amazing deals page / home strip | `GET products?on_sale=1`, or a sale event: `GET sale-events/{slug}` + `GET products?sale_event={slug}` |
| Brand page | `GET brands/{slug}` + `GET products?brand=` |
| Blog page, categories tabs | `GET blog/posts`, `GET blog/categories` |
| About, Terms, Returns, Shipping, FAQ text | CMS pages: `GET pages/{slug}` (rich content) |
| Login (phone → code) | `auth/login/otp` → `auth/login/otp/verify`; unknown numbers register: `auth/register` → `register/verify` |
| Header cart badge, cart page (lines, quantity stepper, savings, total) | `GET cart` (`items_count`), `POST`/`PUT`/`DELETE cart/items…`, `DELETE cart` |
| Cart coupon form | `PUT`/`DELETE cart/coupon` (customers; guests are asked to log in) |
| Login while a guest cart exists | `POST cart/merge` right after the login |
| Shipping fee, free-shipping progress | `POST shipping/quote` (`fee`, `free_over_amount`, `remaining_for_free`) |
| Province/city in address forms | `GET provinces` |

## Gaps: in the template, not in the API yet

Decide these with the user before building them (several are open questions in `CLAUDE.md`):

- **Checkout, orders, payment** (the cart is built; Zarinpal, cash on delivery «پرداخت در محل»), **order statuses** for the tabs and the 4-step tracker, invoices («دریافت فاکتور»), **returns** («درخواست مرجوعی», «مرجوعی» counts) and cancellation.
- **Delivery time slots** at checkout (day + window + fee); shipping methods today have only `delivery_days {min,max}`.
- **Customer addresses** (list, add, edit, delete, default) and **profile editing** (name, email; the template also shows a national ID «کد ملی», which the users table doesn't have).
- **Login flow difference:** the template has one "login or register" phone screen, then only a code; the API registers with name + password first. Needs a decision (e.g. register by OTP, name asked later).
- **Wishlist** (+ «موجود شد خبرم کن» back-in-stock alerts) and **compare** (could stay in the browser).
- **Best sellers** («پرفروش‌ترین‌ها» sort, weekly ranking): needs sales data, so orders first.
- **Home page content:** hero slider, the two promo cards and which products fill "deals", "best sellers" and "new arrivals" have no admin/API yet (banners / home sections).
- **Product Q&A** («پرسش‌ها» tab) and star ratings: only comments exist.
- **Contact form**, **"request a product"** and **newsletter email signup**: no endpoints.
- **Popular searches** chips: no endpoint.
- **FAQ** as grouped accordions: either a page with headings or a dedicated FAQ module.
- **Blog extras:** reading time («N دقیقه مطالعه») and a featured post.
- **Store info** (support phone, email, office address, working hours, the header's same-day delivery note, trust badges): no store-info settings in the API.
- **Seller line** («فروشنده: آمپر») is fixed text: single store.
