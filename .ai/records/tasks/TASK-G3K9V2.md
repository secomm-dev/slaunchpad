---
id: TASK-G3K9V2
type: task
project_code: SLP
parent: {type: feature, id: FEAT-QA23PZ}
legacy_ids: []
title: 'ShippingCore Zone & Carrier Coverage Admin UX — country/province/ward form UX, searchable multiselect, Carrier Coverage screen, relocate coverage + rate-orchestration fields khỏi GHN system.xml'
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md
risk: medium
status: in_review  # dev-complete 2026-09-22; TL CONDITIONALLY APPROVED cùng ngày — cả 2 fix bắt buộc + 1 khuyến nghị đã implement + test (475 OK); chờ TL verify lại trước integration
created: 2026-09-22
updated: 2026-09-22
plan: ../../plans/TASK-G3K9V2-implementation-plan.md
decisions: []
decision_assessment: minor
decision_refs: [DEC-FEATQA23PZ-001]
verified_against_commit:
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/Ghn/
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
changes_architecture: false
---

# [SLP][FEAT-QA23PZ][TASK-G3K9V2] ShippingCore Zone & Carrier Coverage Admin UX — country/province/ward form UX, searchable multiselect, Carrier Coverage screen, relocate coverage + rate-orchestration fields khỏi GHN system.xml

TL directive "ShippingCore Zone & Carrier Coverage Admin UX" (16 section, 2026-09-22) — mở rộng
AC của FEAT-QA23PZ (dev-complete chờ TL). KHÔNG đổi runtime eligibility semantics.

## Mini Spec

### Goal

Admin UX business-friendly, tập trung zone/carrier assignment về ShippingCore:

1. Zone form: Country (Vietnam — fixed display), Province searchable multiselect (label
   `Name (VN-XX)`), Included Wards cascading searchable multiselect (`Name (CODE)`), auto-clear
   stale wards khi đổi province, BỎ Excluded Wards khỏi UI (backend contract giữ nguyên),
   hiển thị read-only "carriers referencing this zone".
2. Carrier Coverage screen mới trong ShippingCore (Secomm menu): Availability =
   `ALL | SELECTED_ZONES | ALL_EXCEPT_SELECTED_ZONES`, Allowed Zones (enabled zones only,
   searchable), + Rate Source Mode + Address Resolution Policy (relocate từ GHN system.xml).
3. Carrier modules KHÔNG còn sở hữu zone-assignment/policy UX; config PATH giữ nguyên
   `carriers/secomm_ghn/...` → zero data migration, readers runtime không đổi.

### Expected Behavior

- Zone options lấy từ `Secomm_VietNamAddress` canonical (VN_ADMIN_2025): provinces = level-1,
  wards = level-2 theo `region_code`. `VnAddressUnitProviderInterface` thêm 2 method ADDITIVE
  `getByLevel()` + `getByRegion()` — DB seeded hiện có `parent_code = NULL` trên mọi row con
  (defect stream VietNamAddress đã ghi nhận) nên `getChildren()` không dùng được cho lookup này.
- Ward options endpoint trả `label = "name (CODE)"`; save path Validator đã reject stale
  include-ward (deterministic block) — JS auto-clear là UX layer phía trên.
- Coverage screen ghi qua `ScopeConfig` WriterInterface (DEFAULT scope) + clean cache `config`;
  validate server-side: availability ∈ enum; SELECTED_ZONES/ALL_EXCEPT_SELECTED_ZONES ⇒ ≥1 zone;
  zone code phải tồn tại (deleted → reject; disabled → cho phép, runtime diagnostic — mirror
  semantics backend model GHN bị xoá); mode/policy dùng enum `ShippingCore\Api\Rate\RateSourceMode`
  + `ShippingCore\Api\Address\AddressResolutionPolicy` (owner sẵn có).
- Availability = `ALL | SELECTED_ZONES | ALL_EXCEPT_SELECTED_ZONES` — string-aligned 1:1 với
  runtime `Api\Address\DestinationScope` (3 values sau TASK-R8WR1R, dev-complete cùng working
  tree 2026-09-22): mọi giá trị UI chọn đều được `CarrierEligibilityEvaluator` honor. Admin
  surface CHẶT hơn runtime ở một điểm: zone mode + 0 zone bị REJECT ở save (runtime coi
  ALL_EXCEPT + list rỗng ≡ ALL — admin không bao giờ tạo được state đó từ UI).
- Carrier registry DI-array (`Model\CarrierCoverage\CarrierRegistry`); `Secomm_Ghn` tự đăng ký
  `secomm_ghn` label "GHN (Giao Hàng Nhanh)". GHTK chưa wired → chưa đăng ký (không dead config).
- ACL `Secomm_ShippingCore::carrier_coverage` (+ `::carrier_coverage_manage`); menu Secomm
  "Carrier Coverage" cạnh "Shipping Zones".

### Constraints / Rules

- KHÔNG đụng runtime eligibility: `DestinationScope`, `CarrierEligibilityEvaluator`,
  `CarrierDestinationScopeConfig`, `GhnConfig` readers, `Ghn::collect()` wiring — nguyên vẹn
  trong task này (việc thêm giá trị runtime thứ 3 thuộc TASK-R8WR1R — task song song cùng
  working tree; không đụng nhau, không conflict file).
- KHÔNG expose Excluded Wards UI; KHÔNG xoá production contract (`exclude_ward_codes`:
  schema/matcher/validator giữ nguyên); UI save không còn posting excludes (documented).
- KHÔNG provider IDs / PRE-2025 codes trong ShippingCore data — validator + option sources chỉ
  canonical VN_ADMIN_2025.
- KHÔNG multi-country, GIS, postcode, provider-specific zone definitions (§15 directive).
- VietNamAddress change = additive read methods (Tier-2 flag trong report; không đụng import/GraphQL).
- Admin JS = Luma/RequireJS (không Hyvä rules); KHÔNG thêm lib mới (select2 v.v.) — searchable =
  custom component trên core multiselect.

### Out of Scope

Runtime ALL_EXCEPT_SELECTED_ZONES evaluation (task song song); multi-store/website coverage UI
(P1 default scope); import/export zone; zone-level carrier assignment WRITE từ zone form
(read-only hiển thị thôi — assignment thuộc Carrier Coverage, một source of truth);
GHTK coverage registration; excludeWardCodes data migration.

### Acceptance Criteria

- Province options từ canonical VN data (provider level-1, label `Name (VN-XX)`), không hardcode.
- Ward options lọc theo selected province (region_code), persist code canonical, label `name (CODE)`.
- Empty ward selection = whole province (matcher hiện có, lock bằng test hiện trạng).
- Stale ward sau khi đổi province: JS auto-clear + Validator reject (test).
- Disabled zone không xuất hiện trong Allowed Zones options (EnabledZoneCodes — giữ nguyên).
- Carrier coverage values persist đúng paths cũ + clean cache; validation reject case sai.
- Existing GHN coverage/policy config: KHÔNG migration (cùng path) — verify reader đọc được giá trị
  persist từ screen mới.
- Không provider-specific IDs trong zone mapping (validator existence check).
- `setup:di:compile` + unit suites (ShippingCore/Ghn/VietNamAddress) + `bin/project-ai-validate
  --check-specs --check-records --check-identity` green.

## TL review outcome + fixes (2026-09-22)

**Verdict: CONDITIONALLY APPROVED** — 2 bắt buộc + 1 khuyến nghị, đã implement tất cả trước
integration:

1. **[BẮT BUỘC — DONE]** Zone form save **phải preserve `exclude_ward_codes`** khi field
   không có trong UI/request: `Zone\Save` — POST thiếu key (UI save) ⇒ load persisted list
   (ZoneFactory + ZoneResource) và giữ nguyên; POST CÓ key (API-style) ⇒ set explicit (clear
   chủ đích vẫn được). Test: `testEditSaveWithoutExcludeKeyPreservesPersistedList`,
   `testExplicitExcludeKeyStillWins`, `testNewZoneWithoutExcludeKeyStartsEmpty`.
2. **[BẮT BUỘC — DONE]** Xoá zone đang được Carrier Coverage tham chiếu bị **BLOCK**:
   `Zone\Delete` (refuse + error nêu tên carrier) + `Zone\MassDelete` (block TOÀN BỘ batch —
   không partial delete) qua guard mới `Model\CarrierCoverage\ZoneReferenceGuard`.
   Test: `testReferencedZoneDeletionIsBlocked`, `testReferencedZoneBlocksWholeBatch`.
3. **[KHUYẾN NGHỊ — DONE: explicit impact warning]** Disable zone được tham chiếu vẫn thực
   hiện (soft state) nhưng thêm warning nêu zones + carriers (`Zone\Save` disabled-save +
   `Zone\MassStatus`); static notice field Enabled + confirm messages grid cập nhật cùng
   semantics. Test: `testDisabledReferencedZoneWarnsOnSave`,
   `testDisableReferencedZoneWarnsWithImpact`, `testEnableNeverWarns`,
   `testDisableUnreferencedZoneDoesNotWarn`.
4. **[GIỮ NGUYÊN theo TL]** ALL_EXCEPT + 0 zone: runtime = ALL (R8WR1R), admin = reject —
   hợp lý, không đổi.

ShippingCore suite sau fixes: **475 tests / 1241 assertions OK**.

## Final TL verification — scope integrity (2026-09-22)

**Yêu cầu:** zone deletion protection KHÔNG được chỉ dựa DEFAULT scope; chốt đúng 1 scope
model; disabled-referenced zone phải visible khi edit coverage.

**Chốt scope model (bằng chứng code):** runtime ĐỌC scoped values ⇒ **Option A — scoped
references remain valid**:
- `Ghn::collect()` (Ghn.php:228-231,369) truyền `$storeId` từ rate request vào cả 4 readers;
  readers dùng `ScopeConfig::getValue(path, SCOPE_STORE, $storeId)` — Magento single-parent
  fallback (store explicit → website explicit → default). WEBSITE/STORE persisted values
  LIVE cho stores của chúng; DEFAULT governs context-less reads.
- Legacy GHN system.xml fields `showInWebsite=1 showInStore=1` ⇒ scoped values có thể tồn tại.
- `app/etc/config.php` = 0 entry `carriers/*` (verified) ⇒ persisted truth = `core_config_data`.

**Implement:**
1. `CarrierZoneIndex` r2 — đọc PERSISTED per-scope thẳng từ `core_config_data` (1 query, exact
   paths từ registry; KHÔNG ScopeConfig effective — tránh merge/shadow che khuất tham chiếu;
   KHÔNG runtime diagnostics). `findReferences(zoneCode)` trả metadata {carrier, carrier_label,
   scope, scope_id, scope_label} ("Default" | "Website: <name>" | "Store View: <name>"; scope id
   mồ côi fallback "#scope name" — vẫn tính cho guard). `carriersForZone` = distinct carriers.
   Chỉ index REGISTERED carriers. Deterministic order: default → websites → stores → path.
2. `ZoneReferenceGuard` r2 — `findReferences()` passthrough + `describeReferences()`
   ("GHN (… ) (Website: vietnam_store)") + `describeZoneReference()` ("CODE (refs)") cho
   mass-action messages. 4 controllers (Save warning / Delete block / MassDelete block /
   MassStatus warning) giờ nhìn thấy tham chiếu ở MỌI scope.
3. **§6 disabled-referenced visibility:** source model mới `ReferencableZoneCodes` cho coverage
   picker — enabled zones + disabled-ZONE-ĐƯỢC-THAM-CHIẾU hiển thị rõ "— Disabled" (edit không
   mất nhìn thấy; chỉ mất khỏi config khi admin chủ động bỏ chọn); disabled-không-tham-chiếu
   KHÔNG offer (không select mới được). `EnabledZoneCodes` xoá (orphan). Runtime: disabled vẫn
   không match (không đổi).
4. §7 giữ nguyên toàn bộ; §8 không thêm scope switcher UI / storage mới / runtime change.

**Verification:** ShippingCore suite **491 tests / 1268 assertions OK** (tests A–E + guard +
index + ReferencableZoneCodes; §5.F structural — index không còn phụ thuộc ScopeConfig/diagnostics
nào); VietNamAddress **195 OK**; Ghn **373 OK**; `setup:di:compile` OK; validator: record 0 FAIL.

## Integration review (TASK-R8WR1R + TASK-G3K9V2, 2026-09-22)

Verification-only; 2 genuine defects fixed, không thêm feature:

1. **§11 ALL_EXCEPT disable framing** — warning cũ nói "stops matching" là SAI cho carrier
   ALL_EXCEPT (disable excluded zone = MỞ RỘNG coverage). Fix text ở `Save` warning +
   `MassStatus` warning + form Enabled notice + grid disable confirm + USER_GUIDE: nhả rõ
   "EXPANDS their service area".
2. **§18.K test gap** — chưa có test chứng minh reader đọc store-scoped: thêm
   `testStoreScopedCoverageReadUsesStoreScopeWithStoreId` + `testContextLessReadUsesDefaultScope`
   (assert SCOPE_STORE + storeId 5 cho cả 2 paths; default scope khi null).

**Registered-carrier audit (§10):** consumer của 4 paths = (A) `secomm_ghn` — REGISTERED trong
CarrierRegistry (Ghn di.xml); (B) ShippingCore generic plumbing (CarrierDestinationScopeConfig /
PolicyConfig / CarrierZoneIndex — registry-agnostic infrastructure, không phải carrier consumer);
GHTK (GhtkConfig) chỉ đọc active/tracking/showmethod — KHÔNG consume coverage paths, không
registered (intentional, §13 no blocker). Không có shared-coverage consumer ngoài registry.

**§18 mapping:** A–G = CarrierEligibilityEvaluatorTest (14 case) + CarrierDestinationScopeConfigTest
(r2 + store-scoped); H = CarrierZoneIndexTest A–C + DeleteTest + MassDeleteTest website-scope;
I = SaveTest preserve; J = ReferencableZoneCodesTest; K = store-scoped reader test (mới).

**Regression:** ShippingCore 493 OK · VietNamAddress 195 OK · Ghn 373 OK · Ghtk 237 OK
(1 PHPUnit deprecation notice, non-blocking) · compile OK · validator: cả 2 record 0 FAIL.

## Hotfix — coverage edit page fatal (2026-09-22, post-freeze smoke)

**Defect:** `Call to a member function addFieldToFilter() on null` (AbstractDataProvider:169)
khi mở `secomm_shippingcore/coverage/edit`. **Root cause:** `Magento\Ui\Component\Form::
getDataSourceData()` LUÔN gọi `addFilter()` trên form DataProvider trước `getData()`;
`AbstractDataProvider::addFilter()` route vào collection — `CoverageFormDataProvider` không set
collection ⇒ null. (`ZoneFormDataProvider` không dính vì có set collection từ CollectionFactory.)

**Fix:** inject `Zone\CollectionFactory` — collection chỉ để hấp thụ `addFilter()` bắt buộc của
framework (không bao giờ load; record vẫn đến từ `getData()` override) — đúng pattern của
ZoneFormDataProvider. Regression test: `testFrameworkFormAddFilterDoesNotFatal`
(mock collection + đúng sequence addFilter→getData).

**Verification:** ShippingCore **494 tests OK**; compile OK; cache flush; real-DI smoke
(bootstrap Magento, ObjectManager thật): addFilter + getData không fatal + record
`secomm_ghn` trả đủ 5 field với registry merged-di đúng (GHN registered).
