# Graph Report - ecommerceCms  (2026-10-16)

## Corpus Check
- 598 files · ~179,035 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 4756 nodes · 11527 edges · 149 communities (122 shown, 27 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 130 edges (avg confidence: 0.85)
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
- FormAlert.vue
- BulkEditPage.vue
- ProductEditorPage.vue
- ProductVariant
- CustomerDetailPage.vue
- PostEditorPage.vue
- Controller
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Product
- RefreshPricesAction
- CustomerTokenResource
- MenuItem
- Exception
- Illuminate\Support\Facades\DB
- UpdateMediaAssetRequest.php
- Tag
- Illuminate\Foundation\Http\FormRequest
- types/api.ts
- useLocale
- OptionFormDialog.vue
- ProductsPage.vue
- PageEditorPage.vue
- Illuminate\Database\Eloquent\Model
- AppIcon.vue
- .trim
- Illuminate\Database\Eloquent\Factories\HasFactory
- BulkProductCard.vue
- SeoPanel.vue
- VariantsEditor.vue
- CouponsPage.vue
- PostsPage.vue
- AttributesPage.vue
- Illuminate\Database\Eloquent\Builder
- CategoryAttributesField.vue
- Permission
- BylineDialog.vue
- MenusPage.vue
- CategoryFormDialog.vue
- CategoriesPage.vue
- PromotionController.php
- SaleEventVariantResource
- LoginPage.vue
- PagesPage.vue
- PromotionsPage.vue
- Carbon\CarbonImmutable
- Illuminate\Validation\Rule
- CouponCustomersDialog.vue
- AppDialog.vue
- products/types.ts
- UserResource
- Page
- MediaDetailsDrawer.vue
- AuthException
- categories/types.ts
- AttributeFormDialog.vue
- AppButton.vue
- MediaUploadQueue.vue
- UserRole
- BulkUpdateVariantsAction.php
- NumberField.vue
- MediaAsset
- CouponTargetsDialog.vue
- CategoryTree
- load
- SaveSaleEventRequest
- CategoryType
- Category
- CategoryNode
- toApiError
- AiResult
- SaveProductRequest.php
- RichTextEditor.vue
- .parent
- SmsSender
- SeoContentType
- StorefrontPostFilters
- MediaUpload
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- RefreshPricesCommand.php
- media/types.ts
- AutosavePageRequest.php
- FarazSmsDriver
- CustomersPage.vue
- AutosavePostRequest.php
- MediaPickerDialog.vue
- SaleEventEditorPage.vue
- Promotion
- Storefront/ProductResource.php
- EventItemsSection.vue
- Attribute
- PromotionVariantsDialog.vue
- Illuminate\Database\Eloquent\Relations\BelongsToMany
- SuggestSeoAction.php
- CategoryNode
- SaleEventItem
- AiException
- MoveCategoryDialog.vue
- MediaType
- ProductPickerDialog.vue
- FindCouponCustomersAction.php
- MediaException
- Post
- Illuminate\Database\Eloquent\Collection
- BrandException
- CatalogException
- Pricing
- SaleEventFilters
- SendOtpAction
- AttributeType
- ClaudeAiDriver.php
- TagsPage.vue
- flatten
- CategoryParentSelect.vue
- Catalog/CategoryController.php
- UpdateCategoryAction.php
- CategoryTreeResource
- StorefrontProductFilters
- Coupon
- PreviewToken
- VersionHistoryDialog.vue
- SeoText
- ApiExceptionRenderer.php
- TagsInput.vue
- RegisterRequest.php
- discounts/types.ts
- Settings
- SaleEvent
- CategoryException
- AppSpinner.vue
- ProductStatus
- BulkUpdateVariantsRequest
- UpdateCustomerData
- AppServiceProvider
- SettingsPage.vue
- RecordLastLogin.php
- fromLocalInput
- env.d.ts
- SavePostRequest.php
- PageStatus
- CategoryTreeResource
- AiTextGenerator
- ReorderCategoriesRequest
- Illuminate\Http\Resources\Json\AnonymousResourceCollection
- User

## God Nodes (most connected - your core abstractions)
1. `User` - 311 edges
2. `toApiError()` - 132 edges
3. `Controller` - 129 edges
4. `Product` - 84 edges
5. `ProductVariant` - 81 edges
6. `useLocale()` - 75 edges
7. `Post` - 69 edges
8. `CategoryTree` - 68 edges
9. `Page` - 67 edges
10. `Category` - 66 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --references--> `Brand`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Brand.php
- `{closure#2}()` --references--> `Attribute`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Attribute.php
- `{closure#7}()` --references--> `Option`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Option.php
- `{closure#1}()` --references--> `CategoryNode`  [EXTRACTED]
  app/Actions/Categories/ReorderCategoriesAction.php → app/Data/Categories/CategoryNode.php
- `{closure#2}()` --references--> `User`  [EXTRACTED]
  app/Actions/Coupons/FindCouponCustomersAction.php → app/Models/User.php

## Import Cycles
- None detected.

## Communities (149 total, 27 thin omitted)

### Community 0 - "Illuminate\Http\Request"
Cohesion: 0.04
Nodes (27): SessionController, DashboardController, StockMovementController, SeoSuggestionController, SessionController, BrandController, MenuController, ManagesAuthSession (+19 more)

### Community 1 - "StaffPage.vue"
Cohesion: 0.05
Nodes (54): staffApi, add(), emit, fields, form, props, { t }, emit (+46 more)

### Community 2 - "ref_vue"
Cohesion: 0.05
Nodes (47): route, { t }, { t }, closeButton, collapsed, desktopQuery, drawerOpen, isDesktop (+39 more)

### Community 3 - "MediaPage.vue"
Cohesion: 0.05
Nodes (48): assets, auth, busy, canManage, canUpload, currentFolder, deleting, deletingFolder (+40 more)

### Community 4 - "CouponEditorPage.vue"
Cohesion: 0.04
Nodes (70): CODE_MAX, CODE_MIN, CouponForm, CouponProblem, couponProblems(), emptyForm(), emptyTargets(), formFromCoupon() (+62 more)

### Community 5 - "FormAlert.vue"
Cohesion: 0.05
Nodes (51): confirm(), emit, form, props, { t }, customersApi, anonymise(), emit (+43 more)

### Community 6 - "BulkEditPage.vue"
Cohesion: 0.04
Nodes (52): countChanges(), mapErrors(), setValue(), toPayload(), activeFilterCount, all, brand, brandOptions (+44 more)

### Community 7 - "ProductEditorPage.vue"
Cohesion: 0.05
Nodes (51): clock, acceptNormalized(), alertMessage, attributes, attributesLoading, auth, brands, canManage (+43 more)

### Community 8 - "ProductVariant"
Cohesion: 0.10
Nodes (15): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#2}(), {closure#3}() (+7 more)

### Community 9 - "CustomerDetailPage.vue"
Cohesion: 0.06
Nodes (37): activityLogApi, detail, eventKey, { formatDateTime, formatRelativeTime, isRtl }, ICONS, look, missingSubject, props (+29 more)

### Community 10 - "PostEditorPage.vue"
Cohesion: 0.05
Nodes (47): acceptNormalized(), alertMessage, applyVersion(), auth, authors, autosavedAt, autosaveNow(), autosaveState (+39 more)

### Community 11 - "Controller"
Cohesion: 0.05
Nodes (28): UpdateBylineAction, RegisterCustomerAction, RequestOtpAction, SetCategoryAttributesAction, ReorderCategoriesAction, PreviewPageAction, OtpPurpose, Login (+20 more)

### Community 12 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.06
Nodes (7): {closure#2}(), {closure#14}(), PageAutosave, PageRevision, PostAutosave, PostRevision, ProductAttributeValue

### Community 13 - "Product"
Cohesion: 0.08
Nodes (10): DeleteProductAction, {closure#3}(), MatchingProductsAction, {closure#2}(), {closure#1}(), SaveProductAction, ProductController, ProductResource (+2 more)

### Community 14 - "RefreshPricesAction"
Cohesion: 0.13
Nodes (8): {closure#1}(), {closure#2}(), RefreshPricesAction, SavePromotionsAction, DeleteSaleEventAction, ReorderSaleEventsAction, PromotionSet, SaleEventSet

### Community 15 - "CustomerTokenResource"
Cohesion: 0.13
Nodes (9): IssueCustomerTokenAction, LoginWithOtpAction, ResetPasswordAction, VerifyOtpAction, VerifyRegistrationAction, LoginWithOtpController, ResetPasswordController, VerifyRegistrationController (+1 more)

### Community 16 - "MenuItem"
Cohesion: 0.08
Nodes (18): {closure#1}(), {closure#2}(), SaveMenuAction, MenuItemData, MenuItemType, Category, Page, Url (+10 more)

### Community 17 - "Exception"
Cohesion: 0.10
Nodes (5): {closure#1}(), CouponException, SaleEventException, EnsureUserIsActive, SetLocaleFromHeader

### Community 18 - "Illuminate\Support\Facades\DB"
Cohesion: 0.06
Nodes (14): EndUserSessionsAction, AnonymiseCustomerAction, {closure#1}(), SearchCustomersAction, SetCustomerStatusAction, UpdateCustomerAction, {closure#1}(), SetStaffStatusAction (+6 more)

### Community 19 - "UpdateMediaAssetRequest.php"
Cohesion: 0.22
Nodes (4): MediaAssetData, {closure#1}(), {closure#2}(), UpdateMediaAssetRequest

### Community 20 - "Tag"
Cohesion: 0.14
Nodes (9): {closure#1}(), ManageTagsAction, {closure#1}(), ResolveTagsAction, {closure#1}(), TagController, TagRequest, TagResource (+1 more)

### Community 21 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.04
Nodes (21): CustomerFilters, PasswordLoginRequest, PhoneNumberRequest, VerifyOtpRequest, ReorderBrandsRequest, SetCategoryAttributesRequest, IndexCustomersRequest, IndexActivityLogRequest (+13 more)

### Community 22 - "types/api.ts"
Cohesion: 0.12
Nodes (29): accountApi, Byline, SaveBylinePayload, MediaPicture, Page, PageAutosave, PageFilters, PageListItem (+21 more)

### Community 23 - "useLocale"
Cohesion: 0.08
Nodes (36): confirmed, display(), emit, focused, { formatNumber }, onBlur(), onFocus(), onInput() (+28 more)

### Community 24 - "OptionFormDialog.vue"
Cohesion: 0.09
Nodes (18): optionsApi, alertMessage, displayOptions, emit, fields, form, props, save() (+10 more)

### Community 25 - "ProductsPage.vue"
Cohesion: 0.07
Nodes (37): allSelected, auth, brand, brandOptions, brands, bulk(), bulkBusy, canManage (+29 more)

### Community 26 - "PageEditorPage.vue"
Cohesion: 0.05
Nodes (47): acceptNormalized(), alertMessage, applyVersion(), auth, autosavedAt, autosaveNow(), autosaveState, autosaveTimer (+39 more)

### Community 27 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.05
Nodes (13): CreateBrandAction, DeleteBrandAction, {closure#1}(), {closure#1}(), {closure#3}(), ScoreSeoAction, SeoSubject, {closure#3}() (+5 more)

### Community 28 - "AppIcon.vue"
Cohesion: 0.04
Nodes (56): CatalogItemAction, emit, { formatNumber }, menuItems, more, preview, props, { t } (+48 more)

### Community 29 - ".trim"
Cohesion: 0.13
Nodes (6): {closure#1}(), {closure#3}(), {closure#3}(), {closure#4}(), Digits, PhoneNumber

### Community 30 - "Illuminate\Database\Eloquent\Factories\HasFactory"
Cohesion: 0.04
Nodes (17): MediaUsageRole, Avatar, Banner, Content, Gallery, Image, Logo, OgImage (+9 more)

### Community 31 - "BulkProductCard.vue"
Cohesion: 0.08
Nodes (33): BulkVariant, BulkVariantPayload, discountPercent(), EditableField, FieldProblem, isEdited(), PageEdits, problems() (+25 more)

### Community 32 - "SeoPanel.vue"
Cohesion: 0.05
Nodes (59): onValueInput(), filtered, ALEF, analyzeReadability(), analyzeSeo(), characterCount(), check(), DESCRIPTION_MIN_WORDS (+51 more)

### Community 33 - "VariantsEditor.vue"
Cohesion: 0.08
Nodes (31): addOption(), available, bulk, choose(), chosen, ChosenOption, chosenOptions, comboCount (+23 more)

### Community 34 - "CouponsPage.vue"
Cohesion: 0.05
Nodes (42): couponPayloadOf(), discountLabel, summary, { t }, onAdd(), stockText(), act(), amount() (+34 more)

### Community 35 - "PostsPage.vue"
Cohesion: 0.08
Nodes (32): postsApi, auth, author, authorOptions, authors, blogTree, canManage, category (+24 more)

### Community 36 - "AttributesPage.vue"
Cohesion: 0.08
Nodes (29): actOnAttribute(), actOnOption(), attributeForm, attributes, auth, canManage, deleting, error (+21 more)

### Community 37 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.03
Nodes (79): {closure#1}(), {closure#2}(), {closure#3}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+71 more)

### Community 38 - "CategoryAttributesField.vue"
Cohesion: 0.11
Nodes (15): all, available, byId, error, id, inherited, inheritedIds, loadAll() (+7 more)

### Community 39 - "Permission"
Cohesion: 0.06
Nodes (31): Permission, AccessAdminPanel, AnonymiseCustomers, DeactivateCustomers, ManageAttributes, ManageBlogCategories, ManageBrands, ManageCategories (+23 more)

### Community 40 - "BylineDialog.vue"
Cohesion: 0.10
Nodes (18): avatar, emit, fields, form, id, loadError, loading, props (+10 more)

### Community 41 - "MenusPage.vue"
Cohesion: 0.05
Nodes (56): menusApi, categoryGroups, emit, errors, fields, id, isValidLink(), props (+48 more)

### Community 42 - "CategoryFormDialog.vue"
Cohesion: 0.09
Nodes (25): keyphraseUsedBy, attributeIds, categoryType, emit, fields, fill(), form, hasImage (+17 more)

### Community 43 - "CategoriesPage.vue"
Cohesion: 0.07
Nodes (30): act(), auth, canManage, canSeeBlog, canSeeProduct, collapsed, deleting, dragging (+22 more)

### Community 44 - "PromotionController.php"
Cohesion: 0.13
Nodes (7): FindPromotionVariantsAction, ListPromotionsAction, PromotionController, {closure#1}(), SavePromotionsRequest, PromotionPickerProductResource, PromotionResource

### Community 45 - "SaleEventVariantResource"
Cohesion: 0.15
Nodes (5): {closure#1}(), {closure#2}(), CouponCustomerLookup, SaleEventVariantResource, {closure#1}()

### Community 46 - "LoginPage.vue"
Cohesion: 0.05
Nodes (45): auth, bylineOpen, emit, groups, loggingOut, logout(), roleLabel, route (+37 more)

### Community 47 - "PagesPage.vue"
Cohesion: 0.09
Nodes (26): pagesApi, auth, canManage, error, filtered, { formatNumber, formatRelativeTime }, load(), loading (+18 more)

### Community 48 - "PromotionsPage.vue"
Cohesion: 0.03
Nodes (89): apply(), emit, endsAt, errors, field, fieldOptions, filled, { formatNumber } (+81 more)

### Community 49 - "Carbon\CarbonImmutable"
Cohesion: 0.08
Nodes (19): {closure#2}(), {closure#2}(), {closure#3}(), {closure#4}(), PagePreview, PostPreview, VariantData, PromotionRowData (+11 more)

### Community 50 - "Illuminate\Validation\Rule"
Cohesion: 0.07
Nodes (13): BylineData, BrandData, OptionData, ValueData, OptionDisplayType, Color, Text, UpdateBylineRequest (+5 more)

### Community 51 - "CouponCustomersDialog.vue"
Cohesion: 0.08
Nodes (22): asCouponCustomer(), emit, error, find(), { formatNumber, formatDate, localizeDigits }, goTo(), load(), loading (+14 more)

### Community 52 - "AppDialog.vue"
Cohesion: 0.15
Nodes (14): emit, end, invalid, props, start, { t }, close(), emit (+6 more)

### Community 53 - "products/types.ts"
Cohesion: 0.12
Nodes (23): OptionDisplayType, BulkPage, productsApi, BulkProduct, emit, error, { formatNumber, formatDateTime, localizeDigits }, load() (+15 more)

### Community 54 - "UserResource"
Cohesion: 0.20
Nodes (5): LoginWithPasswordAction, LoginWithOtpController, LoginWithPasswordController, LoginWithPasswordController, UserResource

### Community 55 - "Page"
Cohesion: 0.05
Nodes (16): DeletePageAction, ResolvePagePreviewAction, {closure#1}(), SavePageAction, SavePageAutosaveAction, {closure#2}(), MenuTargetController, PageAutosaveController (+8 more)

### Community 56 - "MediaDetailsDrawer.vue"
Cohesion: 0.09
Nodes (24): alt, asset, closeButton, cover, dirty, editable, emit, error (+16 more)

### Community 57 - "AuthException"
Cohesion: 0.12
Nodes (5): {closure#1}(), {closure#1}(), PasswordReset, AuthException, OtpCode

### Community 58 - "categories/types.ts"
Cohesion: 0.19
Nodes (12): categoriesApi, childCount, confirm(), emit, form, { formatNumber }, props, { t } (+4 more)

### Community 59 - "AttributeFormDialog.vue"
Cohesion: 0.11
Nodes (23): attributesApi, alertMessage, emit, fields, filterableOptions, form, props, save() (+15 more)

### Community 60 - "AppButton.vue"
Cohesion: 0.04
Nodes (66): brandsApi, emit, fields, form, hasLogo, logo, optional(), props (+58 more)

### Community 61 - "MediaUploadQueue.vue"
Cohesion: 0.09
Nodes (23): emit, extension, { formatNumber }, picture, props, size, { t }, size (+15 more)

### Community 62 - "UserRole"
Cohesion: 0.06
Nodes (17): AddStaffMemberAction, ChangeStaffRoleAction, GuardStaffChangeAction, RemoveStaffMemberAction, SearchStaffAction, AddStaffMemberData, StaffFilters, {closure#1}() (+9 more)

### Community 63 - "BulkUpdateVariantsAction.php"
Cohesion: 0.09
Nodes (16): UpdateBrandAction, AdjustStockAction, BulkUpdateVariantsAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), RefreshProductSearchTextAction (+8 more)

### Community 64 - "NumberField.vue"
Cohesion: 0.09
Nodes (24): add(), adding, emit, error, fieldOptions, money, props, startField (+16 more)

### Community 65 - "MediaAsset"
Cohesion: 0.08
Nodes (7): DeleteMediaAssetAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), MediaAsset, {closure#20}()

### Community 66 - "CouponTargetsDialog.vue"
Cohesion: 0.08
Nodes (24): brands, categories, chosen, confirm(), counts, coveringParent(), emit, error (+16 more)

### Community 67 - "CategoryTree"
Cohesion: 0.15
Nodes (4): CreateCategoryAction, {closure#1}(), {closure#2}(), CategoryTree

### Community 68 - "load"
Cohesion: 0.22
Nodes (13): closeDetails(), confirmDelete(), deleteFolder(), filters(), folders, load(), loadFolders(), loadMore() (+5 more)

### Community 70 - "CategoryType"
Cohesion: 0.12
Nodes (7): CategoryData, CategoryType, Blog, Product, {closure#1}(), IndexCategoriesRequest, SaveCategoryRequest

### Community 71 - "Category"
Cohesion: 0.11
Nodes (11): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+3 more)

### Community 72 - "CategoryNode"
Cohesion: 0.19
Nodes (11): CategoryNode, {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#3}(), {closure#4}() (+3 more)

### Community 73 - "toApiError"
Cohesion: 0.05
Nodes (49): saleEventPayloadOf(), saleEventsApi, act(), auth, busy, canManage, deleting, error (+41 more)

### Community 75 - "SaveProductRequest.php"
Cohesion: 0.08
Nodes (12): ProductData, SpecificationData, ProductType, Simple, Variable, {closure#1}(), {closure#11}(), {closure#4}() (+4 more)

### Community 76 - "RichTextEditor.vue"
Cohesion: 0.08
Nodes (28): charCount, editor, editorKey, embedError, embedLink, embedOpen, emit, full (+20 more)

### Community 78 - "SmsSender"
Cohesion: 0.16
Nodes (4): SmsSender, ArraySmsDriver, LogSmsDriver, SmsManager

### Community 79 - "SeoContentType"
Cohesion: 0.11
Nodes (10): SeoContentType, BlogCategory, Brand, Category, Page, Post, Product, SaleEvent (+2 more)

### Community 81 - "MediaUpload"
Cohesion: 0.09
Nodes (10): AppendMediaChunkAction, {closure#1}(), CancelMediaUploadAction, {closure#1}(), CompleteMediaUploadAction, StartMediaUploadAction, MediaUploadController, MediaUploadResource (+2 more)

### Community 82 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.10
Nodes (9): SearchPagesAction, SearchPostsAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), SearchStorefrontPostsAction, PageFilters (+1 more)

### Community 84 - "media/types.ts"
Cohesion: 0.13
Nodes (16): mediaApi, UploadItem, MediaAsset, MediaFilters, MediaFolder, MediaUploadSession, MediaUrls, MediaUsage (+8 more)

### Community 87 - "CustomersPage.vue"
Cohesion: 0.09
Nodes (26): useCustomerActions(), activate(), close(), finished(), run(), actions, error, load() (+18 more)

### Community 89 - "MediaPickerDialog.vue"
Cohesion: 0.09
Nodes (24): accept, assets, auth, canUpload, chosen, confirm(), emit, error (+16 more)

### Community 90 - "SaleEventEditorPage.vue"
Cohesion: 0.06
Nodes (40): acceptNormalized(), alertMessage, auth, banner, canManage, confirmDelete, dirty, eventWindow (+32 more)

### Community 91 - "Promotion"
Cohesion: 0.13
Nodes (6): {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), Promotion

### Community 92 - "Storefront/ProductResource.php"
Cohesion: 0.06
Nodes (26): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}(), {closure#9}(), {closure#2}() (+18 more)

### Community 93 - "EventItemsSection.vue"
Cohesion: 0.04
Nodes (71): addOpen, applyBulkDates(), applyBulkDiscount(), applyBulkLimit(), datesFor, datesRow, DISCOUNT_PROBLEMS, discountOpen (+63 more)

### Community 94 - "Attribute"
Cohesion: 0.07
Nodes (17): DeleteCatalogItemAction, ReorderListAction, {closure#1}(), SaveAttributeAction, {closure#1}(), SaveOptionAction, SyncValuesAction, ApplicableAttributeController (+9 more)

### Community 95 - "PromotionVariantsDialog.vue"
Cohesion: 0.06
Nodes (38): add(), allMatching, allOn(), brandOptions, canAdd, categoryOptions, changedFilters, ChosenVariant (+30 more)

### Community 96 - "Illuminate\Database\Eloquent\Relations\BelongsToMany"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 97 - "SuggestSeoAction.php"
Cohesion: 0.19
Nodes (4): {closure#5}(), SuggestSeoAction, SeoSuggestionContext, SeoSuggestions

### Community 98 - "CategoryNode"
Cohesion: 0.21
Nodes (8): nodes, props, tree, CategoryAction, CategoryTreeContext, categoryTreeKey, useCategoryTree(), CategoryNode

### Community 99 - "SaleEventItem"
Cohesion: 0.08
Nodes (15): VariantPrice, DiscountSource, Promotion, Sale, SaleEvent, {closure#2}(), {closure#1}(), {closure#2}() (+7 more)

### Community 100 - "AiException"
Cohesion: 0.18
Nodes (3): {closure#3}(), AiException, FakeAiDriver

### Community 101 - "MoveCategoryDialog.vue"
Cohesion: 0.18
Nodes (14): excludedParents, emit, excluded, form, move(), parentId, props, { t } (+6 more)

### Community 102 - "MediaType"
Cohesion: 0.10
Nodes (9): MediaFilters, MediaUploadData, MediaType, Document, Image, Video, IndexMediaRequest, {closure#1}() (+1 more)

### Community 103 - "ProductPickerDialog.vue"
Cohesion: 0.07
Nodes (28): allMatchingSelected, brandOptions, categoryOptions, choosable, emit, EMPTY, filtering, Filters (+20 more)

### Community 104 - "FindCouponCustomersAction.php"
Cohesion: 0.21
Nodes (4): {closure#2}(), FindCouponCustomersAction, CouponCustomerLookupController, LookupCouponCustomersRequest

### Community 106 - "Post"
Cohesion: 0.07
Nodes (11): DeletePostAction, {closure#1}(), SavePostAction, SavePostAutosaveAction, PostAutosaveController, PostController, PostRevisionController, PostListResource (+3 more)

### Community 113 - "AttributeType"
Cohesion: 0.21
Nodes (6): AttributeData, AttributeType, Boolean, Number, Select, Text

### Community 115 - "TagsPage.vue"
Cohesion: 0.11
Nodes (17): auth, canManage, confirm(), dialog, error, form, { formatNumber }, intoId (+9 more)

### Community 116 - "flatten"
Cohesion: 0.10
Nodes (19): parentPath, all, flatten(), loadChoices(), categoryOptions, all, available, byId (+11 more)

### Community 117 - "CategoryParentSelect.vue"
Cohesion: 0.29
Nodes (6): id, options, parentId, props, selected, { t }

### Community 118 - "Catalog/CategoryController.php"
Cohesion: 0.29
Nodes (3): BlogCategoryController, CategoryController, CategoryResource

### Community 121 - "StorefrontProductFilters"
Cohesion: 0.17
Nodes (4): BuildStorefrontFiltersAction, SearchStorefrontProductsAction, StorefrontProductFilters, StorefrontProductQuery

### Community 124 - "Coupon"
Cohesion: 0.05
Nodes (24): CountCouponStatesAction, DeleteCouponAction, {closure#1}(), SaveCouponAction, {closure#1}(), SearchCouponsAction, SyncCouponCustomersAction, CouponData (+16 more)

### Community 125 - "PreviewToken"
Cohesion: 0.06
Nodes (8): PreviewPostAction, ResolvePostPreviewAction, ResolveSaleEventPreviewAction, PostController, SaleEventController, PostResource, SaleEventResource, PreviewToken

### Community 127 - "VersionHistoryDialog.vue"
Cohesion: 0.15
Nodes (13): emit, { formatDateTime, formatNumber }, hasSeoFields, isCurrent, listError, loadingRevision, props, restore() (+5 more)

### Community 128 - "SeoText"
Cohesion: 0.05
Nodes (12): {closure#1}(), SeoAnalysis, SeoCheck, SeoInput, SeoRating, Bad, Good, Ok (+4 more)

### Community 132 - "TagsInput.vue"
Cohesion: 0.19
Nodes (11): tagsApi, add(), id, key(), known, onKeydown(), open, suggestions (+3 more)

### Community 137 - "discounts/types.ts"
Cohesion: 0.07
Nodes (39): couponsApi, promotionsApi, addError, adding, capped, choose(), error, { formatNumber } (+31 more)

### Community 140 - "Settings"
Cohesion: 0.08
Nodes (10): PreviewSaleEventAction, PriceDisplayCurrency, Rial, Toman, ProductController, ProductFiltersRequest, ProductResource, Setting (+2 more)

### Community 141 - "SaleEvent"
Cohesion: 0.07
Nodes (4): {closure#2}(), SaleEvent, {closure#2}(), RichText

### Community 145 - "AppSpinner.vue"
Cohesion: 0.29
Nodes (6): entry(), id, SpecificationInput, { t }, toggleValue(), values

### Community 146 - "ProductStatus"
Cohesion: 0.10
Nodes (9): ChangeProductStatusAction, ProductFilters, ProductStatus, Archived, Draft, Published, ChangeProductStatusRequest, IndexBulkProductsRequest (+1 more)

### Community 150 - "SettingsPage.vue"
Cohesion: 0.13
Nodes (18): settingsApi, auth, canManage, currencies, fields, form, load(), loadError (+10 more)

### Community 152 - "fromLocalInput"
Cohesion: 0.11
Nodes (20): today, datesLabel(), dateText(), emit, end, { formatNumber, formatDateTime }, props, shown() (+12 more)

### Community 163 - "SavePostRequest.php"
Cohesion: 0.18
Nodes (7): PostData, PostStatus, Draft, Published, {closure#1}(), {closure#2}(), SavePostRequest

### Community 164 - "PageStatus"
Cohesion: 0.23
Nodes (5): PageData, PageStatus, Draft, Published, SavePageRequest

### Community 166 - "CategoryTreeResource"
Cohesion: 0.38
Nodes (3): CategoryTreeResource, {closure#1}(), {closure#2}()

### Community 167 - "AiTextGenerator"
Cohesion: 0.24
Nodes (3): AiTextGenerator, AiManager, ClaudeAiDriver

### Community 170 - "Illuminate\Http\Resources\Json\AnonymousResourceCollection"
Cohesion: 0.09
Nodes (15): SearchActivityLogAction, SearchProductsAction, FindEventVariantsAction, FindOverridingEventsAction, SaveSaleEventAction, SearchSaleEventsAction, ActivityLogController, BulkProductController (+7 more)

### Community 172 - "User"
Cohesion: 0.06
Nodes (19): {closure#1}(), ReorderBrandsAction, DeleteCategoryAction, DeleteMediaFolderAction, ManageMediaFolderAction, MoveMediaAssetsAction, SearchMediaAction, UpdateMediaAssetAction (+11 more)

## Knowledge Gaps
- **96 isolated node(s):** `Text`, `Number`, `Boolean`, `Select`, `Product` (+91 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 1789 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **27 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Illuminate\Http\Request`, `Controller`, `Settings`, `Product`, `RefreshPricesAction`, `CustomerTokenResource`, `MenuItem`, `Exception`, `Illuminate\Support\Facades\DB`, `ProductStatus`, `Tag`, `SaleEvent`, `UpdateCustomerData`, `RecordLastLogin.php`, `Illuminate\Database\Eloquent\Model`, `.trim`, `Illuminate\Database\Eloquent\Factories\HasFactory`, `SavePostRequest.php`, `Illuminate\Database\Eloquent\Builder`, `Permission`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `PromotionController.php`, `SaleEventVariantResource`, `Carbon\CarbonImmutable`, `Illuminate\Validation\Rule`, `UserResource`, `Page`, `AuthException`, `UserRole`, `BulkUpdateVariantsAction.php`, `MediaAsset`, `CategoryTree`, `Category`, `MediaUpload`, `Promotion`, `Attribute`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `SuggestSeoAction.php`, `FindCouponCustomersAction.php`, `Post`, `Illuminate\Database\Eloquent\Collection`, `UpdateCategoryAction.php`, `Coupon`, `PreviewToken`?**
  _High betweenness centrality (0.105) - this node is a cross-community bridge._
- **What connects `Text`, `Number`, `Boolean` to the rest of the system?**
  _96 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.038492381716118684 - nodes in this community are weakly interconnected._
- **Why does `Controller` connect `Controller` to `Illuminate\Http\Request`, `Settings`, `Product`, `CustomerTokenResource`, `MenuItem`, `Illuminate\Support\Facades\DB`, `Tag`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `User`, `PromotionController.php`, `UserResource`, `Page`, `UserRole`, `MediaUpload`, `Attribute`, `FindCouponCustomersAction.php`, `Post`, `Catalog/CategoryController.php`, `Coupon`, `PreviewToken`?**
  _High betweenness centrality (0.017) - this node is a cross-community bridge._
- **Should `StaffPage.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.05357142857142857 - nodes in this community are weakly interconnected._
- **Why does `PreviewToken` connect `PreviewToken` to `Carbon\CarbonImmutable`, `Controller`, `Settings`, `Page`?**
  _High betweenness centrality (0.016) - this node is a cross-community bridge._
- **Should `ref_vue` be split into smaller, more focused modules?**
  _Cohesion score 0.04794210764360018 - nodes in this community are weakly interconnected._