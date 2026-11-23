# Graph Report - ecommerceCms  (2026-10-16)

## Corpus Check
- 619 files · ~181,719 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 4869 nodes · 11808 edges · 153 communities (129 shown, 24 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 131 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d2a97356`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\Http\Request
- StaffPage.vue
- ref_vue
- MediaPage.vue
- CouponEditorPage.vue
- CustomersPage.vue
- BulkEditPage.vue
- ProductEditorPage.vue
- ProductVariant
- CustomerDetailPage.vue
- PostEditorPage.vue
- Controller
- Illuminate\Database\Eloquent\Model
- Product
- RefreshPricesAction
- CustomerTokenResource
- MenuItem
- Exception
- Illuminate\Container\Attributes\CurrentUser
- UpdateMediaAssetRequest.php
- Tag
- Illuminate\Foundation\Http\FormRequest
- MediaPicture
- useLocale.ts
- OptionFormDialog.vue
- ProductsPage.vue
- PageEditorPage.vue
- Brand
- AppIcon.vue
- .trim
- MediaUsageRole
- BulkProductCard.vue
- SeoPanel.vue
- VariantsEditor.vue
- CouponsPage.vue
- PostsPage.vue
- AttributesPage.vue
- Illuminate\Database\Eloquent\Builder
- CategoryAttributesField.vue
- Permission
- errors.ts
- MenusPage.vue
- CategoryFormDialog.vue
- CategoriesPage.vue
- Illuminate\Http\Resources\Json\AnonymousResourceCollection
- CouponTerms
- LoginPage.vue
- PagesPage.vue
- PromotionsPage.vue
- Carbon\CarbonImmutable
- HasMediaFields
- CouponCustomersDialog.vue
- ref_vue_i18n
- Illuminate\Http\Response
- UserResource
- Page
- MediaDetailsDrawer.vue
- AuthException
- analysis.ts
- AttributeFormDialog.vue
- BrandsPage.vue
- MediaUploadQueue.vue
- UserRole
- PromotionBulkDialog.vue
- AddProductsDialog.vue
- MediaAsset
- CouponTargetsDialog.vue
- CategoryTree
- formatNumber
- SaveSaleEventRequest
- CategoryType
- Category
- CategoryNode
- toApiError
- AiTextGenerator
- SaveProductRequest.php
- RichTextEditor.vue
- .parent
- SmsSender
- SeoContentType
- media/types.ts
- MediaUpload
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- RefreshPricesCommand.php
- ImagesManager.vue
- coupons.ts
- FarazSmsDriver
- EndUserSessionsAction
- SaveCategoryRequest
- MediaPickerDialog.vue
- SaleEventEditorPage.vue
- Promotion
- Storefront/ProductResource.php
- EventItemsSection.vue
- Attribute
- PromotionVariantsDialog.vue
- Coupon
- SuggestSeoAction.php
- CategoryRow.vue
- SaleEventItem
- FakeAiDriver
- SaleEventResource
- MediaType
- ProductPickerDialog.vue
- Illuminate\Support\Collection
- MediaException
- Post
- Illuminate\Database\Eloquent\Collection
- CouponException
- CatalogException
- Pricing
- SaleEvent
- SendOtpAction
- HasSlugField
- LoginWithOtpAction
- GuardStaffChangeAction
- CategoriesField.vue
- flatten
- Catalog/CategoryController.php
- SeoScoreDot.vue
- PriceDisplayCurrency
- load
- BulkNumberInput.vue
- AccountActionException
- SaveCouponRequest.php
- PostResource
- AiException
- ProductFiltersRequest
- SeoText
- ApiExceptionRenderer.php
- CustomerFilters
- errorOf
- products/types.ts
- formatRelativeTime
- UserRole.php
- Illuminate\Validation\Validator
- discounts/types.ts
- Settings
- RichText
- CategoryException
- AppSpinner.vue
- BulkUpdateVariantsRequest.php
- UpdateCustomerData
- AppServiceProvider
- SettingsPage.vue
- RecordLastLogin.php
- fromLocalInput
- env.d.ts
- SavePostRequest.php
- CategoryTreeResource
- ReorderCategoriesRequest
- Products/ProductController.php
- User

## God Nodes (most connected - your core abstractions)
1. `User` - 315 edges
2. `Controller` - 133 edges
3. `toApiError()` - 131 edges
4. `ProductVariant` - 87 edges
5. `Product` - 84 edges
6. `useLocale()` - 75 edges
7. `CategoryTree` - 70 edges
8. `Post` - 69 edges
9. `Category` - 67 edges
10. `Page` - 67 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --references--> `Brand`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Brand.php
- `{closure#2}()` --references--> `Attribute`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Attribute.php
- `{closure#7}()` --references--> `Option`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Option.php
- `{closure#8}()` --references--> `OptionValue`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/OptionValue.php
- `{closure#1}()` --references--> `CategoryNode`  [EXTRACTED]
  app/Actions/Categories/ReorderCategoriesAction.php → app/Data/Categories/CategoryNode.php

## Import Cycles
- None detected.

## Communities (153 total, 24 thin omitted)

### Community 0 - "Illuminate\Http\Request"
Cohesion: 0.05
Nodes (23): PageRevisionController, CouponCheckController, CouponCustomerResource, MenuItemResource, PageAutosaveResource, PageListResource, PageRevisionResource, PostAutosaveResource (+15 more)

### Community 1 - "StaffPage.vue"
Cohesion: 0.04
Nodes (76): confirm(), emit, form, props, { t }, categoriesApi, childCount, confirm() (+68 more)

### Community 2 - "ref_vue"
Cohesion: 0.04
Nodes (59): route, { t }, { t }, closeButton, collapsed, desktopQuery, drawerOpen, isDesktop (+51 more)

### Community 3 - "MediaPage.vue"
Cohesion: 0.05
Nodes (61): assets, auth, busy, canManage, canUpload, closeDetails(), confirmDelete(), currentFolder (+53 more)

### Community 4 - "CouponEditorPage.vue"
Cohesion: 0.05
Nodes (37): couponProblems(), normalizeCode(), alertMessage, appliesOptions, attempted, auth, canManage, chosenCustomerIds (+29 more)

### Community 5 - "CustomersPage.vue"
Cohesion: 0.06
Nodes (47): customersApi, anonymise(), emit, form, { localizeDigits }, props, { t }, typedPhone (+39 more)

### Community 6 - "BulkEditPage.vue"
Cohesion: 0.04
Nodes (52): countChanges(), mapErrors(), setValue(), toPayload(), activeFilterCount, all, brand, brandOptions (+44 more)

### Community 7 - "ProductEditorPage.vue"
Cohesion: 0.05
Nodes (51): clock, acceptNormalized(), alertMessage, attributes, attributesLoading, auth, brands, canManage (+43 more)

### Community 8 - "ProductVariant"
Cohesion: 0.04
Nodes (26): {closure#9}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#2}(), {closure#1}() (+18 more)

### Community 9 - "CustomerDetailPage.vue"
Cohesion: 0.06
Nodes (37): activityLogApi, detail, eventKey, { formatDateTime, formatRelativeTime, isRtl }, ICONS, look, missingSubject, props (+29 more)

### Community 10 - "PostEditorPage.vue"
Cohesion: 0.05
Nodes (48): acceptNormalized(), alertMessage, applyVersion(), auth, authors, autosavedAt, autosaveNow(), autosaveState (+40 more)

### Community 11 - "Controller"
Cohesion: 0.05
Nodes (24): RegisterCustomerAction, RequestOtpAction, SetCategoryAttributesAction, OtpPurpose, Login, Register, SendLoginOtpController, CategoryAttributeController (+16 more)

### Community 12 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.05
Nodes (7): AiRequest, {closure#1}(), PageAutosave, PageRevision, PostAutosave, PostRevision, StockMovement

### Community 13 - "Product"
Cohesion: 0.04
Nodes (23): AdjustStockAction, {closure#1}(), BulkUpdateVariantsAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#3}() (+15 more)

### Community 14 - "RefreshPricesAction"
Cohesion: 0.10
Nodes (8): {closure#1}(), {closure#2}(), RefreshPricesAction, DeleteSaleEventAction, {closure#1}(), ReorderSaleEventsAction, PromotionSet, SaleEventSet

### Community 15 - "CustomerTokenResource"
Cohesion: 0.15
Nodes (8): IssueCustomerTokenAction, LoginWithOtpController, LoginWithPasswordController, ResetPasswordController, VerifyRegistrationController, PasswordLoginRequest, VerifyOtpRequest, CustomerTokenResource

### Community 16 - "MenuItem"
Cohesion: 0.08
Nodes (16): {closure#1}(), {closure#2}(), SaveMenuAction, MenuItemData, MenuItemType, Category, Page, Url (+8 more)

### Community 18 - "Illuminate\Container\Attributes\CurrentUser"
Cohesion: 0.08
Nodes (13): SearchCustomersAction, FindOverridingEventsAction, SaveSaleEventAction, SearchSaleEventsAction, CustomerAnonymisationController, CustomerController, CustomerStatusController, SaleEventController (+5 more)

### Community 19 - "UpdateMediaAssetRequest.php"
Cohesion: 0.22
Nodes (4): MediaAssetData, {closure#1}(), {closure#2}(), UpdateMediaAssetRequest

### Community 20 - "Tag"
Cohesion: 0.20
Nodes (7): {closure#1}(), ManageTagsAction, {closure#1}(), TagController, TagRequest, TagResource, Tag

### Community 21 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.04
Nodes (21): RegisterCustomerData, PasswordLoginRequest, PhoneNumberRequest, VerifyOtpRequest, ReorderBrandsRequest, SetCategoryAttributesRequest, IndexCouponsRequest, UpdateCustomerRequest (+13 more)

### Community 22 - "MediaPicture"
Cohesion: 0.17
Nodes (23): MediaPicture, Page, PageAutosave, PageFilters, PageListItem, PagePreview, PageRevision, PageRevisionSummary (+15 more)

### Community 23 - "useLocale.ts"
Cohesion: 0.05
Nodes (58): confirmed, apply(), emit, { formatNumber }, limit, props, { t }, MAX_PER_ORDER (+50 more)

### Community 24 - "OptionFormDialog.vue"
Cohesion: 0.10
Nodes (17): optionsApi, alertMessage, displayOptions, emit, fields, form, props, save() (+9 more)

### Community 25 - "ProductsPage.vue"
Cohesion: 0.07
Nodes (34): allSelected, auth, brand, brandOptions, brands, bulkBusy, canManage, category (+26 more)

### Community 26 - "PageEditorPage.vue"
Cohesion: 0.05
Nodes (46): acceptNormalized(), alertMessage, applyVersion(), auth, autosavedAt, autosaveNow(), autosaveState, autosaveTimer (+38 more)

### Community 27 - "Brand"
Cohesion: 0.07
Nodes (12): CreateBrandAction, DeleteBrandAction, {closure#1}(), UpdateBrandAction, UpdateCategoryAction, RefreshProductSearchTextAction, ScoreSeoAction, SeoSubject (+4 more)

### Community 28 - "AppIcon.vue"
Cohesion: 0.05
Nodes (45): CatalogItemAction, emit, { formatNumber }, menuItems, more, preview, props, { t } (+37 more)

### Community 29 - ".trim"
Cohesion: 0.10
Nodes (7): {closure#1}(), {closure#3}(), {closure#4}(), CheckCouponRequest, {closure#4}(), Digits, PhoneNumber

### Community 30 - "MediaUsageRole"
Cohesion: 0.08
Nodes (13): MediaUsageRole, Avatar, Banner, Content, Gallery, Image, Logo, OgImage (+5 more)

### Community 31 - "BulkProductCard.vue"
Cohesion: 0.08
Nodes (35): BulkProduct, BulkVariant, BulkVariantPayload, discountPercent(), EditableField, FieldProblem, isEdited(), PageEdits (+27 more)

### Community 32 - "SeoPanel.vue"
Cohesion: 0.09
Nodes (27): META_DESCRIPTION_MAX, META_DESCRIPTION_MIN, TITLE_MAX, TITLE_MIN, seoApi, { formatNumber }, keyphrase, metaDescription (+19 more)

### Community 33 - "VariantsEditor.vue"
Cohesion: 0.07
Nodes (33): OptionDisplayType, addOption(), available, bulk, choose(), chosen, ChosenOption, chosenOptions (+25 more)

### Community 34 - "CouponsPage.vue"
Cohesion: 0.07
Nodes (29): couponPayloadOf(), act(), auth, busy, canManage, copyCode(), deleting, disableUsed() (+21 more)

### Community 35 - "PostsPage.vue"
Cohesion: 0.09
Nodes (31): auth, author, authorOptions, authors, blogTree, canManage, category, error (+23 more)

### Community 36 - "AttributesPage.vue"
Cohesion: 0.08
Nodes (29): actOnAttribute(), actOnOption(), attributeForm, attributes, auth, canManage, deleting, error (+21 more)

### Community 37 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.03
Nodes (86): {closure#1}(), {closure#2}(), {closure#3}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+78 more)

### Community 38 - "CategoryAttributesField.vue"
Cohesion: 0.11
Nodes (15): all, available, byId, error, id, inherited, inheritedIds, loadAll() (+7 more)

### Community 39 - "Permission"
Cohesion: 0.06
Nodes (31): Permission, AccessAdminPanel, AnonymiseCustomers, DeactivateCustomers, ManageAttributes, ManageBlogCategories, ManageBrands, ManageCategories (+23 more)

### Community 40 - "errors.ts"
Cohesion: 0.05
Nodes (42): accountApi, Byline, SaveBylinePayload, avatar, emit, fields, form, id (+34 more)

### Community 41 - "MenusPage.vue"
Cohesion: 0.05
Nodes (57): menusApi, categoryGroups, emit, errors, fields, id, isValidLink(), props (+49 more)

### Community 42 - "CategoryFormDialog.vue"
Cohesion: 0.07
Nodes (31): keyphraseUsedBy, attributeIds, categoryType, emit, excludedParents, fields, fill(), form (+23 more)

### Community 43 - "CategoriesPage.vue"
Cohesion: 0.07
Nodes (33): emit, move(), act(), auth, canManage, canSeeBlog, canSeeProduct, collapsed (+25 more)

### Community 44 - "Illuminate\Http\Resources\Json\AnonymousResourceCollection"
Cohesion: 0.09
Nodes (13): SearchActivityLogAction, FindPromotionVariantsAction, ListPromotionsAction, SavePromotionsAction, FindEventVariantsAction, ActivityLogController, StockMovementController, PromotionController (+5 more)

### Community 45 - "CouponTerms"
Cohesion: 0.05
Nodes (28): CheckCouponAction, SimulateCouponAction, CheckedCoupon, CouponItem, CouponQuote, CouponQuoteLine, CouponTerms, CouponAppliesTo (+20 more)

### Community 46 - "LoginPage.vue"
Cohesion: 0.09
Nodes (28): adminAuthApi, auth, code, codeLength, countdown, finishLogin(), form, { localizeDigits } (+20 more)

### Community 47 - "PagesPage.vue"
Cohesion: 0.09
Nodes (26): pagesApi, auth, canManage, error, filtered, { formatNumber, formatRelativeTime }, load(), loading (+18 more)

### Community 48 - "PromotionsPage.vue"
Cohesion: 0.04
Nodes (69): add(), addOpen, applyBulkChange(), applyDates(), auth, bulkOpen, canManage, changedCount (+61 more)

### Community 49 - "Carbon\CarbonImmutable"
Cohesion: 0.07
Nodes (20): {closure#2}(), {closure#2}(), {closure#3}(), {closure#4}(), PagePreview, PostPreview, VariantData, PromotionRowData (+12 more)

### Community 50 - "HasMediaFields"
Cohesion: 0.08
Nodes (9): BylineData, PageData, PageStatus, Draft, Published, UpdateBylineRequest, SaveBrandRequest, SavePageRequest (+1 more)

### Community 51 - "CouponCustomersDialog.vue"
Cohesion: 0.10
Nodes (17): asCouponCustomer(), emit, error, { formatNumber, formatDate, localizeDigits }, goTo(), load(), loading, page (+9 more)

### Community 52 - "ref_vue_i18n"
Cohesion: 0.04
Nodes (51): props, status, { t }, { localizeDigits, formatDateTime, formatRelativeTime }, { t }, { t }, emit, end (+43 more)

### Community 53 - "Illuminate\Http\Response"
Cohesion: 0.11
Nodes (5): DeleteCouponAction, SessionController, CouponController, SessionController, CouponResource

### Community 54 - "UserResource"
Cohesion: 0.22
Nodes (5): LoginWithPasswordAction, LoginWithOtpController, LoginWithPasswordController, ManagesAuthSession, UserResource

### Community 55 - "Page"
Cohesion: 0.06
Nodes (14): DeletePageAction, PreviewPageAction, ResolvePagePreviewAction, {closure#1}(), SavePageAction, SavePageAutosaveAction, PageAutosaveController, PageController (+6 more)

### Community 56 - "MediaDetailsDrawer.vue"
Cohesion: 0.09
Nodes (24): alt, asset, closeButton, cover, dirty, editable, emit, error (+16 more)

### Community 57 - "AuthException"
Cohesion: 0.10
Nodes (5): {closure#1}(), {closure#1}(), AuthException, EnsureUserIsActive, OtpCode

### Community 58 - "analysis.ts"
Cohesion: 0.15
Nodes (23): onValueInput(), filtered, ALEF, analyzeReadability(), analyzeSeo(), characterCount(), check(), DESCRIPTION_MIN_WORDS (+15 more)

### Community 59 - "AttributeFormDialog.vue"
Cohesion: 0.11
Nodes (23): attributesApi, alertMessage, emit, fields, filterableOptions, form, props, save() (+15 more)

### Community 60 - "BrandsPage.vue"
Cohesion: 0.05
Nodes (53): brandsApi, emit, fields, form, hasLogo, logo, optional(), props (+45 more)

### Community 61 - "MediaUploadQueue.vue"
Cohesion: 0.12
Nodes (16): emit, extension, { formatNumber }, picture, props, size, { t }, size (+8 more)

### Community 62 - "UserRole"
Cohesion: 0.10
Nodes (9): AddStaffMemberData, StaffFilters, UserRole, Admin, Author, Customer, SuperAdmin, IndexStaffRequest (+1 more)

### Community 63 - "PromotionBulkDialog.vue"
Cohesion: 0.11
Nodes (21): apply(), emit, endsAt, errors, field, fieldOptions, filled, { formatNumber } (+13 more)

### Community 64 - "AddProductsDialog.vue"
Cohesion: 0.15
Nodes (13): add(), adding, emit, error, fieldOptions, money, props, startField (+5 more)

### Community 65 - "MediaAsset"
Cohesion: 0.05
Nodes (14): DeleteMediaAssetAction, DeleteMediaFolderAction, ManageMediaFolderAction, MoveMediaAssetsAction, SearchMediaAction, UpdateMediaAssetAction, MediaAssetController, MediaFolderController (+6 more)

### Community 66 - "CouponTargetsDialog.vue"
Cohesion: 0.08
Nodes (24): brands, categories, chosen, confirm(), counts, coveringParent(), emit, error (+16 more)

### Community 67 - "CategoryTree"
Cohesion: 0.20
Nodes (3): {closure#1}(), {closure#2}(), CategoryTree

### Community 68 - "formatNumber"
Cohesion: 0.12
Nodes (20): filtered, results, discountLabel, summary, onAdd(), stockText(), amount(), condition() (+12 more)

### Community 70 - "CategoryType"
Cohesion: 0.11
Nodes (7): CategoryType, Blog, Product, {closure#1}(), IndexCategoriesRequest, CategoryTreeResource, {closure#1}()

### Community 71 - "Category"
Cohesion: 0.10
Nodes (12): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+4 more)

### Community 72 - "CategoryNode"
Cohesion: 0.21
Nodes (12): CategoryNode, {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#3}() (+4 more)

### Community 73 - "toApiError"
Cohesion: 0.06
Nodes (48): useCustomerActions(), activate(), close(), finished(), run(), saleEventPayloadOf(), saleEventsApi, act() (+40 more)

### Community 74 - "AiTextGenerator"
Cohesion: 0.12
Nodes (5): AiTextGenerator, AiPrompt, AiResult, ClaudeAiDriver, {closure#1}()

### Community 75 - "SaveProductRequest.php"
Cohesion: 0.07
Nodes (13): ProductData, ProductFilters, SpecificationData, ProductStatus, Archived, Draft, Published, ProductType (+5 more)

### Community 76 - "RichTextEditor.vue"
Cohesion: 0.08
Nodes (28): charCount, editor, editorKey, embedError, embedLink, embedOpen, emit, full (+20 more)

### Community 78 - "SmsSender"
Cohesion: 0.16
Nodes (4): SmsSender, ArraySmsDriver, LogSmsDriver, SmsManager

### Community 79 - "SeoContentType"
Cohesion: 0.12
Nodes (9): SeoContentType, BlogCategory, Brand, Category, Page, Post, Product, SaleEvent (+1 more)

### Community 80 - "media/types.ts"
Cohesion: 0.17
Nodes (15): mediaApi, BY_EXTENSION, contentTypeOf(), nextChunk(), TYPES, UploadItem, UploadStatus, useMediaUploadStore (+7 more)

### Community 81 - "MediaUpload"
Cohesion: 0.09
Nodes (10): AppendMediaChunkAction, {closure#1}(), CancelMediaUploadAction, {closure#1}(), CompleteMediaUploadAction, StartMediaUploadAction, MediaUploadController, MediaUploadResource (+2 more)

### Community 82 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.04
Nodes (22): BuildStorefrontFiltersAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}(), SearchStorefrontProductsAction (+14 more)

### Community 84 - "ImagesManager.vue"
Cohesion: 0.12
Nodes (13): emit, id, picking, props, { t }, pictureOf(), add(), { formatNumber } (+5 more)

### Community 85 - "coupons.ts"
Cohesion: 0.15
Nodes (16): CODE_MAX, CODE_MIN, CouponForm, CouponProblem, generateCode(), MAX_PERCENT, mergeLinks(), TARGET_KINDS (+8 more)

### Community 87 - "EndUserSessionsAction"
Cohesion: 0.20
Nodes (5): EndUserSessionsAction, AnonymiseCustomerAction, {closure#1}(), SetCustomerStatusAction, SetStaffStatusAction

### Community 88 - "SaveCategoryRequest"
Cohesion: 0.20
Nodes (3): CreateCategoryAction, CategoryData, SaveCategoryRequest

### Community 89 - "MediaPickerDialog.vue"
Cohesion: 0.09
Nodes (24): accept, assets, auth, canUpload, chosen, confirm(), emit, error (+16 more)

### Community 90 - "SaleEventEditorPage.vue"
Cohesion: 0.06
Nodes (39): acceptNormalized(), alertMessage, auth, banner, canManage, confirmDelete, dirty, eventWindow (+31 more)

### Community 92 - "Storefront/ProductResource.php"
Cohesion: 0.14
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#8}(), ProductResource

### Community 93 - "EventItemsSection.vue"
Cohesion: 0.05
Nodes (53): addMissingVariants(), addOpen, applyBulkDates(), applyBulkDiscount(), applyBulkLimit(), datesFor, datesRow, DISCOUNT_PROBLEMS (+45 more)

### Community 94 - "Attribute"
Cohesion: 0.06
Nodes (23): DeleteCatalogItemAction, ReorderListAction, {closure#1}(), {closure#2}(), SaveAttributeAction, {closure#1}(), SaveOptionAction, SyncValuesAction (+15 more)

### Community 95 - "PromotionVariantsDialog.vue"
Cohesion: 0.06
Nodes (38): add(), allMatching, allOn(), brandOptions, canAdd, categoryOptions, changedFilters, ChosenVariant (+30 more)

### Community 96 - "Coupon"
Cohesion: 0.06
Nodes (14): CountCouponStatesAction, {closure#1}(), {closure#1}(), SearchCouponsAction, CouponFilters, {closure#1}(), {closure#4}(), {closure#1}() (+6 more)

### Community 97 - "SuggestSeoAction.php"
Cohesion: 0.19
Nodes (5): {closure#3}(), {closure#5}(), SuggestSeoAction, SeoSuggestionContext, SeoSuggestions

### Community 98 - "CategoryRow.vue"
Cohesion: 0.11
Nodes (20): collapsed, { formatNumber }, hasChildren, menuItems, props, seoLabel, { t }, tree (+12 more)

### Community 99 - "SaleEventItem"
Cohesion: 0.12
Nodes (10): VariantPrice, DiscountSource, Promotion, Sale, SaleEvent, {closure#2}(), {closure#1}(), {closure#2}() (+2 more)

### Community 101 - "SaleEventResource"
Cohesion: 0.19
Nodes (3): ResolveSaleEventPreviewAction, SaleEventController, SaleEventResource

### Community 102 - "MediaType"
Cohesion: 0.10
Nodes (9): MediaFilters, MediaUploadData, MediaType, Document, Image, Video, IndexMediaRequest, {closure#1}() (+1 more)

### Community 103 - "ProductPickerDialog.vue"
Cohesion: 0.07
Nodes (28): allMatchingSelected, brandOptions, categoryOptions, choosable, emit, EMPTY, filtering, Filters (+20 more)

### Community 104 - "Illuminate\Support\Collection"
Cohesion: 0.10
Nodes (12): {closure#2}(), FindCouponCustomersAction, CouponCustomerLookup, CouponCustomerLookupController, LookupCouponCustomersRequest, CouponCustomerLookupResource, {closure#1}(), {closure#1}() (+4 more)

### Community 106 - "Post"
Cohesion: 0.06
Nodes (13): DeletePostAction, PreviewPostAction, {closure#1}(), SavePostAction, SavePostAutosaveAction, PostAutosaveController, PostController, PostPreviewController (+5 more)

### Community 107 - "Illuminate\Database\Eloquent\Collection"
Cohesion: 0.22
Nodes (4): GetDashboardSummaryAction, DashboardSummary, DashboardController, DashboardResource

### Community 111 - "SaleEvent"
Cohesion: 0.10
Nodes (3): {closure#2}(), SaleEventFilters, SaleEvent

### Community 113 - "HasSlugField"
Cohesion: 0.08
Nodes (16): AttributeData, OptionData, ValueData, AttributeType, Boolean, Number, Select, Text (+8 more)

### Community 114 - "LoginWithOtpAction"
Cohesion: 0.21
Nodes (5): LoginWithOtpAction, ResetPasswordAction, VerifyOtpAction, VerifyRegistrationAction, PasswordReset

### Community 115 - "GuardStaffChangeAction"
Cohesion: 0.21
Nodes (3): ChangeStaffRoleAction, GuardStaffChangeAction, RemoveStaffMemberAction

### Community 116 - "CategoriesField.vue"
Cohesion: 0.15
Nodes (10): all, available, byId, chosen, id, ids, primaryId, props (+2 more)

### Community 117 - "flatten"
Cohesion: 0.12
Nodes (16): parentName, parentPath, id, options, parentId, props, selected, { t } (+8 more)

### Community 118 - "Catalog/CategoryController.php"
Cohesion: 0.29
Nodes (3): BlogCategoryController, CategoryController, CategoryResource

### Community 119 - "SeoScoreDot.vue"
Cohesion: 0.17
Nodes (10): SeoCheck, SeoRating, { formatNumber }, items, ORDER, props, { t }, color (+2 more)

### Community 120 - "PriceDisplayCurrency"
Cohesion: 0.20
Nodes (5): PriceDisplayCurrency, Rial, Toman, Setting, {closure#1}()

### Community 121 - "load"
Cohesion: 0.24
Nodes (10): emptyForm(), emptyTargets(), formFromCoupon(), ids(), toCouponPayload(), dirty, load(), payload() (+2 more)

### Community 122 - "BulkNumberInput.vue"
Cohesion: 0.27
Nodes (9): display(), emit, focused, { formatNumber }, onBlur(), onFocus(), onInput(), props (+1 more)

### Community 124 - "SaveCouponRequest.php"
Cohesion: 0.10
Nodes (11): SaveCouponAction, SyncCouponCustomersAction, SyncCouponTargetsAction, CouponData, CouponCustomerScope, All, Selected, {closure#1}() (+3 more)

### Community 125 - "PostResource"
Cohesion: 0.17
Nodes (3): ResolvePostPreviewAction, PostController, PostResource

### Community 128 - "SeoText"
Cohesion: 0.04
Nodes (16): {closure#1}(), ResolveTagsAction, {closure#1}(), {closure#3}(), {closure#1}(), SeoAnalysis, SeoCheck, SeoInput (+8 more)

### Community 131 - "errorOf"
Cohesion: 0.47
Nodes (6): customersError, errorOf(), excludedError, listErrorOf(), problemText(), targetsError

### Community 132 - "products/types.ts"
Cohesion: 0.13
Nodes (20): BulkPage, tagsApi, add(), id, key(), known, onKeydown(), open (+12 more)

### Community 133 - "formatRelativeTime"
Cohesion: 0.50
Nodes (4): headerDetail, scheduleNote, stateDetail, formatRelativeTime()

### Community 136 - "Illuminate\Validation\Validator"
Cohesion: 0.08
Nodes (12): SetLocaleFromHeader, {closure#1}(), SimulateCouponRequest, {closure#1}(), {closure#3}(), {closure#1}(), SaveProductRequest, {closure#1}() (+4 more)

### Community 137 - "discounts/types.ts"
Cohesion: 0.07
Nodes (40): couponsApi, promotionsApi, addError, adding, capped, choose(), error, { formatNumber } (+32 more)

### Community 140 - "Settings"
Cohesion: 0.16
Nodes (4): PreviewSaleEventAction, SaleEventPreview, ProductController, Settings

### Community 141 - "RichText"
Cohesion: 0.06
Nodes (6): PageAutosaveData, PostAutosaveData, AutosavePageRequest, AutosavePostRequest, {closure#2}(), RichText

### Community 145 - "AppSpinner.vue"
Cohesion: 0.29
Nodes (6): entry(), id, SpecificationInput, { t }, toggleValue(), values

### Community 147 - "BulkUpdateVariantsRequest.php"
Cohesion: 0.23
Nodes (5): BulkUpdateVariantsRequest, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 150 - "SettingsPage.vue"
Cohesion: 0.06
Nodes (33): applicable, apply(), emit, field, fieldOptions, { formatNumber }, money, props (+25 more)

### Community 152 - "fromLocalInput"
Cohesion: 0.19
Nodes (13): datesLabel(), dateText(), emit, end, { formatNumber, formatDateTime }, props, shown(), start (+5 more)

### Community 163 - "SavePostRequest.php"
Cohesion: 0.18
Nodes (7): PostData, PostStatus, Draft, Published, {closure#1}(), {closure#2}(), SavePostRequest

### Community 166 - "CategoryTreeResource"
Cohesion: 0.38
Nodes (3): CategoryTreeResource, {closure#1}(), {closure#2}()

### Community 170 - "Products/ProductController.php"
Cohesion: 0.08
Nodes (11): ChangeProductStatusAction, DeleteProductAction, MatchingProductsAction, SearchProductsAction, BulkProductController, ProductController, IndexBulkProductsRequest, IndexProductsRequest (+3 more)

### Community 172 - "User"
Cohesion: 0.05
Nodes (19): UpdateBylineAction, {closure#1}(), ReorderBrandsAction, DeleteCategoryAction, ReorderCategoriesAction, AddStaffMemberAction, {closure#1}(), SearchStaffAction (+11 more)

## Knowledge Gaps
- **103 isolated node(s):** `Text`, `Number`, `Boolean`, `Select`, `Product` (+98 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 1812 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **24 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Illuminate\Http\Request`, `Controller`, `Settings`, `Product`, `RefreshPricesAction`, `CustomerTokenResource`, `MenuItem`, `Illuminate\Database\Eloquent\Model`, `Illuminate\Container\Attributes\CurrentUser`, `UpdateCustomerData`, `Tag`, `Illuminate\Foundation\Http\FormRequest`, `RecordLastLogin.php`, `Brand`, `.trim`, `MediaUsageRole`, `SavePostRequest.php`, `Illuminate\Database\Eloquent\Builder`, `Permission`, `Products/ProductController.php`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `CouponTerms`, `Carbon\CarbonImmutable`, `Illuminate\Http\Response`, `UserResource`, `Page`, `AuthException`, `UserRole`, `MediaAsset`, `Category`, `MediaUpload`, `EndUserSessionsAction`, `SaveCategoryRequest`, `Promotion`, `Attribute`, `Coupon`, `SuggestSeoAction.php`, `Illuminate\Support\Collection`, `Post`, `Illuminate\Database\Eloquent\Collection`, `LoginWithOtpAction`, `GuardStaffChangeAction`, `SaveCouponRequest.php`?**
  _High betweenness centrality (0.106) - this node is a cross-community bridge._
- **What connects `Text`, `Number`, `Boolean` to the rest of the system?**
  _103 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.04788732394366197 - nodes in this community are weakly interconnected._
- **Why does `ProductVariant` connect `ProductVariant` to `SaleEventItem`, `SaveSaleEventRequest`, `Illuminate\Validation\Validator`, `SaveProductRequest.php`, `Illuminate\Database\Eloquent\Model`, `Product`, `RefreshPricesAction`, `SaleEvent`, `CouponTerms`, `Carbon\CarbonImmutable`, `Pricing`, `RefreshPricesCommand.php`, `BulkUpdateVariantsRequest.php`, `Promotion`, `Storefront/ProductResource.php`, `Attribute`?**
  _High betweenness centrality (0.020) - this node is a cross-community bridge._
- **Should `StaffPage.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.038461538461538464 - nodes in this community are weakly interconnected._
- **Why does `Product` connect `Product` to `SeoText`, `MediaAsset`, `Illuminate\Database\Eloquent\Builder`, `Category`, `Illuminate\Validation\Validator`, `ProductVariant`, `Products/ProductController.php`, `SaveProductRequest.php`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `RichText`, `RefreshPricesAction`, `Illuminate\Database\Eloquent\Model`, `Illuminate\Contracts\Pagination\LengthAwarePaginator`, `Tag`, `MediaUsageRole`, `Brand`, `Storefront/ProductResource.php`, `Attribute`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **Should `ref_vue` be split into smaller, more focused modules?**
  _Cohesion score 0.035443037974683546 - nodes in this community are weakly interconnected._