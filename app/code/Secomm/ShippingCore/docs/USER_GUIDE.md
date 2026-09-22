# Secomm Shipping Stack — Workflow & User Guide

> Phạm vi: `Secomm_ShippingCore` · `Secomm_VietNamAddress` · `Secomm_AddressDropdown` · `Secomm_Ghn` · `Launchpad_MageplazaTableRate` (trên nền `Mageplaza_TableRateShipping` v4.0.8).
> Audit toàn bộ 5 module ngày **2026-09-21**, đối chiếu code + `etc/*.xml` + CLI + README/CHANGELOG của từng module.
> Prose tiếng Việt; code, path, config key giữ English.

---

## Mục lục

1. [Kiến trúc tổng thể](#1-kiến-trúc-tổng-thể)
2. [Secomm_ShippingCore — shared kernel](#2-secomm_shippingcore--shared-kernel)
3. [Secomm_VietNamAddress — dữ liệu địa chỉ VN](#3-secomm_vietnamaddress--dữ-liệu-địa-chỉ-vn)
4. [Secomm_AddressDropdown — dropdown địa chỉ](#4-secomm_addressdropdown--dropdown-địa-chỉ)
5. [Secomm_Ghn — carrier GHN](#5-secomm_ghn--carrier-ghn)
6. [Launchpad_MageplazaTableRate — TableRate + fallback](#6-launchpad_mageplazatablerate--tablerate--fallback)
7. [CLI command reference](#7-cli-command-reference)
8. [Bảng dữ liệu tổng hợp](#8-bảng-dữ-liệu-tổng-hợp)
9. [Playbook theo case](#9-playbook-theo-case)
10. [Kết quả audit — trạng thái & lưu ý](#10-kết-quả-audit--trạng-thiệu--lưu-ý)

---

## 1. Kiến trúc tổng thể

```text
Secomm_AddressDropdown      (engine dropdown + profile, generic)
        ▲
Secomm_VietNamAddress       (dữ liệu hành chính VN: scheme + mapping + resolver)
        ▲
Secomm_ShippingCore         (kernel: contracts + orchestration, KHÔNG biết carrier nào)
        ▲                    ▲
Secomm_Ghn              Launchpad_MageplazaTableRate → Mageplaza_TableRateShipping (vendor)
```

Dependency direction **bắt buộc một chiều** (cấm đảo): `Launchpad → ShippingCore / VietNamAddress / Mageplaza` · `Ghn → ShippingCore → VietNamAddress` · cấm `ShippingCore → Mageplaza`, cấm `Launchpad → carrier module`, cấm carrier nào depend `Secomm_Ghn`.

Pipeline chuẩn một lượt rate (ShippingCore "hard-stop flow"):

```text
address handoff (Stage 1, canonical)
  → carrier API / provider mapping (Stage 2, carrier-owned)
  → CarrierRateOutcome (SUCCESS | UNAVAILABLE | TECHNICAL_FAILURE)
  → ServiceLevelRateAggregate
  → ServiceLevelRateDecision (REALTIME | FALLBACK | UNAVAILABLE)
```

| Module | Vai trò | Sở hữu chính |
|---|---|---|
| `Secomm_ShippingCore` | Contract + orchestration dùng chung cho mọi carrier | Rate outcome/aggregate/orchestrator, fallback policy, address handoff, tracking processor, HTTP primitives, COD resolver |
| `Secomm_VietNamAddress` | SSOT dữ liệu hành chính VN versioned | Schemes `VN_ADMIN_2025` (2 cấp) / `VN_ADMIN_PRE_2025` (3 cấp), mapping edges, resolver + bridge |
| `Secomm_AddressDropdown` | Dropdown phân cấp AJAX cho address form | `directory_region_city` (đệ quy `parent_city_id`), Address Profile engine, GraphQL `addressLocations`/`addressSchema` |
| `Secomm_Ghn` | Carrier adapter GHN trên ShippingCore | `secomm_ghn_*` tables, GHN API client, rate/create/cancel/return, webhook + cron tracking |
| `Launchpad_MageplazaTableRate` | Composition Launchpad quanh Mageplaza TableRate | Per-method settings (show/fallback/members), City/Area dimension, fallback price provider |

---

## 2. Secomm_ShippingCore — shared kernel

### 2.1 Workflow runtime

**Rate pipeline (hard-ordered), owner `Model\Rate\CarrierRateExecutionService`:**

1. **Eligibility theo destination-scope** — `CarrierEligibilityEvaluator`: `DestinationScope::ALL` → eligible ngay; `SELECTED_ZONES` → đối chiếu canonical destination với zone trong `CanonicalZoneRegistry` (matcher fail-closed: disabled → false, exclude-ward thắng, so sánh strict case-sensitive). Không khớp zone nào → ineligible `DESTINATION_NOT_IN_SCOPE` (mất cả realtime lẫn fallback).
2. **RateSourceMode** — `CARRIER_ONLY` / `CARRIER_WITH_FALLBACK` / `FALLBACK_ONLY`. `FALLBACK_ONLY` bỏ qua origin/handoff/realtime, giữ eligibility legacy-address.
3. **Origin readiness** — `OriginProviderInterface` (mặc định `ShippingOriginProvider` đọc Magento Shipping Origin theo store). Origin thiếu country → `INVALID_CONFIGURATION`, fail-closed.
4. **AddressResolutionPolicy** qua MỘT đường handoff dùng chung `CarrierAddressHandoffServiceInterface` — policy `STRICT` (ẩn khi ambiguous) / `FALLBACK` (mặc định, để lớp fallback xử lý) / `PICK_PRIMARY` (opt-in, chọn candidate `is_primary=1` qua `VnPrimaryCandidateSelectorInterface`). Handoff UNMAPPED nhưng carrier khai báo textual fallback → vẫn vào realtime.
5. **Realtime contributor** — carrier tự implement `RealtimeCarrierRateContributorInterface` (KHÔNG có default). Nhận final handoff, trả `CarrierRateOutcome`.
6. **Outcome + eligibility** — `SafeDegradationEligibilityPolicy`: `TECHNICAL_FAILURE` → eligible (`TECHNICAL_FALLBACK`); `UNAVAILABLE` + `CANONICAL_AMBIGUOUS` → `LEGACY_ADDRESS_FALLBACK`; `UNAVAILABLE` + `PROVIDER_MAPPING_MISSING` → `INTEGRATION_LIMITATION`; `INVALID_CONFIGURATION` / `CANONICAL_UNMAPPED` / `UNSUPPORTED_DESTINATION` → **không bao giờ** fallback.
7. **Aggregate (E-SL1)** — `ServiceLevelRateAggregator` gom outcome theo service-level code (validate qua `ShippingServiceLevelRegistry`, DI `serviceLevels`).
8. **Decision (E-SL2)** — `ServiceLevelRateOrchestrator`: bất kỳ realtime SUCCESS nào → suppress fallback; level disabled → UNAVAILABLE; fallback tối đa gọi 1 provider (`FallbackRateProviderPool`, đúng 0 hoặc 1 provider — >1 → fail-fast); provider trả `null` → UNAVAILABLE; **rate 0 là fallback hợp lệ**.

> ⚠️ Hiện trạng (audit): `CarrierRateExecutionService` **chưa có consumer runtime** — `Secomm_Ghn` đang tự gọi handoff → calculator và ghi outcome qua `CarrierRateOutcomeCollectorInterface`; phần "composition" chạy execution service chờ freeze upstream.

**Tracking pipeline** — `ShipmentTrackingProcessor` là ĐÚNG MỘT đường cho webhook + API fetch + cron:
tìm track theo `carrier_code + track_number` → duplicate no-op; terminal sticky (DELIVERED/RETURNED/CANCELLED/LOST/DAMAGED không bao giờ downgrade; `DELIVERY_FAILED → IN_TRANSIT` = reattempt hợp lệ); guard timestamp out-of-order → persist `secomm_carrier_tracking_state` (normalized + raw) → cập nhật `Track.description` + shipment comment → dispatch `secomm_shipping_tracking_updated`, `secomm_shipment_carrier_{delivered,returned,delivery_failed}`. **Không bao giờ** đụng Magento order state. Cron engine dùng chung: `TrackingReconciliationService` (virtualType per carrier: fetcher + `carrierCode` + `batchSize`).

**Physical** — `StoreWeightConverter` chuyển store weight (`general/locale/weight_unit`: `kgs` ×1000, `lbs` ×453.59237, khác → exception) sang **gram** cho carrier; `ConfiguredDefaultPackageDimensions` (cm) chỉ dùng để **prefill** form package; snapshot vật lý persist vào `sales_shipment.packages` dưới key `secomm_physical` — retry replay snapshot, không tính lại.

**COD** — `ConfiguredCodPaymentMethodResolver` đọc `secomm_shippingcore/cod/payment_methods` (comma-separated, match exact case-sensitive, rỗng = không có gì là COD). Chỉ **identification** — carrier tự map tiền (`cod_amount`, `pick_money`).

**HTTP** — `CurlCarrierHttpClient` (connect 5s / total 15s, 1 exchange/call, không retry, không log payload) + `RetryPolicy` (`safeRead` retry NETWORK/SERVER_ERROR/TIMEOUT; create-order luôn single-attempt; 429 classify nhưng không retry). Lưu ý: `Secomm_Ghn` **chưa** dùng stack này (xem §10).

### 2.2 Nơi config

Admin: **Stores → Configuration → General → tab `Secomm` → Secomm Shipping Core** (section `secomm_shippingcore`, default-scope only).

| Config path | Type | Default | Ý nghĩa |
|---|---|---|---|
| `secomm_shippingcore/cod/payment_methods` | text | (rỗng) | Danh sách payment method code coi là COD, vd `cashondelivery` |
| `secomm_shippingcore/physical/default_package_length` | text (integer) | (rỗng) | Prefill chiều dài package (cm) |
| `secomm_shippingcore/physical/default_package_width` | text (integer) | (rỗng) | Prefill chiều rộng (cm) |
| `secomm_shippingcore/physical/default_package_height` | text (integer) | (rỗng) | Prefill chiều cao (cm) |

**Extension points (DI, mặc định rỗng — module composition đóng góp):** `serviceLevels`, `canonicalZones`, `fallbackRateProviders`, `externalAddressResolvers`, `fallbackEnabledByLevel`. Bình thường hoá ra: không cấu hình gì → không có service level, không zone, không fallback (inert là state hợp lệ).

### 2.3 Bảng DB

`secomm_carrier_tracking_state` — `entity_id` PK; `carrier_code` + `tracking_number` (UNIQUE); `shipment_entity_id`; `normalized_status` (index); `carrier_status_code/message/updated_at`; `last_synced_at` (index); `source` (`webhook|api`); timestamps.

---

## 3. Secomm_VietNamAddress — dữ liệu địa chỉ VN

### 3.1 Scheme model

- Hai scheme immutable: **`VN_ADMIN_2025`** (2 cấp: 34 tỉnh → 3.321 phường/xã) và **`VN_ADMIN_PRE_2025`** (3 cấp: 63 tỉnh → 699 quận/huyện → 10.595 phường/xã). Variant snapshot: `VN_ADMIN_PRE_2025_SNAPSHOT_2024` (63/696/10.035).
- Unit code là internal Secomm identity (`VNA25-…` / `VNAP25-…`) — không phải mã chính phủ, không phải mã carrier.
- **Runtime = swap model**: `directory_country_region` + `directory_region_city` chỉ chứa ĐÚNG MỘT scheme tại một thời điểm; scheme nào đang chạy do config `secomm_vietnam_address/general/active_scheme` quyết (chỉ được trust khi registry status = `CURRENT`).
- **Reference layer**: `secomm_vietnam_address_unit` giữ MỌI scheme từng import — kiến thức lịch sử sống sót qua các lần swap.
- **Mapping layer**: `secomm_vietnam_address_mapping` — edge có hướng giữa unit code (`SAME_AS | RENAMED_TO | MERGED_INTO | SPLIT_INTO`, có `is_primary` cho candidate ưu tiên). Resolution theo cardinality: 1 edge → `MAPPED`, >1 → `AMBIGUOUS` (đề cử, không tự chọn), 0 → `UNMAPPED`. 924 ward PRE-2025 không có edge → UNMAPPED hợp lệ.
- Baseline mapping `VN_ADMIN_PRE_2025_TO_2025_mapping.csv` (10.064 edge, reviewed 2026-09-04) **tự import qua `setup:upgrade`**.

### 3.2 Workflow import / swap scheme

```bash
# Validate trước (không ghi) — report kèm guard_violations (bảng carrier chặn swap)
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --swap --dry-run

# Swap runtime sang scheme khác (purge + import + snapshot + flip config, 1 transaction)
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --swap

# Chỉ refresh scheme đang chạy (upsert theo code, giữ region_id/city_id)
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_2025

# Populate scheme lịch sử vào reference layer (không đụng runtime/active_scheme)
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --reference-only

# Mapping edges
bin/magento secomm:vietnam-address:import-mapping <file.csv> [--dry-run]
bin/magento secomm:vietnam-address:validate-mapping [--file <path>]

# Backfill parent_code level-2 (idempotent)
bin/magento secomm:vietnam-address:hierarchy:repair [--scheme <code>]
```

Fresh install: `ImportVnAdmin2025SchemePatch` tự bootstrap toàn bộ từ `VN_ADMIN_2025_import.csv` qua `setup:upgrade`; các patch sau đó populate PRE-2025 + snapshot 2024 vào reference layer — **không cần chạy CLI tay khi deploy**.

### 3.3 Ward validation plugins (neutralise + log, không chặn save)

Điểm bắn (toàn bộ VN-only, ward không khớp → xoá `city`/`destCity` trước khi persist + warning log):

| Injection point | Plugin |
|---|---|
| Storefront cart shipping estimate | `Plugin\Cart\ValidateVietNamWard` (trên `Magento\Shipping\Model\Shipping`) |
| Customer address save (admin) | `Plugin\Customer\Address\ValidateVietNamWard` |
| Store Information / Shipping Origin config | `Plugin\Adminhtml\Config\ValidateVietNamWard` (trên `Magento\Config\Model\Config`) |
| Admin order create/edit (quote save) | `Plugin\Quote\ValidateVietNamWard` |
| Order address edit | `Plugin\Sales\ValidateVietNamWard` |
| MSI source form | `Plugin\Inventory\ValidateVietNamWard` |

⚠️ Đổi scheme (swap) làm hỏng name-matching của address đã lưu (pre-launch accepted; fix dài hạn = §23 unit-code snapshot).

### 3.4 Nơi config

| Config path | Default | Ghi chú |
|---|---|---|
| `secomm_vietnam_address/general/active_scheme` | `VN_ADMIN_2025` | Backend model **chặn sửa tay** nếu scheme chưa install (registry CURRENT); thuộc default scope. Do CLI import quản lý |

Bảng: `secomm_vietnam_address_scheme` (registry + status label), `secomm_vietnam_address_unit` (reference layer), `secomm_vietnam_address_mapping` (edges). ACL: `Secomm_VietNamAddress::config`.

---

## 4. Secomm_AddressDropdown — dropdown địa chỉ

### 4.1 Workflow

- **Hierarchy**: `directory_region_city` đệ quy qua `parent_city_id` (sub_city layer đã retire); lọc theo `secomm_address_profile_location`.
- **Profile engine**: profile là XML ship theo code (`etc/address_profiles.xml`, merge theo `profile code`, cache id `secomm_addressdropdown_address_profiles`). Module này chỉ khai profile `default` (region + city depth 1). `Secomm_VietNamAddress` đóng góp 2 profile VN: `vn_admin_2025` (tỉnh → phường) và `vn_admin_pre_2025` (tỉnh → huyện → phường).
- **Data feeder duy nhất** là CLI `secomm:vietnam-address:import` của VietNamAddress — module này chỉ render/đọc.
- **GraphQL** (dùng cho Hyvä checkout): `addressSchema(input)` — levels cho country/profile; `addressLocations(input)` — node đệ quy (yêu cầu đúng một trong `region_id`/`parent_city_id` + `profile_code`); `GetListCity` (deprecated).
- **Admin**: CRUD City/Region/Country tại menu quản lý địa chỉ (ACL `Secomm_AddressDropdown::listing` → `management`); import/export entity `address_dropdown` (sample `Files/Sample/address_dropdown.csv`).
- Đồng thời inject frontend model dropdown lên 2 field core: `general/store_information/city` và `shipping/origin/city` (dropdown ward phụ thuộc).

### 4.2 Nơi config (store-scoped đầy đủ)

| Config path | Default | Ý nghĩa |
|---|---|---|
| `address/general/enable` | (không default) | Bật/tắt dropdown |
| `address/general/renderer` | `legacy` | `legacy` = template region→city cố định; `schema` = renderer theo Address Profile (per-store A/B cutover) |
| `address/profiles/mapping` | (không default) | Serialized array `countryId => profile_code`, vd `a:1:{s:2:"VN";s:13:"vn_admin_2025";}`. Country không map → field Magento native |

⚠️ Audit note: `system.xml` tham chiếu resource `Secomm_AddressDropdown::config` nhưng `acl.xml` chỉ khai `::listing`/`::management` — menu dùng thêm `Secomm_AddressDropdown::AddressDropdown`, `::country` cũng chưa khai. Xem §10.

---

## 5. Secomm_Ghn — carrier GHN

### 5.1 Kiến trúc & luồng chính

```text
Secomm_Ghn → Secomm_ShippingCore → Secomm_VietNamAddress   (KHÔNG depend legacy Secomm_GiaoHangNhanh/GhnAddressMapper)
```

- **Rating**: destination canonical (`VN_ADMIN_2025`) → ShippingCore handoff về `VN_ADMIN_PRE_2025` (capability RATE = PRE-2025 + UNIT_ID) → mapping → `district_id + ward_code` legacy → GHN Calculate Fee. `service_type_id`: < 20.000 g single package → `2` (light), ngược lại `5` (heavy, kèm `items[]` per package).
- **Create Order**: handoff `VN_ADMIN_2025` + TEXT_NAME → mapping sang tên GHN 2025 → `is_new_to_address=true`. Rating và create **không reuse** resolution path.
- **Fail closed**: mapping miss = exception (`GhnMappingNotFoundException`), không fuzzy name runtime, không magic fallback rate, empty body = exception.

**Shipment lifecycle (create)** — `GhnShipmentCreationService::createForShipment()`:
1. Idempotency: `client_order_code = 'GHNS' + shipmentId`; row `SUBMITTED` có order code → no-op.
2. Ghi `PENDING` **trước** khi gọi GHN; thiếu physical data (posted packages hoặc snapshot `secomm_physical`) → `INVALID_PARCEL` (không thay bằng default).
3. Limit per package: ≤ 50.000 g, mỗi cạnh ≤ 200 cm (rate-time limit GHN thực tế 150 cm — sandbox observed).
4. Stage-1 handoff CREATE + Stage-2 mapping 2025; thiếu tên mới → `PROVIDER_MAPPING_MISSING`.
5. Payload: `shop_id` (header), `payment_type_id`, `required_note`, `content`; **KHÔNG gửi** `cod_amount`/`insurance_value`/`order_value` (policy COD/insurance thuộc upstream).
6. POST `v2/shipping-order/create-order` **single attempt**; HTTP 200 nhưng thiếu `order_code` → row `UNKNOWN` (không tạo blind second order).
7. Kết quả: `SUBMITTED` (kèm fee, expected delivery) | `FAILED` (business/mapping) | `UNKNOWN` (technical). Track GHN được attach như native shipment track (title "GHN").

Observer tạo tự động: event `sales_order_shipment_save_commit_after` → `GhnShipmentCreateObserver` (idempotent, không bao giờ throw, gate raw method prefix `secomm_ghn_`).

**Tracking** — hai nguồn, cùng một processor ShippingCore:
- **Webhook** `POST /secomm_ghn/webhook/tracking` (frontend route; frontName `secomm_ghn` vì legacy `ghn` đã bị chiếm). Auth = **shared-secret header**: merchant tạo custom header `X-Secomm-Ghn-Secret` trên portal GHN với đúng giá trị `carriers/secomm_ghn/webhook_secret`; Magento so sánh `hash_equals`; rỗng/sai → 401. Chỉ xử lý `Type: SWITCH_STATUS`; loại khác → ack `unsupported_event_type`; exception bất ngờ → 500 để GHN retry.
- **Cron** `secomm_ghn_tracking_refresh` (mỗi phút, group default) — chỉ chạy khi `tracking_refresh_enabled=1`; re-sync row có `last_synced_at` cũ hơn `tracking_refresh_threshold_hours` (mặc định 6h), batch 50, qua Order Info API.

Status mapping đầy đủ (`GhnStatusMapper`): `ready_to_pick`→CREATED · `picking|money_collect_picking`→PICKING · `picked`→PICKED_UP · `storing|transporting|sorting`→IN_TRANSIT · `delivering|money_collect_delivering`→OUT_FOR_DELIVERY · `delivered`→DELIVERED · `delivery_fail`→DELIVERY_FAILED · `waiting_to_return|return|return_transporting|return_sorting|returning|return_fail`→RETURNING · `returned`→RETURNED · `cancel`→CANCELLED · `lost`→LOST · `damage|scrap`→DAMAGED · khác →UNKNOWN.

**Cancel / Return** — admin POST (redirect về shipment view, có notifier + reconcile tracking):
- Cancel: `/admin/secomm_ghn/shipment/cancel` — `reason_code` bắt buộc: `GHN-CO001` (pickup overdue) | `GHN-CO002` (out of stock) | `GHN-CO003` (customer cancelled) | `GHN-CANCEL-OTHER`; ACL `Secomm_Ghn::cancel_shipment`.
- Return (force R2S): `/admin/secomm_ghn/shipment/returnShipment` — chỉ khi GHN đang `delivery_fail | storing | waiting_to_return | return`; ACL `Secomm_Ghn::return_shipment`.

**Rate adjustment (merchant buffer)** — chỉ áp lên rate SUCCESS (không đụng parcel eligibility): bật `rate_adjustment_enabled`, chọn `fixed` (VND) | `percent` (%), `rate_adjustment_value`, làm tròn `none|1000|5000` (ceil theo step), apply `carrier_rate_only` (mặc định — GHN chỉ điều chỉnh rate GHN) | `carrier_and_fallback` (chờ bridge composition).

### 5.2 Address mapping lifecycle (dataset)

```text
GHN API → secomm:ghn:address:export (--scheme --dir --dataset-version)     # CSV deterministic + manifest.json (sha256)
      → OFFLINE review (đối chiếu canonical Secomm CSVs)                    # APPROVED / REVIEW_REQUIRED / UNRESOLVED / AMBIGUOUS
secomm:ghn:address:import (--dir)                                          # CHỈ row APPROVED được activate (UPSERT, fail-loud)
secomm:ghn:address:audit (--scheme --format=table|json)                    # production_ready gate
secomm:ghn:address:suggest (--scheme)                                      # workfile đề xuất — KHÔNG TỰ approve
secomm:ghn:address:sync (--scheme [--dry-run])                             # kéo GHN master data (unit mất → DISABLED)
```

- Mapping production **không bao giờ** sinh tự động; mọi write APPROVED chỉ qua importer.
- Bundled `data/` = dataset **v1.0.0** (2026-09-11, sandbox): master 2025 = 3.355 row, master PRE-2025 = 12.772, mapping 2025 = 3.355, mapping PRE-2025 = 10.794; import đầu tiên không cần GHN API.
- Identity portable: Secomm `scheme_code + unit_code` ↔ GHN `scheme_code + provider_key` — không có DB id trong file.

### 5.3 Nơi config

Admin: **Stores → Configuration → Sales → Delivery Methods → GHN (Giao Hàng Nhanh)** (section `carriers`, group `secomm_ghn`).

| Config path | Default | Ghi chú |
|---|---|---|
| `carriers/secomm_ghn/active` | `0` | Bật carrier |
| `carriers/secomm_ghn/title` | `GHN` | Tiêu đề carrier |
| `carriers/secomm_ghn/name` | `GHN Delivery` | Tên method hiển thị checkout |
| `carriers/secomm_ghn/sort_order` | `20` | Thứ tự |
| `carriers/secomm_ghn/showmethod` | `0` | Hiện method khi không applicable |
| `carriers/secomm_ghn/sallowspecific` | `1` | 0 = all countries, 1 = specific |
| `carriers/secomm_ghn/specificcountry` | `VN` | Country được phục vụ |
| `carriers/secomm_ghn/specificerrmsg` | (chuỗi chuẩn) | Error message |
| `carriers/secomm_ghn/environment` | `sandbox` | `sandbox` = `dev-online-gateway.ghn.vn/shiip/public-api`; đổi atomic per store |
| `carriers/secomm_ghn/api_token` | — | obscure + backend Encrypted |
| `carriers/secomm_ghn/shop_id` | — | Shop ID trên GHN portal |
| `carriers/secomm_ghn/origin_district_id` | (rỗng) | Optional GHN legacy district id cho `from_district_id`; rỗng = GHN giá theo địa chỉ shop |
| `carriers/secomm_ghn/payment_type` | `1` | **1 = Shop pays** (mặc định đúng — shipping đã thu ở checkout); 2 = Buyer pays (sẽ double-charge). Không liên quan COD |
| `carriers/secomm_ghn/required_note` | `CHOXEMHANGKHONGTHU` | Ghi chú bắt buộc trên đơn GHN |
| `carriers/secomm_ghn/webhook_secret` | (rỗng) | **Default+Website scope**. Rỗng = mọi callback bị 401. Đây là shared-secret header, KHÔNG phải signature GHN |
| `carriers/secomm_ghn/tracking_refresh_enabled` | `0` | Default-only. Bật cron re-sync tracking |
| `carriers/secomm_ghn/tracking_refresh_threshold_hours` | `6` | Default-only. Ngưỡng stale (giờ) |
| `carriers/secomm_ghn/debug` | `0` | Default-only. Log payload đã scrub (token luôn bị xoá) → `var/log/secomm_ghn.log` |
| `carriers/secomm_ghn/connection_timeout` | `10` | Default-only (giây) |
| `carriers/secomm_ghn/request_timeout` | `30` | Default-only (giây) |
| `carriers/secomm_ghn/rate_source_mode` | `CARRIER_WITH_FALLBACK` | `CARRIER_ONLY` / `CARRIER_WITH_FALLBACK` / `FALLBACK_ONLY` (không gọi GHN RATE API) |
| `carriers/secomm_ghn/address_resolution_policy` | `FALLBACK` | RATE-only: `STRICT` / `FALLBACK` / `PICK_PRIMARY` (opt-in dùng candidate primary PRE-2025) |
| `carriers/secomm_ghn/rate_adjustment_enabled` | `0` | Buffer giá sau khi có rate thành công |
| `carriers/secomm_ghn/rate_adjustment_type` | `fixed` | `fixed` (VND) / `percent` |
| `carriers/secomm_ghn/rate_adjustment_value` | `0` | Giá trị điều chỉnh |
| `carriers/secomm_ghn/rate_adjustment_rounding` | `none` | `none` / `1000` / `5000` (ceil theo bước) |
| `carriers/secomm_ghn/rate_adjustment_apply_to` | `carrier_rate_only` | `carrier_rate_only` / `carrier_and_fallback` |
| `carriers/secomm_ghn/address_dataset` | (note) | Hiển thị info dataset bundled (frontend model `DatasetInfo`) |

> README của module ghi `payment_type` default `2` — đã lỗi thời; `etc/config.xml` hiện tại là `1` (code-verified).

### 5.4 Cache / log / cron / ACL

- Cache type `secomm_ghn_mapping` — chỉ cache resolution THÀNH CÔNG (flush khi import mapping mới).
- Log `var/log/secomm_ghn.log` (channel riêng, token scrub).
- Cron `secomm_ghn_tracking_refresh` `* * * * *` (runtime-gated bởi config).
- ACL: `Secomm_Ghn::ghn` → `shipment_actions` → `cancel_shipment` / `return_shipment`; khai thêm `Secomm_Ghn::config` (section carriers thực tế protected bởi Magento_Sales).

---

## 6. Launchpad_MageplazaTableRate — TableRate + fallback

### 6.1 Runtime model

```text
Magento Shipping Framework (plugin DUY NHẤT trên collectRates — storefront + REST + GraphQL + admin)
   ├─ MethodVisibilityFilter     — ẩn method có show_to_customer=0
   └─ FallbackCoordinator        — theo từng fallback group (use_as_fallback=1):
         không member nào tham gia        → không fallback
         bất kỳ member SUCCESS            → suppress
         ≥1 member policy-eligible        → cộng thêm giá TableRate nội bộ (không bao giờ report là carrier SUCCESS)
```

- TableRate là **nguồn GIÁ fallback**, không phải eligibility engine; membership không bao giờ ẩn carrier.
- Method không có settings row = hành vi Mageplaza native (visible, không fallback) — zero regression.
- Bucket code fallback: `mptr_<method_id>`.

### 6.2 City/Area dimension

- Ràng buộc tùy chọn per rate row: bảng `launchpad_mptablerate_rate_city` (`rate_id` + `city_code` = `directory_region_city.code` / Secomm unit code).
- Precedence 3 tier: (1) row đúng destination node → (2) row wildcard đúng region đó → (3) wildcard còn lại. Chỉ thu hẹp LOCATION scope; trong tier vẫn SUM/MIN/MAX như Mageplaza.
- Destination không resolve được (AMBIGUOUS/UNMAPPED/non-VN) → city row không bao giờ khớp, wildcard tier nguyên vẹn.
- Không có city row nào = 100% hành vi legacy.

### 6.3 Nơi config

**Không có system config nào cho module này** (config.xml + system.xml cố tình rỗng — global mode `launchpad_mptablerate/general/mode` đã bị xoá). Mọi cài đặt nằm per-method:

**Sales → Table Rate Shipping (menu Mageplaza) → Shipping Methods → edit method → tab "Launchpad Settings":**

| Field | Bảng | Ý nghĩa |
|---|---|---|
| Show to Customer | `launchpad_mptablerate_method_setting.show_to_customer` | Phơi method ở checkout độc lập (cần carrier Mageplaza active) |
| Use as Fallback | `...use_as_fallback` | Method là fallback group, member realtime gate giá emergency |
| Fallback Members | `launchpad_mptablerate_method_member` | Chọn realtime methods làm member (options động, loại trừ `mptablerate`) |

**Rate form** (Add TableRate row): field **City / Area** là cascading select (Region → City/Area, AJAX `launchpad_mptablerate/city/options?region=<id>`); để trống = wildcard toàn region. Save-time validation: `city_code` không tồn tại hoặc không thuộc region của row → reject (không bao giờ tự biến thành wildcard).

**Endpoint admin tự phục vụ (route `launchpad_mptablerate`, ACL `Mageplaza_TableRateShipping::method`):**

| URL | Công dụng |
|---|---|
| `GET launchpad_mptablerate/city/options?region=<region_id>` | JSON options cho cascading select |
| `GET launchpad_mptablerate/city/referenceCsv[?region=<region_id>]` | `city_reference.csv` — cột `country_code, region_code, region_name, city_code, city_name, parent_city_code, parent_city_name` (UTF-8+BOM) |
| `GET launchpad_mptablerate/city/importTemplate[?region=<region_id>]` | `tablerate_import_template.csv` — header = bộ cột importer + `city_name` informational + 2 row ví dụ |

### 6.4 CSV import rates

- Import qua Mageplaza grid: `mptablerate/import/process` / `mptablerate/method/importProcess` — nhưng dùng **importer preference của Launchpad**, bộ cột (thứ tự = contract):
  `name, country_id, region, weight_from, weight_to, subtotal_from, subtotal_to, qty_from, qty_to, product_fixed_rate, product_percentage_rate, weight_fixed_rate, order_fixed_rate, delivery, postcode, postcode_from, postcode_to, shipping_group, city_code` (+ `city_name` template-only, bị strip trước khi persist).
- Fix round-trip của Mageplaza: chấp nhận `postcode*`, `shipping_group` vốn có trong export nhưng importer gốc không nhận.
- Identity = `city_code`; `city_name` chỉ hiển thị; wildcard = `city_code` rỗng (+ `region=*` trong CSV).
- `city_code` lạ → row đó fail validation (báo trong kết quả import); city không thuộc region của row → reject.

### 6.5 Config Mageplaza gốc (vendor, tham chiếu)

Admin: **Stores → Configuration → Sales → Delivery Methods → Table Rate Shipping by Mageplaza** (`carriers/mptablerate/*`). Điểm chính:

| Path | Default | Ghi chú |
|---|---|---|
| `carriers/mptablerate/active` | `0` | Bật carrier TableRate |
| `carriers/mptablerate/title` | `Table Rate` | Store-scoped được |
| `carriers/mptablerate/sallowspecific` / `specificcountry` | `0` / — | Phạm vi country |
| `carriers/mptablerate/include_virtual_price` | `1` | Tính sản phẩm ảo vào subtotal |
| `carriers/mptablerate/volume_weight` | `weight` | `weight` / `v_attribute` (L×W×H) / `user_attribute` |
| `carriers/mptablerate/v_attribute` | — | Thuộc tính thể tích (V hoặc LxWxH) |
| `carriers/mptablerate/user_attribute_1..3` | `ts_dimensions_length/width/height` | Thuộc tính phụ |
| `carriers/mptablerate/shipping_factor` | `5000` | Hệ số dimensional weight (store-scope được) |
| `carriers/mptablerate/specificerrmsg` | (chuỗi chuẩn) | Store-scope được |
| `carriers/mptablerate/showmethod` / `sort_order` | `0` / — | |

Bảng vendor: `mageplaza_tablerate_method` (`calculate_rule` = SUM/MIN/MAX, `store_id`, `customer_group`, `labels`), `mageplaza_tablerate_rate` (điều kiện weight/subtotal/qty/postcode/shipping_group + 4 cột giá). ACL: `Mageplaza_TableRateShipping::mptablerate` → `::method`.

---

## 7. CLI command reference

### Secomm_VietNamAddress

```bash
secomm:vietnam-address:import --scheme=<VN_ADMIN_2025|VN_ADMIN_PRE_2025> [--swap] [--rebuild] [--reference-only] [--dry-run]
secomm:vietnam-address:import-mapping <file> [--dry-run]
secomm:vietnam-address:validate-mapping [--file <path>]
secomm:vietnam-address:hierarchy:repair [--scheme=<code>]
```

- `--swap`: bắt buộc khi đổi scheme (purge runtime VN data trước import; FK-guard với bảng carrier mapping). `--rebuild`: purge + xoá snapshot scheme, import từ đầu (city_id mới). `--reference-only`: chỉ reference layer (mutually exclusive với `--swap`/`--rebuild`). Exit 1 khi report có error.
- `import-mapping`: header `source_scheme,source_code,target_scheme,target_code,relation_type[,is_primary]`; validate orphan/ambiguity/duplicate; upsert.
- `validate-mapping`: không `--file` = validate DB; có `--file` = chỉ validate file.

### Secomm_Ghn

```bash
secomm:ghn:address:sync    --scheme=<GHN_ADMIN_2025|GHN_ADMIN_PRE_2025> [--dry-run]
secomm:ghn:address:audit   --scheme=<VN_ADMIN_2025|VN_ADMIN_PRE_2025> [--format=table|json]
secomm:ghn:address:suggest --scheme=<canonical> [--export-dir <dir>] [--output <path>] [--review-output <path>]
secomm:ghn:address:import  [--dir <path>] [--scheme <canonical>] [--master-only] [--mapping-only] [--dry-run]
secomm:ghn:address:export  [--scheme <ghn>] [--dir <path>] [--dataset-version <label>]   # default dir <var>/secomm_ghn/export
secomm:ghn:shipment:retry  <shipment_id>          # idempotent — resubmit đúng client_order_code
secomm:ghn:shipment:cancel <shipment_id> <GHN-CO001|GHN-CO002|GHN-CO003|GHN-CANCEL-OTHER> [--reason <text>]
secomm:ghn:shipment:return <shipment_id>          # force R2S — chỉ khi GHN đang delivery_fail/storing/waiting_to_return/return
```

- `audit` luôn exit 0; `production_ready: NO` chỉ mang tính thông tin — không tự approve gì.
- `suggest` ghi 2 file: workfile import-compatible + review artifact (`REVIEW_REQUIRED/AMBIGUOUS/UNRESOLVED`).
- `import` mặc định đọc bundled `data/` (bootstrap); `--master-only`/`--mapping-only` mutually exclusive.

---

## 8. Bảng dữ liệu tổng hợp

| Bảng | Module | Nội dung |
|---|---|---|
| `secomm_carrier_tracking_state` | ShippingCore | Tracking state normalized + raw (UNIQUE carrier+number) |
| `secomm_vietnam_address_scheme` | VietNamAddress | Registry scheme (`CURRENT/HISTORICAL/FUTURE` là label) |
| `secomm_vietnam_address_unit` | VietNamAddress | Reference layer mọi scheme (UNIQUE scheme+code) |
| `secomm_vietnam_address_mapping` | VietNamAddress | Edges pre-2025↔2025 (+`is_primary`) |
| `directory_region_city` / `_name` | AddressDropdown | Hierarchy runtime (code `VNA25-*`/`VNAP25-*`, đệ quy `parent_city_id`) |
| `secomm_address_profile_location` | AddressDropdown | Membership profile ↔ region/city |
| `secomm_ghn_address_unit` | Ghn | GHN master data dual-scheme (UNIQUE scheme+provider_key; mất row → DISABLED) |
| `secomm_ghn_address_mapping` | Ghn | Bridge canonical↔GHN, chỉ APPROVED (UNIQUE scheme+unit) |
| `secomm_ghn_shipment` | Ghn | Trạng thái create: `PENDING/SUBMITTED/FAILED/UNKNOWN`, `client_order_code` UNIQUE |
| `launchpad_mptablerate_method_setting` | Launchpad | `show_to_customer`, `use_as_fallback` per method |
| `launchpad_mptablerate_method_member` | Launchpad | Member của fallback group (UNIQUE method+carrier+method) |
| `launchpad_mptablerate_rate_city` | Launchpad | Ràng buộc city per rate row |
| `mageplaza_tablerate_method` / `_rate` | Mageplaza | Method + rate rows (SUM/MIN/MAX, điều kiện) |

---

## 9. Playbook theo case

### Case 1 — Fresh install (bootstrap toàn stack)

```bash
bin/magento module:enable Secomm_AddressDropdown Secomm_VietNamAddress Secomm_ShippingCore Secomm_Ghn Launchpad_MageplazaTableRate
bin/magento setup:upgrade          # patches tự import VN_ADMIN_2025 + PRE-2025 reference + mapping baseline + GHN dataset v1.0.0
bin/magento setup:di:compile && bin/magento setup:static-content:deploy -f
bin/magento secomm:ghn:address:audit --scheme VN_ADMIN_2025      # xác nhận dataset
```

Config tối thiểu: `address/profiles/mapping` = `a:1:{s:2:"VN";s:13:"vn_admin_2025";}` (store scope) để checkout dùng dropdown VN 2025.

### Case 2 — Bật GHN sandbox

| Path | Giá trị |
|---|---|
| `carriers/secomm_ghn/environment` | `sandbox` |
| `carriers/secomm_ghn/api_token` | token sandbox (obscure, encrypted) |
| `carriers/secomm_ghn/shop_id` | shop id sandbox |
| `carriers/secomm_ghn/active` | `1` |
| `carriers/secomm_ghn/specificcountry` | `VN` |
| `carriers/secomm_ghn/payment_type` | `1` (Shop pays) |
| `carriers/secomm_ghn/debug` | `1` (chỉ khi cần tra log `var/log/secomm_ghn.log`) |
| `carriers/secomm_ghn/rate_source_mode` | `CARRIER_ONLY` nếu chưa cấu hình fallback |

Kiểm tra rate: cart có destination VN (region + ward hợp lệ) → method "GHN Delivery" hiện với giá realtime. Hạn mức rate-time: mỗi cạnh ≤ 150 cm; nặng ≥ 20 kg hoặc multi-package → `service_type_id=5`.

### Case 3 — GHN production

1. Đổi `environment` = `production` + `api_token`/`shop_id` production (per-website scope nếu nhiều store).
2. Chạy lại dataset lifecycle nếu cần refresh: `secomm:ghn:address:sync --scheme GHN_ADMIN_2025` → `audit` → chỉ coi production ready khi coverage đạt.
3. Webhook: tạo custom header `X-Secomm-Ghn-Secret` trên GHN portal với giá trị trùng `carriers/secomm_ghn/webhook_secret` (đặt secret trước — secret rỗng = 401 hết callback).
4. Bật `tracking_refresh_enabled=1` (webhook vẫn là nguồn chính; cron là lưới an toàn 6h).
5. QC end-to-end: đặt đơn → tạo shipment → GHN order SUBMITTED → webhook đổi trạng thái → track description cập nhật.

### Case 4 — Fallback TableRate (giá khẩn khi GHN lỗi kỹ thuật)

1. Config `secomm_shippingcore` — không cần gì (fallback pool được Launchpad đóng góp tự động).
2. Mageplaza: `carriers/mptablerate/active=1` + import rates CSV (Case 5).
3. Sales → Table Rate Methods → method fallback → **Launchpad Settings**: `Use as Fallback = Yes`; `Fallback Members` = `GHN (secomm_ghn)`.
4. GHN: `rate_source_mode = CARRIER_WITH_FALLBACK`.
5. Hành vi: GHN TECHNICAL_FAILURE (timeout/5xx) → giá TableRate xuất hiện; GHN UNAVAILABLE thuần / config lỗi → KHÔNG fallback (fail-closed); GHN SUCCESS → fallback bị suppress.

### Case 5 — Import TableRate CSV (có/không City)

1. Sales → Table Rate Methods → edit method → rate form → tải **Import Template** (có sẵn row ví dụ + wildcard) và (tuỳ chọn) **City Reference CSV**.
2. Điền giá theo cột; `city_code` lấy từ City Reference CSV (hoặc chọn bằng form). Row wildcard: `city_code` rỗng + `region=*`.
3. Import qua Mageplaza CSV import (`mptablerate/import/process`); kết quả liệt kê row fail (city lạ / sai region).
4. Kiểm tra storefront: đặt destination thuộc city đó → giá city tier; destination khác region → wildcard tier.

### Case 6 — Ẩn/hiện TableRate method ở checkout

- Ẩn khỏi checkout nhưng vẫn làm fallback: `Show to Customer = No` + `Use as Fallback = Yes` (member gating vẫn hoạt động).
- Method thường (bán trực tiếp): để trống settings row hoặc `Show to Customer = Yes`.

### Case 7 — Đổi scheme hành chính VN (hiện 2025 → pre-2025)

```bash
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --swap --dry-run   # đọc guard_violations trước
bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --swap
bin/magento cache:flush
```

Lưu ý: ĐỔI SCHEME LÀM HỎNG NAME-MATCHING của address đã lưu (ward validation sẽ neutralise `city`); dropdown/profile tự theo scheme mới qua `address/profiles/mapping` (CLI tự cập nhật). Không swap khi đang có mapping carrier tham chiếu runtime rows (`guard_violations` non-empty).

### Case 8 — Xử lý shipment tạo GHN thất bại

```bash
bin/magento secomm:ghn:shipment:retry <shipment_id>          # FAILED/UNKNOWN — idempotent, đúng client_order_code
bin/magento secomm:ghn:shipment:cancel <shipment_id> GHN-CO003 --reason "khách hủy"
bin/magento secomm:ghn:shipment:return <shipment_id>
```

Row `UNKNOWN` (timeout/5xx lúc create) — kiểm tra trên GHN portal trước khi retry (retry an toàn do idempotent code). Cancel/Return từ admin grid shipment view cũng được (ACL tương ứng).

### Case 9 — Tracking không cập nhật

1. Log `var/log/secomm_ghn.log` (bật `debug` nếu cần).
2. Webhook: xác nhận URL công khai `POST /secomm_ghn/webhook/tracking` + header secret khớp (mismatch → GHN nhận 401).
3. Cron: `tracking_refresh_enabled=1`, kiểm tra `bin/magento cron:run` / cron schedule `secomm_ghn_tracking_refresh` mỗi phút; row stale > `tracking_refresh_threshold_hours` mới được re-sync, batch 50/lượt.
4. Trạng thái terminal (DELIVERED/RETURNED/CANCELLED/LOST/DAMAGED) là sticky — webhook/cron sẽ không đổi nữa (by design).

### Case 10 — Khai báo COD

- `secomm_shippingcore/cod/payment_methods` = các code payment COD (vd `cashondelivery`), comma-separated, exact match.
- ⚠️ Hiện tại **chưa carrier nào gửi tiền COD sang GHN/GHTK** (`cod_amount`/`pick_money` chưa implement) — config chỉ dùng để identification.

### Case 11 — Zone chặn carrier theo destination (nâng cao, DI)

Zones/service levels/fallback per-level là **DI composition**, không có admin UI: đóng góp qua `canonicalZones` / `serviceLevels` / `fallbackEnabledByLevel` trong `etc/di.xml` của module composition. Không cấu hình gì = mọi destination hợp lệ đều được phục vụ (scope `ALL`).

---

## 10. Kết quả audit — trạng thái & lưu ý

### 10.1 Trạng thái GHN (từ CHANGELOG 0.9.0-draft, 2026-09-18)

| Phase | Trạng thái |
|---|---|
| GHN-A skeleton | done (TL approve 2026-09-10) |
| GHN-B master data + mapping | in progress (dataset v1.0.0 bundled, chờ TL review) |
| GHN-C rate | **dev-complete 2026-09-14 — chờ TL review + QC L3** |
| GHN-D create + cancel/return | **dev-complete 2026-09-15 — chờ TL review** |
| GHN-E1 webhook tracking + cron | **dev-complete 2026-09-15 — chờ TL review** |
| GHN-F cutover legacy | proposed |

**Kết luận: chưa production-ready.** An toàn mặc định: `active=0`, `tracking_refresh_enabled=0`, environment `sandbox`.

### 10.2 Finding kỹ thuật (audit 2026-09-21)

1. **`CarrierRateExecutionService` chưa có consumer runtime** — GHN tự orchestrate handoff→calculator; phần composition dùng execution service chờ upstream freeze. Contracts đã hoàn chỉnh + tested.
2. **Hai HTTP stack song song**: ShippingCore cung cấp `CurlCarrierHttpClient` + `RetryPolicy` (connect 5s/total 15s) nhưng `GhnApiClient` đang dùng `Magento\Framework\HTTP\Client\Curl` với timeout riêng (10s/30s) + retry GET riêng (3 lần, backoff 500ms×attempt). Không phải bug, nhưng cần thống nhất về sau.
3. **COD một chiều chưa hoàn tất** — chỉ identification; không carrier gửi `cod_amount`.
4. **ACL id lệch** (`Secomm_AddressDropdown`): `system.xml` dùng `::config`, `menu.xml` dùng `::AddressDropdown`/`::country` — không khai trong `acl.xml` (chỉ có `::listing`/`::management`). Có thể gây 403/ẩn menu với role không phải admin full.
5. **Dataset file stale note** (từ BUG-ZTGGYZ): `VnSchemes::unitFile(PRE_2025)` còn trỏ `VN_ADMIN_PRE_2025_import.csv` trong khi dataset hiện hành là `..._SNAPSHOT_2024_import.csv` — re-import CLI scheme PRE-2025 sẽ đọc file cũ. Follow-up đã ghi nhận.
6. **README Ghn lệch 1 điểm**: `payment_type` default ghi `2`, thực tế config.xml = `1` (Shop pays — đúng chính sách đã thu shipping ở checkout).
7. **Webhook GHN là shared-secret header, không phải signature** — GHN không ký callback; bảo mật phụ thuộc secret strength + HTTPS. Secret rỗng = mọi callback 401 (fail closed, đúng thiết kế).
8. **Scope config GHN**: `debug`/`connection_timeout`/`request_timeout`/`tracking_*`/`address_dataset` chỉ default-scope; `webhook_secret` default+website. Nhớ set ở đúng scope khi thao tác.
9. **Audit an toàn trước đó (2026-08-28, re-audit 2026-09-03)**: cụm module address từng có 7/8 BLOCK legacy issue (SQLi/ACL/int-cast) ghi nhận CHƯA có ticket fix; batch YA2C0W sạch. Cần TL xác nhận trạng thái xử lý hiện tại (ngoài phạm vi audit tài liệu này).
10. **Fallback/zone/service-level inert theo mặc định** — zero provider/zone/level đăng ký là state hợp lệ; mọi hành vi fallback quan sát được đến từ `Launchpad_MageplazaTableRate` (per-method) hoặc DI composition.

### 10.3 Kiểm thử

```bash
php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --testsuite Magento_Unit_Tests_App_Code --filter 'Secomm\\ShippingCore'
# Launchpad_MageplazaTableRate: suite 67 green (TASK-JZXM66)
```

---

*Tài liệu sinh từ audit code ngày 2026-09-21; nguồn: README + CHANGELOG + `etc/*.xml` + `Console/Command` của 5 module. Khi đổi config/contract, cập nhật file này cùng PR.*
