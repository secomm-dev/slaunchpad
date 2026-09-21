# [SPIKE-9Z231Q] Secomm_Ghn — Kiến trúc module GHN canonical trên ShippingCore (audit legacy + GHN API compatibility matrix)

> **Trạng thái**: PROPOSAL — chờ TL/SA review trước khi implement bất kỳ production code nào.
> **Ngày**: 2026-09-08 · **Mode A** (audit/design, 0 production code) · **Parent**: FEAT-YA2C0W
> **Nguồn API**: developer.ghn.vn (docs EN, captured 2026-09-08) — mọi URL ghi ở §2.
> **Liên quan**: SPIKE-W273TB, TASK-AQT7V3 (Phase E-A contracts), TASK-5XDG1P (Phase E-B impl, chờ TL review), BUG-JBX3H9 (fail-closed GHN),
> DEC-FEATYA2C0W-003/-004, DEC-TASKNDASAD-001 (origin), DEC-TASK86NX9T-001 (tracking),
> DEC-TASK3F6QWZ-001/-002.
> **Cập nhật 2026-09-08 (v3)**: TL/SA **verified trực tiếp từ GHN data/runtime**: GHN operational
> flow yêu cầu **exact old ward** — old province + old district + current ward/name KHÔNG đủ.
> Same-district optimization REJECTED; E-B v2 (external resolver pool) trở thành MANDATORY;
> recommendation chốt: **A — KEEP FULL PRE_2025 RESOLUTION**. NO_MATCH classification mới
> (`classify_no_match.php` + `no-match-classification.json`).

---

## CONFIRMED ARCHITECTURE AFTER TL/SA CLARIFICATION (2026-09-08, v3)

**Recommendation chốt: `A — KEEP FULL PRE_2025 RESOLUTION`** — resolution phải đạt
**full address-unit granularity (province + district + exact old ward)**; không còn
phương án district-granular hay same-district optimization (rejected — xem §16).

Hai lớp identity tách biệt tuyệt đối:

| Lớp | Vai trò |
|---|---|
| `VN_ADMIN_2025` | Canonical/customer scheme của Launchpad (2 cấp, active scheme runtime) |
| `VN_ADMIN_PRE_2025` | **Secomm canonical historical representation** — intermediate scheme carrier-required cho GHN; phải resolve được ở **ward-level** (3 thành phần: province + district + exact old ward) |
| GHN ProvinceID / DistrictID / WardCode | **Provider-specific representation** — KHÔNG PHẢI identity của PRE_2025; chỉ tồn tại trong provider mapping (imported/audited offline) |

Runtime chain (mỗi bước chỉ biết ngay lớp trước của nó):

```text
VN_ADMIN_2025 (customer address)
    ↓ ShippingAddressResolutionManager (E-B — canonical reverse 2025→PRE_2025, EXACT old ward)
VN_ADMIN_PRE_2025 (full unit granularity; AMBIGUOUS/UNMAPPED → external resolver pool → fail closed)
    ↓ Secomm_Ghn provider mapping (scheme_code + unit_code → GHN operational IDs)
GHN ProvinceID + DistrictID + WardCode
    ↓
Available Services / Calculate Fee / Create Order
```

Quy tắc bắt buộc:

1. **`RESOLVED` đối với GHN = exact PRE_2025 province + district + ward khả dụng.** A
   district-only match KHÔNG được coi là resolved (TL/SA verified: GHN cần exact old ward).
2. `Secomm_Ghn` KHÔNG tự convert 2025→PRE_2025, không fuzzy match, không resolve ambiguity,
   không gọi VietMap. Nó chỉ consume resolved carrier-required address (`ResolvedShippingAddress`).
3. Deterministic required — first-match CẤM, no random candidate. Current unit map về nhiều
   PRE wards → `AMBIGUOUS`, không tự chọn.
4. Fail closed khi không resolve được exact ward (internal deterministic + external resolver
   đều không) — chi tiết §7.4.
5. **An external address resolver is operationally required for GHN** whenever internal
   ADMIN_2025→PRE_2025 resolution cannot identify an exact historical ward (93.26% theo §15).
   `Secomm_VietMap` = current preferred implementation candidate, đằng sau
   `ExternalAddressResolverInterface` (E-A pool) — dependency `VietMap → ShippingCore
   contract`, KHÔNG `Secomm_Ghn → VietMap`; Secomm_Ghn không biết resolver cụ thể nào được dùng.
6. **Primary KPI: `FULL_PRE_2025_ADDRESS_RESOLVED`** (province + district + exact old ward) —
   không dùng district-level 90.88% làm resolution rate (đó chỉ là supporting statistic).

Decision tree:

```text
VN_ADMIN_2025
      |
      v
Can map deterministically to EXACT VN_ADMIN_PRE_2025 ward?   [E-B internal resolver]
      |
   +--+--+
   |     |
  YES    NO
   |     |
   |     v
   |   External address resolver available?                    [ExternalAddressResolverPool — MANDATORY path]
   |        |
   |      +-+-+
   |      |   |
   |     YES  NO
   |      |   |
   |      v   v
   |   resolve  FAIL CLOSED
   |      |      (GHN method unavailable / no shipment)
   +------+      |
      |          |
      v          |
VN_ADMIN_PRE_2025 (exact ward: province + district + ward)
      |
      v
GHN provider mapping (scheme_code + unit_code → GHN_PROVINCE_ID/DISTRICT_ID/WARD_CODE)
      |
   +--+--+
   |     |
  hit   miss → FAIL CLOSED
   |
   v
GHN operational API (old-style province/district/ward)
```

---

## 1. Legacy functionality inventory

Hai legacy module, **reference-only** (không refactor/copy wholesale):

### 1.1 `Secomm_GiaoHangNhanh` (~100 file PHP) — tích hợp nghiệp vụ GHN

| Nhóm | Thành phần | Ghi chú evidence |
|---|---|---|
| Carrier | `Model/Carrier/GHN.php` (abstract) + `GHN/Standard` + `GHN/Express` — carrier codes `giaohangnhanh_standard` / `giaohangnhanh_express` | `collectRates` → `estimateShippingCost()` catch-all → `null` → method unavailable; **chọn service bằng NAME**: const `SERVICE_NAME_SHORT` = 'Hàng nhẹ'/'Hàng nặng' match `short_name` GHN (`Standard.php:18`, `Express.php:19`, `GHN.php:239`) — name-based identity (banned-pattern adj); **default fee ẩn `$shippingFee = 10`** trước khi call fee (`GHN.php:176-218`); stash `shipping_service_id/type_id` lên shipping address (L186-187); **KHÔNG cache** services/fee (2 API call mỗi collectRates) |
| canDisplay | Gate weight/dims/converted-mass theo `carriers/ghn/advanced_settings/<code>/*` (Express 50kg/200cm, Standard 5000kg/20000cm, per-item limits) + `Plugin/Shipping/Model/Shipping::beforeCollectRates` (sortOrder 22) copy product dims lên rate items | Plugin throw-swallow khi quote không có items |
| HTTP framework | `IntegrationBase/*` (top-level, KHÔNG dưới `Model/`) — generic stack: `Service/Command/{CommandPool,CommandInterface,CommandException,Result/ArrayResult}`, `Service/Http/{TransferFactory,Transfer(+Builder),Client/Curl,Converter/JsonToArray,ClientException}`, `Service/Config`, `Logger/Logger` (`bf_integration.log`) | Command template: `Model/Service/Command/ServiceCommand` (build → transfer → send → validate → handle → `ArrayResult` theo `resultKey`); `GhnCommandPool` (di.xml:91-104) đăng ký 8 command: `get_services`, `calculate_rate`, `cancel_order`, `get_order_info`, `synchronize_order`, `get_districts`, `get_provinces`, `get_wards` |
| Request builders | `Model/Service/Request/*`: `AbstractDataBuilder` + 8 builder (`ServicesDataBuilder`, `ShippingDetailsDataBuilder`, `SynchronizeOrderDataBuilder`, `CancelOrderDataBuilder`, `OrderInfoDataBuilder`, `GetDistricts/Provinces/WardsDataBuilder`) | Choke-point mapping địa chỉ: `AbstractDataBuilder::resolveGhnLocation()` (`AbstractDataBuilder.php:156-201` — region_id+city_id → district_id+ward_code qua `GhnAddressMapper`); BUG-JBX3H9 đã xóa toàn bộ fake-data branches + `is_develop_mode` |
| Response/Validator | `Model/Service/Response/` đúng 2 handler (`SynchronizeOrderHandler` — set `ghn_status`+`tracking_code` rồi `$order->save()`; `CancelOrderHandler` — set `ghn_canceling_status`); Validators `Model/Service/Validator/*` quyết valid CHỈ theo `message === 'Success'` (`AbstractResponseValidator.php:19-20`) — error text GHN bị vứt | GHN error chi tiết không bao giờ tới log/admin — điểm discard khi reimplement |
| Rate | `Helper/Rate.php` — VND → store base currency (BUG-YQT1FW: convert theo **base** currency, không display currency) | |
| Order sync | `Model/Service/OrderSyncService.php` + `Model/Queue/OrderSyncConsumer`, `OrderCancelConsumer` — topics `ghn.sync.order` / `ghn.cancel.order`, connection `db`; `sync_mode` async/direct; auto-shipment sau sync | `GHN_SERVICE` map service_id → Magento carrier code |
| Observers | `SalesOrderPlaceAfterObserver` lắng nghe **`checkout_onepage_controller_success_action`** (không phải `sales_order_place_after` — tên class misleading, `events.xml:3-5`), `SalesOrderCancelAfter` (`order_cancel_after`), `SalesShipmentSaveAfter` (`sales_order_shipment_save_after`) | Bật/tắt theo config `auto_sync_*`; KHÔNG có cron (`crontab.xml`/`cron_groups.xml` không tồn tại) |
| Webhook | `Controller/Webhook/ShippingUpdate` — `POST ghn/webhook/shippingUpdate` (frontend router frontName `ghn`), CSRF-exempt **+ không có signature/HMAC** (`validateForCsrf` luôn true, L192-195); payload legacy lowercase (`tracking_code`,`status`,…); `CARRIER_CODE_CANDIDATES` = `[standard, express, giaohangnhanh]` thử lần lượt (L107-111, L270-287) | Lưu `ghn_webhook_track` + update `ghn_status` trên `sales_order`/grid + comment + **mutate order state song song pipeline** (`handleOrderStatus` L344-361: delivered→complete, cancel→canceled, exception/damage/lost→state closed) — vi phạm DEC-TASK86NX9T-001 rule 6 |
| Tracking | `Model/Tracking/GhnStatusMapper.php` — **ĐÃ implements `CarrierStatusMapperInterface`** (không qua DI preference; inject concrete vào webhook controller `di.xml:313-317`); map 19 entry, miss → UNKNOWN (bao gồm `exception/damage/lost/scrap` → UNKNOWN) | Legacy map đã normalize đúng chuẩn pipeline → REUSE được; phần legacy riêng: `Model/TrackModel.php` + `GHN::getTrackingInfo()` đọc `ghn_webhook_track` table (fallback hiển thị), `getAllTracking()` collapse về phần tử cuối |
| Plugins | `Model/Plugin/Adminhtml/OrderView*` + Sales Order/Shipping | Nút "Sync GHN" admin, hiển thị trạng thái |
| Console | `Console/Generate*Commands` | Sinh dữ liệu hỗ trợ |
| DB | Bảng riêng: `ghn_webhook_track` (FK CASCADE sales_order), `secomm_giaohangnhanh_province/district/ward` (master data 3-cấp; **`ward_code` là INT unsigned** trong khi GHN WardCode là String có thể leading-zero — truncation/corruption risk); ALTER core: `sales_order` (+`ghn_status`,`tracking_code`,`ghn_canceling_status`), `sales_order_grid` (mirror), `quote_address` (+`district`,`shipping_service_id`,`shipping_service_type_id`); Extension attributes `district` (string) trên 4 interface; Setup patch `AddGhnOrderStatuses` | README §6 + `etc/db_schema.xml:4-95` |
| Frontend | `view/frontend/web/js/model/shipping-rates-validator*.js` + `view/.../shipping-rates-validation{,-express}.js`, `templates/address/edit.phtml` (district dropdown trên checkout address), layout `checkout_index_index`/`checkout_cart_index` | Bề mặt checkout gắn `quote_address.district` + service stash — phải vào transition plan §12 |
| Config | `giaohangnhanh_setting/general/*` (sandbox_flag **default 1**, api_token encrypted, shop_id, payment_type=2, note_code, district [kho, system.xml only], URLs (production `https://online-gateway.ghn.vn/shiip/public-api`, sandbox `dev-…`), payment_for_shipping_cod (Helper/Data only), sync_mode=async, auto_sync_* default 0, admin manual sync default 1, debug=1, default dims 10/10/10, debugReplaceKeys=token) + `carriers/giaohangnhanh_express|standard/*` + `carriers/ghn/advanced_settings/*` | Không còn `is_develop_mode` (đã xóa BUG-JBX3H9) |
| Plugins | `Plugin/Shipping/Model/Shipping` (dims copy, global), `Plugin/Sales/Model/Order::afterCanCancel` (chặn cancel khi `ghn_status !== 'ready_to_pick'` + có tracking), `Plugin/Adminhtml/OrderViewPlugin` (nút Sync GHN, adminhtml/di.xml) | |
| Route admin | `admin/ghn/order/sync` (ACL `Magento_Sales::actions`) — direct hoặc publish MQ theo `sync_mode` | |
| Console | `ghn:province:generate`, `ghn:region:generate`, `ghn:ward:generate` — populate master-data tables (skip existing; ward lặp per-district + progress log) | |

### 1.2 `Secomm_GhnAddressMapper` (~90 file PHP) — mapping địa chỉ

| Thành phần | Vai trò |
|---|---|
| `Api/LocationResolverInterface` + `Model/LocationResolver` | `resolve(regionId, cityId)` (id-based, chính) / `resolveByName(regionId, cityName)` (name fallback) → `district_id`+`ward_code`; throw `NoSuchEntityException` khi miss; cache 2 tầng (`ghn_addr_map_{r}_{c}` + `ghn_addr_cityid_{r}_{md5(name)}`, 86400s hardcoded) |
| First-match semantics | `LocationMapping::findByAddress` — `status=1` + `ORDER BY priority DESC, entity_id DESC LIMIT 1` (`ResourceModel/LocationMapping.php:23-35`): row mới hơn shadow row cũ **im lặng**, không có khái niệm AMBIGUOUS — vi phạm DEC-004 D9 |
| Bảng + admin UI | ĐÚNG 1 bảng `secomm_ghn_address_mapping_location` (`region_id`,`city_id` FK runtime + `ghn_province_id`,`ghn_district_id`,`ghn_ward_code` NOT NULL; **không unique constraint** — dedup application-level theo natural key `(city_id, ghn_ward_code)`); 11 admin controller `ghn_address_mapper/mapping/*` (CRUD + CSV import/export + sample/reference-pack download) + `ajax/cascadingOptions` |
| Import | `MappingImporter` — **CSV only** (không có client GHN master data API); cột bắt buộc `region_id, city_id, ghn_province_id, ghn_district_id, ghn_ward_code`; existing + `--update` → update, không → skipped; transaction + bulk name-map 5 query; CLI `ghn:mapping:import|export|validate` |
| Reference data | Module B **đọc** master data 3 bảng `secomm_giaohangnhanh_*` do Module A populate (CLI `ghn:province|region|ward:generate`) — KHÔNG sở hữu |
| Guard | `Import/DirectoryReferenceGuard implements VietNamAddress DirectoryReferenceGuardInterface` — chặn swap scheme khi còn mapping rows tham chiếu runtime ids (đăng ký vào `VnAddressSchemeImporter.directoryReferenceGuards`) |
| Identity model | **region_id/city_id (DB-sinh) làm key** — vi phạm trực tiếp DEC-FEATYA2C0W-003 §8 (city_id đổi mỗi swap); đây là lý do KHÔNG tái sử dụng bảng này làm canonical mapping |

### 1.3 Đã có trong hệ sinh thái (không phải legacy GHN nhưng cùng đời)

- `Secomm_VietNamAddress`: scheme registry + unit reference layer (`secomm_vietnam_address_unit`, UNIQUE(scheme_code, code)) + mapping edges (`secomm_vietnam_address_mapping`) + 2 resolver CÓ IMPLEMENTATION + DI preference:
  - `VnAdminAddressResolverInterface::resolve(sourceScheme, sourceCode, targetScheme)` → EXACT/MAPPED/AMBIGUOUS/UNMAPPED (cross-scheme translation) — `Api/VnAdminAddressResolverInterface.php:35`
  - `VnOperationalAddressResolverInterface::resolveFromRuntime(regionId, cityId)` → canonical (scheme_code, unit_code) của ACTIVE scheme — `Model/VnOperationalAddressResolver.php` (TASK-Q4B98P, DEC-004 D5)
- `Secomm_ShippingCore` Phase E-A contracts (`Api/Address/*`, TASK-AQT7V3): capability/result/context/external-resolver/manager (manager KHÔNG có impl) + pool 0-provider.
- `Secomm_ShippingCore` tracking pipeline (DEC-TASK86NX9T-001): `TrackingUpdate` → `CarrierTrackingProcessorInterface` (`ShipmentTrackingProcessor`) → `NormalizedTrackingStatus` + bảng `secomm_carrier_tracking_state`; GHTK đã tiêu thụ (`GhtkStatusMapper implements CarrierStatusMapperInterface`, `WebhookPayloadParser` → `TrackingUpdate`, `TrackingRefreshService`). **Không có pool/tag mapper** — mỗi carrier inject concrete mapper của mình vào nơi tiêu thụ (precedent GHN + GHTK + Ahamove đều vậy).

### 1.4 Cross-cutting findings (legacy debt — ghi nhận, KHÔNG fix trong task này)

1. **Circular dependency A↔B**: `Secomm_GhnAddressMapper` khai báo `Secomm_GiaoHangNhanh` trong module.xml + composer (để đọc bảng master-data), trong khi `GiaoHangNhanh\Model\Service\Request\AbstractDataBuilder` type-hint `GhnAddressMapper\Api\LocationResolverInterface`. Secomm_Ghn thiết kế mới KHÔNG tái diễn (§5 dependency direction).
2. **Class bị tham chiếu không tồn tại**: `ResultInterfaceFactory` (`IntegrationBase/.../Validator/AbstractValidator.php:8,25` + `AbstractResponseValidator.php:10`) không được định nghĩa ở đâu trong app/code — dead/broken path của validator framework.
3. **Webhook không auth**: không HMAC/signature; combine với việc mutate order state → rủi ro giả mạo callback.
4. **Duplicate status maps**: `GhnStatusMapper` (19 entry normalized) vs webhook's `MAPPING_STATUS_GHN_MAGENTO`/`MAPPING_STATE_GHN_MAGENTO` (22 entry, exception/damage/lost → `exception`/closed) — hai bảng chân lý không đồng bộ.
5. **Rate path không cache**: mỗi collectRates = 2 call GHN (available-services + fee) — chưa có policy cache nào.
6. **`JsonToArray::convert()`** có code chết sau bare `throw \Exception` (`Converter/JsonToArray.php:56-58`).

---

## 2. GHN API inventory (docs hiện hành — developer.ghn.vn)

Base URL: production `https://online-gateway.ghn.vn`, staging `https://dev-online-gateway.ghn.vn`.
Auth: header `Token` + `ShopId` (int) trên MỌI endpoint dưới đây.

| # | Operation | Endpoint | Address identity yêu cầu | Ghi chú quan trọng |
|---|---|---|---|---|
| 1 | Get Service (available services) | `POST /shiip/public-api/v2/shipping-order/available-services` | **district-based**: body `shop_id`, `from_district` (Int, bắt buộc), `to_district` (Int, bắt buộc) | Response: `service_id` (**"for reference only; not used when calling Calculate Fee or Create Order"**), `short_name`, `service_type_id` (2 = <20kg, 5 = ≥20kg/multi-parcel). **Empty HTTP-200 body = shop không tồn tại** (quirk). Error `WARD_IS_INVALID`: "A new-format address could not be mapped to a district" → GHN tự map địa chỉ mới→district nội bộ nhưng KHÔNG documented cho caller |
| 2 | Calculate Fee | `POST /shiip/public-api/v2/shipping-order/fee` | **ID-based**: `to_district_id` (Int, bắt buộc) + `to_ward_code` (String, bắt buộc) + `weight` + `service_type_id`; `from_district_id`/`from_ward_code` **OPTIONAL — default = địa chỉ shop đã đăng ký** | Response `total` + breakdown 16 loại fee (VND). Error: `USER_ERR_COMMON`, `CLIENT_NOT_OWNER_OF_SHOP` |
| 3 | Create Order | `POST /shiip/public-api/v2/shipping-order/create` | **NAME-based + flag model**: `to_ward_name`, `to_district_name` (bắt buộc KHI `is_new_to_address=false`, bỏ trống khi true), `to_province_name`, **`is_new_to_address` (Bool)** — `false` = đơn vị cũ (ward+district+province), `true` = đơn vị mới 2 cấp (ward+province). Tương tự `from_*`/`return_*` với flag riêng | **KHÔNG có trường district_id/ward_code**. `client_order_code` = idempotency key (re-send trả lại `order_code` cũ, không tạo trùng). `service_type_id` 2/5; `cod_amount` ≤50tr; `insurance_value` ≤5tr; `required_note` enum (`KHONGCHOXEMHANG`/`CHOXEMHANGKHONGTHU`/`CHOTHUHANG`); `items[]` bắt buộc khi `service_type_id=5` (per-parcel dims); `content` bắt buộc khi không có `items`. Response: `order_code` + fee + `expected_delivery_time`. Docs chính thức ghi nhận cải cách 01/07/2025 ngay trong mô tả flag |
| 4 | Update Order | `POST /shiip/public-api/v2/shipping-order/update` | Partial update; `order_code` + các field Create | **`cod_amount` change yêu cầu `otp`** (gửi SMS điện thoại đăng ký → không thể tự động hóa hoàn toàn). Ma trận field-update-per-status chi tiết trong docs |
| 5 | Cancel Order | `POST /shiip/public-api/v2/switch-status/cancel` | `order_codes[]` (batch **all-or-nothing**), `reason_code` enum (`GHN-CO001..003`, `GHN-CANCEL-OTHER`), `reason` free-text | HTTP 200 với `data[].result=false` = order hợp lệ nhưng không thể hủy ở status hiện tại |
| 6 | Order Info | `GET /shiip/public-api/v2/shipping-order/detail?order_code=` | — | ~120 field: `status`, fee, `leadtime`, `converted_weight`, … ; có biến thể lookup theo `client_order_code` |
| 7 | Master data LEGACY (3 cấp) | `GET /shiip/public-api/master-data/province` · `/master-data/district?province_id=` · `/master-data/ward?district_id=` | DistrictID (Int,vd 3695), WardCode (**String**, có thể leading-zero, vd "90795") | Docs đánh dấu **"Legacy address model"**; `SupportType` 0/1/2/3 (locked/pickup/delivery/both) |
| 8 | Master data MỚI (2 cấp) | `GET /shiip/public-api/v3/master-data/province/all` · `GET /v3/master-data/ward/all-by-province-id?province_id=` | `_id` Int (vd 1000001/1003646), `name` (**"use verbatim as to_ward_name"**), `extension_names[]` (biến thể không-dấu/viết tắt), `status` (1/2/10) | 34 tỉnh, limit 200/call; `parent_id` trỏ tỉnh |
| 9 | Webhook Order Status | GHN POST → URL đăng ký trên Developer Portal | Payload **PascalCase**: `OrderCode`, `ClientOrderCode`, `Type` (`create`/`switch_status`/`update_cod`/`update_fee`/`cod`/…), `Status`, `Time`, `Reason`, `CODAmount`, `ConvertedWeight`, `Fee`, `TotalFee`, `Warehouse`, `ShipperName/Phone`, `PodURL` | **Idempotent requirement: dedup theo `OrderCode`+`Type`+`Time`**; FIFO per order; retry backoff tới 12h (2xx = done; 4xx trừ 408/429 = DROP vĩnh viễn; 5xx/408/429/timeout = retry); mỗi field LUÔN có mặt (zero-value, không omit); Staging và Production là 2 account + 2 config webhook riêng |
| 10 | Order Status Codes | docs/master-data/order-status | 23 status: `ready_to_pick, picking, money_collect_picking, picked, storing, sorting, transporting, delivering, money_collect_delivering, delivered✓, delivery_fail, waiting_to_return, return, return_transporting, return_sorting, returning, return_fail, returned✓, cancel✓, exception✓, lost✓, damage✓, scrap✓` | ✓ = final |

**Tính nhất quán dữ liệu hiện tại**: platform đang ở trạng thái chuyển tiếp — Fee/Available-Services dùng ID legacy; Create docs NAME + flag cả 2 model; master data có song song 2 catalogue. **Clarification TL/SA (2026-09-08) xác nhận thực tế operational**: GHN VẪN chạy province/district/ward old-style cho các operational flow quan trọng (fee + create) — docs `is_new_to_address` là documented capability, KHÔNG được chọn làm kiến trúc (xem Rejected Alternatives). ⇒ Địa chỉ được thiết kế theo MỘT representation operational (old-style p/d/w), không per-operation branching.

---

## 3. Address representation matrix (per operation)

Biểu diễn địa chỉ VN của Secomm: canonical identity = `(scheme_code, unit_code)` (DEC-FEATYA2C0W-003). Active scheme runtime = `VN_ADMIN_2025` (2 cấp). Carrier-required intermediate = `VN_ADMIN_PRE_2025` (3 cấp — có district). Provider representation = GHN operational (old-style p/d/w).

| Operation | GHN field | Nguồn dữ liệu Secomm | Cần GHN ID? | Cần tên GHN verbatim? |
|---|---|---|---|---|
| Available Services | `from_district`, `to_district` | provider mapping → `GHN_DISTRICT_ID` | **CÓ (Int)** | không |
| Calculate Fee | `to_district_id`, `to_ward_code` (+optional `from_*`) | provider mapping → `GHN_DISTRICT_ID` + `GHN_WARD_CODE` | **CÓ** | không |
| Create Order | `to_ward_name`, `to_district_name`, `to_province_name`, `is_new_to_address=false` | provider mapping → `GHN_WARD_NAME`/`GHN_DISTRICT_NAME`/`GHN_PROVINCE_NAME` (old-style) | không (gửi names) | **CÓ (verbatim)** |
| Origin (available-services) | `from_district` | `OriginProviderInterface` → canonical → provider mapping → `GHN_DISTRICT_ID` | CÓ | không |
| Origin (create) | `from_*` optional | default = shop profile GHN (omit khi không override) | không | không |
| Tracking / Cancel / Order Info | `order_code` / `client_order_code` | không liên quan địa chỉ | không | không |

Hệ quả thiết kế (v2 — sau clarification):

1. **MỘT representation operational duy nhất** (old-style province/district/ward) cho mọi operation địa chỉ — không per-operation branching giữa 2 model. `is_new_to_address` luôn `false` trong kiến trúc này.
2. **Create Order gửi NAMES** (old-style, từ provider mapping) — không gửi ID trên create; ID (district/ward) dùng cho services + fee.
3. `extension_names[]` của master data = chất liệu matching **cho import tooling thôi** — tuyệt đối không fuzzy/name-match lúc checkout (banned).

---

## 4. Legacy reuse / discard matrix

| Thành phần legacy | Quyết định | Lý do / thay thế trong Secomm_Ghn |
|---|---|---|
| `IntegrationBase` HTTP/command stack | **REUSE pattern, reimplement lean** | Command pattern tốt; nhưng reimplement gọn trong module mới (generic client + command pool) — không copy nguyên khối; bỏ broken validator framework (`ResultInterfaceFactory` không tồn tại), bỏ code chết `JsonToArray`, validators phải giữ error message GHN thay vì chỉ `message==='Success'` |
| Chọn service theo `SERVICE_NAME_SHORT` ('Hàng nhẹ'/'Hàng nặng') | **DISCARD** | Name-based identity (banned); thay bằng `service_type_id` 2/5 (docs: service_id chỉ tham chiếu) |
| Default fee ẩn `$shippingFee = 10` | **DISCARD** | Silent fallback giá — mọi lỗi fee phải fail closed (method unavailable) |
| Stash `shipping_service_id/type_id` lên quote address + frontend district UI | **RE-EVALUATE** | `shipping_service_id` obsolete; nếu cần snapshot service cho create-order thì snapshot theo `service_type_id`; district UI thuộc phạm vi canonical renderer (E-C), module mới không đăng ký extension attribute `district` |
| Carrier abstract + Standard/Express + `canDisplay()` | **REUSE logic shape** | Ngưỡng weight/dims/converted-mass giữ làm config; ánh xạ sang `service_type_id` 2/5 thay vì service_id |
| `AbstractDataBuilder::resolveGhnLocation` (region_id/city_id → GHN IDs) | **DISCARD (thay bằng canonical resolver)** | Identity region_id/city_id bị cấm; thay bằng chuỗi §7. Hành vi fail-closed (BUG-JBX3H9) giữ nguyên semantíc |
| `GhnAddressMapper` (bảng + resolver + admin UI) | **DISCARD runtime; tham khảo import UX** | Key DB-sinh, mất khi swap scheme; thay bằng provider mapping scheme_code+unit_code (§7). Admin UI/import/export tham khảo pattern, viết mới |
| `Helper/Rate` currency conversion | **REUSE logic** | VND→base currency + guard rate>0 (bài học BUG-YQT1FW) |
| `OrderSyncService` + MQ consumers + observers | **REUSE flow shape, reimplement** | Topics ĐỔI TÊN (tránh đụng legacy khi coexist — §12); thêm idempotency `client_order_code` (docs chính thức) |
| Webhook controller | **REWRITE theo docs mới** | Payload cũ lowercase không còn đúng docs (PascalCase + dedup keys + retry contract); BỎ mutate order state (DEC-TASK86NX9T-001 rule 6) — đi qua pipeline ShippingCore |
| `GhnStatusMapper` (normalized map) | **REUSE nguyên tắc map** (đã implements `CarrierStatusMapperInterface`, 19 entry, miss→UNKNOWN — khớp §11) | Port class sang Secomm_Ghn (đổi namespace), cùng inject-concrete pattern như GHTK/Ahamove precedent |
| Webhook legacy path: mutate order state + `MAPPING_STATUS_GHN_MAGENTO`/`STATE` maps + `ghn_webhook_track` + `AddGhnOrderStatuses` + cột `ghn_status`/`tracking_code` | **DISCARD cho module mới** | Thay bằng parser docs mới (PascalCase) → `TrackingUpdate` → pipeline; KHÔNG mutate order state (DEC-TASK86NX9T-001 rule 6); cột legacy giữ làm lịch sử |
| `GHN::getTrackingInfo()` đọc `ghn_webhook_track` cho frontend track | **RE-EVALUATE** | Hiển thị track chuyển dần sang `secomm_carrier_tracking_state` (processor đã update Track.description + comments); trong transition có thể giữ read-only từ bảng cũ cho order cũ |
| Cột `quote_address.district/shipping_service_*` + extension attributes | **RE-EVALUATE ở GHN-A** | `shipping_service_id` (service_id) đã obsolete theo docs — thay bằng `service_type_id`; extension attribute `district` không còn cần nếu pipeline canonical; quyết định chi tiết khi implement, không copy mù |
| GHN master-data tables `secomm_giaohangnhanh_province/district/ward` | **DISCARD (không duplicate)** | Master data GHN = dữ liệu provider, không phải canonical; chỉ giữ trong provider mapping + import staging (banned: duplicate canonical tables) |
| Admin Sync button/plugins | **REUSE ý tưởng** | GHN-D làm lại trên module mới |
| Config `giaohangnhanh_setting/*` | **DISCARD paths, giữ semantics** | Config mới `secomm_ghn/*` có **environment switch** (§10); token vẫn encrypted |

---

## 5. Proposed module structure — `Secomm_Ghn`

```
app/code/Secomm/Ghn/
├── etc/
│   ├── module.xml                 # sequence: Secomm_ShippingCore, Secomm_VietNamAddress
│   ├── config.xml                 # defaults secomm_ghn/* (env sandbox|production)
│   ├── di.xml                     # API client, command pool, status mapper, resolver pool
│   ├── queue_consumer.xml|publisher.xml|topology.xml   # topics MỚI (tên riêng, §12)
│   ├── events.xml                 # observers auto-sync (flag-gated)
│   ├── db_schema.xml + whitelist  # provider mapping + (nếu cần) staging import
│   └── adminhtml/system.xml
├── Api/
│   ├── GhnClientInterface.php             # boundary HTTP: request(endpoint, headers, body)
│   ├── ProviderLocationMapperInterface.php# (schemeCode, unitCode, representation) → GhnProviderLocation
│   └── Data/GhnProviderLocationInterface.php  # districtId?, wardCode?, wardName?, districtName?, provinceName?, representation
├── Model/
│   ├── Config.php                          # per-environment token/shop_id/base-url (encrypted token)
│   ├── Client/GhnClient.php                # CHỈ HTTP/auth/timeout/retry — không business
│   ├── Command/                            # thin commands per operation (services|fee|create|cancel|detail|master-data)
│   ├── Address/
│   │   └── ProviderLocationMapper.php      # §7 provider lookup (single operational representation)
│   ├── Carrier/Ghn.php (+ Standard/Express hoặc theo service_type — quyết định TL §8.4)
│   ├── Rate/ … , Order/ (create/cancel builders), Tracking/ (StatusMapper, WebhookParser, RefreshService)
│   └── Exception/GhnAddressResolutionException.php  # fail-closed taxonomy (1 class + LocalizedException)
├── Console/ (master-data sync + mapping audit CLI)
└── Test/Unit/…
```

Dependency direction (DEC-004 D1): `Secomm_Ghn → Secomm_ShippingCore → Secomm_VietNamAddress`; **KHÔNG** `Secomm_Ghn → GiaoHangNhanh/GhnAddressMapper/VietMap/Google`. Không route qua legacy resolver, không preference-chiến hệ thống resolvers legacy.

---

## 6. Capability contract decision + semantics `getRequiredScheme()` (re-review sau clarification)

**Semantics `getRequiredScheme()` — CONFIRMED**: = **canonical administrative scheme mà carrier
cần TRƯỚC khi provider-specific mapping xảy ra** (carrier-required scheme ở lớp Secomm-canonical,
KHÔNG phải provider identity). Đối với GHN ⇒ `getRequiredScheme() = 'VN_ADMIN_PRE_2025'` —
không phải `VN_ADMIN_2025` (active customer scheme), không bao giờ là GHN ID namespace. Docblock
hiện tại (`CarrierAddressCapabilityInterface.php` — "Canonical administrative scheme the carrier
expects (e.g. VN_ADMIN_PRE_2025)" + header "provider mapping is carrier-owned") đã đủ rõ cho
interpretation này: **KHÔNG flag architecture gap, KHÔNG sửa contract production** (gợi ý
docblock-only — nhấn "before provider-specific mapping" — defer tới lần chạm file tới).

| Nhu cầu | E-A contract | Đủ? |
|---|---|---|
| Carrier khai báo carrier-required scheme cho orchestration translate | `getRequiredScheme()` | **Đúng semantics đã confirm** |
| Fallback text (geocode) | `supportsTextualFallback()` = `false` baseline | **Đủ** — GHN không geocode trong phạm vi |
| Per-operation address requirement | KHÔNG có trong contract | **Không cần** (D10) — single operational representation sau clarification (§3) |
| Provider IDs trong contract | KHÔNG có (chủ ý) | **Đúng thiết kế** — GHN IDs thuộc provider mapping carrier-owned |

**Kết luận v2**: contract E-A ĐỦ; GHN capability impl (GHN-A) khai báo
`getRequiredScheme() = VN_ADMIN_PRE_2025` + `supportsTextualFallback() = false`.

**Trạng thái E-B (re-evaluated sau clarification — cập nhật so với v1 report)**:

- Impl **đã tồn tại** (TASK-5XDG1P, chờ TL review): `ShippingAddressResolutionManager` delegate
  100% qua `VnAdminAddressResolverInterface` (EXACT/MAPPED/AMBIGUOUS/UNMAPPED), non-VN →
  `UnsupportedDestinationException` (không status thứ 5), cache request-scoped, pool + textual
  fallback KHÔNG invoke.
- Mapping statuses clarification ↔ E-B hiện tại:

| Clarification | E-B impl hiện tại | Đánh giá |
|---|---|---|
| RESOLVED | `EXACT` \| `MAPPED` | tương đương — deterministic |
| AMBIGUOUS | `AMBIGUOUS` | khớp — candidates không auto-select |
| UNMAPPED | `UNMAPPED` | khớp |
| UNSUPPORTED | `UnsupportedDestinationException` (non-VN) | tương đương semantically — non-VN là bypass ngoài scope address-resolution |

- **Gap thật nằm ở coverage + pool invocation** (§15, v3): GHN cần exact old ward ⇒ chỉ
  ONE_TO_ONE (5.60%) resolve được trực tiếp; 93.26% ONE_TO_MANY (bao gồm same-district) đều
  là `AMBIGUOUS` operationally. E-B v1 (chưa invoke pool) chỉ đủ cho 5.60%; **E-B v2 MANDATORY**
  — flow bắt buộc:
  ```text
  internal deterministic resolver
      → EXACT/MAPPED  → return resolved (đủ ward-level)
      → AMBIGUOUS     → invoke ExternalAddressResolverPool
      → UNMAPPED      → invoke external resolver if policy permits
      → external unresolved → fail closed
  ```
  No first-match · no random candidate · **no district-only success for GHN**.
- **Runtime result contract review (theo yêu cầu clarification §6)**: cần phân biệt tối thiểu
  `RESOLVED_FULL / AMBIGUOUS / UNMAPPED / EXTERNAL_RESOLVED / EXTERNAL_FAILED / UNSUPPORTED`.
  Đối chiếu contract hiện tại (`ResolvedShippingAddressInterface` 4-status REUSE +
  `UnsupportedDestinationException`):

| Ngữ nghĩa cần | Contract hiện tại | Đủ? |
|---|---|---|
| RESOLVED_FULL | `EXACT` \| `MAPPED` — `RESOLVED` với GHN nghĩa là unit_code resolve được là **exact PRE_2025 ward** (unit-level: province+district+ward thông qua ward unit + parent chain); district-only match phải KHÔNG đi vào trạng thái này | Đủ về status; **guard "full = ward-level" là việc của E-B v2 impl + provider mapping validation** (mapping row phải đủ province/district/ward mới tính hit) |
| AMBIGUOUS / UNMAPPED | `AMBIGUOUS` / `UNMAPPED` | Đủ |
| EXTERNAL_RESOLVED | KHÔNG phân biệt được — `ResolvedShippingAddress` không mang resolution source | **Gap (informational)** — flag follow-up task: assess thêm `resolutionSource`/`EXTERNAL_RESOLVED` nếu cần audit/KPI; KHÔNG đổi contract trong spike |
| EXTERNAL_FAILED | Không có status riêng — sau pool thất bại vẫn là AMBIGUOUS/UNMAPPED + log | Đủ về semantics (fail closed đúng); gap informational giống trên |
| UNSUPPORTED | `UnsupportedDestinationException` (non-VN) | Đủ (không status thứ 5 — đã agreed) |

- **GHN-C blocked** cho tới khi full PRE_2025 resolution path operational: (a) TASK-5XDG1P qua
  TL review, (b) E-B v2 (pool invocation khi AMBIGUOUS) implemented, (c) VietMap bridge PoC
  đạt ngưỡng (Q1/Q2 §17), (d) NO_MATCH 38 records được xử lý data (§15.3).

---

## 7. Provider mapping model

### 7.1 Schema (đề xuất — Tier 2 khi implement)

Bảng `secomm_ghn_location_mapping` trong `Secomm_Ghn` (tên bám gợi ý DEC-FEATYA2C0W-003 `secomm_ghn_address_mapping_location` — chốt tên lúc implement):

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `mapping_id` | PK | |
| `scheme_code` | VARCHAR | `VN_ADMIN_PRE_2025` (carrier-required scheme đã resolve) — KHÔNG hardcode cột legacy-only |
| `unit_code` | VARCHAR | canonical unit code PRE_2025 (`VNAP25-*` ward/district, region `VN-XX`) |
| `ghn_province_id` | INT NULL | `GHN_PROVINCE_ID` — provider-specific, KHÔNG phải identity PRE_2025 |
| `ghn_district_id` | INT NULL | `GHN_DISTRICT_ID` |
| `ghn_ward_code` | VARCHAR NULL | `GHN_WARD_CODE` (String — có thể leading-zero) |
| `ghn_ward_name` / `ghn_district_name` / `ghn_province_name` | VARCHAR NULL | `GHN_OPERATIONAL_ADDRESS` names verbatim (create old-style cần) |
| `support_type` | SMALLINT NULL | 0/1/2/3 từ master data (filter method khả dụng) |
| `source` / `synced_at` | metadata | audit import |
| **UNIQUE** | `(scheme_code, unit_code)` | sau clarification: MỘT representation operational duy nhất → không cần `representation` trong key |

- **Naming (v2)**: bỏ `GHN_LEGACY`/`GHN_NEW` — GHN vẫn operational duy trì dữ liệu đó nên không gọi là legacy. Dùng `GHN_OPERATIONAL_ADDRESS` (tập representation) + `GHN_PROVINCE_ID`/`GHN_DISTRICT_ID`/`GHN_WARD_CODE` (định danh từng cấp).
- **Shared-table rule**: bảng hiện tại GHN-local (D10 — lean). Nếu sau này promote thành bảng multi-provider shared thì BẮT BUỘC thêm `provider_code` vào key: `UNIQUE(provider_code, scheme_code, unit_code)`.
- KHÔNG có region_id/city_id — quyền sở hữu canonical identity thuộc VietNamAddress; bảng này chỉ provider-side. Không FK sang runtime tables (portable cross-swap, precedent DEC-003 §5).

### 7.2 Runtime resolution order (fail-closed, deterministic)

Input: `ResolvedShippingAddress` từ E-B manager — **scheme đã là `VN_ADMIN_PRE_2025`** (manager đã làm reverse 2025→PRE_2025 trước; Secomm_Ghn KHÔNG tự translate — clarification rule 1). `Secomm_Ghn` chỉ còn provider lookup:

```
input: ResolvedShippingAddress (scheme_code = VN_ADMIN_PRE_2025, unit_code = VNAP25-*)
       + operation requirement (services | fee | create)

1. PROVIDER MAP — ProviderLocationMapper lookup (scheme_code, unit_code)
   → trả GHN_DISTRICT_ID/GHN_WARD_CODE (services+fee) hoặc GHN_*_NAME (create)
2. Miss (0 hàng, hoặc hàng thiếu field operation cần) → FAIL CLOSED
   — GhnAddressResolutionException + PSR-3 warning context
     (scheme_code, unit_code, operation — không PII) →
     rate: GHN method unavailable (catch carrier) · sync: MQ retry/log · admin: error message
```

KHÔNG có bước translate trong Secomm_Ghn (đã xóa khỏi module — moved into E-B), không name-matching runtime, không first-match, không default IDs (kế thừa BUG-JBX3H9). `resolveByName` của legacy KHÔNG được port. Note resilience: nếu provider-mapping lookup MISS nhưng `ResolvedShippingAddress` có candidate codes (từ AMBIGUOUS đã được disambiguate ở E-B), KHÔNG thử từng candidate lúc runtime — đó là việc của resolution layer.

### 7.3 Import tooling (name matching CHỈ ở đây)

CLI `secomm_ghn:sync-master-data`: kéo master data GHN old-style (`/master-data/province|district|ward`) → staging → match với canonical PRE_2025 units (`secomm_vietnam_address_unit` WHERE scheme='VN_ADMIN_PRE_2025' + `extension_names`) → **xuất báo cáo candidate cho review/chấp nhận** (auto-accept chỉ khi match 1:1 tuyệt đối theo code/extension_name chuẩn hóa) → ghi mapping. Output audit file: các unit KHÔNG map được → report, không bỏ im lặng. Migration từ bảng legacy GhnAddressMapper (nếu cần seed): join `(region_id, city_id)` → `VnOperationalAddressResolverInterface::resolveFromRuntime()` → `(scheme_code, unit_code)` — **hàng không resolve được hoặc first-match/invalid legacy (R26) → audit report, KHÔNG migrate mù**. Khác legacy (CSV-only, natural key `(city_id, ghn_ward_code)`): nguồn chính = API master data; CSV chỉ kênh bổ sung (export audit → import lại sau review). Sau migration: bảng legacy tham chiếu runtime ids có thể xóa → `DirectoryReferenceGuard` tự hết chặn swap scheme.

---

## 7.4 Fail-closed behavior theo clarification (quote + create)

Khi `2025 → PRE_2025` không deterministic **và** không external resolver phù hợp (decision tree section CONFIRMED ARCHITECTURE):

- **Storefront quote**: carrier GHN đánh dấu **unavailable cho address đó** (collectRates trả
  false — method không xuất hiện) + **reason code** ghi vào log với context
  (`reason=address_resolution_ambiguous|unmapped|provider_map_missing`, scheme_code/unit_code —
  không PII). Không hiển thị fee sai, không exception cho customer, không stack trace.
- **Create order (order sync)**: **KHÔNG tạo GHN shipment** khi resolution chưa đạt trạng thái
  valid (EXACT/MAPPED trên carrier-required scheme + provider mapping hit đầy đủ field
  operation cần). MQ message không được consume "thành công nửa vời": fail + log `[GHN OrderSync]`
  + re-throw (retry); admin direct-sync → error message tường minh. Retry lặp lại vẫn fail
  → hàng vào dead-letter/theo dõi (policy MQ tái sử dụng hiện hành).
- **Origin**: available-services cần `from_district` ⇒ origin cũng phải qua cùng chuỗi
  (OriginProvider → canonical → provider mapping). Origin không resolve được → fail closed
  tương tự (không quote gì cả) — kế thừa BUG-JBX3H9.

---

## 8. Rate + Available Services flow (GHN-C)

```
collectRates(RateRequest)
 → E-B manager resolve (capability = GHN capability, getRequiredScheme=VN_ADMIN_PRE_2025)
      chain: customer address (VN_ADMIN_2025) → reverse resolve → VN_ADMIN_PRE_2025
      · EXACT/MAPPED  → ResolvedShippingAddress(scheme=PRE_2025, unit_code)
      · AMBIGUOUS     → external resolver pool nếu capability cho phép + provider available
                        (E-B v2; Secomm_Ghn không biết resolver nào)
                        · vẫn không resolve được → fail closed
      · UNMAPPED      → fail closed
 → ProviderLocationMapper: (PRE_2025, unit_code) → GHN_DISTRICT_ID + GHN_WARD_CODE
      · miss → fail closed (reason=provider_map_missing)
 → Origin (§10): from_district cho available-services; fee có thể bỏ from_* (default shop)
 → available-services (shop_id, from_district, to_district) — lưu ý: empty body = shop not found → fail
 → chọn service_type_id theo weight (<20kg → 2; ≥20kg/multi-parcel → 5) + canDisplay() gates
 → fee (to_district_id, to_ward_code, weight, service_type_id, [cod_value nếu payment COD])
 → total (VND) → convert base currency (Helper/Rate logic, guard rate>0)
 → Magento Rate method
```

**Availability reality check (v3 — exact-old-ward required)**: chỉ ONE_TO_ONE (5.60%) resolve
được trực tiếp từ reverse mapping thuần; 93.26% ONE_TO_MANY — **bao gồm same-district — là
AMBIGUOUS operationally** (TL/SA verified: GHN cần exact old ward; không còn đường thoát
district-granular — rejected §16). Không có external resolver ⇒ GHN quote chỉ available 5.60%.
Các đường mở availability (KHÔNG phá fail-closed):

1. **E-B v2 (MANDATORY) + ExternalAddressResolverPool** — resolver (Secomm_VietMap preferred
   candidate) disambiguate AMBIGUOUS/UNMAPPED bằng street-level → exact PRE_2025 ward.
2. Data authoring fix cho 38 NO_MATCH (offline, §15.3 — taxonomy phân loại trước, không route
   mù vào VietMap).

Điểm quyết định cho TL (giữ nguyên v1):

1. **`service_id` đã obsolete** — module mới gửi **`service_type_id` duy nhất**.
2. **Granularity Magento method**: giữ 2 carrier codes (standard/express) ánh xạ `service_type_id` 2/5; `canDisplay()` gate weight/dims.
3. Available-services cần `from_district` BẮT BUỘC ⇒ origin phải qua cùng chuỗi resolve; origin không resolve được → fail closed (kế thừa BUG-JBX3H9 cho origin side).

---

## 9. Create-order flow (+ cancel; update deferred) — GHN-D

```
Order placed / shipment created (observer, flag-gated) → MQ (topic riêng, §12)
 → consumer: OrderSyncService
   → địa chỉ: ResolvedShippingAddress snapshot (snapshot ưu tiên — §14 R6)
        scheme = VN_ADMIN_PRE_2025 + unit_code (đã resolve qua E-B lúc place-order hoặc re-resolve)
   → ProviderLocationMapper: (PRE_2025, unit_code) → GHN_OPERATIONAL_ADDRESS names
        · miss / thiếu field → FAIL (MQ retry) — không tự chế tên
   → client: create old-style (Token+ShopId headers; is_new_to_address=false;
        to_ward_name/to_district_name/to_province_name từ mapping;
        client_order_code = increment_id)
   → response order_code → lưu tracking_code + chạy pipeline tracking CREATED
 → auto-shipment (giữ behavior hiện có, flag)
Cancel: observer order_cancel_after → MQ → cancel (order_codes[1], reason_code config default GHN-CO003)
Update: DEFERRED — đổi cod_amount cần OTP SMS (không tự động hóa được); chỉ làm nếu business yêu cầu, flow OTP thủ công
```

`is_new_to_address` **luôn `false`** trong kiến trúc đã confirm — create old-style thống nhất với
fee/services, một representation duy nhất. Idempotency: `client_order_code` — retry MQ an toàn
(GHN trả order_code cũ). `service_type_id` từ rate-time snapshot hoặc re-derive theo weight.
Origin block `from_*` bỏ trống → GHN dùng shop profile.

**Gap documentation (không infer, báo TL)**: docs Create mô tả `is_new_to_address=true` nhưng
TL/SA đã xác nhận operational thực tế = old-style p/d/w (premise của kiến trúc này); branch
new-2-level chuyển sang Rejected Alternatives (§16) cùng evidence docs. Nếu empirical test sau
này chứng minh new-model hoạt động end-to-end cho quote+create → có thể revisit như một
alternative, không phải mặc định.

---

## 10. Origin / ShopId flow (GHN-A/B)

- **ShopId = origin identity bên GHN** (shop đăng ký địa chỉ kho trên GHN dashboard). Header `ShopId` + body `shop_id` (available-services) bắt buộc mọi call → config `secomm_ghn/{environment}/shop_id` + `token` (encrypted) + `base_url`; switch `environment = sandbox|production`. Staging và Production là **2 account GHN riêng** (docs webhook) → config per-environment là bắt buộc, không phải nice-to-have.
- `OriginProviderInterface` (DEC-TASKNDASAD-001) → `OriginInterface` cho text fields (`from_name/phone/address`, return block) khi merchant muốn override; **không có override → omit field** → GHN dùng shop profile (default documented). Không đọc `shipping/origin/*` trực tiếp trong request builder.
- Origin canonical: `OriginInterface.regionId/ward` → `VnOperationalAddressResolverInterface::resolveFromRuntime` → canonical (active scheme = `VN_ADMIN_2025`) → **cùng chain E-B reverse** (2025→PRE_2025, deterministic-or-fail) → provider mapping → `GHN_DISTRICT_ID` — chỉ cần cho **available-services `from_district`**. Origin KHÔNG đi đường tắt riêng, KHÔNG map trực tiếp runtime→GHN. Origin không resolve được → fail closed (§7.4) — hành vi khớp BUG-JBX3H9 hiện tại.
- Shop mapping separation: KHÔNG trộn shop/warehouse GHN vào provider mapping bảng location; shop = config, không phải location row. Multi-source (MSI) là extension sau qua OriginProvider preference — không thiết kế trước (D10).

---

## 11. Tracking flow (GHN-E)

Mirror đúng pattern GHTK đã chạy (DEC-TASK86NX9T-001); **legacy `GiaoHangNhanh` đã đi được 2/3 đường này**:

1. `GhnStatusMapper` legacy **đã implements `CarrierStatusMapperInterface`** với map chuẩn (19 entry; `exception/damage/lost` → UNKNOWN; miss → UNKNOWN) — port nguyên tắc map sang Secomm_Ghn (thêm `scrap` nếu muốn tường minh, hiện rơi vào miss→UNKNOWN như nhau). Inject concrete như precedent GHTK/Ahamove/GHN (`<type name="…Webhook…"><argument name="statusMapper">`).
2. Webhook: route riêng `secomm_ghn/webhook/orderStatus` (URL đăng ký trên Developer Portal), CSRF-exempt, **luôn 2xx nhanh**; parser **PascalCase theo docs mới** (`OrderCode`, `Type`, `Status`, `Time`, `ClientOrderCode`, `Reason/ReasonCode`, `CODAmount`, `ConvertedWeight`, …) → dedup keys `OrderCode+Type+Time` → `TrackingUpdate` → `CarrierTrackingProcessorInterface::process()`. **KHÔNG mutate order state** — bỏ hẳn parallel path `handleOrderStatus` của legacy. Auth webhook: dùng **custom headers của GHN Developer Portal webhook config** (portal cho đăng ký header — legacy không có gì) so khớp secret, precedent GHTK `webhook_secret`.
3. Retry semantics GHN (docs): 2xx = done; 4xx trừ 408/429 = **drop vĩnh viễn**; 5xx/408/429/timeout = retry backoff tới 12h; FIFO per order — nghĩa là controller phải 2xx cho mọi payload parse-được (kể cả unknown status → UNKNOWN), chỉ 5xx khi thật sự lỗi infra; KHÔNG bao giờ 4xx (bị drop không retry).
4. Carrier-code candidates: kế thừa `CARRIER_CODE_CANDIDATES` (`standard`, `express`) — pipeline lookup theo `carrier_code + tracking_number` trong `secomm_carrier_tracking_state`; module mới chỉ có 2 codes (bỏ `giaohangnhanh` bare — chỉ legacy order rất cũ).
5. Reconciliation: cron `Order Info` GET cho shipment non-terminal + stale — **default OFF** (opt-in, precedent GHTK); legacy KHÔNG có cron → đây là tính năng mới của pipeline chung.
6. Legacy artifacts không port: `ghn_webhook_track`, cột `ghn_status`, order-status custom, mutate state — lịch sử nằm ở comments + bảng tracking state mới; track hiển thị frontend cho order cũ đọc từ bảng cũ (read-only, §4).

---

## 12. Transition / cutover plan (coexistence audit)

| Bề mặt xung đột | Legacy | Plan |
|---|---|---|
| Carrier codes | `giaohangnhanh_standard/express` | Module mới **dùng lại cùng codes** (đơn hàng/quote cũ tham chiếu code); tại mỗi thời điểm CHỈ MỘT module active (legacy `carriers/giaohangnhanh_*` disabled trước khi bật mới). Đề xuất cửa sổ cutover ngắn, quote in-flight hỏng được chấp nhận (đặt maintenance checkout) |
| Config paths | `giaohangnhanh_setting/*`, `carriers/giaohangnhanh_*` | Mới: `secomm_ghn/*` + `carriers/giaohangnhanh_*` (giữ carrier section — do Magento carrier config convention); migration script copy giá trị tương ứng, review thủ công |
| DI preferences | Legacy preferences trong `GiaoHangNhanh/etc/di.xml` | Không đụng nhau (namespace khác); legacy module disabled → preferences tắt theo |
| Events/observers | `SalesOrderPlaceAfter`… | Observer mới flag-gated config riêng; legacy observer inactive khi legacy disabled |
| Queue topics | `ghn.sync.order`, `ghn.cancel.order` | Mới: `secomm_ghn.sync_order` / `secomm_ghn.cancel_order` (topic riêng — tránh double-consume khi cả 2 module tồn tại code-base) |
| Webhook route + GHN dashboard URL | `/giaohangnhanh/webhook/shippingUpdate` | Route mới `/secomm_ghn/webhook/orderStatus`; **đổi URL trong GHN Developer Portal là bước cutover tường minh** (staging + production riêng) |
| DB | `ghn_webhook_track`, `secomm_giaohangnhanh_*`, cột `sales_order.ghn_status/tracking_code`, `quote_address.district/…` | KHÔNG drop trong transition (lịch sử); GHN-F mới quyết định archive/drop + data migration mapping (§7.3) |
| Frontend checkout | `shipping-rates-validator*.js` + `address/edit.phtml` (district dropdown) + extension attribute `district` | Module mới KHÔNG đăng ký lại; surface district thuộc canonical renderer (FEAT-YA2C0W/E-C). Trong transition: legacy field vẫn hoạt động nếu legacy checkout path còn dùng; QC dual-path trước cutover |
| Rate-path API caching | Không có (2 call GHN mỗi collectRates) | Module mới: same behavior đầu (đúng realtime), đánh giá cache services/tuyến ở GHN-C nếu latency là vấn đề QC — KHÔNG cache fee có COD |
| Circular dependency legacy (A↔B) | `GhnAddressMapper` sequence `GiaoHangNhanh` + builder type-hint resolver | Tự mất khi cả 2 module removed ở GHN-F; module mới không tái diễn |
| Orders in-flight tại cutover | tracking theo legacy columns | Giữ legacy module CODE nhưng disabled observers/webhook → tracking in-flight chuyển qua webhook mới (order_code giữ nguyên trong `sales_order.tracking_code` — pipeline mới lookup được) |

Nguyên tắc: **không xóa code legacy cho tới GHN-F**; legacy = reference + rollback path.

---

## 13. Phases GHN-A..F (v3 — exact old ward là yêu cầu verified)

| Phase | Nội dung | Blocker |
|---|---|---|
| **GHN-A** | Skeleton module: registration, config env-switch (token/shop_id/base_url/debug), `GhnClientInterface` + client (auth headers, timeout, retry, PSR-3 logger, error envelope `{code,message,data}` + empty-body quirk), exception taxonomy, capability impl (`getRequiredScheme=VN_ADMIN_PRE_2025`), structure §5 | **Không — proceed** |
| **GHN-B** | Provider mapping: db_schema (Tier 2), `ProviderLocationMapper` — key: **exact PRE_2025 unit (ward-level, đủ province+district+ward)** → GHN operational p/d/w; master-data sync CLI + audit report; NO_MATCH data task (§15.3: fix DATA_GAP 9, authoring STRUCTURAL 11, review UNKNOWN 18); migration seed từ GhnAddressMapper (qua operational resolver + audit first-match/invalid); origin flow §10 | Target confirmed; **implement = task riêng sau review** |
| **E-B v2** | **PRIORITY BLOCKER — MANDATORY**: internal deterministic (EXACT/MAPPED ward-level) → AMBIGUOUS/UNMAPPED ⇒ invoke `ExternalAddressResolverPool` → external unresolved ⇒ fail closed. No first-match / no random candidate / no district-only success | TASK-5XDG1P TL review + scope v2 |
| **VietMap** | **Separate feature/task: PoC + adapter** — implement `ExternalAddressResolverInterface` đằng sau pool; đo precision (Q1/Q2 §17) trước khi wire; KHÔNG bundle vào Secomm_Ghn; Secomm_Ghn không biết resolver cụ thể | Sau E-B v2 scope; PoC song song được |
| **GHN-C** | Rate/services: capability wiring, collectRates + available-services + fee + service_type_id + currency | **Blocked tới khi full PRE_2025 resolution path operational** (E-B v2 + VietMap PoC đạt ngưỡng + NO_MATCH data xử lý — §8) |
| **GHN-D** | Order sync: create old-style (idempotent `client_order_code`), cancel consumer, MQ topics mới, auto-shipment, admin sync button | GHN-B + E-B v2 |
| **GHN-E** | Tracking: webhook + `GhnStatusMapper` + pipeline integration + reconciliation cron (opt-in) | GHN-D (có order_code) |
| **GHN-F** | Cutover + legacy removal: URL webhook flip, config migration, in-flight plan, drop/archive legacy tables + columns, xóa `Secomm_GiaoHangNhanh` + `Secomm_GhnAddressMapper` khỏi codebase | GHN-A..E + QC store thật |

Mỗi phase = task riêng (Mode C/B), spec-first, tests theo directive (mapping exists/missing/partial, rate fail-closed + AMBIGUOUS behavior, idempotency create, webhook dedup/sticky-terminal, currency).

---

## 14. Risks / open decisions (TL/SA)

| # | Rủi ro / quyết định | Mức | Khuyến nghị |
|---|---|---|---|
| R1 | **E-B chưa implement** — GHN-C blocked; không được viết orchestration thay thế trong Secomm_Ghn | High | Chốt E-B làm task kế tiếp sau report này; GHN-A/B chạy song song được |
| R2 | Docs vs thực tế: available-services/fee có chấp nhận địa chỉ mới qua server-side map không (`WARD_IS_INVALID` hint) — undocumented | Medium | KHÔNG rely; verify staging ở GHN-B/C nếu muốn mở đường new-model (hiện đã rejected — §16); mọi kết luận phải có staging evidence |
| R3 | Provider mapping phủ chưa đủ (VN 34 tỉnh × hàng nghìn ward mới) — name verbatim matching rủi ro sai | High | Import CLI + audit report bắt buộc; gate bật GHN mới = coverage report đạt ngưỡng (TL chốt %) |
| R4 | `service_id` obsolete — quote payload cũ gửi service_id sẽ sai/nhận lỗi tiềm ẩn | Low | Module mới gửi `service_type_id` duy nhất; QC đối chiếu staging |
| R5 | COD update cần OTP — không tự động hóa được | Low | Deferred; flow thủ công nếu business cần |
| R6 | Địa chỉ snapshot tại order-place vs re-resolve lúc sync (address có thể đổi scheme sau swap) | Medium | Snapshot `(scheme_code, unit_code)` lúc place order (DEC-003 §23 direction); re-resolve chỉ khi thiếu |
| R7 | Status terminal ngoài tập normalized (`exception/lost/damage/scrap`) map UNKNOWN có vi phạm sticky-terminal không | Medium | Raw status + reason luôn lưu; quyết định map cụ thể ở GHN-E (có thể thêm discussion DEC nhỏ nếu muốn terminal riêng) |
| R8 | Dual active GHN (2 module cùng carrier codes) gây double method/double consume nếu config sai | High | Cutover checklist tường minh (§12); QC gate |
| R9 | Webhook contract mới (PascalCase, dedup, retry-drop 4xx) khác payload legacy đang chạy | Medium | Parser mới đọc docs mới; test golden payloads; QC portal config staging + production riêng |
| R10 | IP whitelist GHN (401 "IP not allowed") trên môi trường production | Low | Ghi runbook DevOps |
| R11 | Empty-body shop-not-found (HTTP 200 rỗng) → false-positive thành công nếu client naive | Low | Client bắt buộc kiểm tra body rỗng → exception |
| R12 | Tên bảng mapping: DEC-003 gợi ý `secomm_ghn_address_mapping_location`; §7.1 viết ngắn `secomm_ghn_location_mapping` | Low | Chốt lúc GHN-B (không material) |
| R13 | Webhook legacy không auth + mutate order state (audit §1.4.3) | Medium (hiện hữu) | Module mới: custom-header secret từ GHN portal + bỏ mutate path; QC ký HMAC-style nếu GHN hỗ trợ thêm |
| R14 | Legacy debt chết (`ResultInterfaceFactory` missing, `JsonToArray` unreachable, `ward_code` INT column) | Low (hiện hữu) | Không fix legacy; module mới tránh tái diễn; WardCode luôn VARCHAR |
| R15 | Rate path mỗi collectRates = 2 API call, không cache → latency checkout + rate-limit GHN | Medium | GHN-C: đo trước; chỉ cache available-services (không fee); fail-open cache ngắn nếu QC cần |
| R16 | Coverage mapping GHN mới (34 tỉnh) là gate bật module — thiếu ward = method biến mất (fail closed đúng) nhưng merchant có thể tưởng lỗi hệ thống | Medium | Coverage report CLI + ngưỡng bật (R3) + admin diện trạng thái coverage |
| R17 | **Reverse mapping one-to-many** (§15: 93.26% AMBIGUOUS ward-level, bao gồm same-district) — exact-old-ward required nên KHÔNG còn đường thoát district-granular | **Critical** | **E-B v2 MANDATORY** (pool invocation) + VietMap bridge PoC + data authoring NO_MATCH; không bao giờ first-match |
| R18 | **District info mất sau ADMIN_2025** (265 wards = 7.98% span >1 old district) — không đủ cấp hành chính để quay lại PRE_2025 | High | Street-level disambiguation (resolver) hoặc fail closed; data authoring không thể sửa (lossy gốc) |
| R19 | **Street address cần để disambiguate** — customer address 2 cấp không mang đủ thông tin | High | Context contract đã có `getStreetText()` (E-A); resolver consumer; không ép user nhập thêm lúc checkout |
| R20 | **External resolver unavailable** (VietMap down/quota/hết hạn key) — giờ là SPOF operationally cho ~93% địa chỉ | **High (tăng từ Medium)** | Fail closed + GHN method unavailable + log; không retry-storm (cache status theo request); health-check pool; SLA/quota monitoring VietMap; cân nhắc multi-provider pool sau PoC |
| R21 | **VietMap result không map sạch về PRE_2025** (trả name/đơn vị khác dataset canonical) | Medium | Bridge task bắt buộc normalize output về (scheme_code, unit_code) trước khi trả vào pool; không-match → coi như không có resolver |
| R22 | **GHN master-data ≠ government PRE_2025** (tên/code lệch, SupportType khóa tuyến) | Medium | Import CLI match audit 1:1 + review; mismatch → audit report; không auto-accept fuzzy |
| R23 | **GHN sau này migrate operational APIs sang ADMIN_2025** — kiến trúc PRE_2025-intermediate trở thành bước thừa | Low (tương lai) | Thiết kế đã tách provider mapping khỏi resolution; chuyển target scheme = capability change 1 dòng (`getRequiredScheme`) + re-import mapping; giữ evidence new-model (§16) làm nền |
| R24 | **Provider IDs đổi trong khi PRE_2025 stable** — GHN re-number district/ward | Medium | Provider mapping là dữ liệu import được (`synced_at`/`source` audit); re-sync CLI; identity canonical không đổi |
| R25 | **Quote/create dùng provider fields khác nhau** (fee cần ID, create cần names) — drift giữa 2 bộ giá trị trong mapping | Medium | Một hàng mapping chứa đủ ID + names cho cùng location; validation import: row phải đủ bộ field liên quan hoặc bị loại (fail closed) |
| R26 | **Migration legacy mapping chứa invalid/first-match records** (legacy dedup `priority DESC, entity_id DESC` shadow row cũ) | High | Seed migration KHÔNG tin raw legacy rows: re-verify từng row qua operational resolver + đối chiếu GHN master data hiện hành; bất nhất → audit report, bỏ row |
| R27 | **38 NO_MATCH wards** — chỉ 1.14% nhưng là island/former-district units (địa điểm "khó" của vận chuyển): unhandled ⇒ những địa chỉ này mất GHN vĩnh viễn | Medium | Taxonomy §15.3: fix DATA_GAP (9) bằng data authoring; policy riêng cho STRUCTURAL (11); review tay UNKNOWN (18); KHÔNG route mù vào VietMap |
| R28 | **KPI nhầm lẫn district-granular 90.88%** — báo cáo/monitoring dùng nhầm làm resolution rate ⇒ quyết định sai về readiness | Medium | KPI duy nhất = `FULL_PRE_2025_ADDRESS_RESOLVED` (5.60% hiện tại); district-level chỉ là supporting statistic (§15.2) |

---

## 15. Reverse mapping coverage audit — VN_ADMIN_2025 → VN_ADMIN_PRE_2025

Script: `.ai/evidence/SPIKE-9Z231Q/audit_reverse_mapping.php` (read-only, chạy trên dataset
`app/code/Secomm/VietNamAddress/Files/`) · Full stats: `reverse-mapping-stats.json`.

Input: 3.321 current wards (2025) · 11.294 legacy units (PRE_2025 = district + ward) ·
10.064 edges authored PRE→2025 (region 63 + ward-level 10.001; `MERGED_INTO` 9.250,
`SPLIT_INTO` 627, `SAME_AS` 146, `RENAMED_TO` 41).

### 15.1 Phân loại chiều NGƯỢC (current ward → old ward)

| Nhóm | Số lượng | % | Ý nghĩa runtime |
|---|---|---|---|
| **ONE_TO_ONE** (deterministic) | **186** | **5.60%** | Resolve được ngay 1 old ward — GHN usable không cần resolver |
| **ONE_TO_MANY** (AMBIGUOUS reverse) | **3.097** | **93.26%** | Nhiều old wards merge vào 1 current ward — reverse ambiguous; tự chọn = first-match (CẤM) |
| — trong đó cùng old district | 2.832 | 85.27% | District deterministic; chỉ ward-level ambiguous |
| — trong đó khác old district | 265 | 7.98% | District cũng ambiguous — **district info đã mất** |
| **NO_MATCH** (fail closed) | **38** | **1.14%** | Current ward không có edge nào (đa số là former-district elevations: Phú Quốc, Kiên Hải, Hoàng Sa, Vị Thanh…) — data authoring gap, fix offline được |
| INVALID_MAPPING | **0** | 0 | 0 edge hỏng (63 region-level edges được classify riêng — hợp lệ) |

- **Coverage district-granularity (SUPPORTING STATISTIC ONLY — không phải resolution rate)**:
  3.018/3.321 = 90.88% current wards có old-district deterministic. **KHÔNG dùng làm KPI** —
  GHN cần exact old ward nên same-district ONE_TO_MANY vẫn `AMBIGUOUS` operationally (v3).
- **Province-level**: 0 current ward có sources x.cross tỉnh cũ (merge giữ trong tỉnh) —
  province luôn suy ra được. 23/34 region-level targets có >1 incoming edge (binh thường
  của merger — không ảnh hưởng runtime vì province lấy trực tiếp từ `region_code` của current ward).
- **Không suy luận đảo trực tiếp chiều PRE→2025**: 9.250 `MERGED_INTO` là N→1 — đảo lại thành
  1→N ambiguity; chỉ `SPLIT_INTO` (627) + `RENAMED_TO`/`SAME_AS` (187) cho reverse deterministic
  (khớp 186 one-to-one thực đếm — chênh lệch do edge region-level và edge trùng ward).

### 15.2 Primary KPI: FULL_PRE_2025_ADDRESS_RESOLVED

| Metric | Giá trị hiện tại | Ghi chú |
|---|---|---|
| **FULL_PRE_2025_ADDRESS_RESOLVED** (province+district+exact ward, trực tiếp) | **186 / 3.321 = 5.60%** | KPI chính — resolution rate thật của reverse mapping thuần |
| FULL qua external resolver (projected) | TBD — Q1/Q2 §17 | Sau VietMap PoC; đây là con số vận hành thực |
| AMBIGUOUS (cần external resolver) | 3.097 / 3.321 = 93.26% | Bao gồm same-district (2.832) — vẫn AMBIGUOUS, v3 |
| NO_MATCH (chưa classify) | 38 / 3.321 = 1.14% | → §15.3 |
| district-granular deterministic | 90.88% | Supporting statistic ONLY |

### 15.3 NO_MATCH classification (38 records — taxonomy theo clarification §8)

Script: `classify_no_match.php` · Raw: `no-match-classification.json`. Rule: name-match
(normalized, bỏ prefix hành chính) × region-edge consistency (old region phải merge đúng vào
new region của ward).

| Nhóm | SL | Xử lý |
|---|---|---|
| `SECOMM_MAPPING_DATA_GAP` | **9** | Old ward identity TỒN TẠI (region-consistent) nhưng thiếu edge — **canonical mapping defect → fix data** (author edges, ưu tiên full ward-set thay vì chỉ same-name ward) |
| `STRUCTURAL_ADMIN_CHANGE` | **11** | New ward = former DISTRICT/đảo elevated (Kiên Hải, Phú Quốc, Hoàng Sa, Bạch Long Vĩ, Côn Đảo, Lý Sơn, Cồn Cỏ, Cát Hải, Vân Đồn, Cô Tô, Long Phú) — không có single old ward tương đương; cần authoring dạng district→ward hoặc ward-set merge |
| `UNKNOWN` (manual review) | **18** | 10 same-name candidate nhưng region edge KHÔNG connect (coincidence) + 8 không match normalized name (VD "Langbiang - Đà Lạt", "Đạ Huoai 3", "Albá") — review tay, có thể rơi vào TRUE_NO_HISTORICAL_EQUIVALENT hoặc DATA_GAP bị tên lệch |
| `PROVIDER_SPECIAL_CASE` | 0 | reserved — GHN-specific nếu phát hiện khi import master data |

Không route mù cả 38 vào VietMap (clarification rule): DATA_GAP fix bằng data; STRUCTURAL cần
authoring policy; chỉ UNKNOWN/là ambiguous-ward-case mới xem xét external resolution path.

---

## 16. Rejected alternative — GHN current province + virtual district + current ward

> **UPDATE 2026-09-10 — supersede MỘT PHẦN bởi DEC-FEATFQWEQ3-001 (accepted, user acting as
> SA/TL):** Create Order của `Secomm_Ghn` dùng lại NAME-based `is_new_to_address=true` +
> `GHN_ADMIN_2025` names (SPEC-FEATFQWEQ3 §17/§44) — tức phần "create" của conclusion v3
> ("exact old ward cho cả fee lẫn create") KHÔNG còn hiệu lực; rating VẪN giữ full PRE_2025
> resolution (A — KEEP). Gate an toàn: staging evidence `is_new_to_address` với ward ĐÃ MERGE
> bắt buộc trước khi code GHN-D (TASK-9Q5ZAK). Nội dung section dưới giữ nguyên như evidence
> lịch sử của lần rejected 2026-09-08.

**Trạng thái: NOT SELECTED AS CURRENT ARCHITECTURE** (TL/SA clarification 2026-09-08).
Method ánh xạ trực tiếp `VN_ADMIN_2025 → GHN current province (+ virtual district) → current ward`,
bỏ qua PRE_2025. Evidence hữu ích được GIỮ (không xóa):

- Create Order docs NAME-based + `is_new_to_address` + `Get Province (New)`/`Get Ward (New)`
  catalogue 34 tỉnh/3.321+ ward trùng cấu trúc ADMIN_2025 (§2 #3/#8).
- `WARD_IS_INVALID` ("new-format address could not be mapped to a district") — chứng tỏ GHN
  tự map new-format → district nội bộ.

**Lý do rejected**:

1. Operational APIs thực tế (fee + create theo confirmation TL/SA) vẫn phụ thuộc old-style
   province/district/ward — "virtual district" là layer tự chế giữa hai hệ, không có chuẩn
   dữ liệu GHN nào xác nhận.
2. Canonical Launchpad scheme (ADMIN_2025) thiếu district — mọi ánh xạ trực tiếp phải
   PHÁT MINH district → hidden ambiguity (chính vấn đề R17/R18 nhưng bị che dưới layer provider).
3. Force direct current mapping tạo ambiguity ẩn: GHN current-ward namespace không identity
   theo scheme nào của Secomm; không audit được; fail khi GHN đổi catalogue.
4. PRE_2025 hiện là carrier-required intermediate rõ ràng hơn: có dataset canonical riêng,
   có resolver 4-state, có audit được — và provider mapping về GHN là việc thuần túy
   data-import có thể verify.

Revisit chỉ khi: empirical evidence chứng minh new-model APIs (is_new_to_address end-to-end
quote + create + services) hoạt động đầy đủ → khi đó chuyển capability sang
`getRequiredScheme() = VN_ADMIN_2025` + re-import mapping, bỏ intermediate.

### 16.1 Hypotheses REJECTED bởi verification TL/SA (v3 — exact old ward)

TL/SA verify trực tiếp từ GHN data/runtime: **GHN operational flow yêu cầu exact old ward**.
Các hypothesis sau REJECTED, không cần focused compatibility spike nữa:

| Hypothesis rejected | Lý do |
|---|---|
| `old province + old district + current ward` | Không đủ — GHN cần exact old ward |
| `old province + old district + current ward name` | Không đủ — name không thay thế được exact PRE_2025 ward identity |
| `district-granular resolution is sufficient` | Sai — district-only match không được coi là resolved cho GHN |
| `same-district ONE_TO_MANY can avoid exact old ward resolution` | Sai — same-district (2.832 wards) vẫn `AMBIGUOUS` operationally; 90.88% chỉ là supporting statistic |

---

## 17. Remaining Empirical Questions (v3 — sau khi same-district rejected)

| # | Câu hỏi | Cách verify | Blocker cho |
|---|---|---|---|
| Q1 | VietMap resolve **exact PRE_2025 ward** reliably từ full current address (street + ward + province) không? | PoC bridge task riêng: precision/recall trên sample representative (ưu tiên cross-district 265 + island units) | VietMap go/no-go + E-B v2 wire |
| Q2 | % địa chỉ customer THỰC TẾ resolve được tự động (internal 5.60% + external projected)? | Kết hợp Q1 với địa chỉ thật (order history/anonymized) | Gate bật GHN-C |
| Q3 | Confidence/failure policy của external resolver (ngưỡng chấp nhận, cooldown, fallback)? | Policy task sau PoC — lean, deterministic, fail-closed | E-B v2 chi tiết |
| Q4 | Xử lý 38 NO_MATCH theo taxonomy §15.3: DATA_GAP (9) authoring edges thế nào; STRUCTURAL (11) policy; UNKNOWN (18) review kết luận gì? | Data-authoring task (offline) + review tay | GHN-B data plan |
| Q5 | GHN provider mapping phủ được TẤT CẢ PRE_2025 wards không (đặc biệt các new-ward elevated từ district)? | `secomm_ghn:sync-master-data` dry-run + coverage diff vs `secomm_vietnam_address_unit` (PRE_2025) | GHN-B completeness gate |
| Q6 | GHN master data / provider mapping cần refresh định kỳ bao lâu một (thay đổi SupportType, thêm/hủy ward code)? | Dry-run diff theo chu kỳ (2 tuần đầu, rồi giảm tần suất) | Ops runbook |

---

## Completion report pointer

Xem completion report (chat) + record SPIKE-9Z231Q + evidence `.ai/evidence/SPIKE-9Z231Q/`.
