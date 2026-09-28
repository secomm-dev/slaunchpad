# TASK-WY6WP5 — Shipping Coverage P1 Admin UX — Evidence

> Ngày: 2026-09-23 · Mode A · parent FEAT-QA23PZ · DEC-TASKWY6WP5-001

## 1. Defects tìm thấy + fix trong quá trình implement (browser-verified)

Đây là giá trị lớn nhất của task: **cả 3 root-cause của "selector không render" ở TASK-G3K9V2 đều đã được khoanh và sửa**, không phải chỉ thay UI:

1. **Ward AJAX URL chưa bao giờ được inject** — trên Magento 2.4.8, framework KHÔNG truyền
   meta vào form DataProvider (`mergeMetadata` gọi `getMeta()` trên provider với constructor
   meta rỗng — debug-log chứng minh `meta keys=[]`). Code inject có guard
   `isset($meta[...]['config'])` của G3K9V2 là **dead code** → `optionsUrl` không bao giờ có
   mặt → Included Wards không bao giờ nạp options. Fix: build meta path **unconditionally**
   trong `ZoneFormDataProvider::getMeta()` (framework array-merge kết quả vào field config).
   Coverage edit-mode meta (disabled + single option) fix tương tự.
2. **Record-key contract của form hydration** — `Magento\Ui\Component\Form::getDataSourceData()`
   hydrate `$data[$id]` với `$id` = GIÁ TRỊ request param (`target_code`). Record key cũ
   `CARRIER:secomm_ghn` (composite identity key) → lookup fail thầm lặng → client nhận rỗng →
   form luôn rơi về XML defaults. Fix: key record bằng `$identity->code()`.
3. **Import-link path sai** — `<link name="provincesValue">${ $.provider }:data.general.include_province_codes</link>`
   — data provider là FLAT (fieldset không đóng góp path segment) → link trỏ đúng là
   `data.include_province_codes`. Sau fix: chọn province → AJAX
   `wardOptions?provinces=VN-15&isAjax=true` → **168 ward options** (HCM) load đúng.

## 2. Integration proof (directive §31) — CLI, DB thật

Script: `integration-proof.php` · output: `integration-proof-output.txt` — **tất cả PASS**:

- Baseline 0 row `carriers/secomm_ghn/*` → runtime reader default ALL, eligible mọi destination.
- `adapter->save(CARRIER:secomm_ghn, SELECTED_ZONES, [HCM_INNER], CARRIER_WITH_FALLBACK, FALLBACK)`
  → đúng 4 rows DEFAULT trong `core_config_data`.
- Fresh `CarrierDestinationScopeConfig` đọc lại đúng `SELECTED_ZONES` / `[HCM_INNER]` (không
  đụng GHN runtime).
- `CarrierEligibilityEvaluator`: VN-15 → eligible (matched=HCM_INNER); VN-01 → ineligible +
  `DESTINATION_NOT_IN_SCOPE`.
- `reset()` → xoá 4 rows, reader về default ALL, eligible lại toàn quốc.
- Lưu ý: proof bắt 1 bug thật mà unit test mock không thấy — `['c' => '1']` trong
  `hasDefaultRow` bị Zend quote thành identifier → đã đổi sang `COUNT(*)`.

## 3. Browser/Admin smoke (directive §32) — 27/27 PASS

Script: `admin-smoke.js` (Playwright/Chromium, session admin thật — user tạm tạo bằng CLI và
ĐÃ XOÁ sau run) · kết quả: `smoke-results.txt` · screenshots: `smoke-*.png`.

Đủ 6 mục checklist directive §32 + lifecycle thật: Add Coverage → chọn GHN (ui-select) →
SELECTED_ZONES → chip HCM_INNER → Save (message thật) → grid Configured → Edit (field value
`["HCM_INNER"]` đọc qua uiRegistry — authoritative) → Reset to Defaults (modal confirm) →
message "4 configuration value(s) removed — the target remains registered" → grid về
Not Configured. Zone form: province type-to-search hoạt động, ward cascade load sau khi chọn
tỉnh, block "Carriers Referencing This Zone" biến mất.

`ADMIN_SMOKE = PASS` (không BLOCKED_BY_ENVIRONMENT).

## 4. Unit suites (số chính xác)

| Suite | Tests | Assertions | Kết quả |
|---|---|---|---|
| Secomm_ShippingCore | **545** (baseline 494 → +51) | 1420 | OK |
| Secomm_Ghn (+GhnAddressMapper) | 387 | 211009 | OK |
| Secomm_Ghtk | 237 | 585 | OK |
| Secomm_VietNamAddress | 195 | 786 | OK |
| Launchpad (TableRate bridge) | 135 | 234 | OK |

Failures: 0 · Errors: 0 · Skips: 0 · PHPUnit deprecation notices: 9 (pre-existing, non-blocking — same baseline as trước task).

`setup:di:compile`: OK (2 lần, sau rename và sau un-final adapter).
`bin/project-ai-validate --check-specs --check-records --check-identity`: record mới
0 FAIL / 0 WARN (89 FAIL còn lại là pre-existing từ records/plans cũ ngoài scope).

## 5. DB state sau task

- `carriers/secomm_ghn/*`: 0 rows (baseline — smoke tự reset; runtime default ALL).
- Zones: `NOITHANH` (có sẵn) + `HCM_INNER` (mới, VN-15 — giữ lại làm dữ liệu thật cho TL review).
- Zone test artifact `TESTHYZ` đã xoá; smoke admin user đã xoá; chỉ còn 1 admin user gốc.
