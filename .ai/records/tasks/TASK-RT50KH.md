---
id: TASK-RT50KH
type: task
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
legacy_ids: []
title: 'Product Shipping Dimensions Contract + GHN Checkout Dimension Pre-Validation — reuse length/width/height (decimal/GLOBAL), Base dimension reader, bật live 150cm hard-limit chain'
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-FQWEQ3-ghn-carrier-adapter.md
risk: medium
status: in_review  # dev-complete 2026-09-23; Base 11 + Ghn 411 + ShippingCore 550 + Launchpad 135 OK; migration verified; admin smoke PASS (render/labels/save/persist)
created: 2026-09-23
updated: 2026-09-23
plan: ../../plans/TASK-RT50KH-implementation-plan.md
decisions: [DEC-TASKRT50KH-001]
decision_assessment: minor
decision_refs: [DEC-TASKRT50KH-001]
related_tickets: [TASK-MQ2DRG, TASK-WAWNDS]
verified_against_commit:
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Base/
  - app/code/Secomm/Ghn/
changes_project_state: true
changes_architecture: false
---

# [SLP][FEAT-FQWEQ3][TASK-RT50KH] Product Shipping Dimensions Contract + GHN Checkout Dimension Pre-Validation — reuse length/width/height (decimal/GLOBAL), Base dimension reader, bật live 150cm hard-limit chain

TL directive "Product Shipping Dimensions Contract + GHN Checkout Dimension Pre-Validation"
(21 section, 2026-09-23) — đóng phần DEFERRED của TASK-MQ2DRG. TL quyết định (AskUserQuestion
2026-09-23): **Option A — Reuse** codes `length/width/height` qua xoá+tạo lại (audit DB: 0/176
products có giá trị — empty shells). KHÔNG cartonization/splitting/qty-multiplication; KHÔNG
đổi ShippingCore runtime; KHÔNG generic carrier constraint engine.

## Mini Spec

### Goal

1. **Attribute contract** (Secomm_Base): xoá 3 attr varchar/STORE + tạo lại cùng codes —
   decimal backend, GLOBAL scope, input text + `frontend_class validate-number
   validate-zero-or-greater`, labels "Shipping Length (cm)/Width (cm)/Height (cm)",
   searchable/comparable/visible_on_front/used_in_listing/is_used_in_grid = false, group
   General (tự nhân rộng 9 sets). Migration patch add-only (getDependencies về patch gốc;
   fresh install: original → migration, nhất quán).
2. **Read contract** (Secomm_Base — product-data owner): `Api\ShippingDimensionsReaderInterface::
   read(Quote\Item): ?Api\Data\ShippingDimensions` — authoritative CHỈ khi cả 3 present +
   numeric + > 0 → **ceil() từng giá trị → int cm** (conservative tại 150: 149.2→150 quotable,
   150.1→151 reject); partial/zero/negative/non-numeric/blank → null (= missing, không bao giờ
   rejection). Composite resolution trong reader: configurable ship-together → selected child
   (`getChildren()[0]->getProduct()`); bundle ship-together → parent dims; ship-separately →
   child product; simple/grouped-simple → own; virtual/downloadable → estimator skip sẵn
   (downloadable = Virtual subtype — verified); product null/children rỗng → null.
3. **GHN wiring** (1 điểm): `QuoteParcelEstimator` đọc dims 1 lần per expanded item qua reader
   → `EstimatedPackage(..., lengthCm, widthCm, heightCm)` (cùng dims cho mọi unit — không nhân
   với qty) → `findHardLimitViolation()` live: longest side > 150cm (RATE_MAX_SIDE_CM,
   SANDBOX_OBSERVED) → UNAVAILABLE `GHN_PACKAGE_{LENGTH|WIDTH|HEIGHT}_LIMIT_EXCEEDED`, không
   gọi API, không fallback (carrier-owned). Fee payload GIỮ omit dims. Fix 150 de-dup
   (`GhnPackageLimits::MAX_DIMENSION_CM` → alias `RATE_MAX_SIDE_CM` — silent-failure MQ2DRG).

### Expected Behavior

- Missing/malformed dims → KHÔNG reject (Case 7/8/9) — provider remains final authority.
- Complete dims, longest side ≤ 150 → rate tiếp bình thường (Case 1/2).
- Longest side > 150 (length/width/height bất kỳ) → hard UNAVAILABLE không API (Case 3-6).
- qty > 1 → dims mỗi unit giống hệt (Case 12).
- Weight matrix TASK-MQ2DRG unchanged (Case 15/16); FALLBACK semantics unchanged (Case 14 —
  hard reasons không fallback).
- Admin: 3 fields (cm) labels render ở General group của mọi attribute set; save/reload
  persist; 0/negative blocked bởi frontend validation.

### Constraints / Rules

- FROZEN: ShippingCore runtime; fee payload (dims vẫn omit); CREATE path; Secomm_Base plugin;
  Ahamove consumer (decimal-compatible); `GhnShipmentConstraints` (reuse consts — không
  duplicate 150).
- Không sửa patch `AddDimensionProductAttribute` đã execute — migration add-only.
- Đơn vị: cm cố định bởi contract; không tự parse text tùy ý; không substitute 0/1/default
  package dims.

### Out of Scope

Cartonization/packing; generic multi-carrier constraint framework; METHOD coverage; backfill
data; đổi Ahamove/plugin; multi-source dimension providers.

### Acceptance Criteria

Directive §16 đủ 16 case (mapping trong plan) + admin smoke (render/save/validation/checkout
160cm → GHN ẩn + log reason) hoặc `ADMIN_SMOKE = BLOCKED_BY_ENVIRONMENT`. Suites: Base (mới)
+ Ghn + ShippingCore + Ghtk + VietNamAddress + Launchpad green; compile + validator; invariant
grep §19 (11 mục); final report A–Q.

## Implementation summary (2026-09-23 — dev-complete)

- Option A executed: migration patch `UpgradeDimensionAttributesToShippingContract` (delete+
  recreate decimal/GLOBAL/(cm)/validation — DB verified: is_global=1, frontend_class set,
  flags 0, 9 sets per code, 0 value rows).
- Base contract: `Api/Data/ShippingDimensions` + `Api/ShippingDimensionsReaderInterface` +
  `Model/Shipping/ProductShippingDimensionsReader` (complete-only + ceil + composite:
  configurable→child, bundle→parent); di preference; module.xml += Magento_Quote; first
  Test/Unit suite (11 tests).
- GHN: estimator ctor += reader; dims per expanded item → mọi unit (không nhân qty);
  docblock supersede ×3; `GhnPackageLimits::MAX_DIMENSION_CM` → alias (150 de-dup — correction
  silent-failure MQ2DRG, ghi nhận DEC).
- Suites: Base **11/24** (first), Ghn **411/211072** (+11: 7 estimator + mapper-test fix —
  reader stub), ShippingCore 550, Ghtk 240, VietNamAddress 195, Launchpad 135 — 0F/0E.
- Admin smoke PASS: fields render với "(cm)" labels, save/persist (CLI E2E: reader đọc REAL
  EAV decimal 40.5 → 41×20×30), cleanup null → no rejection. Checkout storefront UI flow
  không chạy (CLI reader E2E + unit coverage thay thế) — ghi trung thực trong evidence.
- **BUG FIX phát hiện qua admin smoke (user báo cáo): tab "Launchpad Settings" không hiển thị.**
  Root cause: `MethodTabs::_beforeToHtml` gọi `parent::_beforeToHtml()` (kết thúc bằng core
  `Widget\Tabs::_beforeToHtml` → `assign('tabs', $_tabs)` — snapshot template data) TRƯỚC khi
  `addTab('launchpad')` → tab không bao giờ tới template. Fix: addTab BEFORE parent
  (`addTabAfter(..., 'rate')` khi edit / addTab cuối khi create) + regression test
  `MethodTabsTest` (2 tests — snapshot + order). Verified browser: tab render, DOM
  `method_tabs_launchpad` ✓.
- **Follow-up (user report): modal Import Rates trỏ file mẫu tới Google Drive của Mageplaza
  (schema cũ, thiếu cột City/Area)** — fix bằng preference block
  `Launchpad\...\Import\Edit\Form` override `_getDownloadSampleFileHtml()` → link tới
  `launchpad_mptablerate/city/importTemplate` (template 20 cột Launchpad). Browser-verified:
  modal render link mới, fetch 200 text/csv có `city_code` + `city_name`. + unit test
  `FormTest` (2 tests). Launchpad **100/196** OK.
- **Follow-up 2b (user request): cột City / Area hiển thị NAME thay vì raw code** — join thêm
  `directory_region_city` (COALESCE name fallback raw code) vào `CityGrid::setCollection` +
  cột on-screen đổi index sang `city_area_display`; export CSV GIỮ nguyên `city_code` raw
  (roundtrip intact). SQL-verified: join trả "An Hoi Dong" cho rate có gán city. ⏳ Hiển thị
  trên browser chờ admin login khả dụng (captcha/lock môi trường — xem follow-up 3).
- **Follow-up 2 (user report): Rates grid không data + sort City/Area lỗi 1054** — root cause:
  `CityGrid` join SAU khi parent chain (`Extended::_prepareCollection`) áp sort + `load()` —
  join sau load vô dụng. Fix: chuyển join sang override **`setCollection()`** (real method
  `Widget\Grid:196` — join chạy trước sort/load). Browser-verified: grid 2 rate rows (gồm
  VNA25 code), sort click City/Area → 0 new 1054, export CSV vẫn có cột `city_code`. Note:
  on-screen hiển thị raw code (resolve label = optional follow-up); import = APPEND semantics
  (native Mageplaza) — documented trong section text.
- ⚠ Client-side admin validation (`validate-number` classes) KHÔNG render vào form JSON —
  core `CatalogEavValidationRules::mapRules` gap (switch không map
  `validate-zero-or-greater`; build trả `{"validate-number":true}` nhưng meta không surface).
  Read-side reader contract là guard chính (test-locked). Đề xuất follow-up riêng nếu TL
  muốn UI-level chặn.
