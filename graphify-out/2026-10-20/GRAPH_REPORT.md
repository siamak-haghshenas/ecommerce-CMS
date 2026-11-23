# Graph Report - ecommerceCms  (2026-10-20)

## Corpus Check
- 726 files · ~211,867 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 5650 nodes · 13735 edges · 181 communities (148 shown, 33 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 144 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `5cf2da8e`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\Http\Request
- AppButton.vue
- useLocale.ts
- MediaPage.vue
- CouponEditorPage.vue
- ref_vue
- BulkEditPage.vue
- ProductEditorPage.vue
- ProductVariant
- errors.ts
- PostEditorPage.vue
- Controller
- Illuminate\Database\Eloquent\Model
- ShippingMethodEditorPage.vue
- Promotion
- CustomerTokenResource
- MenuItem
- Exception
- User
- UpdateMediaAssetRequest
- Tag
- Illuminate\Foundation\Http\FormRequest
- useLocale
- OptionFormDialog.vue
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
- TagsPage.vue
- MenusPage.vue
- CategoryFormDialog.vue
- CategoriesPage.vue
- Illuminate\Http\Resources\Json\AnonymousResourceCollection
- CouponTerms
- LoginPage.vue
- PagesPage.vue
- PromotionsPage.vue
- Carbon\CarbonImmutable
- HasSlugField
- CommentsPage.vue
- ref_vue_i18n
- ShippingMethodsPage.vue
- LoginWithOtpAction
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
- MediaFolder
- CouponTargetsDialog.vue
- CartCatalog
- formatNumber
- SavePostRequest.php
- CategoryTree
- Category
- ShippingTestBox.vue
- toApiError
- AiResult
- Illuminate\Validation\Validator
- RichTextEditor.vue
- .parent
- SmsSender
- SeoContentType
- CommentStatus
- MediaUpload
- BuildStorefrontFiltersAction.php
- RefreshPricesCommand.php
- Cart
- coupons.ts
- FarazSmsDriver
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
- AiTextGenerator
- ShippingOption
- MediaType
- ProductPickerDialog.vue
- CouponCalculator.php
- MediaException
- Post
- discounts/types.ts
- CouponException
- CatalogException
- CouponSampleOrder.vue
- SaleEvent
- SendOtpAction
- VersionHistoryDialog.vue
- ShippingRateData
- CouponCustomersDialog.vue
- CourierCitiesDialog.vue
- flatten
- CartLine
- SeoCheckList.vue
- PriceDisplayCurrency
- CouponItem
- CouponResource
- promotions.ts
- SaveCouponRequest.php
- Products/ProductController.php
- AiException
- ProductFiltersRequest
- SeoText
- ApiExceptionRenderer.php
- CustomerFilters
- PreviewToken
- products/types.ts
- attributes/types.ts
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
- StorefrontProductQuery
- PageResource
- comments/api.ts
- Illuminate\Database\Eloquent\Collection
- UpdateCustomerData
- AppServiceProvider
- SettingsPage.vue
- RecordLastLogin.php
- fromLocalInput
- env.d.ts
- HasSeo
- ValuesEditor.vue
- comments.ts
- ResolveTagsAction
- HasComments
- SetLimitDialog.vue
- queryString
- StoreCommentRequest
- StorefrontPostFilters
- BulkNumberInput.vue
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- shipping/api.ts
- CartSummary
- BulkUpdateVariantsRequest
- save
- ActivityResource.php
- BrandException
- CommentException
- Illuminate\Support\Facades\DB
- moderate
- queryString
- hasFiles
- menus/api.ts
- SuggestSeoRequest
- RegisterCustomerData
- AuthenticateIfTokenSent.php

## God Nodes (most connected - your core abstractions)
1. `User` - 364 edges
2. `Controller` - 159 edges
3. `toApiError()` - 150 edges
4. `ProductVariant` - 98 edges
5. `Product` - 95 edges
6. `useLocale()` - 82 edges
7. `Post` - 76 edges
8. `CategoryTree` - 70 edges
9. `Category` - 67 edges
10. `Page` - 67 edges

## Surprising Connections (you probably didn't know these)
- `{closure#2}()` --calls--> `SeoText`  [INFERRED]
  app/Support/Seo/SeoAnalyzer.php → app/Support/Seo/SeoText.php
- `{closure#1}()` --references--> `User`  [EXTRACTED]
  app/Actions/Auth/RegisterCustomerAction.php → app/Models/User.php
- `{closure#1}()` --references--> `Brand`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Brand.php
- `{closure#2}()` --references--> `Attribute`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Attribute.php
- `{closure#7}()` --references--> `Option`  [EXTRACTED]
  app/Actions/Catalog/BuildStorefrontFiltersAction.php → app/Models/Option.php

## Import Cycles
- None detected.

## Communities (181 total, 33 thin omitted)

### Community 0 - "Illuminate\Http\Request"
Cohesion: 0.04
Nodes (31): CouponSimulationController, PageRevisionController, SessionController, BrandController, CouponCheckController, CouponCustomerResource, DashboardResource, PageAutosaveResource (+23 more)

### Community 1 - "AppButton.vue"
Cohesion: 0.04
Nodes (75): confirm(), emit, form, props, { t }, childCount, confirm(), emit (+67 more)

### Community 2 - "useLocale.ts"
Cohesion: 0.07
Nodes (31): closeButton, collapsed, desktopQuery, drawerOpen, isDesktop, menuButton, menuExpanded, route (+23 more)

### Community 3 - "MediaPage.vue"
Cohesion: 0.05
Nodes (34): assets, auth, busy, canManage, canUpload, currentFolder, deleting, deletingFolder (+26 more)

### Community 4 - "CouponEditorPage.vue"
Cohesion: 0.04
Nodes (51): couponProblems(), generateCode(), mergeLinks(), normalizeCode(), addCustomers(), alertMessage, appliesOptions, attempted (+43 more)

### Community 5 - "ref_vue"
Cohesion: 0.04
Nodes (69): customersApi, anonymise(), confirmed, emit, form, { localizeDigits }, props, { t } (+61 more)

### Community 6 - "BulkEditPage.vue"
Cohesion: 0.04
Nodes (52): countChanges(), mapErrors(), setValue(), toPayload(), activeFilterCount, all, brand, brandOptions (+44 more)

### Community 7 - "ProductEditorPage.vue"
Cohesion: 0.05
Nodes (51): clock, acceptNormalized(), alertMessage, attributes, attributesLoading, auth, brands, canManage (+43 more)

### Community 8 - "ProductVariant"
Cohesion: 0.05
Nodes (34): AdjustStockAction, {closure#1}(), BulkUpdateVariantsAction, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}() (+26 more)

### Community 9 - "errors.ts"
Cohesion: 0.05
Nodes (51): accountApi, SaveBylinePayload, activityLogApi, detail, eventKey, { formatDateTime, formatRelativeTime, isRtl }, ICONS, look (+43 more)

### Community 10 - "PostEditorPage.vue"
Cohesion: 0.05
Nodes (48): acceptNormalized(), alertMessage, applyVersion(), auth, authors, autosavedAt, autosaveNow(), autosaveState (+40 more)

### Community 11 - "Controller"
Cohesion: 0.05
Nodes (26): {closure#1}(), RegisterCustomerAction, RequestOtpAction, SetCategoryAttributesAction, OtpPurpose, Login, Register, LoginWithOtpController (+18 more)

### Community 12 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.03
Nodes (21): {closure#2}(), {closure#1}(), {closure#14}(), AiRequest, {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}() (+13 more)

### Community 13 - "ShippingMethodEditorPage.vue"
Cohesion: 0.05
Nodes (43): addCities(), alertMessage, attempted, auth, canManage, chosenCityIds, citiesError, citiesOpen (+35 more)

### Community 14 - "Promotion"
Cohesion: 0.06
Nodes (15): {closure#1}(), {closure#2}(), {closure#1}(), VariantPrice, DiscountSource, Promotion, Sale, SaleEvent (+7 more)

### Community 15 - "CustomerTokenResource"
Cohesion: 0.17
Nodes (7): IssueCustomerTokenAction, LoginWithOtpController, LoginWithPasswordController, VerifyRegistrationController, PasswordLoginRequest, VerifyOtpRequest, CustomerTokenResource

### Community 16 - "MenuItem"
Cohesion: 0.08
Nodes (18): {closure#1}(), {closure#2}(), SaveMenuAction, MenuItemData, MenuItemType, Category, Page, Url (+10 more)

### Community 17 - "Exception"
Cohesion: 0.09
Nodes (5): {closure#1}(), SaleEventException, ShippingException, EnsureUserIsActive, SetLocaleFromHeader

### Community 18 - "User"
Cohesion: 0.05
Nodes (20): UpdateBylineAction, ReorderBrandsAction, DeleteCategoryAction, DeleteCouponAction, SavePageAutosaveAction, DeleteProductAction, DeleteShippingMethodAction, ListShippingMethodsAction (+12 more)

### Community 20 - "Tag"
Cohesion: 0.20
Nodes (7): {closure#1}(), ManageTagsAction, {closure#1}(), TagController, TagRequest, TagResource, Tag

### Community 21 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.03
Nodes (23): PasswordLoginRequest, PhoneNumberRequest, VerifyOtpRequest, ReorderBrandsRequest, SetCategoryAttributesRequest, BulkDeleteCommentsRequest, UpdateCustomerRequest, IndexActivityLogRequest (+15 more)

### Community 23 - "useLocale"
Cohesion: 0.08
Nodes (37): useLocale(), localizeDigits(), setLocale(), toggleLocale(), describedBy, display(), focused, { formatNumber } (+29 more)

### Community 24 - "OptionFormDialog.vue"
Cohesion: 0.18
Nodes (10): alertMessage, displayOptions, emit, fields, form, props, save(), slugEdited (+2 more)

### Community 25 - "ProductsPage.vue"
Cohesion: 0.07
Nodes (38): allSelected, auth, brand, brandOptions, brands, bulk(), bulkBusy, canManage (+30 more)

### Community 26 - "PageEditorPage.vue"
Cohesion: 0.05
Nodes (46): acceptNormalized(), alertMessage, applyVersion(), auth, autosavedAt, autosaveNow(), autosaveState, autosaveTimer (+38 more)

### Community 27 - "Product"
Cohesion: 0.05
Nodes (20): CreateBrandAction, DeleteBrandAction, UpdateBrandAction, UpdateCategoryAction, {closure#3}(), MatchingProductsAction, {closure#1}(), {closure#2}() (+12 more)

### Community 28 - "AppIcon.vue"
Cohesion: 0.04
Nodes (47): { t }, auth, bylineOpen, comments, emit, { formatNumber }, groups, loggingOut (+39 more)

### Community 29 - "Comment"
Cohesion: 0.07
Nodes (15): AddCommentAction, CountCommentStatesAction, DeleteCommentsAction, FindCommentableAction, ModerateCommentsAction, ReplyToCommentAction, SearchCommentsAction, CommentController (+7 more)

### Community 30 - "MediaUsageRole"
Cohesion: 0.08
Nodes (13): MediaUsageRole, Avatar, Banner, Content, Gallery, Image, Logo, OgImage (+5 more)

### Community 31 - "BulkProductCard.vue"
Cohesion: 0.08
Nodes (33): BulkVariant, BulkVariantPayload, discountPercent(), EditableField, FieldProblem, isEdited(), PageEdits, problems() (+25 more)

### Community 32 - "SeoPanel.vue"
Cohesion: 0.07
Nodes (31): META_DESCRIPTION_MAX, META_DESCRIPTION_MIN, SeoRating, TITLE_MAX, TITLE_MIN, seoApi, { formatNumber }, keyphrase (+23 more)

### Community 33 - "VariantsEditor.vue"
Cohesion: 0.08
Nodes (31): addOption(), available, bulk, choose(), chosen, ChosenOption, chosenOptions, comboCount (+23 more)

### Community 34 - "saleEvents.ts"
Cohesion: 0.11
Nodes (24): addMissingVariants(), applyBulkDiscount(), note(), applicable, apply(), emit, field, fieldOptions (+16 more)

### Community 35 - "PostsPage.vue"
Cohesion: 0.08
Nodes (32): auth, author, authorOptions, authors, blogTree, canManage, category, error (+24 more)

### Community 36 - "AttributesPage.vue"
Cohesion: 0.08
Nodes (29): actOnAttribute(), actOnOption(), attributeForm, attributes, auth, canManage, deleting, error (+21 more)

### Community 37 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.03
Nodes (92): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}() (+84 more)

### Community 38 - "CategoryAttributesField.vue"
Cohesion: 0.11
Nodes (15): all, available, byId, error, id, inherited, inheritedIds, loadAll() (+7 more)

### Community 39 - "Permission"
Cohesion: 0.05
Nodes (34): Permission, AccessAdminPanel, AnonymiseCustomers, DeactivateCustomers, ManageAttributes, ManageBlogCategories, ManageBrands, ManageCategories (+26 more)

### Community 40 - "TagsPage.vue"
Cohesion: 0.08
Nodes (26): dashboardApi, auth, canSeeFigures, error, { formatNumber, formatDate }, load(), loading, summary (+18 more)

### Community 41 - "MenusPage.vue"
Cohesion: 0.07
Nodes (28): actSub(), actTop(), addingUnder, addItem(), auth, canManage, dialogOpen, editing (+20 more)

### Community 42 - "CategoryFormDialog.vue"
Cohesion: 0.08
Nodes (28): keyphraseUsedBy, attributeIds, categoryType, emit, excludedParents, fields, fill(), form (+20 more)

### Community 43 - "CategoriesPage.vue"
Cohesion: 0.07
Nodes (33): emit, move(), act(), auth, canManage, canSeeBlog, canSeeProduct, collapsed (+25 more)

### Community 44 - "Illuminate\Http\Resources\Json\AnonymousResourceCollection"
Cohesion: 0.06
Nodes (17): ListPromotionsAction, SavePromotionsAction, FindEventVariantsAction, StockMovementController, PromotionController, SaleEventOrderController, SaleEventVariantController, ProvinceController (+9 more)

### Community 45 - "CouponTerms"
Cohesion: 0.10
Nodes (12): SimulateCouponAction, SimulateShippingAction, CouponTerms, CouponAppliesTo, All, Selected, CouponType, Fixed (+4 more)

### Community 46 - "LoginPage.vue"
Cohesion: 0.08
Nodes (31): logout(), adminAuthApi, auth, code, codeLength, countdown, finishLogin(), form (+23 more)

### Community 47 - "PagesPage.vue"
Cohesion: 0.09
Nodes (26): pagesApi, auth, canManage, error, filtered, { formatNumber, formatRelativeTime }, load(), loading (+18 more)

### Community 48 - "PromotionsPage.vue"
Cohesion: 0.05
Nodes (49): add(), addOpen, applyDates(), auth, bulkOpen, canManage, counts, datesFor (+41 more)

### Community 49 - "Carbon\CarbonImmutable"
Cohesion: 0.07
Nodes (18): {closure#2}(), {closure#2}(), {closure#3}(), PostPreview, VariantData, PromotionRowData, SavePromotionsData, SaleEventData (+10 more)

### Community 50 - "HasSlugField"
Cohesion: 0.07
Nodes (17): AttributeData, OptionData, ValueData, AttributeType, Boolean, Number, Select, Text (+9 more)

### Community 51 - "CommentsPage.vue"
Cohesion: 0.06
Nodes (27): ACTION_ICON, auth, badge, bulkIds, busy, deleting, error, filtering (+19 more)

### Community 52 - "ref_vue_i18n"
Cohesion: 0.03
Nodes (62): route, { t }, avatar, emit, fields, form, id, loadError (+54 more)

### Community 53 - "ShippingMethodsPage.vue"
Cohesion: 0.06
Nodes (40): shippingPayloadOf(), act(), auth, busy, canManage, creating, deleting, error (+32 more)

### Community 54 - "LoginWithOtpAction"
Cohesion: 0.14
Nodes (7): LoginWithOtpAction, ResetPasswordAction, VerifyOtpAction, VerifyRegistrationAction, PasswordReset, ResetPasswordController, ResetPasswordRequest

### Community 55 - "Page"
Cohesion: 0.05
Nodes (15): DeletePageAction, {closure#1}(), SavePageAction, PageAutosaveData, PageData, PageStatus, Draft, Published (+7 more)

### Community 56 - "MediaPickerDialog.vue"
Cohesion: 0.03
Nodes (92): mediaApi, emit, extension, { formatNumber }, picture, props, size, { t } (+84 more)

### Community 57 - "AuthException"
Cohesion: 0.12
Nodes (5): LoginWithPasswordAction, {closure#1}(), {closure#1}(), AuthException, OtpCode

### Community 58 - "analysis.ts"
Cohesion: 0.17
Nodes (21): ALEF, analyzeReadability(), analyzeSeo(), characterCount(), check(), DESCRIPTION_MIN_WORDS, normalize(), occurrences() (+13 more)

### Community 59 - "AttributeFormDialog.vue"
Cohesion: 0.16
Nodes (12): alertMessage, emit, fields, filterableOptions, form, props, save(), slugEdited (+4 more)

### Community 60 - "BrandsPage.vue"
Cohesion: 0.04
Nodes (59): brandsApi, emit, fields, form, hasLogo, logo, optional(), props (+51 more)

### Community 61 - "CouponQuote"
Cohesion: 0.10
Nodes (8): CartCoupon, CheckedCoupon, CouponQuote, ShippingBasket, SimulatedShipping, CouponProblem, MinOrder, NoEligibleItems

### Community 62 - "UserRole"
Cohesion: 0.09
Nodes (10): AddStaffMemberData, StaffFilters, {closure#1}(), UserRole, Admin, Author, Customer, SuperAdmin (+2 more)

### Community 63 - "PromotionBulkDialog.vue"
Cohesion: 0.10
Nodes (23): apply(), emit, endsAt, errors, field, fieldOptions, filled, { formatNumber } (+15 more)

### Community 64 - "MediaAsset"
Cohesion: 0.07
Nodes (6): DeleteMediaAssetAction, UpdateMediaAssetAction, MediaAssetData, {closure#1}(), MediaAsset, {closure#21}()

### Community 65 - "MediaFolder"
Cohesion: 0.09
Nodes (10): DeleteMediaFolderAction, ManageMediaFolderAction, MoveMediaAssetsAction, SearchMediaAction, MediaAssetController, MediaFolderController, MediaFolderRequest, MediaAssetResource (+2 more)

### Community 66 - "CouponTargetsDialog.vue"
Cohesion: 0.08
Nodes (25): brands, categories, chosen, confirm(), counts, coveringParent(), emit, error (+17 more)

### Community 67 - "CartCatalog"
Cohesion: 0.11
Nodes (10): {closure#1}(), {closure#1}(), {closure#1}(), CartLineProblem, ExceedsLimit, OutOfStock, PriceChanged, Unavailable (+2 more)

### Community 68 - "formatNumber"
Cohesion: 0.08
Nodes (29): filtered, seoLabel, results, newCustomersTrend, onAdd(), stockText(), amount(), condition() (+21 more)

### Community 69 - "SavePostRequest.php"
Cohesion: 0.09
Nodes (10): BylineData, PostData, PostStatus, Draft, Published, UpdateBylineRequest, {closure#1}(), {closure#2}() (+2 more)

### Community 70 - "CategoryTree"
Cohesion: 0.04
Nodes (36): {closure#1}(), {closure#2}(), ReorderCategoriesAction, CategoryData, CategoryNode, CategoryType, Blog, Product (+28 more)

### Community 71 - "Category"
Cohesion: 0.06
Nodes (19): CreateCategoryAction, {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}() (+11 more)

### Community 72 - "ShippingTestBox.vue"
Cohesion: 0.08
Nodes (25): addError, adding, choose(), cityId, cityName, cityOptions, couponCode, error (+17 more)

### Community 73 - "toApiError"
Cohesion: 0.05
Nodes (60): load(), removeReply(), saveReply(), useCustomerActions(), activate(), close(), finished(), run() (+52 more)

### Community 74 - "AiResult"
Cohesion: 0.14
Nodes (4): AiPrompt, AiResult, ClaudeAiDriver, {closure#1}()

### Community 75 - "Illuminate\Validation\Validator"
Cohesion: 0.07
Nodes (15): ProductData, SpecificationData, ProductType, Simple, Variable, {closure#1}(), {closure#1}(), {closure#11}() (+7 more)

### Community 76 - "RichTextEditor.vue"
Cohesion: 0.08
Nodes (28): charCount, editor, editorKey, embedError, embedLink, embedOpen, emit, full (+20 more)

### Community 78 - "SmsSender"
Cohesion: 0.16
Nodes (4): SmsSender, ArraySmsDriver, LogSmsDriver, SmsManager

### Community 79 - "SeoContentType"
Cohesion: 0.13
Nodes (8): SeoContentType, BlogCategory, Brand, Category, Page, Post, Product, SaleEvent

### Community 80 - "CommentStatus"
Cohesion: 0.08
Nodes (13): CommentFilters, ModerationData, CommentableType, Post, Product, CommentStatus, Approved, Pending (+5 more)

### Community 81 - "MediaUpload"
Cohesion: 0.09
Nodes (9): AppendMediaChunkAction, CancelMediaUploadAction, {closure#1}(), CompleteMediaUploadAction, StartMediaUploadAction, MediaUploadController, MediaUploadResource, MediaUpload (+1 more)

### Community 82 - "BuildStorefrontFiltersAction.php"
Cohesion: 0.16
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}(), {closure#2}(), AttributeValue

### Community 84 - "Cart"
Cohesion: 0.06
Nodes (20): AddCartItemAction, ApplyCartCouponAction, ClearCartAction, MergeCartsAction, RemoveCartCouponAction, RemoveCartItemAction, ResolveCartAction, SetCartItemQuantityAction (+12 more)

### Community 85 - "coupons.ts"
Cohesion: 0.13
Nodes (22): CODE_MAX, CODE_MIN, CouponForm, CouponProblem, emptyForm(), emptyTargets(), formFromCoupon(), ids() (+14 more)

### Community 87 - "ShippingTerms"
Cohesion: 0.13
Nodes (8): ShippingMethodData, ShippingTerms, ShippingMethodType, Courier, Volume, Weight, {closure#2}(), HasShippingMethodFields

### Community 88 - "MediaPicture"
Cohesion: 0.17
Nodes (23): Byline, MediaPicture, Page, PageAutosave, PageFilters, PageListItem, PagePreview, PageRevision (+15 more)

### Community 89 - "SeoAnalyzer.php"
Cohesion: 0.11
Nodes (9): {closure#1}(), SeoAnalysis, SeoCheck, SeoRating, Bad, Good, Ok, {closure#2}() (+1 more)

### Community 90 - "SaleEventEditorPage.vue"
Cohesion: 0.06
Nodes (40): acceptNormalized(), alertMessage, auth, banner, canManage, confirmDelete, dirty, eventWindow (+32 more)

### Community 91 - "RatesEditor.vue"
Cohesion: 0.11
Nodes (23): { formatNumber }, lastEnd, money, props, rates, starts, { t }, unit (+15 more)

### Community 92 - "OptionValue"
Cohesion: 0.11
Nodes (16): {closure#9}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}() (+8 more)

### Community 93 - "EventItemsSection.vue"
Cohesion: 0.06
Nodes (37): addOpen, applyBulkDates(), applyBulkLimit(), datesFor, datesRow, DISCOUNT_PROBLEMS, discountOpen, errorText() (+29 more)

### Community 94 - "Attribute"
Cohesion: 0.07
Nodes (16): DeleteCatalogItemAction, ReorderListAction, {closure#1}(), SaveAttributeAction, {closure#1}(), SaveOptionAction, SyncValuesAction, ApplicableAttributeController (+8 more)

### Community 95 - "PromotionVariantsDialog.vue"
Cohesion: 0.06
Nodes (39): add(), allMatching, allOn(), brandOptions, canAdd, categoryOptions, changedFilters, ChosenVariant (+31 more)

### Community 96 - "Coupon"
Cohesion: 0.08
Nodes (12): {closure#1}(), SaveCouponAction, SyncCouponCustomersAction, SyncCouponTargetsAction, {closure#1}(), {closure#4}(), {closure#1}(), {closure#1}() (+4 more)

### Community 97 - "SuggestSeoAction.php"
Cohesion: 0.19
Nodes (5): {closure#3}(), {closure#5}(), SuggestSeoAction, SeoSuggestionContext, SeoSuggestions

### Community 98 - "CategoryRow.vue"
Cohesion: 0.10
Nodes (23): categoriesApi, collapsed, { formatNumber }, hasChildren, menuItems, props, { t }, tree (+15 more)

### Community 99 - "ShippingMethod"
Cohesion: 0.14
Nodes (5): {closure#1}(), QuotedShipping, ShippingQuote, {closure#1}(), ShippingMethod

### Community 100 - "AiTextGenerator"
Cohesion: 0.18
Nodes (3): AiTextGenerator, AiManager, FakeAiDriver

### Community 101 - "ShippingOption"
Cohesion: 0.14
Nodes (11): ShippingOption, ShippingFreeReason, Coupon, FreeOver, ShippingUnavailableReason, BelowMinOrder, CityNotServed, Inactive (+3 more)

### Community 102 - "MediaType"
Cohesion: 0.11
Nodes (9): MediaFilters, MediaUploadData, MediaType, Document, Image, Video, IndexMediaRequest, {closure#1}() (+1 more)

### Community 103 - "ProductPickerDialog.vue"
Cohesion: 0.07
Nodes (28): allMatchingSelected, brandOptions, categoryOptions, choosable, emit, EMPTY, filtering, Filters (+20 more)

### Community 104 - "CouponCalculator.php"
Cohesion: 0.16
Nodes (9): CouponQuoteLine, CouponLineReason, Discounted, Excluded, NotTargeted, {closure#1}(), {closure#2}(), {closure#3}() (+1 more)

### Community 106 - "Post"
Cohesion: 0.04
Nodes (18): DeletePostAction, PreviewPostAction, ResolvePostPreviewAction, {closure#1}(), SavePostAction, SavePostAutosaveAction, SearchStorefrontPostsAction, PostAutosaveData (+10 more)

### Community 107 - "discounts/types.ts"
Cohesion: 0.14
Nodes (22): promotionsApi, Coupon, CouponDetail, CouponFilters, CouponLineReason, CouponList, CouponSimulation, DiscountType (+14 more)

### Community 110 - "CouponSampleOrder.vue"
Cohesion: 0.09
Nodes (19): couponsApi, addError, adding, capped, choose(), discountLabel, error, { formatNumber } (+11 more)

### Community 111 - "SaleEvent"
Cohesion: 0.04
Nodes (24): RefreshPricesAction, DeleteSaleEventAction, FindOverridingEventsAction, ReorderSaleEventsAction, {closure#2}(), {closure#4}(), SaveSaleEventAction, {closure#1}() (+16 more)

### Community 113 - "VersionHistoryDialog.vue"
Cohesion: 0.09
Nodes (21): choose(), emit, { formatDateTime, formatNumber }, hasSeoFields, isCurrent, listError, loadingRevision, loadList() (+13 more)

### Community 114 - "ShippingRateData"
Cohesion: 0.15
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), ShippingRateData, {closure#1}(), {closure#1}(), {closure#2}(), ShippingRate (+1 more)

### Community 115 - "CouponCustomersDialog.vue"
Cohesion: 0.10
Nodes (17): asCouponCustomer(), emit, error, { formatNumber, formatDate, localizeDigits }, goTo(), load(), loading, page (+9 more)

### Community 116 - "CourierCitiesDialog.vue"
Cohesion: 0.12
Nodes (16): allPicked, confirm(), emit, { formatNumber }, load(), loadError, normalize(), picked (+8 more)

### Community 117 - "flatten"
Cohesion: 0.07
Nodes (26): parentName, parentPath, id, options, parentId, props, selected, { t } (+18 more)

### Community 118 - "CartLine"
Cohesion: 0.21
Nodes (8): CartLine, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#1}()

### Community 119 - "SeoCheckList.vue"
Cohesion: 0.29
Nodes (6): SeoCheck, { formatNumber }, items, ORDER, props, { t }

### Community 120 - "PriceDisplayCurrency"
Cohesion: 0.20
Nodes (5): PriceDisplayCurrency, Rial, Toman, Setting, {closure#1}()

### Community 121 - "CouponItem"
Cohesion: 0.17
Nodes (7): {closure#1}(), {closure#2}(), {closure#1}(), CouponItem, {closure#1}(), {closure#2}(), CartItem

### Community 122 - "CouponResource"
Cohesion: 0.14
Nodes (6): CountCouponStatesAction, SearchCouponsAction, CouponFilters, CouponController, IndexCouponsRequest, CouponResource

### Community 123 - "promotions.ts"
Cohesion: 0.23
Nodes (15): changedCount, fill(), problemOf(), save(), applyBulk(), isChanged(), mapErrors(), outOfRange() (+7 more)

### Community 124 - "SaveCouponRequest.php"
Cohesion: 0.08
Nodes (10): CouponData, CouponCustomerScope, All, Selected, {closure#1}(), {closure#3}(), SaveCouponRequest, SimulateCouponRequest (+2 more)

### Community 125 - "Products/ProductController.php"
Cohesion: 0.11
Nodes (8): SearchProductsAction, BulkProductController, ProductController, IndexBulkProductsRequest, IndexProductsRequest, BulkProductResource, ProductListResource, ProductResource

### Community 132 - "products/types.ts"
Cohesion: 0.11
Nodes (24): OptionDisplayType, BulkPage, tagsApi, BulkProduct, add(), id, key(), known (+16 more)

### Community 133 - "attributes/types.ts"
Cohesion: 0.22
Nodes (11): attributesApi, optionsApi, Attribute, ATTRIBUTE_TYPES, AttributeType, AttributeValue, CategoryAttributes, Option (+3 more)

### Community 134 - "ShippingMethodResource"
Cohesion: 0.27
Nodes (4): SaveShippingMethodAction, ShippingMethodController, SaveShippingMethodRequest, ShippingMethodResource

### Community 135 - "MenuItemDialog.vue"
Cohesion: 0.21
Nodes (11): categoryGroups, emit, errors, fields, id, isValidLink(), props, submit() (+3 more)

### Community 136 - ".trim"
Cohesion: 0.05
Nodes (10): ReplyToCommentRequest, {closure#2}(), {closure#7}(), SimulateShippingRequest, ApplyCartCouponRequest, CheckCouponRequest, QuoteShippingRequest, {closure#4}() (+2 more)

### Community 137 - "CouponsPage.vue"
Cohesion: 0.06
Nodes (33): today, couponPayloadOf(), { t }, act(), auth, busy, canManage, copyCode() (+25 more)

### Community 138 - "ShippingCalculator.php"
Cohesion: 0.17
Nodes (8): ShippingBasketLine, ShippingSimulationController, {closure#1}(), ShippingSimulationResource, {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 139 - "load"
Cohesion: 0.22
Nodes (13): closeDetails(), confirmDelete(), deleteFolder(), filters(), folders, load(), loadFolders(), loadMore() (+5 more)

### Community 140 - "Settings"
Cohesion: 0.12
Nodes (6): PreviewPageAction, PreviewSaleEventAction, PagePreview, SaleEventPreview, ProductController, Settings

### Community 141 - "RichText"
Cohesion: 0.06
Nodes (5): AutosavePageRequest, {closure#1}(), SaveSaleEventRequest, {closure#2}(), RichText

### Community 143 - "MenuItemRow.vue"
Cohesion: 0.23
Nodes (10): actions, detail, emit, props, { t }, TYPE_ICONS, displayLabel(), MenuRow (+2 more)

### Community 144 - "StorefrontProductQuery"
Cohesion: 0.17
Nodes (4): BuildStorefrontFiltersAction, SearchStorefrontProductsAction, StorefrontProductFilters, StorefrontProductQuery

### Community 145 - "PageResource"
Cohesion: 0.25
Nodes (3): ResolvePagePreviewAction, PageController, PageResource

### Community 146 - "comments/api.ts"
Cohesion: 0.33
Nodes (8): commentsApi, Comment, CommentableType, CommentFilters, CommentList, CommentStatus, ModeratedStatus, StaffRef

### Community 147 - "Illuminate\Database\Eloquent\Collection"
Cohesion: 0.17
Nodes (4): GetDashboardSummaryAction, ListProvincesAction, DashboardSummary, CategoryAttributes

### Community 150 - "SettingsPage.vue"
Cohesion: 0.12
Nodes (18): settingsApi, auth, canManage, currencies, fields, form, load(), loadError (+10 more)

### Community 152 - "fromLocalInput"
Cohesion: 0.36
Nodes (8): moderationNote(), datesLabel(), dateText(), shown(), dateText(), shownDate(), fromLocalInput(), formatDateTime()

### Community 155 - "ValuesEditor.vue"
Cohesion: 0.18
Nodes (8): add(), id, onValueInput(), props, rows, { t }, ValueInput, valueRow

### Community 156 - "comments.ts"
Cohesion: 0.22
Nodes (9): BULK_MAX, pageSelection(), REASON_MAX, REPLY_MAX, STATUSES, statusesFor(), toggleId(), togglePage() (+1 more)

### Community 159 - "SetLimitDialog.vue"
Cohesion: 0.22
Nodes (10): apply(), emit, { formatNumber }, invalid, limit, props, { t }, limitInvalid() (+2 more)

### Community 160 - "queryString"
Cohesion: 0.28
Nodes (9): open(), openId, place, queryString(), search, sort, type, updateQuery() (+1 more)

### Community 163 - "BulkNumberInput.vue"
Cohesion: 0.27
Nodes (9): display(), emit, focused, { formatNumber }, onBlur(), onFocus(), onInput(), props (+1 more)

### Community 164 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.07
Nodes (12): SearchPagesAction, SearchPostsAction, ChangeProductStatusAction, FindPromotionVariantsAction, PageFilters, PostFilters, ProductFilters, ProductStatus (+4 more)

### Community 165 - "shipping/api.ts"
Cohesion: 0.31
Nodes (8): shippingApi, Province, SaveShippingMethodPayload, ShippingMethod, ShippingMethodList, ShippingRate, ShippingSimulation, ShippingUnavailableReason

### Community 168 - "save"
Cohesion: 0.28
Nodes (9): dirty, load(), save(), serialized(), switchTo(), targets, undo(), rowFromItem() (+1 more)

### Community 169 - "ActivityResource.php"
Cohesion: 0.36
Nodes (3): SearchActivityLogAction, ActivityLogController, ActivityResource

### Community 172 - "Illuminate\Support\Facades\DB"
Cohesion: 0.04
Nodes (23): EndUserSessionsAction, AnonymiseCustomerAction, {closure#1}(), SearchCustomersAction, SetCustomerStatusAction, UpdateCustomerAction, AddStaffMemberAction, {closure#1}() (+15 more)

### Community 173 - "moderate"
Cohesion: 0.40
Nodes (6): choose(), confirmDelete(), confirmReject(), counted(), moderate(), reload()

### Community 174 - "queryString"
Cohesion: 0.40
Nodes (5): itemId, page, queryString(), search, status

### Community 175 - "hasFiles"
Cohesion: 0.40
Nodes (5): hasFiles(), onDragEnter(), onDragOver(), onDrop(), upload()

### Community 176 - "menus/api.ts"
Cohesion: 0.48
Nodes (5): menusApi, MenuItem, MenuLocation, MenuTargets, SaveMenuItem

## Knowledge Gaps
- **127 isolated node(s):** `Text`, `Number`, `Boolean`, `Select`, `Unavailable` (+122 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 2075 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **33 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Illuminate\Http\Request`, `ShippingMethodResource`, `ProductVariant`, `Controller`, `Settings`, `Illuminate\Database\Eloquent\Model`, `Promotion`, `CustomerTokenResource`, `MenuItem`, `Exception`, `Illuminate\Database\Eloquent\Collection`, `Tag`, `Illuminate\Foundation\Http\FormRequest`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `RecordLastLogin.php`, `Product`, `Comment`, `MediaUsageRole`, `Illuminate\Contracts\Pagination\LengthAwarePaginator`, `Illuminate\Database\Eloquent\Builder`, `Permission`, `ActivityResource.php`, `Illuminate\Support\Facades\DB`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `Carbon\CarbonImmutable`, `LoginWithOtpAction`, `Page`, `AuthException`, `CouponQuote`, `UserRole`, `MediaAsset`, `MediaFolder`, `SavePostRequest.php`, `CategoryTree`, `Category`, `MediaUpload`, `Cart`, `Attribute`, `Coupon`, `SuggestSeoAction.php`, `ShippingMethod`, `Post`, `SaleEvent`, `ShippingRateData`, `CouponResource`, `SaveCouponRequest.php`, `Products/ProductController.php`?**
  _High betweenness centrality (0.083) - this node is a cross-community bridge._
- **What connects `Text`, `Number`, `Boolean` to the rest of the system?**
  _127 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Http\Request` be split into smaller, more focused modules?**
  _Cohesion score 0.03736263736263736 - nodes in this community are weakly interconnected._
- **Why does `ProductVariant` connect `ProductVariant` to `ShippingCalculator.php`, `Illuminate\Database\Eloquent\Model`, `RichText`, `Promotion`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `Product`, `BulkUpdateVariantsRequest`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `CouponTerms`, `Carbon\CarbonImmutable`, `CouponQuote`, `MediaAsset`, `CartCatalog`, `Category`, `Illuminate\Validation\Validator`, `RefreshPricesCommand.php`, `OptionValue`, `CouponCalculator.php`, `SaleEvent`, `CartLine`, `CouponItem`?**
  _High betweenness centrality (0.024) - this node is a cross-community bridge._
- **Should `AppButton.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.03882783882783883 - nodes in this community are weakly interconnected._
- **Why does `Controller` connect `Controller` to `Illuminate\Http\Request`, `ShippingMethodResource`, `ShippingCalculator.php`, `Settings`, `CustomerTokenResource`, `MenuItem`, `PageResource`, `User`, `Tag`, `Comment`, `ActivityResource.php`, `Illuminate\Support\Facades\DB`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `LoginWithOtpAction`, `Page`, `MediaFolder`, `CategoryTree`, `MediaUpload`, `Cart`, `Attribute`, `Post`, `SaleEvent`, `CouponResource`, `Products/ProductController.php`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **Should `useLocale.ts` be split into smaller, more focused modules?**
  _Cohesion score 0.06956521739130435 - nodes in this community are weakly interconnected._