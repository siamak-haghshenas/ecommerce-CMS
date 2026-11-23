# Graph Report - ecommerceCms  (2026-10-15)

## Corpus Check
- 698 files · ~211,014 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 5528 nodes · 13482 edges · 166 communities (133 shown, 33 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 172 edges (avg confidence: 0.87)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `e001071c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\Http\Request
- StaffPage.vue
- ref_vue_i18n
- MediaPage.vue
- Product
- CustomersPage.vue
- BulkEditPage.vue
- ProductEditorPage.vue
- ProductVariant
- ActivityLogPage.vue
- PostEditorPage.vue
- Illuminate\Http\JsonResponse
- CouponEditorPage.vue
- ShippingMethodsPage.vue
- CouponsPage.vue
- CustomerTokenResource
- MenuItem
- Illuminate\Database\Eloquent\Model
- Illuminate\Container\Attributes\CurrentUser
- User
- Tag
- Illuminate\Foundation\Http\FormRequest
- MediaPicture
- OtpInput.vue
- analysis.ts
- ProductsPage.vue
- PageEditorPage.vue
- bulkEdit.ts
- AppIcon.vue
- SeoAnalyzer.php
- MediaUsageRole
- BulkProductCard.vue
- SeoPanel.vue
- VariantsEditor.vue
- CategoriesPage.vue
- PostsPage.vue
- CustomerGroupsPage.vue
- Illuminate\Database\Eloquent\Builder
- AppButton.vue
- Permission
- ref_vue
- MenusPage.vue
- CouponCalculator.php
- CategoryFormDialog.vue
- TextField.vue
- Illuminate\Support\Facades\DB
- ShippingZonesPage.vue
- PagesPage.vue
- PromotionsPage.vue
- Promotion
- HasMediaFields
- AppServiceProvider.php
- SavePromotionsRequest.php
- CategoriesField.vue
- UserResource
- Page
- MediaDetailsDrawer.vue
- OtpCode
- ShippingRate
- CategoryRow.vue
- BrandsPage.vue
- ShippingZoneLocation
- UserRole
- SaveProductRequest.php
- CouponResource.php
- MediaAsset
- CustomerGroupEditorPage.vue
- CategoryTree
- HasSeo
- ShippingZone
- CategoryType
- Category
- CategoryNode
- SaleEventsPage.vue
- SaleEventResource
- Illuminate\Validation\Validator
- RichTextEditor.vue
- AiException
- SmsSender
- SeoContentType
- QuoteShippingRequest
- MediaUpload
- Post
- CompleteMediaUploadAction.php
- MediaFolderNav.vue
- AuthException
- FarazSmsDriver
- CustomerGroupType
- LoginWithOtpAction
- MediaPickerDialog.vue
- SaleEventEditorPage.vue
- ShippingDestination
- OptionValue
- EventItemsSection.vue
- Attribute
- PromotionVariantsDialog.vue
- SuggestSeoAction.php
- SaveCustomerAddressRequest
- Carbon\CarbonInterface
- SeoText
- MediaException
- MediaType
- ProductPickerDialog.vue
- rules.ts
- ProductFiltersRequest
- queryString
- formatNumber
- Brand
- SaveMenuRequest
- SaveShippingMethodRequest
- ShippingTesterPage.vue
- SendOtpAction
- HasSlugField
- Settings
- .parent
- ZoneDialog.vue
- CouponResource
- SaveCouponRequest
- Coupon
- CustomerGroup
- BuildStorefrontFiltersAction
- toApiError
- RegisterRequest
- media/types.ts
- PreviewCouponRequest
- City
- AppDialog.vue
- CouponException
- ApiExceptionRenderer.php
- Shipping & Fulfillment — Architecture Proposal
- shipping.ts
- 3. Database design
- SaleEvent
- ScoreSeoAction
- ShippingMethodEditorPage.vue
- CategoryTreeResource
- discounts/types.ts
- NumberField.vue
- CheckCouponRequest
- Carbon\CarbonImmutable
- RichText
- CategoryException
- Controller
- save
- customer-groups/api.ts
- shipping/types.ts
- Illuminate\Database\Eloquent\Collection
- UpdateCustomerData
- AppServiceProvider
- errors.ts
- RecordLastLogin.php
- fromLocalInput
- env.d.ts
- ResolveTagsAction
- StorefrontPostFilters
- ProductResource
- ShippingTabs.vue
- CustomerFilters
- SavePostRequest.php
- SaveCategoryRequest
- AiTextGenerator
- Illuminate\Http\Resources\Json\AnonymousResourceCollection
- SeoScoreDot.vue
- DeleteMediaAssetAction

## God Nodes (most connected - your core abstractions)
1. `User` - 365 edges
2. `toApiError()` - 158 edges
3. `Controller` - 149 edges
4. `Product` - 90 edges
5. `ProductVariant` - 90 edges
6. `useLocale()` - 83 edges
7. `CategoryTree` - 70 edges
8. `Category` - 69 edges
9. `Post` - 69 edges
10. `Page` - 67 edges

## Surprising Connections (you probably didn't know these)
- `6.2 Shipment status` --references--> `AdjustStockAction`  [INFERRED]
  docs/shipping-architecture.md → app/Actions/Products/AdjustStockAction.php
- `5.1 Single flow` --references--> `ShippingOption`  [INFERRED]
  docs/shipping-architecture.md → app/Data/Shipping/ShippingOption.php
- `3.2 Customer addresses (new, decided: address book)` --references--> `PhoneNumber`  [INFERRED]
  docs/shipping-architecture.md → app/Support/PhoneNumber.php
- `10. Phased plan` --references--> `ShippingCalculator`  [INFERRED]
  docs/shipping-architecture.md → app/Support/Shipping/ShippingCalculator.php
- `7.2 Vue admin` --references--> `useMoney()`  [INFERRED]
  docs/shipping-architecture.md → resources/js/admin/modules/settings/store.ts

## Import Cycles
- None detected.

## Communities (166 total, 33 thin omitted)

### Community 0 - "Illuminate\Http\Request"
Cohesion: 0.04
Nodes (23): SessionController, PageRevisionController, LocationController, ManagesAuthSession, ActivityResource, CustomerAddressResource, DashboardResource, MediaUploadResource (+15 more)

### Community 1 - "StaffPage.vue"
Cohesion: 0.05
Nodes (54): staffApi, add(), emit, fields, form, props, { t }, emit (+46 more)

### Community 2 - "ref_vue_i18n"
Cohesion: 0.04
Nodes (53): route, { t }, { t }, closeButton, collapsed, desktopQuery, drawerOpen, isDesktop (+45 more)

### Community 3 - "MediaPage.vue"
Cohesion: 0.05
Nodes (61): assets, auth, busy, canManage, canUpload, closeDetails(), confirmDelete(), currentFolder (+53 more)

### Community 4 - "Product"
Cohesion: 0.06
Nodes (13): SearchStorefrontProductsAction, SearchCouponsAction, SearchPostsAction, {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), SaveProductAction (+5 more)

### Community 5 - "CustomersPage.vue"
Cohesion: 0.03
Nodes (75): customersApi, anonymise(), emit, form, { localizeDigits }, props, { t }, typedPhone (+67 more)

### Community 6 - "BulkEditPage.vue"
Cohesion: 0.05
Nodes (37): activeFilterCount, all, brand, brandOptions, brands, category, categoryOptions, confirmUndo (+29 more)

### Community 7 - "ProductEditorPage.vue"
Cohesion: 0.05
Nodes (48): acceptNormalized(), alertMessage, attributes, attributesLoading, auth, brands, canManage, chosen (+40 more)

### Community 8 - "ProductVariant"
Cohesion: 0.04
Nodes (37): AdjustStockAction, {closure#1}(), BulkUpdateVariantsAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}() (+29 more)

### Community 9 - "ActivityLogPage.vue"
Cohesion: 0.10
Nodes (24): activityLogApi, detail, eventKey, { formatDateTime, formatRelativeTime, formatNumber, isRtl }, ICONS, look, missingSubject, props (+16 more)

### Community 10 - "PostEditorPage.vue"
Cohesion: 0.05
Nodes (48): acceptNormalized(), alertMessage, applyVersion(), auth, authors, autosavedAt, autosaveNow(), autosaveState (+40 more)

### Community 11 - "Illuminate\Http\JsonResponse"
Cohesion: 0.05
Nodes (13): SetCategoryAttributesAction, {closure#1}(), {closure#2}(), {closure#1}(), CustomerAddressException, CustomerGroupException, ProductException, ShippingException (+5 more)

### Community 12 - "CouponEditorPage.vue"
Cohesion: 0.04
Nodes (65): Choice, emit, { formatNumber }, props, query, selected, { t }, addItems() (+57 more)

### Community 13 - "ShippingMethodsPage.vue"
Cohesion: 0.12
Nodes (19): auth, busy, canManage, deleting, error, { formatNumber }, load(), loading (+11 more)

### Community 14 - "CouponsPage.vue"
Cohesion: 0.07
Nodes (28): { t }, auth, busy, canManage, copyCode(), deleting, error, { formatNumber, formatDate } (+20 more)

### Community 15 - "CustomerTokenResource"
Cohesion: 0.14
Nodes (9): IssueCustomerTokenAction, ResetPasswordAction, LoginWithOtpController, LoginWithPasswordController, ResetPasswordController, VerifyRegistrationController, PasswordLoginRequest, VerifyOtpRequest (+1 more)

### Community 16 - "MenuItem"
Cohesion: 0.10
Nodes (15): {closure#1}(), {closure#2}(), SaveMenuAction, MenuItemData, MenuItemType, Category, Page, Url (+7 more)

### Community 17 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.03
Nodes (12): {closure#2}(), SeoSuggestionContext, {closure#14}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), PageAutosave (+4 more)

### Community 18 - "Illuminate\Container\Attributes\CurrentUser"
Cohesion: 0.04
Nodes (24): EndUserSessionsAction, AnonymiseCustomerAction, {closure#1}(), {closure#2}(), SearchCustomersAction, SetCustomerStatusAction, UpdateCustomerAction, AddStaffMemberAction (+16 more)

### Community 19 - "User"
Cohesion: 0.04
Nodes (30): UpdateBylineAction, DeleteCategoryAction, ReorderCategoriesAction, CancelMediaUploadAction, DeleteMediaFolderAction, ManageMediaFolderAction, MoveMediaAssetsAction, SearchMediaAction (+22 more)

### Community 20 - "Tag"
Cohesion: 0.20
Nodes (7): {closure#1}(), ManageTagsAction, {closure#1}(), TagController, TagRequest, TagResource, Tag

### Community 21 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.04
Nodes (17): PasswordLoginRequest, PhoneNumberRequest, ReorderBrandsRequest, SetCategoryAttributesRequest, FindCustomersByPhoneRequest, UpdateCustomerRequest, IndexActivityLogRequest, MediaChunkRequest (+9 more)

### Community 22 - "MediaPicture"
Cohesion: 0.17
Nodes (23): MediaPicture, Page, PageAutosave, PageFilters, PageListItem, PagePreview, PageRevision, PageRevisionSummary (+15 more)

### Community 23 - "OtpInput.vue"
Cohesion: 0.09
Nodes (30): confirmed, display(), emit, focused, { formatNumber }, onBlur(), onFocus(), onInput() (+22 more)

### Community 24 - "analysis.ts"
Cohesion: 0.14
Nodes (25): onValueInput(), shown, shown, filtered, ALEF, analyzeReadability(), analyzeSeo(), characterCount() (+17 more)

### Community 25 - "ProductsPage.vue"
Cohesion: 0.04
Nodes (69): OptionDisplayType, BulkPage, productsApi, tagsApi, BulkProduct, emit, error, { formatNumber, formatDateTime, localizeDigits } (+61 more)

### Community 26 - "PageEditorPage.vue"
Cohesion: 0.05
Nodes (46): acceptNormalized(), alertMessage, applyVersion(), auth, autosavedAt, autosaveNow(), autosaveState, autosaveTimer (+38 more)

### Community 27 - "bulkEdit.ts"
Cohesion: 0.13
Nodes (14): BulkVariant, BulkVariantPayload, countChanges(), discountPercent(), EditableField, FieldProblem, PageEdits, RowErrors (+6 more)

### Community 28 - "AppIcon.vue"
Cohesion: 0.06
Nodes (38): CatalogItemAction, emit, { formatNumber }, menuItems, more, preview, props, { t } (+30 more)

### Community 29 - "SeoAnalyzer.php"
Cohesion: 0.11
Nodes (9): {closure#1}(), SeoAnalysis, SeoCheck, SeoRating, Bad, Good, Ok, {closure#2}() (+1 more)

### Community 30 - "MediaUsageRole"
Cohesion: 0.07
Nodes (13): MediaUsageRole, Avatar, Banner, Content, Gallery, Image, Logo, OgImage (+5 more)

### Community 31 - "BulkProductCard.vue"
Cohesion: 0.11
Nodes (23): isEdited(), problems(), valueOf(), changedCount, clearSale(), collapsed, dotClass, emit (+15 more)

### Community 32 - "SeoPanel.vue"
Cohesion: 0.09
Nodes (27): META_DESCRIPTION_MAX, META_DESCRIPTION_MIN, TITLE_MAX, TITLE_MIN, seoApi, { formatNumber }, keyphrase, metaDescription (+19 more)

### Community 33 - "VariantsEditor.vue"
Cohesion: 0.07
Nodes (35): clock, addOption(), available, bulk, choose(), chosen, ChosenOption, chosenOptions (+27 more)

### Community 34 - "CategoriesPage.vue"
Cohesion: 0.07
Nodes (31): act(), auth, canManage, canSeeBlog, canSeeProduct, collapsed, deleting, dragging (+23 more)

### Community 35 - "PostsPage.vue"
Cohesion: 0.08
Nodes (32): auth, author, authorOptions, authors, blogTree, canManage, category, categoryOptions (+24 more)

### Community 36 - "CustomerGroupsPage.vue"
Cohesion: 0.09
Nodes (22): customerGroupsApi, auth, busy, canManage, counts, deleting, error, { formatNumber, formatRelativeTime } (+14 more)

### Community 37 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.03
Nodes (103): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#1}() (+95 more)

### Community 38 - "AppButton.vue"
Cohesion: 0.03
Nodes (92): attributesApi, optionsApi, alertMessage, emit, fields, filterableOptions, form, props (+84 more)

### Community 39 - "Permission"
Cohesion: 0.06
Nodes (35): Permission, AccessAdminPanel, AnonymiseCustomers, DeactivateCustomers, ManageAttributes, ManageBlogCategories, ManageBrands, ManageCategories (+27 more)

### Community 40 - "ref_vue"
Cohesion: 0.03
Nodes (64): emit, { formatNumber, localizeDigits }, looking, lookupError, message, notFound, pasted, Picked (+56 more)

### Community 41 - "MenusPage.vue"
Cohesion: 0.05
Nodes (57): menusApi, categoryGroups, emit, errors, fields, id, isValidLink(), props (+49 more)

### Community 42 - "CouponCalculator.php"
Cohesion: 0.11
Nodes (11): CartLineData, CouponLineResult, {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#3}() (+3 more)

### Community 43 - "CategoryFormDialog.vue"
Cohesion: 0.06
Nodes (41): attributeIds, categoryType, emit, excludedParents, fields, fill(), form, hasImage (+33 more)

### Community 44 - "TextField.vue"
Cohesion: 0.05
Nodes (33): avatar, emit, fields, form, id, loadError, loading, props (+25 more)

### Community 45 - "Illuminate\Support\Facades\DB"
Cohesion: 0.07
Nodes (14): {closure#1}(), DeleteCustomerAddressAction, SaveCustomerAddressAction, {closure#1}(), SetDefaultCustomerAddressAction, DeleteShippingMethodAction, {closure#1}(), SaveShippingMethodAction (+6 more)

### Community 46 - "ShippingZonesPage.vue"
Cohesion: 0.03
Nodes (67): auth, bylineOpen, emit, groups, loggingOut, logout(), roleLabel, route (+59 more)

### Community 47 - "PagesPage.vue"
Cohesion: 0.09
Nodes (26): pagesApi, auth, canManage, error, filtered, { formatNumber, formatRelativeTime }, load(), loading (+18 more)

### Community 48 - "PromotionsPage.vue"
Cohesion: 0.03
Nodes (87): apply(), emit, endsAt, errors, field, fieldOptions, filled, { formatNumber } (+79 more)

### Community 49 - "Promotion"
Cohesion: 0.09
Nodes (12): {closure#1}(), {closure#2}(), {closure#3}(), PromotionRowData, SaleEventItemData, PromotionType, Fixed, Percent (+4 more)

### Community 50 - "HasMediaFields"
Cohesion: 0.07
Nodes (10): {closure#1}(), BylineData, PageData, PageStatus, Draft, Published, UpdateBylineRequest, SaveBrandRequest (+2 more)

### Community 51 - "AppServiceProvider.php"
Cohesion: 0.09
Nodes (10): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#3}(), {closure#5}(), {closure#6}(), {closure#8}() (+2 more)

### Community 52 - "SavePromotionsRequest.php"
Cohesion: 0.17
Nodes (3): SavePromotionsData, {closure#1}(), SavePromotionsRequest

### Community 53 - "CategoriesField.vue"
Cohesion: 0.17
Nodes (9): available, byId, chosen, id, ids, primaryId, props, { t } (+1 more)

### Community 54 - "UserResource"
Cohesion: 0.20
Nodes (5): LoginWithPasswordAction, LoginWithOtpController, LoginWithPasswordController, VerifyOtpRequest, UserResource

### Community 55 - "Page"
Cohesion: 0.05
Nodes (17): DeletePageAction, ResolvePagePreviewAction, SavePageAction, SavePageAutosaveAction, SearchPagesAction, PageFilters, {closure#1}(), {closure#2}() (+9 more)

### Community 56 - "MediaDetailsDrawer.vue"
Cohesion: 0.08
Nodes (25): alt, asset, closeButton, cover, dirty, editable, emit, error (+17 more)

### Community 57 - "OtpCode"
Cohesion: 0.19
Nodes (3): {closure#1}(), {closure#1}(), OtpCode

### Community 58 - "ShippingRate"
Cohesion: 0.10
Nodes (16): ShippingOption, FreeShippingReason, Coupon, Threshold, ShippingUnavailableReason, MaxWeight, MinOrder, NoAddress (+8 more)

### Community 59 - "CategoryRow.vue"
Cohesion: 0.11
Nodes (19): collapsed, { formatNumber }, hasChildren, menuItems, props, { t }, tree, nodes (+11 more)

### Community 60 - "BrandsPage.vue"
Cohesion: 0.04
Nodes (59): confirm(), emit, form, props, { t }, brandsApi, emit, fields (+51 more)

### Community 61 - "ShippingZoneLocation"
Cohesion: 0.28
Nodes (4): {closure#5}(), {closure#1}(), {closure#2}(), ShippingZoneLocation

### Community 62 - "UserRole"
Cohesion: 0.10
Nodes (11): AddStaffMemberData, StaffFilters, {closure#1}(), UserRole, Admin, Author, Customer, SuperAdmin (+3 more)

### Community 63 - "SaveProductRequest.php"
Cohesion: 0.07
Nodes (14): {closure#2}(), ProductData, SpecificationData, VariantData, ProductStatus, Archived, Draft, Published (+6 more)

### Community 64 - "CouponResource.php"
Cohesion: 0.11
Nodes (12): {closure#1}(), {closure#2}(), CouponTargetType, Brand, Category, Product, Tag, {closure#1}() (+4 more)

### Community 66 - "CustomerGroupEditorPage.vue"
Cohesion: 0.06
Nodes (33): auth, canManage, deleteOpen, deleting, description, difference, dirty, fill() (+25 more)

### Community 67 - "CategoryTree"
Cohesion: 0.11
Nodes (6): CreateCategoryAction, UpdateMediaAssetAction, MediaAssetData, {closure#1}(), {closure#2}(), CategoryTree

### Community 69 - "ShippingZone"
Cohesion: 0.11
Nodes (9): DeleteShippingZoneAction, {closure#1}(), SaveShippingZoneAction, ShippingZoneData, ShippingZoneController, {closure#3}(), SaveShippingZoneRequest, ShippingZoneResource (+1 more)

### Community 70 - "CategoryType"
Cohesion: 0.09
Nodes (6): CategoryData, CategoryType, Blog, Product, IndexCategoriesRequest, ReorderCategoriesRequest

### Community 71 - "Category"
Cohesion: 0.08
Nodes (17): {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#2}() (+9 more)

### Community 72 - "CategoryNode"
Cohesion: 0.17
Nodes (14): {closure#1}(), {closure#2}(), CategoryNode, {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}() (+6 more)

### Community 73 - "SaleEventsPage.vue"
Cohesion: 0.07
Nodes (29): auth, busy, canManage, deleting, error, events, filtered, filtering (+21 more)

### Community 74 - "SaleEventResource"
Cohesion: 0.29
Nodes (3): ResolveSaleEventPreviewAction, SaleEventController, SaleEventResource

### Community 75 - "Illuminate\Validation\Validator"
Cohesion: 0.09
Nodes (11): {closure#1}(), {closure#3}(), {closure#1}(), {closure#2}(), UpdateMediaAssetRequest, {closure#1}(), SaveProductRequest, {closure#1}() (+3 more)

### Community 76 - "RichTextEditor.vue"
Cohesion: 0.08
Nodes (28): charCount, editor, editorKey, embedError, embedLink, embedOpen, emit, full (+20 more)

### Community 78 - "SmsSender"
Cohesion: 0.16
Nodes (4): SmsSender, ArraySmsDriver, LogSmsDriver, SmsManager

### Community 79 - "SeoContentType"
Cohesion: 0.08
Nodes (12): RefreshCustomerGroupsCommand, RefreshPricesCommand, ScoreSeoCommand, SeoContentType, BlogCategory, Brand, Category, Page (+4 more)

### Community 80 - "QuoteShippingRequest"
Cohesion: 0.24
Nodes (3): ShippingQuoteController, QuoteShippingRequest, ShippingQuoteResource

### Community 81 - "MediaUpload"
Cohesion: 0.15
Nodes (4): AppendMediaChunkAction, {closure#1}(), MediaUpload, {closure#7}()

### Community 82 - "Post"
Cohesion: 0.05
Nodes (16): DeletePostAction, PreviewPostAction, ResolvePostPreviewAction, {closure#1}(), SavePostAction, SavePostAutosaveAction, SearchStorefrontPostsAction, PostAutosaveController (+8 more)

### Community 84 - "MediaFolderNav.vue"
Cohesion: 0.22
Nodes (6): emit, fixed, { formatNumber }, MediaPlace, { t }, MediaFolder

### Community 87 - "CustomerGroupType"
Cohesion: 0.06
Nodes (21): {closure#2}(), CustomerGroupData, CustomerRuleData, CustomerGroupMatch, All, Any, CustomerGroupType, Automatic (+13 more)

### Community 88 - "LoginWithOtpAction"
Cohesion: 0.32
Nodes (3): LoginWithOtpAction, VerifyOtpAction, VerifyRegistrationAction

### Community 89 - "MediaPickerDialog.vue"
Cohesion: 0.09
Nodes (24): accept, assets, auth, canUpload, chosen, confirm(), emit, error (+16 more)

### Community 90 - "SaleEventEditorPage.vue"
Cohesion: 0.06
Nodes (40): acceptNormalized(), alertMessage, auth, banner, canManage, confirmDelete, dirty, eventWindow (+32 more)

### Community 91 - "ShippingDestination"
Cohesion: 0.11
Nodes (11): QuoteShippingAction, {closure#2}(), {closure#3}(), {closure#4}(), ShippingContext, ShippingDestination, {closure#2}(), {closure#4}() (+3 more)

### Community 92 - "OptionValue"
Cohesion: 0.07
Nodes (22): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}(), {closure#9}(), {closure#2}() (+14 more)

### Community 93 - "EventItemsSection.vue"
Cohesion: 0.03
Nodes (86): saleEventsApi, add(), adding, emit, error, fieldOptions, money, props (+78 more)

### Community 94 - "Attribute"
Cohesion: 0.06
Nodes (17): DeleteCatalogItemAction, {closure#1}(), ReorderListAction, {closure#1}(), SaveAttributeAction, {closure#1}(), SaveOptionAction, SyncValuesAction (+9 more)

### Community 95 - "PromotionVariantsDialog.vue"
Cohesion: 0.06
Nodes (40): promotionsApi, add(), allMatching, allOn(), brandOptions, canAdd, categoryOptions, changedFilters (+32 more)

### Community 97 - "SuggestSeoAction.php"
Cohesion: 0.14
Nodes (5): {closure#3}(), {closure#5}(), SuggestSeoAction, SeoSuggestions, AiRequest

### Community 99 - "Carbon\CarbonInterface"
Cohesion: 0.07
Nodes (16): VariantPrice, DiscountSource, Promotion, Sale, SaleEvent, {closure#2}(), {closure#1}(), {closure#2}() (+8 more)

### Community 102 - "MediaType"
Cohesion: 0.10
Nodes (9): MediaFilters, MediaUploadData, MediaType, Document, Image, Video, IndexMediaRequest, {closure#1}() (+1 more)

### Community 103 - "ProductPickerDialog.vue"
Cohesion: 0.05
Nodes (43): categoriesApi, childCount, confirm(), emit, form, { formatNumber }, props, { t } (+35 more)

### Community 104 - "rules.ts"
Cohesion: 0.14
Nodes (20): emit, invalid, kind, props, { t }, addRule(), canSave, COMING_FIELDS (+12 more)

### Community 106 - "queryString"
Cohesion: 0.29
Nodes (7): page, queryModel(), queryString(), quick, showFilters, updateQuery(), whenSaved()

### Community 107 - "formatNumber"
Cohesion: 0.10
Nodes (23): filtered, seoLabel, results, findPhones(), splitPhones(), onAdd(), stockText(), amount() (+15 more)

### Community 108 - "Brand"
Cohesion: 0.09
Nodes (10): DeleteBrandAction, {closure#1}(), ReorderBrandsAction, BrandException, BrandController, BrandOrderController, BrandController, BrandResource (+2 more)

### Community 110 - "SaveShippingMethodRequest"
Cohesion: 0.14
Nodes (6): ShippingMethodData, ShippingRateData, ShippingMethodKind, Delivery, Pickup, SaveShippingMethodRequest

### Community 111 - "ShippingTesterPage.vue"
Cohesion: 0.09
Nodes (18): useMoney(), format(), cityId, cityOptions, error, estimate(), { formatNumber }, grams() (+10 more)

### Community 113 - "HasSlugField"
Cohesion: 0.08
Nodes (16): AttributeData, OptionData, ValueData, AttributeType, Boolean, Number, Select, Text (+8 more)

### Community 114 - "Settings"
Cohesion: 0.13
Nodes (7): PriceDisplayCurrency, Rial, Toman, ProductController, Setting, {closure#1}(), Settings

### Community 116 - "ZoneDialog.vue"
Cohesion: 0.11
Nodes (21): shippingApi, chooseFreeProvinces(), chosen, chosenCities(), emit, error, expanded, { formatNumber } (+13 more)

### Community 117 - "CouponResource"
Cohesion: 0.16
Nodes (5): GenerateCouponCodeAction, SaveCouponAction, CouponController, IndexCouponsRequest, CouponResource

### Community 118 - "SaveCouponRequest"
Cohesion: 0.12
Nodes (9): CouponData, CouponScope, All, Selected, CouponType, Fixed, FreeShipping, Percent (+1 more)

### Community 119 - "Coupon"
Cohesion: 0.12
Nodes (6): CheckCouponAction, {closure#2}(), PreviewCouponAction, CouponResult, Coupon, CouponCalculator

### Community 120 - "CustomerGroup"
Cohesion: 0.07
Nodes (15): ChangeCustomerGroupMembersAction, DeleteCustomerGroupAction, FindCustomersByPhoneAction, ListCustomerGroupMembersAction, RefreshCustomerGroupAction, {closure#1}(), SaveCustomerGroupAction, SearchCustomerGroupsAction (+7 more)

### Community 122 - "toApiError"
Cohesion: 0.11
Nodes (27): refreshNow(), add(), load(), remove(), toggle(), togglePage(), onMenu(), saleEventPayloadOf() (+19 more)

### Community 124 - "media/types.ts"
Cohesion: 0.08
Nodes (28): mediaApi, emit, extension, { formatNumber }, picture, props, size, { t } (+20 more)

### Community 126 - "City"
Cohesion: 0.24
Nodes (4): {closure#1}(), LocationController, City, Province

### Community 127 - "AppDialog.vue"
Cohesion: 0.08
Nodes (27): emit, end, invalid, props, start, { t }, emit, { formatDateTime, formatNumber } (+19 more)

### Community 130 - "Shipping & Fulfillment — Architecture Proposal"
Cohesion: 0.10
Nodes (18): 10. Phased plan, 11. Open questions, assumptions and trade-offs, 4.1 Storefront API (`/api/v1`), 4. API proposals, 5.1 Single flow, 5.2 Zone matching, 5.4 Never trusting the client, 5. Shipping cost calculation (+10 more)

### Community 131 - "shipping.ts"
Cohesion: 0.17
Nodes (14): payload, CODE_PATTERN, emptyMethodForm(), emptyRate(), MethodForm, MethodProblem, rateFee(), rateProblem (+6 more)

### Community 132 - "3. Database design"
Cohesion: 0.29
Nodes (5): 3.2 Customer addresses (new, decided: address book), 3.3 Shipping configuration (new), 3.4 Orders (owned by the orders module; shipping columns proposed here), 3.5 Fulfillment (new), 3. Database design

### Community 133 - "SaleEvent"
Cohesion: 0.04
Nodes (24): {closure#1}(), {closure#2}(), RefreshPricesAction, SavePromotionsAction, DeleteSaleEventAction, FindOverridingEventsAction, {closure#1}(), ReorderSaleEventsAction (+16 more)

### Community 134 - "ScoreSeoAction"
Cohesion: 0.10
Nodes (9): CreateBrandAction, UpdateBrandAction, UpdateCategoryAction, RefreshProductSearchTextAction, {closure#1}(), {closure#3}(), ScoreSeoAction, SeoSubject (+1 more)

### Community 135 - "ShippingMethodEditorPage.vue"
Cohesion: 0.05
Nodes (32): auth, canManage, codeTouched, deleteOpen, deleting, dirty, EXAMPLE_WEIGHTS, fieldErrors (+24 more)

### Community 136 - "CategoryTreeResource"
Cohesion: 0.17
Nodes (6): BlogCategoryController, CategoryController, CategoryResource, CategoryTreeResource, {closure#1}(), {closure#2}()

### Community 137 - "discounts/types.ts"
Cohesion: 0.12
Nodes (25): couponsApi, error, loading, money, pickerOpen, preview, products, props (+17 more)

### Community 138 - "NumberField.vue"
Cohesion: 0.20
Nodes (11): describedBy, display(), focused, { formatNumber }, id, model, onBlur(), onFocus() (+3 more)

### Community 140 - "Carbon\CarbonImmutable"
Cohesion: 0.07
Nodes (10): PreviewPageAction, PreviewSaleEventAction, PagePreview, PostPreview, SaleEventData, SaleEventPreview, {closure#1}(), {closure#3}() (+2 more)

### Community 141 - "RichText"
Cohesion: 0.05
Nodes (10): PageAutosaveData, PostAutosaveData, {closure#3}(), AutosavePageRequest, AutosavePostRequest, {closure#4}(), {closure#7}(), {closure#4}() (+2 more)

### Community 143 - "Controller"
Cohesion: 0.06
Nodes (19): {closure#1}(), RegisterCustomerAction, RequestOtpAction, OtpPurpose, Login, Register, SendLoginOtpController, DashboardController (+11 more)

### Community 144 - "save"
Cohesion: 0.40
Nodes (5): mapErrors(), toPayload(), load(), save(), saveAndContinue()

### Community 145 - "customer-groups/api.ts"
Cohesion: 0.36
Nodes (8): GroupTemplate, CustomerGroup, CustomerGroupMatch, CustomerGroupType, CustomerRule, GroupMember, GroupPreview, SaveCustomerGroupPayload

### Community 146 - "shipping/types.ts"
Cohesion: 0.26
Nodes (10): City, Province, SaveShippingMethodPayload, SaveShippingRatePayload, SaveShippingZonePayload, ShippingMethod, ShippingQuote, ShippingUnavailableReason (+2 more)

### Community 147 - "Illuminate\Database\Eloquent\Collection"
Cohesion: 0.14
Nodes (6): GetDashboardSummaryAction, PreviewCustomerGroupAction, DashboardSummary, BulkUpdateVariantsRequest, {closure#1}(), CustomerRules

### Community 150 - "errors.ts"
Cohesion: 0.06
Nodes (40): accountApi, Byline, SaveBylinePayload, dashboardApi, auth, canSeeFigures, error, { formatNumber, formatDate } (+32 more)

### Community 152 - "fromLocalInput"
Cohesion: 0.11
Nodes (20): updated(), today, datesLabel(), dateText(), emit, end, { formatNumber, formatDateTime }, props (+12 more)

### Community 163 - "SavePostRequest.php"
Cohesion: 0.18
Nodes (7): PostData, PostStatus, Draft, Published, {closure#1}(), {closure#2}(), SavePostRequest

### Community 167 - "AiTextGenerator"
Cohesion: 0.11
Nodes (6): AiTextGenerator, AiPrompt, AiResult, AiManager, ClaudeAiDriver, {closure#1}()

### Community 170 - "Illuminate\Http\Resources\Json\AnonymousResourceCollection"
Cohesion: 0.06
Nodes (18): SearchActivityLogAction, SearchProductsAction, ListPromotionsAction, FindEventVariantsAction, ActivityLogController, CustomerAddressController, BulkProductController, StockMovementController (+10 more)

### Community 171 - "SeoScoreDot.vue"
Cohesion: 0.17
Nodes (10): SeoCheck, SeoRating, { formatNumber }, items, ORDER, props, { t }, color (+2 more)

## Knowledge Gaps
- **120 isolated node(s):** `Text`, `Number`, `Boolean`, `Select`, `Product` (+115 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 2073 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **33 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `1. Current architecture findings` connect `ProductVariant` to `Shipping & Fulfillment — Architecture Proposal`, `Carbon\CarbonInterface`, `Product`, `ShippingTesterPage.vue`, `User`, `Coupon`?**
  _High betweenness centrality (0.441) - this node is a cross-community bridge._
- **What connects `Text`, `Number`, `Boolean` to the rest of the system?**
  _120 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.0441358024691358 - nodes in this community are weakly interconnected._
- **Why does `useMoney()` connect `ShippingTesterPage.vue` to `VariantsEditor.vue`, `Shipping & Fulfillment — Architecture Proposal`, `ref_vue_i18n`, `ProductPickerDialog.vue`, `ProductVariant`, `discounts/types.ts`, `ShippingMethodEditorPage.vue`, `CouponEditorPage.vue`, `ShippingMethodsPage.vue`, `CouponsPage.vue`, `PromotionsPage.vue`, `errors.ts`, `ProductsPage.vue`, `BulkProductCard.vue`, `EventItemsSection.vue`, `PromotionVariantsDialog.vue`?**
  _High betweenness centrality (0.441) - this node is a cross-community bridge._
- **Should `StaffPage.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.05069124423963134 - nodes in this community are weakly interconnected._
- **Why does `User` connect `User` to `CouponException`, `Illuminate\Http\Request`, `Product`, `SaleEvent`, `ScoreSeoAction`, `3. Database design`, `ProductVariant`, `Illuminate\Http\JsonResponse`, `Carbon\CarbonImmutable`, `Controller`, `CustomerTokenResource`, `MenuItem`, `Illuminate\Container\Attributes\CurrentUser`, `Illuminate\Database\Eloquent\Collection`, `Tag`, `Illuminate\Foundation\Http\FormRequest`, `Illuminate\Database\Eloquent\Model`, `RecordLastLogin.php`, `MediaUsageRole`, `SavePostRequest.php`, `Illuminate\Database\Eloquent\Builder`, `Permission`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `CouponCalculator.php`, `DeleteMediaAssetAction`, `Illuminate\Support\Facades\DB`, `Promotion`, `HasMediaFields`, `AppServiceProvider.php`, `SavePromotionsRequest.php`, `UserResource`, `Page`, `OtpCode`, `UserRole`, `SaveProductRequest.php`, `CouponResource.php`, `CategoryTree`, `ShippingZone`, `CategoryType`, `CategoryNode`, `Illuminate\Validation\Validator`, `Post`, `CompleteMediaUploadAction.php`, `AuthException`, `LoginWithOtpAction`, `ShippingDestination`, `Attribute`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `SuggestSeoAction.php`, `Brand`, `CouponResource`, `SaveCouponRequest`, `Coupon`, `CustomerGroup`?**
  _High betweenness centrality (0.286) - this node is a cross-community bridge._
- **Should `ref_vue_i18n` be split into smaller, more focused modules?**
  _Cohesion score 0.04381846635367762 - nodes in this community are weakly interconnected._