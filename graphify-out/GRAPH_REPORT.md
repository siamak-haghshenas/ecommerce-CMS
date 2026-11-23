# Graph Report - ecommerceCms  (2026-10-20)

## Corpus Check
- 758 files · ~217,290 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 5778 nodes · 14027 edges · 199 communities (168 shown, 31 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 146 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `5cf2da8e`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\Http\Request
- AppButton.vue
- ref_vue_router
- MediaPage.vue
- CouponEditorPage.vue
- CustomersPage.vue
- BulkEditPage.vue
- ProductEditorPage.vue
- ProductVariant
- CustomerDetailPage.vue
- PostEditorPage.vue
- OtpPurpose
- Illuminate\Database\Eloquent\Model
- ShippingMethodEditorPage.vue
- Carbon\CarbonInterface
- CustomerTokenResource
- MenuItem
- Illuminate\Http\JsonResponse
- Brand
- HasSlugField
- Tag
- Illuminate\Foundation\Http\FormRequest
- Illuminate\Database\Eloquent\Relations\BelongsToMany
- ref_vue
- RefreshPricesAction
- ProductsPage.vue
- PageEditorPage.vue
- Product
- AppIcon.vue
- Comment
- MediaUsageRole
- BulkProductCard.vue
- SeoPanel.vue
- VariantsEditor.vue
- saleEvents.ts
- PostsPage.vue
- AttributesPage.vue
- Illuminate\Database\Eloquent\Builder
- CategoryAttributesField.vue
- Permission
- AccountActionException
- MenusPage.vue
- CategoryFormDialog.vue
- CategoriesPage.vue
- Illuminate\Http\Resources\Json\AnonymousResourceCollection
- CouponTerms
- LoginPage.vue
- PagesPage.vue
- PromotionsPage.vue
- Carbon\CarbonImmutable
- AttributeType
- CommentsPage.vue
- AppDialog.vue
- ShippingMethodsPage.vue
- VerifyOtpAction
- Page
- MediaPickerDialog.vue
- AuthException
- analysis.ts
- AttributeFormDialog.vue
- BrandsPage.vue
- CouponQuote
- UserRole
- PromotionBulkDialog.vue
- MediaAsset
- Illuminate\Http\Response
- CouponTargetsDialog.vue
- CartCatalog
- formatNumber
- Illuminate\Validation\Validator
- CategoryNode
- Category
- ShippingTestBox.vue
- toApiError
- AiTextGenerator
- SaveProductRequest.php
- RichTextEditor.vue
- SmsSender
- SeoContentType
- CommentStatus
- Illuminate\Support\Facades\DB
- BuildStorefrontFiltersAction.php
- RefreshPricesCommand.php
- CartResource
- coupons.ts
- storefront/api.ts
- ShippingTerms
- MediaPicture
- SeoAnalyzer.php
- SaleEventEditorPage.vue
- RatesEditor.vue
- OptionValue
- EventItemsSection.vue
- Attribute
- PromotionVariantsDialog.vue
- Coupon
- SuggestSeoAction.php
- CategoryRow.vue
- ShippingMethod
- StoreHeader.vue
- ShippingOption
- MediaType
- ProductPickerDialog.vue
- CouponCalculator.php
- HomePage.vue
- Post
- discounts/types.ts
- CouponException
- CategoryTree
- CouponSampleOrder.vue
- SaleEvents/SaleEventController.php
- SendOtpAction
- CategoryType
- ShippingRateData
- Cart
- CourierCitiesDialog.vue
- flatten
- CartLine
- PromotionController.php
- PriceDisplayCurrency
- CartItem
- SaleEvent
- promotions.ts
- SaveCouponRequest.php
- ProductController
- AiException
- ProductFiltersRequest
- SeoText
- CategoryTreeResource
- CustomerFilters
- RefreshProductSearchTextAction
- products/types.ts
- StockMovementReason
- ShippingMethodResource
- MenuItemDialog.vue
- .trim
- CouponsPage.vue
- ShippingCalculator.php
- load
- Settings
- RichText
- CategoryException
- MenuItemRow.vue
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- ProductCard.vue
- comments/api.ts
- Illuminate\Database\Eloquent\Collection
- UpdateCustomerData
- AppServiceProvider
- SettingsPage.vue
- RecordLastLogin.php
- fromLocalInput
- env.d.ts
- ScoreSeoAction
- bulkEdit.ts
- comments.ts
- ResolveTagsAction
- HasComments
- money.ts
- queryString
- Promotion
- StorefrontPostFilters
- BulkUpdateVariantsAction.php
- ProductFilters
- shipping/api.ts
- CartSummary
- BulkUpdateVariantsRequest
- save
- SaleEventItem
- CategoriesField.vue
- CouponType
- User
- moderate
- queryString
- hasFiles
- menus/api.ts
- MobileTabBar.vue
- RegisterCustomerData
- EndUserSessionsAction
- SaleEventVariantResource
- SaveSaleEventRequest
- ShippingCalculator
- ModerateCommentRequest
- Cart.php
- ReorderSaleEventsAction.php
- OptionDisplayType
- CartException
- SaveCategoryRequest
- queryString
- CouponItem
- ReorderCategoriesRequest
- save
- ManageTagsAction.php
- AddCartItemRequest
- ProductResource
- formatRelativeTime
- DealsStrip.vue

## God Nodes (most connected - your core abstractions)
1. `User` - 364 edges
2. `Controller` - 159 edges
3. `toApiError()` - 150 edges
4. `ProductVariant` - 100 edges
5. `Product` - 95 edges
6. `useLocale()` - 82 edges
7. `Post` - 76 edges
8. `CategoryTree` - 70 edges
9. `Category` - 67 edges
10. `Page` - 67 edges

## Surprising Connections (you probably didn't know these)
- `{closure#2}()` --calls--> `SeoText`  [INFERRED]
  app/Support/Seo/SeoAnalyzer.php → app/Support/Seo/SeoText.php
- `{closure#1}()` --references--> `Brand`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Brand.php
- `{closure#2}()` --references--> `Attribute`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Attribute.php
- `{closure#8}()` --references--> `OptionValue`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/OptionValue.php
- `{closure#9}()` --references--> `OptionValue`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/OptionValue.php

## Import Cycles
- None detected.

## Communities (199 total, 31 thin omitted)

### Community 0 - "Illuminate\Http\Request"
Cohesion: 0.04
Nodes (30): SearchActivityLogAction, ActivityLogController, SessionController, AuthenticateIfTokenSent, EnsureUserIsActive, ActivityResource, CouponCustomerResource, MenuItemResource (+22 more)

### Community 1 - "AppButton.vue"
Cohesion: 0.04
Nodes (76): confirm(), emit, form, props, { t }, categoriesApi, childCount, confirm() (+68 more)

### Community 2 - "ref_vue_router"
Cohesion: 0.17
Nodes (7): RouteMeta, vue-router, comingSoon, router, routes, RouteMeta, vue-router

### Community 3 - "MediaPage.vue"
Cohesion: 0.05
Nodes (34): assets, auth, busy, canManage, canUpload, currentFolder, deleting, deletingFolder (+26 more)

### Community 4 - "CouponEditorPage.vue"
Cohesion: 0.04
Nodes (47): couponProblems(), generateCode(), mergeLinks(), normalizeCode(), addCustomers(), alertMessage, appliesOptions, attempted (+39 more)

### Community 5 - "CustomersPage.vue"
Cohesion: 0.04
Nodes (65): customersApi, anonymise(), emit, form, { localizeDigits }, props, { t }, typedPhone (+57 more)

### Community 6 - "BulkEditPage.vue"
Cohesion: 0.05
Nodes (36): activeFilterCount, all, brand, brandOptions, brands, category, confirmUndo, discardAndContinue() (+28 more)

### Community 7 - "ProductEditorPage.vue"
Cohesion: 0.05
Nodes (51): clock, acceptNormalized(), alertMessage, attributes, attributesLoading, auth, brands, canManage (+43 more)

### Community 8 - "ProductVariant"
Cohesion: 0.11
Nodes (14): {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}() (+6 more)

### Community 9 - "CustomerDetailPage.vue"
Cohesion: 0.06
Nodes (41): activityLogApi, detail, eventKey, { formatDateTime, formatRelativeTime, isRtl }, ICONS, look, missingSubject, props (+33 more)

### Community 10 - "PostEditorPage.vue"
Cohesion: 0.05
Nodes (48): acceptNormalized(), alertMessage, applyVersion(), auth, authors, autosavedAt, autosaveNow(), autosaveState (+40 more)

### Community 11 - "OtpPurpose"
Cohesion: 0.11
Nodes (7): {closure#1}(), {closure#1}(), OtpPurpose, Login, PasswordReset, Register, OtpCode

### Community 12 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.04
Nodes (16): {closure#2}(), {closure#1}(), {closure#14}(), AiRequest, City, {closure#1}(), {closure#2}(), {closure#3}() (+8 more)

### Community 13 - "ShippingMethodEditorPage.vue"
Cohesion: 0.05
Nodes (43): addCities(), alertMessage, attempted, auth, canManage, chosenCityIds, citiesError, citiesOpen (+35 more)

### Community 14 - "Carbon\CarbonInterface"
Cohesion: 0.13
Nodes (9): VariantPrice, DiscountSource, Promotion, Sale, SaleEvent, {closure#1}(), {closure#2}(), {closure#1}() (+1 more)

### Community 15 - "CustomerTokenResource"
Cohesion: 0.16
Nodes (8): IssueCustomerTokenAction, ResetPasswordAction, LoginWithOtpController, LoginWithPasswordController, ResetPasswordController, VerifyRegistrationController, VerifyOtpRequest, CustomerTokenResource

### Community 16 - "MenuItem"
Cohesion: 0.08
Nodes (18): {closure#1}(), {closure#2}(), SaveMenuAction, MenuItemData, MenuItemType, Category, Page, Url (+10 more)

### Community 17 - "Illuminate\Http\JsonResponse"
Cohesion: 0.05
Nodes (15): RegisterCustomerAction, RequestOtpAction, {closure#1}(), ApiExceptionRenderer, CommentException, ProductException, ShippingException, SendLoginOtpController (+7 more)

### Community 18 - "Brand"
Cohesion: 0.08
Nodes (13): CreateBrandAction, DeleteBrandAction, {closure#1}(), ReorderBrandsAction, UpdateBrandAction, BrandData, BrandException, BrandController (+5 more)

### Community 19 - "HasSlugField"
Cohesion: 0.07
Nodes (11): UpdateBylineAction, BylineData, PageData, PageStatus, Draft, Published, UpdateBylineRequest, SaveBrandRequest (+3 more)

### Community 20 - "Tag"
Cohesion: 0.26
Nodes (6): ManageTagsAction, {closure#1}(), TagController, TagRequest, TagResource, Tag

### Community 21 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.03
Nodes (25): PasswordLoginRequest, PhoneNumberRequest, VerifyOtpRequest, ReorderBrandsRequest, SetCategoryAttributesRequest, BulkDeleteCommentsRequest, IndexCustomersRequest, UpdateCustomerRequest (+17 more)

### Community 22 - "Illuminate\Database\Eloquent\Relations\BelongsToMany"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 23 - "ref_vue"
Cohesion: 0.03
Nodes (107): route, { t }, { t }, closeButton, collapsed, desktopQuery, drawerOpen, isDesktop (+99 more)

### Community 24 - "RefreshPricesAction"
Cohesion: 0.10
Nodes (9): {closure#1}(), {closure#2}(), RefreshPricesAction, AdjustStockAction, SaveProductAction, DeleteSaleEventAction, ReorderSaleEventsAction, PromotionSet (+1 more)

### Community 25 - "ProductsPage.vue"
Cohesion: 0.07
Nodes (38): allSelected, auth, brand, brandOptions, brands, bulk(), bulkBusy, canManage (+30 more)

### Community 26 - "PageEditorPage.vue"
Cohesion: 0.05
Nodes (46): acceptNormalized(), alertMessage, applyVersion(), auth, autosavedAt, autosaveNow(), autosaveState, autosaveTimer (+38 more)

### Community 27 - "Product"
Cohesion: 0.08
Nodes (8): ChangeProductStatusAction, DeleteProductAction, {closure#1}(), {closure#2}(), {closure#1}(), CountUnmeasuredProductsAction, {closure#19}(), Product

### Community 28 - "AppIcon.vue"
Cohesion: 0.04
Nodes (59): auth, bylineOpen, comments, emit, { formatNumber }, groups, loggingOut, moderates (+51 more)

### Community 29 - "Comment"
Cohesion: 0.06
Nodes (16): AddCommentAction, CountCommentStatesAction, DeleteCommentsAction, FindCommentableAction, ModerateCommentsAction, ReplyToCommentAction, SearchCommentsAction, CommentData (+8 more)

### Community 30 - "MediaUsageRole"
Cohesion: 0.05
Nodes (22): MediaUsageRole, Avatar, Banner, Content, Gallery, Image, Logo, OgImage (+14 more)

### Community 31 - "BulkProductCard.vue"
Cohesion: 0.11
Nodes (23): isEdited(), problems(), valueOf(), changedCount, clearSale(), collapsed, dotClass, emit (+15 more)

### Community 32 - "SeoPanel.vue"
Cohesion: 0.08
Nodes (31): META_DESCRIPTION_MAX, META_DESCRIPTION_MIN, SeoRating, TITLE_MAX, TITLE_MIN, seoApi, { formatNumber }, keyphrase (+23 more)

### Community 33 - "VariantsEditor.vue"
Cohesion: 0.08
Nodes (31): addOption(), available, bulk, choose(), chosen, ChosenOption, chosenOptions, comboCount (+23 more)

### Community 34 - "saleEvents.ts"
Cohesion: 0.11
Nodes (24): addMissingVariants(), applyBulkDiscount(), note(), result, invalid, applyBulkChange(), fill(), save() (+16 more)

### Community 35 - "PostsPage.vue"
Cohesion: 0.09
Nodes (31): auth, author, authorOptions, authors, blogTree, canManage, category, error (+23 more)

### Community 36 - "AttributesPage.vue"
Cohesion: 0.08
Nodes (29): actOnAttribute(), actOnOption(), attributeForm, attributes, auth, canManage, deleting, error (+21 more)

### Community 37 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.03
Nodes (88): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+80 more)

### Community 38 - "CategoryAttributesField.vue"
Cohesion: 0.11
Nodes (15): all, available, byId, error, id, inherited, inheritedIds, loadAll() (+7 more)

### Community 39 - "Permission"
Cohesion: 0.06
Nodes (34): Permission, AccessAdminPanel, AnonymiseCustomers, DeactivateCustomers, ManageAttributes, ManageBlogCategories, ManageBrands, ManageCategories (+26 more)

### Community 40 - "AccountActionException"
Cohesion: 0.13
Nodes (5): ChangeStaffRoleAction, GuardStaffChangeAction, RemoveStaffMemberAction, SetStaffStatusAction, AccountActionException

### Community 41 - "MenusPage.vue"
Cohesion: 0.07
Nodes (28): actSub(), actTop(), addingUnder, addItem(), auth, canManage, dialogOpen, editing (+20 more)

### Community 42 - "CategoryFormDialog.vue"
Cohesion: 0.07
Nodes (31): keyphraseUsedBy, attributeIds, categoryType, emit, excludedParents, fields, fill(), form (+23 more)

### Community 43 - "CategoriesPage.vue"
Cohesion: 0.07
Nodes (33): emit, move(), act(), auth, canManage, canSeeBlog, canSeeProduct, collapsed (+25 more)

### Community 44 - "Illuminate\Http\Resources\Json\AnonymousResourceCollection"
Cohesion: 0.06
Nodes (15): SearchProductsAction, FindEventVariantsAction, ResolveSaleEventPreviewAction, BulkProductController, StockMovementController, SaleEventVariantController, ProvinceController, SaleEventController (+7 more)

### Community 45 - "CouponTerms"
Cohesion: 0.15
Nodes (5): SimulateCouponAction, CouponTerms, CouponSimulationController, SimulateCouponRequest, CouponCalculator

### Community 46 - "LoginPage.vue"
Cohesion: 0.05
Nodes (45): logout(), adminAuthApi, auth, code, codeLength, countdown, finishLogin(), form (+37 more)

### Community 47 - "PagesPage.vue"
Cohesion: 0.09
Nodes (26): pagesApi, auth, canManage, error, filtered, { formatNumber, formatRelativeTime }, load(), loading (+18 more)

### Community 48 - "PromotionsPage.vue"
Cohesion: 0.05
Nodes (47): add(), addOpen, applyDates(), auth, bulkOpen, canManage, counts, datesFor (+39 more)

### Community 49 - "Carbon\CarbonImmutable"
Cohesion: 0.08
Nodes (16): {closure#2}(), {closure#3}(), {closure#4}(), PostPreview, PromotionRowData, SavePromotionsData, SaleEventData, SaleEventItemData (+8 more)

### Community 50 - "AttributeType"
Cohesion: 0.14
Nodes (9): AttributeData, ValueData, AttributeType, Boolean, Number, Select, Text, {closure#1}() (+1 more)

### Community 51 - "CommentsPage.vue"
Cohesion: 0.06
Nodes (27): ACTION_ICON, auth, badge, bulkIds, busy, deleting, error, filtering (+19 more)

### Community 52 - "AppDialog.vue"
Cohesion: 0.07
Nodes (29): emit, end, invalid, props, start, { t }, choose(), emit (+21 more)

### Community 53 - "ShippingMethodsPage.vue"
Cohesion: 0.06
Nodes (42): shippingPayloadOf(), act(), auth, busy, canManage, creating, deleting, error (+34 more)

### Community 55 - "Page"
Cohesion: 0.05
Nodes (19): DeletePageAction, PreviewPageAction, ResolvePagePreviewAction, {closure#1}(), SavePageAction, SavePageAutosaveAction, PagePreview, {closure#2}() (+11 more)

### Community 56 - "MediaPickerDialog.vue"
Cohesion: 0.03
Nodes (92): mediaApi, emit, extension, { formatNumber }, picture, props, size, { t } (+84 more)

### Community 57 - "AuthException"
Cohesion: 0.18
Nodes (3): LoginWithOtpAction, LoginWithPasswordAction, AuthException

### Community 58 - "analysis.ts"
Cohesion: 0.14
Nodes (24): onValueInput(), filtered, ALEF, analyzeReadability(), analyzeSeo(), characterCount(), check(), DESCRIPTION_MIN_WORDS (+16 more)

### Community 59 - "AttributeFormDialog.vue"
Cohesion: 0.06
Nodes (40): attributesApi, optionsApi, alertMessage, emit, fields, filterableOptions, form, props (+32 more)

### Community 60 - "BrandsPage.vue"
Cohesion: 0.05
Nodes (53): brandsApi, emit, fields, form, hasLogo, logo, optional(), props (+45 more)

### Community 61 - "CouponQuote"
Cohesion: 0.15
Nodes (6): CouponQuote, ShippingBasket, SimulatedShipping, CouponProblem, MinOrder, NoEligibleItems

### Community 62 - "UserRole"
Cohesion: 0.09
Nodes (9): AddStaffMemberData, StaffFilters, {closure#1}(), UserRole, Admin, Author, Customer, SuperAdmin (+1 more)

### Community 63 - "PromotionBulkDialog.vue"
Cohesion: 0.11
Nodes (21): apply(), emit, endsAt, errors, field, fieldOptions, filled, { formatNumber } (+13 more)

### Community 64 - "MediaAsset"
Cohesion: 0.06
Nodes (3): {closure#1}(), MediaAsset, {closure#21}()

### Community 65 - "Illuminate\Http\Response"
Cohesion: 0.07
Nodes (11): DeleteMediaAssetAction, DeleteMediaFolderAction, ManageMediaFolderAction, MoveMediaAssetsAction, SearchMediaAction, MediaAssetController, MediaFolderController, MediaFolderRequest (+3 more)

### Community 66 - "CouponTargetsDialog.vue"
Cohesion: 0.04
Nodes (45): brands, categories, chosen, confirm(), counts, coveringParent(), emit, error (+37 more)

### Community 67 - "CartCatalog"
Cohesion: 0.14
Nodes (8): {closure#1}(), {closure#1}(), CartLineProblem, ExceedsLimit, OutOfStock, PriceChanged, Unavailable, CartCatalog

### Community 68 - "formatNumber"
Cohesion: 0.09
Nodes (25): filtered, seoLabel, results, onAdd(), stockText(), amount(), condition(), target() (+17 more)

### Community 69 - "Illuminate\Validation\Validator"
Cohesion: 0.08
Nodes (15): PostData, PostStatus, Draft, Published, {closure#1}(), {closure#1}(), SavePostRequest, {closure#1}() (+7 more)

### Community 70 - "CategoryNode"
Cohesion: 0.15
Nodes (14): {closure#1}(), {closure#2}(), CategoryNode, {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}() (+6 more)

### Community 71 - "Category"
Cohesion: 0.07
Nodes (19): {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#10}() (+11 more)

### Community 72 - "ShippingTestBox.vue"
Cohesion: 0.08
Nodes (26): addError, adding, choose(), cityId, cityName, cityOptions, couponCode, error (+18 more)

### Community 73 - "toApiError"
Cohesion: 0.04
Nodes (65): useCustomerActions(), activate(), close(), finished(), run(), saleEventPayloadOf(), saleEventsApi, { t } (+57 more)

### Community 74 - "AiTextGenerator"
Cohesion: 0.09
Nodes (7): AiTextGenerator, AiPrompt, AiResult, AiManager, ClaudeAiDriver, {closure#1}(), FakeAiDriver

### Community 75 - "SaveProductRequest.php"
Cohesion: 0.06
Nodes (17): {closure#2}(), ProductData, SpecificationData, VariantData, ProductStatus, Archived, Draft, Published (+9 more)

### Community 76 - "RichTextEditor.vue"
Cohesion: 0.08
Nodes (28): charCount, editor, editorKey, embedError, embedLink, embedOpen, emit, full (+20 more)

### Community 78 - "SmsSender"
Cohesion: 0.12
Nodes (5): SmsSender, ArraySmsDriver, FarazSmsDriver, LogSmsDriver, SmsManager

### Community 79 - "SeoContentType"
Cohesion: 0.13
Nodes (9): SeoContentType, BlogCategory, Brand, Category, Page, Post, Product, SaleEvent (+1 more)

### Community 80 - "CommentStatus"
Cohesion: 0.10
Nodes (11): CommentFilters, ModerationData, CommentableType, Post, Product, CommentStatus, Approved, Pending (+3 more)

### Community 81 - "Illuminate\Support\Facades\DB"
Cohesion: 0.07
Nodes (11): AppendMediaChunkAction, {closure#1}(), CancelMediaUploadAction, {closure#1}(), CompleteMediaUploadAction, StartMediaUploadAction, MediaException, MediaUploadController (+3 more)

### Community 82 - "BuildStorefrontFiltersAction.php"
Cohesion: 0.18
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#8}(), {closure#9}(), {closure#2}(), AttributeValue

### Community 84 - "CartResource"
Cohesion: 0.13
Nodes (11): AddCartItemAction, ApplyCartCouponAction, ResolveCartAction, SetCartItemQuantityAction, SummarizeCartAction, CheckCouponAction, QuoteShippingAction, CartController (+3 more)

### Community 85 - "coupons.ts"
Cohesion: 0.13
Nodes (22): CODE_MAX, CODE_MIN, CouponForm, CouponProblem, emptyForm(), emptyTargets(), formFromCoupon(), ids() (+14 more)

### Community 86 - "storefront/api.ts"
Cohesion: 0.16
Nodes (18): accountApi, cartApi, CartResponse, catalogApi, ProductQuery, api, isUnauthenticated(), tokens (+10 more)

### Community 87 - "ShippingTerms"
Cohesion: 0.13
Nodes (8): ShippingMethodData, ShippingTerms, ShippingMethodType, Courier, Volume, Weight, {closure#2}(), HasShippingMethodFields

### Community 88 - "MediaPicture"
Cohesion: 0.13
Nodes (28): accountApi, Byline, SaveBylinePayload, MediaPicture, Page, PageAutosave, PageFilters, PageListItem (+20 more)

### Community 89 - "SeoAnalyzer.php"
Cohesion: 0.11
Nodes (9): {closure#1}(), SeoAnalysis, SeoCheck, SeoRating, Bad, Good, Ok, {closure#2}() (+1 more)

### Community 90 - "SaleEventEditorPage.vue"
Cohesion: 0.06
Nodes (37): acceptNormalized(), alertMessage, auth, banner, canManage, confirmDelete, dirty, eventWindow (+29 more)

### Community 91 - "RatesEditor.vue"
Cohesion: 0.10
Nodes (24): { formatNumber }, lastEnd, money, props, rates, starts, startText(), { t } (+16 more)

### Community 92 - "OptionValue"
Cohesion: 0.10
Nodes (17): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+9 more)

### Community 93 - "EventItemsSection.vue"
Cohesion: 0.06
Nodes (37): addOpen, applyBulkDates(), applyBulkLimit(), datesFor, datesRow, DISCOUNT_PROBLEMS, discountOpen, errorText() (+29 more)

### Community 94 - "Attribute"
Cohesion: 0.05
Nodes (21): {closure#7}(), DeleteCatalogItemAction, {closure#1}(), ReorderListAction, {closure#1}(), SaveAttributeAction, {closure#1}(), SaveOptionAction (+13 more)

### Community 95 - "PromotionVariantsDialog.vue"
Cohesion: 0.06
Nodes (39): promotionsApi, add(), allMatching, allOn(), brandOptions, canAdd, categoryOptions, changedFilters (+31 more)

### Community 96 - "Coupon"
Cohesion: 0.06
Nodes (15): CountCouponStatesAction, {closure#1}(), SaveCouponAction, {closure#1}(), SearchCouponsAction, SyncCouponCustomersAction, SyncCouponTargetsAction, CouponFilters (+7 more)

### Community 97 - "SuggestSeoAction.php"
Cohesion: 0.19
Nodes (4): {closure#5}(), SuggestSeoAction, SeoSuggestionContext, SeoSuggestions

### Community 98 - "CategoryRow.vue"
Cohesion: 0.12
Nodes (19): collapsed, { formatNumber }, hasChildren, menuItems, props, { t }, tree, nodes (+11 more)

### Community 99 - "ShippingMethod"
Cohesion: 0.15
Nodes (5): DeleteShippingMethodAction, QuotedShipping, ShippingQuote, {closure#1}(), ShippingMethod

### Community 100 - "StoreHeader.vue"
Cohesion: 0.15
Nodes (14): contentApi, FooterGroup, groups, accountLabel, cart, menu, query, route (+6 more)

### Community 101 - "ShippingOption"
Cohesion: 0.14
Nodes (11): ShippingOption, ShippingFreeReason, Coupon, FreeOver, ShippingUnavailableReason, BelowMinOrder, CityNotServed, Inactive (+3 more)

### Community 102 - "MediaType"
Cohesion: 0.08
Nodes (12): MediaFilters, MediaUploadData, MediaType, Document, Image, Video, IndexMediaRequest, {closure#1}() (+4 more)

### Community 103 - "ProductPickerDialog.vue"
Cohesion: 0.07
Nodes (28): allMatchingSelected, brandOptions, categoryOptions, choosable, emit, EMPTY, filtering, Filters (+20 more)

### Community 104 - "CouponCalculator.php"
Cohesion: 0.16
Nodes (9): CouponQuoteLine, CouponLineReason, Discounted, Excluded, NotTargeted, {closure#1}(), {closure#2}(), {closure#3}() (+1 more)

### Community 105 - "HomePage.vue"
Cohesion: 0.11
Nodes (18): HeroSlide, home, PromoCard, TrustBadge, current, image, paused, props (+10 more)

### Community 106 - "Post"
Cohesion: 0.04
Nodes (18): DeletePostAction, PreviewPostAction, ResolvePostPreviewAction, {closure#1}(), SavePostAction, SavePostAutosaveAction, SearchStorefrontPostsAction, PostAutosaveController (+10 more)

### Community 107 - "discounts/types.ts"
Cohesion: 0.19
Nodes (18): Coupon, CouponDetail, CouponFilters, CouponLineReason, CouponList, CouponSimulation, DiscountType, OverridingEvent (+10 more)

### Community 109 - "CategoryTree"
Cohesion: 0.13
Nodes (6): UpdateMediaAssetAction, MediaAssetData, {closure#1}(), {closure#2}(), {closure#9}(), CategoryTree

### Community 110 - "CouponSampleOrder.vue"
Cohesion: 0.09
Nodes (19): couponsApi, addError, adding, capped, choose(), discountLabel, error, { formatNumber } (+11 more)

### Community 111 - "SaleEvents/SaleEventController.php"
Cohesion: 0.16
Nodes (6): FindOverridingEventsAction, SearchSaleEventsAction, SaleEventFilters, SaleEventController, IndexSaleEventsRequest, SaleEventResource

### Community 113 - "CategoryType"
Cohesion: 0.12
Nodes (7): CategoryType, Blog, Product, {closure#1}(), IndexCategoriesRequest, CategoryTreeResource, {closure#1}()

### Community 114 - "ShippingRateData"
Cohesion: 0.15
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), ShippingRateData, {closure#1}(), {closure#1}(), {closure#2}(), ShippingRate (+1 more)

### Community 115 - "Cart"
Cohesion: 0.12
Nodes (5): ClearCartAction, RemoveCartCouponAction, RemoveCartItemAction, ResolvedCart, Cart

### Community 116 - "CourierCitiesDialog.vue"
Cohesion: 0.05
Nodes (34): avatar, emit, fields, form, id, loadError, loading, props (+26 more)

### Community 117 - "flatten"
Cohesion: 0.12
Nodes (16): parentName, parentPath, id, options, parentId, props, selected, { t } (+8 more)

### Community 118 - "CartLine"
Cohesion: 0.21
Nodes (8): CartLine, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#1}()

### Community 119 - "PromotionController.php"
Cohesion: 0.20
Nodes (6): FindPromotionVariantsAction, ListPromotionsAction, SavePromotionsAction, PromotionController, PromotionPickerProductResource, PromotionResource

### Community 120 - "PriceDisplayCurrency"
Cohesion: 0.20
Nodes (5): PriceDisplayCurrency, Rial, Toman, Setting, {closure#1}()

### Community 121 - "CartItem"
Cohesion: 0.24
Nodes (5): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), CartItem

### Community 122 - "SaleEvent"
Cohesion: 0.15
Nodes (3): {closure#2}(), SaveSaleEventAction, SaleEvent

### Community 123 - "promotions.ts"
Cohesion: 0.15
Nodes (19): changedCount, fill(), load(), problemOf(), save(), undo(), isChanged(), mapErrors() (+11 more)

### Community 124 - "SaveCouponRequest.php"
Cohesion: 0.12
Nodes (8): CouponData, CouponCustomerScope, All, Selected, {closure#1}(), {closure#3}(), SaveCouponRequest, HasCouponCustomerFields

### Community 129 - "CategoryTreeResource"
Cohesion: 0.17
Nodes (6): BlogCategoryController, CategoryController, CategoryResource, CategoryTreeResource, {closure#1}(), {closure#2}()

### Community 131 - "RefreshProductSearchTextAction"
Cohesion: 0.16
Nodes (4): CreateCategoryAction, UpdateCategoryAction, RefreshProductSearchTextAction, CategoryData

### Community 132 - "products/types.ts"
Cohesion: 0.12
Nodes (23): OptionDisplayType, BulkPage, tagsApi, BulkProduct, add(), id, key(), known (+15 more)

### Community 133 - "StockMovementReason"
Cohesion: 0.13
Nodes (9): {closure#1}(), StockMovementReason, Adjustment, BulkEdit, Initial, Order, OrderCancelled, Return (+1 more)

### Community 134 - "ShippingMethodResource"
Cohesion: 0.27
Nodes (4): SaveShippingMethodAction, ShippingMethodController, SaveShippingMethodRequest, ShippingMethodResource

### Community 135 - "MenuItemDialog.vue"
Cohesion: 0.21
Nodes (11): categoryGroups, emit, errors, fields, id, isValidLink(), props, submit() (+3 more)

### Community 136 - ".trim"
Cohesion: 0.05
Nodes (11): ReplyToCommentRequest, {closure#2}(), {closure#4}(), SimulateShippingRequest, ApplyCartCouponRequest, StoreCommentRequest, CheckCouponRequest, QuoteShippingRequest (+3 more)

### Community 137 - "CouponsPage.vue"
Cohesion: 0.07
Nodes (29): couponPayloadOf(), act(), auth, busy, canManage, copyCode(), deleting, disableUsed() (+21 more)

### Community 138 - "ShippingCalculator.php"
Cohesion: 0.23
Nodes (6): ShippingBasketLine, {closure#1}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 139 - "load"
Cohesion: 0.22
Nodes (13): closeDetails(), confirmDelete(), deleteFolder(), filters(), folders, load(), loadFolders(), loadMore() (+5 more)

### Community 140 - "Settings"
Cohesion: 0.16
Nodes (4): PreviewSaleEventAction, SaleEventPreview, ProductController, Settings

### Community 141 - "RichText"
Cohesion: 0.07
Nodes (5): PageAutosaveData, PostAutosaveData, AutosavePageRequest, {closure#2}(), RichText

### Community 143 - "MenuItemRow.vue"
Cohesion: 0.23
Nodes (10): actions, detail, emit, props, { t }, TYPE_ICONS, displayLabel(), MenuRow (+2 more)

### Community 144 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.08
Nodes (9): BuildStorefrontFiltersAction, SearchStorefrontProductsAction, {closure#1}(), SearchPagesAction, SearchPostsAction, StorefrontProductFilters, PageFilters, PostFilters (+1 more)

### Community 145 - "ProductCard.vue"
Cohesion: 0.15
Nodes (12): addToCart(), busy, cart, link, props, router, toast, { toasts } (+4 more)

### Community 146 - "comments/api.ts"
Cohesion: 0.33
Nodes (8): commentsApi, useCommentsStore, Comment, CommentableType, CommentFilters, CommentList, ModeratedStatus, StaffRef

### Community 147 - "Illuminate\Database\Eloquent\Collection"
Cohesion: 0.15
Nodes (5): GetDashboardSummaryAction, ListProvincesAction, DashboardSummary, DashboardController, DashboardResource

### Community 150 - "SettingsPage.vue"
Cohesion: 0.05
Nodes (46): add(), adding, emit, error, fieldOptions, money, props, startField (+38 more)

### Community 152 - "fromLocalInput"
Cohesion: 0.17
Nodes (14): moderationNote(), datesLabel(), dateText(), emit, end, { formatNumber, formatDateTime }, props, shown() (+6 more)

### Community 154 - "ScoreSeoAction"
Cohesion: 0.10
Nodes (6): {closure#1}(), {closure#3}(), ScoreSeoAction, SeoSubject, SeoInput, HasSeo

### Community 155 - "bulkEdit.ts"
Cohesion: 0.13
Nodes (14): BulkVariant, BulkVariantPayload, countChanges(), discountPercent(), EditableField, FieldProblem, PageEdits, RowErrors (+6 more)

### Community 156 - "comments.ts"
Cohesion: 0.20
Nodes (10): BULK_MAX, pageSelection(), REASON_MAX, REPLY_MAX, STATUSES, statusesFor(), toggleId(), togglePage() (+2 more)

### Community 159 - "money.ts"
Cohesion: 0.23
Nodes (11): discounted, left, props, cartLabel, timeLeft, currency, currencyLabel(), formatAmount() (+3 more)

### Community 160 - "queryString"
Cohesion: 0.28
Nodes (9): open(), openId, place, queryString(), search, sort, type, updateQuery() (+1 more)

### Community 161 - "Promotion"
Cohesion: 0.18
Nodes (3): {closure#1}(), Promotion, {closure#1}()

### Community 163 - "BulkUpdateVariantsAction.php"
Cohesion: 0.23
Nodes (7): BulkUpdateVariantsAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), BulkVariantChange, {closure#2}()

### Community 164 - "ProductFilters"
Cohesion: 0.11
Nodes (5): {closure#3}(), MatchingProductsAction, ProductFilters, IndexBulkProductsRequest, IndexProductsRequest

### Community 165 - "shipping/api.ts"
Cohesion: 0.31
Nodes (8): shippingApi, Province, SaveShippingMethodPayload, ShippingMethod, ShippingMethodList, ShippingRate, ShippingSimulation, ShippingUnavailableReason

### Community 166 - "CartSummary"
Cohesion: 0.16
Nodes (3): CartCoupon, CartSummary, CheckedCoupon

### Community 168 - "save"
Cohesion: 0.24
Nodes (10): dirty, load(), save(), serialized(), switchTo(), targets, undo(), rowFromItem() (+2 more)

### Community 170 - "CategoriesField.vue"
Cohesion: 0.15
Nodes (10): all, available, byId, chosen, id, ids, primaryId, props (+2 more)

### Community 171 - "CouponType"
Cohesion: 0.18
Nodes (7): CouponAppliesTo, All, Selected, CouponType, Fixed, FreeShipping, Percent

### Community 172 - "User"
Cohesion: 0.03
Nodes (43): {closure#1}(), MergeCartsAction, SetCategoryAttributesAction, DeleteCategoryAction, ReorderCategoriesAction, SearchCustomersAction, ListShippingMethodsAction, ReorderShippingMethodsAction (+35 more)

### Community 173 - "moderate"
Cohesion: 0.25
Nodes (9): choose(), confirmDelete(), confirmReject(), counted(), load(), moderate(), reload(), removeReply() (+1 more)

### Community 174 - "queryString"
Cohesion: 0.40
Nodes (5): itemId, page, queryString(), search, status

### Community 175 - "hasFiles"
Cohesion: 0.40
Nodes (5): hasFiles(), onDragEnter(), onDragOver(), onDrop(), upload()

### Community 176 - "menus/api.ts"
Cohesion: 0.48
Nodes (5): menusApi, MenuItem, MenuLocation, MenuTargets, SaveMenuItem

### Community 177 - "MobileTabBar.vue"
Cohesion: 0.20
Nodes (9): cart, session, cart, route, session, tabs, paths, StoreIconName (+1 more)

### Community 180 - "EndUserSessionsAction"
Cohesion: 0.27
Nodes (4): EndUserSessionsAction, AnonymiseCustomerAction, {closure#1}(), SetCustomerStatusAction

### Community 181 - "SaleEventVariantResource"
Cohesion: 0.20
Nodes (3): {closure#1}(), {closure#2}(), SaleEventVariantResource

### Community 183 - "ShippingCalculator"
Cohesion: 0.33
Nodes (4): SimulateShippingAction, ShippingSimulationController, ShippingSimulationResource, ShippingCalculator

### Community 185 - "Cart.php"
Cohesion: 0.22
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 187 - "OptionDisplayType"
Cohesion: 0.36
Nodes (4): OptionData, OptionDisplayType, Color, Text

### Community 190 - "queryString"
Cohesion: 0.29
Nodes (7): page, queryModel(), queryString(), quick, showFilters, updateQuery(), whenSaved()

### Community 191 - "CouponItem"
Cohesion: 0.47
Nodes (3): CouponItem, {closure#1}(), {closure#2}()

### Community 193 - "save"
Cohesion: 0.40
Nodes (5): mapErrors(), toPayload(), load(), save(), saveAndContinue()

### Community 197 - "formatRelativeTime"
Cohesion: 0.50
Nodes (4): headerDetail, scheduleNote, stateDetail, formatRelativeTime()

### Community 198 - "DealsStrip.vue"
Cohesion: 0.50
Nodes (3): now, timer, ProductCard

## Knowledge Gaps
- **127 isolated node(s):** `Text`, `Number`, `Boolean`, `Select`, `Unavailable` (+122 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 2130 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **31 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Illuminate\Http\Request`, `RefreshProductSearchTextAction`, `StockMovementReason`, `ShippingMethodResource`, `OtpPurpose`, `Settings`, `Illuminate\Database\Eloquent\Model`, `CustomerTokenResource`, `MenuItem`, `Illuminate\Http\JsonResponse`, `Brand`, `HasSlugField`, `Illuminate\Database\Eloquent\Collection`, `UpdateCustomerData`, `Tag`, `Illuminate\Foundation\Http\FormRequest`, `RefreshPricesAction`, `RecordLastLogin.php`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `Product`, `Comment`, `MediaUsageRole`, `Promotion`, `BulkUpdateVariantsAction.php`, `Illuminate\Database\Eloquent\Builder`, `Permission`, `AccountActionException`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `CouponTerms`, `Carbon\CarbonImmutable`, `EndUserSessionsAction`, `VerifyOtpAction`, `Page`, `AuthException`, `ReorderSaleEventsAction.php`, `UserRole`, `Illuminate\Http\Response`, `ManageTagsAction.php`, `Illuminate\Validation\Validator`, `CategoryNode`, `SaveProductRequest.php`, `Illuminate\Support\Facades\DB`, `CartResource`, `ShippingTerms`, `Attribute`, `Coupon`, `SuggestSeoAction.php`, `ShippingMethod`, `Post`, `CouponException`, `CategoryTree`, `SaleEvents/SaleEventController.php`, `ShippingRateData`, `PromotionController.php`, `SaleEvent`, `SaveCouponRequest.php`, `ProductController`?**
  _High betweenness centrality (0.079) - this node is a cross-community bridge._
- **What connects `Text`, `Number`, `Boolean` to the rest of the system?**
  _127 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.03982892274792836 - nodes in this community are weakly interconnected._
- **Why does `ProductVariant` connect `ProductVariant` to `StockMovementReason`, `ShippingCalculator.php`, `Illuminate\Database\Eloquent\Model`, `Carbon\CarbonInterface`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `RefreshPricesAction`, `Product`, `Promotion`, `BulkUpdateVariantsAction.php`, `BulkUpdateVariantsRequest`, `User`, `CouponTerms`, `Carbon\CarbonImmutable`, `SaleEventVariantResource`, `SaveSaleEventRequest`, `CouponQuote`, `CouponItem`, `MediaAsset`, `CartCatalog`, `Illuminate\Validation\Validator`, `Category`, `SaveProductRequest.php`, `RefreshPricesCommand.php`, `OptionValue`, `CouponCalculator.php`, `CartLine`, `CartItem`, `SaleEvent`?**
  _High betweenness centrality (0.022) - this node is a cross-community bridge._
- **Should `AppButton.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.038461538461538464 - nodes in this community are weakly interconnected._
- **Why does `Controller` connect `User` to `Illuminate\Http\Request`, `CategoryTreeResource`, `ShippingMethodResource`, `Settings`, `CustomerTokenResource`, `MenuItem`, `Illuminate\Http\JsonResponse`, `Brand`, `Illuminate\Database\Eloquent\Collection`, `Tag`, `Comment`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `CouponTerms`, `Page`, `ShippingCalculator`, `Illuminate\Http\Response`, `Illuminate\Support\Facades\DB`, `CartResource`, `Attribute`, `Coupon`, `Post`, `SaleEvents/SaleEventController.php`, `PromotionController.php`, `ProductController`?**
  _High betweenness centrality (0.017) - this node is a cross-community bridge._
- **Should `MediaPage.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.05128205128205128 - nodes in this community are weakly interconnected._