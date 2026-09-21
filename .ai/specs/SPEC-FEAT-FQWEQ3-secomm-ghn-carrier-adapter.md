# SPEC — Secomm_Ghn Carrier Adapter

Specification ID: SPEC-FEAT-FQWEQ3

> Filename: `SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md` — canonical Full Spec của feature
> `Secomm_Ghn` (carrier adapter đầu tiên trên `Secomm_ShippingCore`). Nguồn: spec draft do owner
> cung cấp 2026-09-10 (§1..§51 nguyên văn) + Supplements §52 (quyết định TL/SA 2026-09-10).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-FEAT-FQWEQ3 |
| Feature ID | FEAT-FQWEQ3 (parent: FEAT-YA2C0W — Vietnam address/shipping canonicalization) |
| Specification Level | FULL |
| Author | Owner-provided spec draft; Claude (AI-assisted normalization + supplements) |
| Status | **VALID** — 4 quyết định TL/SA ratified 2026-09-10 (xem §52); TL review spec text chạy cùng code pre-review từng phase |
| Date | 2026-09-10 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (dependency chain + ownership) · DEC-TASK3F6QWZ-001 (legacy mapper — superseded một phần) · DEC-TASK3F6QWZ-002 (tracking pipeline) · DEC-FEATFQWEQ3-001 (create-order dual-scheme — §52/D1) |
| Related Research | SPIKE-9Z231Q (legacy audit + GHN API compatibility matrix; §13 phases; §15 reverse-mapping audit) · SPIKE-YH439T (carrier runtime handoff) |
| Related Ticket(s) | TASK-RJFTPZ (GHN-A) · TASK-MZ2TCB (GHN-B) · TASK-FMBBSD (GHN-C) · TASK-9Q5ZAK (GHN-D) · TASK-RR1ZFN (GHN-E) · TASK-8019VC (GHN-F) |
| Workflow Mode | A (shipping = generic risk category → Tier-2) |

---

> **Status:** Draft for implementation → **VALID (supplemented 2026-09-10)**
> **Module:** `Secomm_Ghn`
> **Platform:** Magento Open Source 2.4.7+ / 2.4.8+
> **Product:** Secomm Launchpad Core
> **Dependencies:** `Secomm_ShippingCore`, `Secomm_VietNamAddress`
> **Replaces:** `Secomm_GiaoHangNhanh`, `Secomm_GhnAddressMapper`
> **Architecture role:** Reference implementation for Vietnamese carrier adapters

---

## 1. Business Intent

`Secomm_Ghn` cung cấp integration đầy đủ với Giao Hàng Nhanh (GHN) cho Secomm Launchpad, bao gồm:

* real-time shipping rate;
* service availability;
* estimated delivery time;
* shipment/order creation;
* shipment cancellation;
* shipment return;
* tracking synchronization;
* shipping label;
* GHN administrative master data;
* mapping giữa canonical Vietnam address của Secomm và GHN address models.

Module phải được thiết kế như **carrier adapter chuẩn đầu tiên** cho `Secomm_ShippingCore`.

Các carrier khác như:

* `Secomm_Ghtk`
* `Secomm_Ahamove`
* future `Secomm_Spx`

sẽ follow cùng architectural pattern nhưng **không depend `Secomm_Ghn`**.

```text
                  Secomm_ShippingCore
                          ↑
              ┌───────────┼───────────┐
              │           │           │
          Secomm_Ghn   Secomm_Ghtk  Secomm_Ahamove
```

---

# 2. Architecture Principles

## 2.1 Provider adapter only

`Secomm_Ghn` chỉ chịu trách nhiệm những gì GHN-specific:

* GHN authentication/configuration;
* GHN API request/response;
* GHN administrative master data;
* GHN address mappings;
* GHN service resolution;
* GHN rate/leadtime;
* GHN shipment lifecycle;
* GHN tracking/status translation.

Generic shipping orchestration thuộc `Secomm_ShippingCore`.

---

## 2.2 Canonical Vietnam address remains outside GHN

`Secomm_VietNamAddress` là source of truth duy nhất của địa chỉ hành chính Việt Nam.

Canonical schemes hiện tại:

```text
VN_ADMIN_2025
VN_ADMIN_PRE_2025
```

GHN không được:

* sửa canonical address;
* thêm GHN IDs vào `secomm_vietnam_address_unit`;
* dùng Magento display name làm provider identity;
* phụ thuộc trực tiếp `directory_region_city` để resolve carrier address.

---

# 3. GHN Address Models

GHN hiện tồn tại đồng thời hai administrative address models.

## 3.1 Current 2025 model

Scheme:

```text
GHN_ADMIN_2025
```

Hierarchy:

```text
Province
   └── Ward
```

Master-data API hiện sử dụng v3.

GHN xác nhận `name` trả về bởi Province New/Ward New chính là value dùng cho Create Order khi:

```text
is_new_to_address = true
```

## 3.2 Pre-2025 model

Scheme:

```text
GHN_ADMIN_PRE_2025
```

Hierarchy:

```text
Province
   └── District
        └── Ward
```

Đây là address model cần cho các APIs hiện vẫn sử dụng:

```text
district_id
ward_code
```

bao gồm ít nhất:

* Calculate Fee;
* Leadtime.

Calculate Fee hiện yêu cầu `to_district_id` và `to_ward_code`. Leadtime cũng sử dụng legacy district/ward address.

---

# 4. Critical Address Rule

Rating và Create Order **không được reuse cùng một address resolution path**.

## Rating

```text
VN_ADMIN_2025
      ↓
Secomm_VietNamAddress relation mapping
      ↓
VN_ADMIN_PRE_2025
      ↓
Secomm_Ghn mapping
      ↓
GHN_ADMIN_PRE_2025
      ↓
district_id + ward_code
```

## Create Shipment

```text
VN_ADMIN_2025
      ↓
Secomm_Ghn mapping
      ↓
GHN_ADMIN_2025
      ↓
province_name + ward_name
      ↓
Create Order
is_new_to_address = true
```

Create Order v2 hiện hỗ trợ trực tiếp 2-level administrative address thông qua:

```text
to_province_name
to_ward_name
is_new_to_address = true
```

và không cần `to_district_name`.

---

# 5. Database Model

## 5.1 `secomm_ghn_address_unit`

Một table duy nhất chứa toàn bộ GHN administrative master data.

### Required conceptual fields

```text
entity_id
scheme_code

provider_id
provider_code

parent_id
depth

name
extension_names

status

source_version
synced_at
created_at
updated_at
```

### Scheme values

```text
GHN_ADMIN_2025
GHN_ADMIN_PRE_2025
```

### Identity rule

Một row đại diện cho **một GHN administrative unit trong một scheme**.

Không thiết kế kiểu:

```text
current_name
legacy_name
old_district_id
new_province_id
...
```

trên cùng row.

---

# 6. GHN Address Mapping

Table:

```text
secomm_ghn_address_mapping
```

Mục đích duy nhất:

> Bridge canonical Secomm administrative identity với GHN provider identity.

Conceptual fields:

```text
entity_id

secomm_scheme_code
secomm_unit_code

ghn_address_unit_id

mapping_method
mapping_status
verified_at

created_at
updated_at
```

Mappings chính:

```text
VN_ADMIN_2025
    ↔ GHN_ADMIN_2025
```

và:

```text
VN_ADMIN_PRE_2025
    ↔ GHN_ADMIN_PRE_2025
```

---

# 7. Mapping Rules

Runtime không fuzzy-match bằng name.

Mapping generation/sync pipeline:

```text
GHN master data
      ↓
normalize
      ↓
exact deterministic match
      ↓
curated aliases
      ↓
unresolved report
      ↓
manual/curated resolution
      ↓
approved mapping
```

Runtime:

```text
canonical code
→ approved mapping
→ GHN identity
```

Không:

```text
customer-entered name
→ fuzzy matching
→ GHN API
```

---

# 8. Address Master Data Synchronization

Module phải hỗ trợ independent synchronization cho:

```text
GHN_ADMIN_PRE_2025
GHN_ADMIN_2025
```

Conceptual commands:

```text
bin/magento secomm:ghn:address:sync
bin/magento secomm:ghn:address:audit
```

Có thể support:

```text
--scheme=GHN_ADMIN_2025
--scheme=GHN_ADMIN_PRE_2025
--dry-run
```

Sync không được chạy trong checkout request.

Sync không được mutate:

```text
secomm_vietnam_address_unit
directory_country_region
directory_region_city
```

---

# 9. Configuration

Admin config phải lean.

Required:

```text
enabled
environment
api_token
shop_id

payment_type
required_note

debug
connection_timeout
request_timeout
```

Recommended optional:

```text
default_insurance_enabled
default_declared_value_policy
pickup_shift_policy
```

Sensitive values phải sử dụng encrypted backend config.

Token không được ghi raw vào log.

---

# 10. API Client

Tất cả GHN calls phải đi qua một centralized client abstraction:

```text
GhnApiClient
```

Responsibilities:

```text
base URL
environment
Token
ShopId
HTTP headers
serialization
timeouts
response parsing
GHN error translation
safe logging
```

Không để từng provider/service class tự tạo Curl request.

---

# 11. Provider Exceptions

Raw GHN errors không được leak ra ShippingCore.

Map tối thiểu thành normalized failures:

```text
ProviderAuthenticationException

ProviderInvalidAddressException

ProviderServiceUnavailableException

ProviderInvalidRequestException

ProviderRateUnavailableException

ProviderTimeoutException

ProviderRemoteException
```

`Secomm_Ghn` báo failure.

`Secomm_ShippingCore` quyết định:

* hide method;
* fallback;
* retry;
* operational action.

*(Note §52: ShippingCore hiện dùng outcome statuses + `ShippingFailureReason` thay vì typed exceptions —
các `Provider*Exception` sống TRONG `Secomm_Ghn` và được translate ở boundary sang
`CarrierRateOutcomeInterface` statuses. Đây là cách thỏa mãn ý đồ §11 trên foundation hiện có.)*

---

# 12. Rate Calculation

GHN Calculate Fee hiện dùng:

```text
/v2/shipping-order/fee
```

và yêu cầu:

```text
to_district_id
to_ward_code
service_type_id
weight
```

với optional dimensions, COD, insurance và item information.

## Flow

```text
Checkout
   ↓
ShippingCore RateRequest
   ↓
OriginProvider
   ↓
destination VN_ADMIN_2025
   ↓
resolve VN_ADMIN_PRE_2025
   ↓
GhnLegacyAddressResolver
   ↓
GHN_ADMIN_PRE_2025
   ↓
available service
   ↓
Calculate Fee
   ↓
normalized RateResult
   ↓
ShippingCore
   ↓
Magento
```

---

# 13. No Magic Rate Fallback

Không được giữ behavior cũ:

```php
$shippingFee = 10;
```

Không được silently return fake shipping price khi GHN API fail.

Failure phải được normalized và trả cho ShippingCore.

Fallback policy thuộc ShippingCore.

---

# 14. Service Resolution

Không hard-code GHN service ID như permanent business identity.

Flow:

```text
route
   ↓
GHN available services
   ↓
GHN service/service_type
   ↓
GhnServiceResolver
   ↓
ShippingCore service level
```

Canonical service vocabulary thuộc ShippingCore, ví dụ:

```text
STANDARD
EXPRESS
SAME_DAY
```

Provider service names/IDs không được expose như canonical Launchpad service identities.

---

# 15. Leadtime

GHN Leadtime hiện vẫn sử dụng legacy district/ward address information.

Flow:

```text
VN_ADMIN_2025
   ↓
VN_ADMIN_PRE_2025
   ↓
GHN_ADMIN_PRE_2025
   ↓
GHN Leadtime
   ↓
normalized ETA
```

ShippingCore quyết định cách expose ETA lên checkout/storefront.

---

# 16. Package Model

`Secomm_Ghn` không tự invent package dimensions.

ShippingCore phải cung cấp normalized parcel/package:

```text
weight
length
width
height

declared_value
cod_amount

items[]
```

GHN adapter transform normalized model sang GHN payload.

Create Order yêu cầu weight/dimensions và hỗ trợ item-level dimensions/weights.

Không được giữ technical debt cũ:

```text
length = 1
width  = 1
height = 1
```

---

# 17. Create Shipment / GHN Order

Create shipment phải sử dụng GHN current administrative address.

## Destination

```text
VN_ADMIN_2025
   ↓
GHN_ADMIN_2025
```

Payload:

```text
is_new_to_address = true

to_province_name = mapped GHN province name
to_ward_name     = mapped GHN ward name
to_district_name = empty
```

Không dùng legacy:

```text
to_district_id
to_ward_code
```

cho Create Order mới.

---

# 18. Sender Address

Sender/origin phải lấy từ:

```text
Secomm_ShippingCore
OriginProviderInterface
```

không hard-code store address trong GHN.

Nếu sender dùng new administrative model:

```text
is_new_from_address = true
```

và resolve:

```text
VN_ADMIN_2025
→ GHN_ADMIN_2025
```

Nếu project chọn để GHN sử dụng ShopId registered origin làm default thì adapter được phép omit applicable `from_*` fields.

---

# 19. Shipment Idempotency

GHN `client_order_code` là unique per shop và GHN hiện support retry-safe behavior: gửi lại code đã tồn tại có thể trả lại order đã tạo thay vì tạo duplicate.

Không sử dụng mặc định:

```text
Magento Order Increment ID
```

làm universal identifier.

Phải derive từ shipment/fulfillment identity.

Conceptual:

```text
client_order_code =
stable ShippingCore shipment reference
```

Requirement:

```text
1 Magento Order
→ có thể có N provider shipments
```

---

# 20. Shipment Persistence

Provider shipment state không lưu trực tiếp bằng hàng loạt GHN-specific columns trên `sales_order`.

Preferred provider table:

```text
secomm_ghn_shipment
```

Conceptual fields:

```text
entity_id

shipping_reference
magento_order_id
magento_shipment_id

shop_id

client_order_code
ghn_order_code

service_type_id

provider_status
provider_reason_code

quoted_fee
actual_fee
cod_amount

expected_delivery_at

created_at
updated_at
```

Không cần mirror toàn bộ GHN Order Info payload.

---

# 21. Shipment Creation Trigger

Preferred business trigger:

```text
ShippingCore shipment/fulfillment request
```

Không bind GHN implementation trực tiếp vào:

```text
checkout success
sales_order_place_after
```

như primary architectural contract.

Provider shipment creation nên async:

```text
ShippingCore
   ↓
create shipment request
   ↓
queue
   ↓
Secomm_Ghn
   ↓
GHN Create Order
```

---

# 22. Queue / Async Processing

Create/Cancel/Return operations có thể dùng Magento message queue.

Queue infrastructure phải nằm sau provider service boundary.

Conceptual operations:

```text
create shipment
cancel shipment
return shipment
reconciliation
```

Consumer phải support:

* idempotency;
* retry;
* normalized errors;
* safe logging.

Không assume RabbitMQ bắt buộc cho Launchpad Core.

Magento DB queue vẫn có thể là default lean deployment.

---

# 23. Tracking Webhook

GHN hiện gửi POST callbacks cho nhiều event types như:

```text
create
switch_status
update_weight
update_cod
update_fee
update_payment_type
cod
update_partial_return
```

Webhook có thể retry và GHN khuyến nghị receiver idempotent; event có thể deduplicate theo provider event information such as `OrderCode + Type + Time`.

Flow:

```text
GHN Webhook
   ↓
Secomm_Ghn webhook endpoint
   ↓
validate/parse
   ↓
deduplicate
   ↓
GhnStatusMapper
   ↓
ShippingCore TrackingUpdate
   ↓
ShippingCore / OrderOperations
```

---

# 24. No Direct Magento Order Status Ownership

`Secomm_Ghn` không được trực tiếp quyết định:

```text
GHN delivered
→ Magento complete

GHN returned
→ Magento closed
```

Provider adapter chỉ translate:

```text
GHN provider status
→ normalized ShippingCore status/event
```

Business order state transition thuộc orchestration layer.

---

# 25. Webhook Event Audit

Optional provider event table:

```text
secomm_ghn_event
```

Recommended fields:

```text
entity_id
ghn_order_code
event_type
provider_status

occurred_at

payload_hash
sanitized_payload

processing_status

created_at
```

Purpose:

* deduplication;
* audit;
* troubleshooting;
* reconciliation.

Không lưu sensitive raw payload không cần thiết vô thời hạn.

---

# 26. Shipment Detail / Reconciliation

Order Info được sử dụng cho:

```text
manual refresh
missed webhook recovery
scheduled reconciliation
troubleshooting
```

Không polling liên tục GHN cho mọi order.

Webhook là primary synchronization mechanism.

---

# 27. Update Shipment

GHN Update Order hỗ trợ partial update nhưng allowed fields phụ thuộc current GHN status.

Module phải expose normalized capability:

```text
updateShipment(ShipmentUpdateRequest)
```

GHN adapter chịu trách nhiệm validate provider state/capability.

Không expose:

```text
updateAnything(array $data)
```

ra ShippingCore.

Advanced COD update/OTP không bắt buộc trong initial Core implementation.

---

# 28. Cancel Shipment

GHN Cancel Order API là operation riêng.

ShippingCore contract phải phân biệt:

```text
cancelShipment()
```

với return.

Cancel phải async-capable và idempotent.

---

# 29. Return Shipment

GHN Return Order là operation riêng và chỉ hợp lệ tại các provider states cho phép.

ShippingCore contract:

```text
requestReturn()
```

Không model chung bằng:

```text
changeStatus()
```

---

# 30. Shipping Label

GHN Print Order tạo print token cho GHN orders.

Provider capability:

```text
getLabel()
```

ShippingCore không cần biết GHN implementation sử dụng temporary print token.

---

# 31. Capability Model

ShippingCore không được assume tất cả carriers support giống GHN.

`Secomm_Ghn` cần declare provider capabilities, conceptually:

```text
rate              = true
leadtime          = true

create_shipment   = true
update_shipment   = true
cancel_shipment   = true
return_shipment   = true

tracking_webhook  = true
label             = true
station_dropoff   = optional
```

Carrier khác có thể return capability khác.

---

# 32. Admin Operational Actions

Initial Core có thể support:

```text
Create/Sync shipment
Refresh shipment information
Cancel shipment
Request return
Print label
View provider status
View last provider error
```

Actions phải đi qua ShippingCore/provider contracts khi applicable.

Admin không được gọi provider client trực tiếp từ controller.

---

# 33. Initial Core API Scope

## Required

| Capability           | GHN integration            |
| -------------------- | -------------------------- |
| Authentication       | Token + ShopId             |
| Current master data  | Province New + Ward New    |
| Legacy master data   | Province + District + Ward |
| Address mapping      | Canonical Secomm ↔ GHN     |
| Service availability | GHN service lookup         |
| Rate                 | Calculate Fee              |
| ETA                  | Leadtime                   |
| Create shipment      | Create Order               |
| Shipment info        | Order Info                 |
| Cancel               | Cancel Order               |
| Return               | Return Order               |
| Tracking             | Webhook                    |
| Label                | Print Order                |

---

# 34. Deferred / Optional

Not required for initial Launchpad Core:

```text
Create GHN Shop

complex station/dropoff workflows

advanced COD OTP update

GHN Ticket APIs

affiliate APIs

advanced COD settlement/reconciliation

complex pickup scheduling UI
```

These can be added when business requirement exists.

---

# 35. Proposed Code Structure

```text
Secomm/Ghn
│
├── Api/
│   ├── Client/
│   ├── Data/
│   └── Exception/
│
├── Model/
│   │
│   ├── Config/
│   │
│   ├── Address/
│   │   ├── MasterData/
│   │   ├── Sync/
│   │   ├── Mapping/
│   │   ├── CurrentAddressResolver/
│   │   └── LegacyAddressResolver/
│   │
│   ├── Rating/
│   │   ├── ServiceResolver/
│   │   ├── RateProvider/
│   │   └── LeadtimeProvider/
│   │
│   ├── Shipment/
│   │   ├── Create/
│   │   ├── Detail/
│   │   ├── Update/
│   │   ├── Cancel/
│   │   ├── Return/
│   │   └── Label/
│   │
│   ├── Tracking/
│   │   ├── Webhook/
│   │   └── StatusMapper/
│   │
│   └── ResourceModel/
│
├── Controller/
│   └── Webhook/
│
├── Console/
│
└── etc/
```

Exact class granularity should remain lean; do not create one interface/class merely to mirror every directory shown above.

---

# 36. Legacy Parity Matrix

Existing source currently contains `Secomm_GiaoHangNhanh` with rate calculation, queue-based create/cancel, webhook tracking, GHN master-data tables and direct Magento integrations.

## Reuse conceptually

| Legacy capability   | New implementation                      |
| ------------------- | --------------------------------------- |
| Central API flow    | Keep concept                            |
| Request builders    | Redesign as provider DTO/request mapper |
| Response handlers   | Keep concept                            |
| Queue create/cancel | Keep async pattern                      |
| COD handling        | Preserve business behavior              |
| payment type        | Preserve                                |
| required note       | Preserve                                |
| status vocabulary   | Reuse knowledge                         |
| webhook ingress     | Rewrite around ShippingCore             |
| GHN status mapper   | Reuse/adapt                             |
| OriginProvider use  | Preserve                                |

---

# 37. Rewrite

| Legacy                           | Reason                                                    |
| -------------------------------- | --------------------------------------------------------- |
| `Model/Carrier/GHN.php`          | Too much rating/provider orchestration in Magento Carrier |
| Express/Standard carrier classes | Service semantics belong in ShippingCore                  |
| `SynchronizeOrderDataBuilder`    | Uses legacy address Create Order                          |
| GHN address lookup               | New dual-scheme model                                     |
| shipment persistence             | Remove direct `sales_order` provider state                |
| tracking pipeline                | No direct Magento state transition                        |
| package calculation              | Use ShippingCore package                                  |
| exception handling               | Introduce typed provider failures                         |

Current legacy carrier catches generic exceptions and can suppress carrier failures during rating; this behavior must not be reproduced.

---

# 38. Remove / Deprecate

The following old architecture must not be carried into `Secomm_Ghn`:

```text
Secomm_GhnAddressMapper dependency

secomm_giaohangnhanh_province
secomm_giaohangnhanh_district
secomm_giaohangnhanh_ward

directory_region_city → GHN provider mapping

quote_address.district

quote_address.shipping_service_id
quote_address.shipping_service_type_id

sales_order.ghn_status
sales_order.tracking_code
sales_order.ghn_canceling_status

GHN checkout/address templates and JS

direct provider webhook → Magento Order state mutation

magic shipping-fee fallback

hard-coded package dimensions

order increment ID as universal client_order_code
```

The current `GhnAddressMapper` maps directly from Magento `region_id/city_id` to GHN legacy province/district/ward identities, which is superseded by canonical `Secomm_VietNamAddress` codes.

---

# 39. Dependency Cleanup

Current source contains an architectural smell:

`Secomm_GiaoHangNhanh` runtime code uses:

```text
Secomm_GhnAddressMapper\Api\LocationResolverInterface
```

while `Secomm_GhnAddressMapper` itself declares `Secomm_GiaoHangNhanh` in its module sequence.

The new module must eliminate this relationship.

Final dependency direction:

```text
Secomm_Ghn
     ↓
Secomm_ShippingCore
     ↓
Secomm_VietNamAddress
```

No reverse dependency.

---

# 40. Migration Strategy

Do **not** rename/refactor `Secomm_GiaoHangNhanh` in-place.

Build:

```text
Secomm_Ghn
```

as independent clean implementation.

Legacy modules remain available temporarily as reference:

```text
Secomm_GiaoHangNhanh
Secomm_GhnAddressMapper
```

After feature parity and migration verification:

```text
disable old modules
migrate required provider configuration
migrate active shipment references if required
remove old runtime dependencies
```

Do not automatically migrate historical webhook logs unless explicitly required.

---

# 41. Minimum Feature Parity Gate

`Secomm_Ghn` cannot replace legacy GHN until these flows pass:

```text
Address sync
Address mapping audit

Available service
Calculate Fee
Leadtime

Create Shipment
Shipment Detail

Cancel Shipment
Return Shipment

Webhook Tracking
Print Label
```

---

# 42. Acceptance Criteria — Address

### AC-ADDR-001

System stores both:

```text
GHN_ADMIN_2025
GHN_ADMIN_PRE_2025
```

in `secomm_ghn_address_unit`.

### AC-ADDR-002

`GHN_ADMIN_2025` represents Province → Ward hierarchy.

### AC-ADDR-003

`GHN_ADMIN_PRE_2025` represents Province → District → Ward hierarchy.

### AC-ADDR-004

Mappings use stable Secomm canonical unit codes, not display names.

### AC-ADDR-005

No GHN ID/code is persisted into canonical `Secomm_VietNamAddress` unit records.

### AC-ADDR-006

Address audit reports:

```text
mapped
unmapped
ambiguous
invalid
disabled provider unit
```

without silently auto-approving ambiguous fuzzy matches.

---

# 43. Acceptance Criteria — Rating

### AC-RATE-001

Customer `VN_ADMIN_2025` destination is resolved through:

```text
VN_ADMIN_PRE_2025
→ GHN_ADMIN_PRE_2025
```

for Calculate Fee.

### AC-RATE-002

Calculate Fee uses provider `district_id + ward_code`.

### AC-RATE-003

No magic/fake shipping price is returned on GHN failure.

### AC-RATE-004

Provider errors are classified and passed to ShippingCore.

### AC-RATE-005

Provider service is normalized to ShippingCore service level.

---

# 44. Acceptance Criteria — Shipment Creation

### AC-SHIP-001

Create Order uses:

```text
is_new_to_address = true
```

for destination.

### AC-SHIP-002

Destination province/ward names come from mapped `GHN_ADMIN_2025` units.

### AC-SHIP-003

Legacy `district_id/ward_code` are not used for new-address Create Order destination.

### AC-SHIP-004

Stable `client_order_code` is shipment/fulfillment-based and supports retry-safe creation.

### AC-SHIP-005

Package dimensions are real normalized dimensions; no `1×1×1` fallback unless ShippingCore has explicitly defined such fallback policy.

---

# 45. Acceptance Criteria — Tracking

### AC-TRACK-001

Webhook parsing is idempotent.

### AC-TRACK-002

Provider statuses are translated through `GhnStatusMapper`.

### AC-TRACK-003

Webhook sends normalized tracking update into ShippingCore.

### AC-TRACK-004

Webhook does not directly set Magento order state/status as provider business logic.

### AC-TRACK-005

Missed webhook can be reconciled from provider shipment information.

---

# 46. Acceptance Criteria — Architecture

### AC-ARCH-001

`Secomm_Ghn` depends on `Secomm_ShippingCore`.

### AC-ARCH-002

No carrier module depends on `Secomm_Ghn`.

### AC-ARCH-003

Generic carrier contracts remain in ShippingCore.

### AC-ARCH-004

GHN-specific DTOs, endpoints and status vocabulary remain inside `Secomm_Ghn`.

### AC-ARCH-005

No new GHN-specific fields are added to Magento checkout/customer address to support provider identity.

### AC-ARCH-006

No new GHN-specific provider status columns are added to `sales_order` as the primary persistence design.

---

# 47. Test Coverage

At minimum include:

## Unit

```text
address resolver
mapping resolver
status mapper
service resolver
request builders
exception translation
idempotency reference generation
```

## Integration

```text
GHN address master sync

Calculate Fee request

Leadtime request

Create Order new address request

Cancel

Return

Webhook parsing

Print label/token
```

## Failure scenarios

```text
unmapped current address
unmapped legacy address

GHN timeout

authentication failure

route not supported

invalid service

duplicate Create Order retry

duplicate webhook

webhook before local persistence completes

invalid/unknown tracking code
```

---

# 48. Observability

Provider logs should include contextual identifiers such as:

```text
operation
shop_id
shipping_reference
client_order_code
ghn_order_code
HTTP status
provider error code
duration
```

Do not log:

```text
API Token
full customer PII unnecessarily
```

Debug logging must be configurable.

---

# 49. Non-Goals

Initial version does not attempt to build:

```text
carrier-agnostic OMS

routing engine

multi-carrier cheapest-provider selection

full WMS

advanced fulfillment orchestration

generic ticket/support platform

GHN account/shop management portal

complex pickup scheduling UI
```

Those responsibilities belong elsewhere or require separate features.

---

# 50. Final Runtime Flows

## Rate

```text
Customer VN_ADMIN_2025
        ↓
ShippingCore
        ↓
VN_ADMIN_PRE_2025
        ↓
GHN_ADMIN_PRE_2025
        ↓
GHN service
        ↓
Calculate Fee
        ↓
Leadtime
        ↓
ShippingCore RateResult
```

## Shipment

```text
ShippingCore shipment
        ↓
VN_ADMIN_2025
        ↓
GHN_ADMIN_2025
        ↓
GHN canonical names
        ↓
Create Order
is_new_to_address=true
        ↓
GHN order_code
        ↓
provider shipment persistence
```

## Tracking

```text
GHN
 ↓
Webhook
 ↓
Secomm_Ghn
 ↓
GhnStatusMapper
 ↓
ShippingCore TrackingUpdate
 ↓
Order Operations
```

---

# 51. Definition of Done

Feature được xem là complete khi:

1. `Secomm_Ghn` build độc lập và không runtime-depend `Secomm_GiaoHangNhanh` hoặc `Secomm_GhnAddressMapper`.
2. Dual GHN administrative schemes được import/sync và audit được.
3. Canonical mappings đạt coverage cần thiết cho supported destinations.
4. Calculate Fee hoạt động qua legacy GHN IDs.
5. Create Order hoạt động qua GHN 2025 names.
6. Create retry không tạo duplicate GHN shipment.
7. Cancel và Return là independent operations.
8. Webhook idempotent và đi qua ShippingCore tracking pipeline.
9. Label generation hoạt động.
10. Không còn provider-specific checkout/address implementation.
11. Không sử dụng fake shipping rate khi provider lỗi.
12. Legacy parity tests pass trước khi old GHN modules bị disable.

---

## Architectural Decision Summary

`Secomm_Ghn` được build mới thay vì tiếp tục mở rộng `Secomm_GiaoHangNhanh`.

Address model:

```text
Secomm:
VN_ADMIN_2025
VN_ADMIN_PRE_2025

GHN:
GHN_ADMIN_2025
GHN_ADMIN_PRE_2025
```

Provider address storage:

```text
secomm_ghn_address_unit
```

Bridge:

```text
secomm_ghn_address_mapping
```

Rating:

```text
CURRENT → PRE_2025 → GHN PRE_2025 → IDs
```

Shipment creation:

```text
CURRENT → GHN 2025 → names
```

Provider lifecycle:

```text
Secomm_Ghn
→ normalized contracts/events
→ Secomm_ShippingCore
```

`Secomm_Ghn` là reference implementation cho Vietnamese carrier modules nhưng **không phải parent/base dependency của các carrier khác**.

---

# 52. Supplements — quyết định TL/SA 2026-09-10 (ratified)

Phần này là phần bổ sung chính thức của spec (không có trong draft gốc) — ghi nhận 4 quyết định
TL/SA đã chốt khi lập implementation plan, cùng các mapping sang hiện trạng code.

## 52.1 D1 — Create Order dual-scheme (DEC-FEATFQWEQ3-001)

Create Order dùng `GHN_ADMIN_2025` names + `is_new_to_address=true` (§4/§17/§44 — AC-SHIP-001..003
giữ nguyên hiệu lực). Quyết định này **supersede kết luận create-old-style của SPIKE-9Z231Q v3**
(TL/SA clarification 2026-09-08 đã rejected NAME-based create; owner re-decided 2026-09-10 theo
hướng ngược lại). Hệ quả:

* `secomm_ghn_address_unit` PHẢI sync cả 2 scheme `GHN_ADMIN_2025` + `GHN_ADMIN_PRE_2025`
  (không chỉ PRE_2025 như spike v3); mapping 2 chiều `VN_ADMIN_2025 ↔ GHN_ADMIN_2025` +
  `VN_ADMIN_PRE_2025 ↔ GHN_ADMIN_PRE_2025` (§6).
* **Staging evidence bắt buộc TRƯỚC khi code GHN-D**: verify `is_new_to_address=true` trả về
  đúng tuyến với ward ĐÃ MERGE (spike §16 hypotheses bị reject vì lý do này — phải testing
  thật với các cặp ward merge từ §15 audit trước khi tin payload).
* SPIKE-9Z231Q report §16 cần update note trỏ về DEC-FEATFQWEQ3-001.

Rating KHÔNG đổi: vẫn qua `VN_ADMIN_PRE_2025` → legacy `district_id` + `ward_code` (§12/§43).

## 52.2 D2 — Phạm vi implementation

Roadmap đầy đủ GHN-A..F; chi tiết file-level chỉ GHN-A (skeleton) + GHN-B (master data + mapping).
GHN-C..F = outline + gates, mỗi phase có TASK record riêng, plan file viết khi kích hoạt.

## 52.3 D4 — PRE_2025 runtime coverage (dependency ngoài FEAT)

Rating bắt buộc resolve exact old ward `VN_ADMIN_PRE_2025` runtime; internal deterministic chỉ phủ
5,60% (186/3.321 one-to-one — spike §15). Do đó các dependency phases NGOÀI FEAT này block GHN-C:

* **E-B v2** (ShippingCore — task riêng sau TASK-5XDG1P review): `ShippingAddressResolutionManager`
  invoke `ExternalAddressResolverPool` khi AMBIGUOUS/UNMAPPED; không resolve được → fail closed.
* **VietMap bridge** (feature riêng): adapter `ExternalAddressResolverInterface`; đo precision trước
  khi wire; dependency `VietMap → ShippingCore contract`, KHÔNG `Secomm_Ghn → VietMap`.
* **NO_MATCH authoring** (38 ward: DATA_GAP 9 / STRUCTURAL 11 / UNKNOWN 18 — spike §15.3).

## 52.4 Mapping sang foundation hiện có

| Spec yêu cầu | Foundation hiện có | Cách thỏa mãn |
|---|---|---|
| §11 provider exceptions | ShippingCore dùng outcome + `ShippingFailureReason` | `Provider*Exception` trong `Secomm_Ghn\Api\Exception`, translate ở boundary sang `CarrierRateOutcomeInterface` |
| §12 rate flow | `CarrierAddressHandoffServiceInterface` (E-C0) + `CarrierAddressCapabilityInterface` | capability `getRequiredScheme() = VN_ADMIN_PRE_2025`; handoff → mapping → fee |
| §14 service levels | `CarrierServiceLevelInterface` + `ShippingServiceLevelRegistry` (E-SL0) | GHN declare levels; không hard-code service_id |
| §13/§16 no-fallback + package | `Api/Fallback/*` + (chưa có) package DTO | fallback qua orchestrator E-SL2; package DTO = slice ShippingCore riêng (GHN-D gate) |
| §21/§22 shipment trigger + queue | (chưa có) shipment contracts + MQ | slice ShippingCore riêng, TL review; MQ connection `db` default |
| §23/§24 webhook | `CarrierTrackingProcessorInterface` + `NormalizedTrackingStatus` | webhook controller trong `Secomm_Ghn`, push qua processor, KHÔNG mutate `sales_order` |
| §5/§6 dual-scheme tables | (chưa có) | GHN-B tạo `secomm_ghn_address_unit` + `secomm_ghn_address_mapping` (Tier-2) |

## 52.5 Endpoint inventory (verify-when-implement)

* Legacy (đã có trong repo, dùng cho PRE_2025 master data): `master-data/{province,district,ward}`
  (`Secomm/GiaoHangNhanh` config `get_{provinces,districts,wards}_url`; wards cần `district_id`).
* Operational v2: `v2/shipping-order/{fee,available-services,create,detail}`, `v2/switch-status/cancel`,
  print, leadtime — path chính xác verify từ developer.ghn.vn lúc implement từng phase.
* New-model master data (Province New / Ward New): GHN docs nói v3 — đường dẫn CHƯA verify trong repo;
  KHÔNG infer field (spike rule); ghi URL nguồn docs vào evidence GHN-B.
* Error envelope `{code, message, data}`; empty HTTP-200 body = shop-not-found quirk (phải throw).
* Webhook: PascalCase payload; dedup `OrderCode + Type + Time`; receiver idempotent; retry-drop 4xx.

## 52.6 Implementation constraints (từ workflow project)

* Mode A Tier-2: spec → task records (Mini-Spec) → plan file (dòng `| Specification |`) → code →
  pre-review → TL review → QC. DB schema (GHN-B, GHN-D) + mọi phase shipping đều Tier-2.
* Parallel coexistence với legacy: carrier code mới (`ghn`), webhook route mới, MQ topic mới,
  config namespace `secomm_ghn/*` — tránh double method/double consume (spike R8).
* PHP 8.2+ `strict_types`, no ObjectManager, parameterized SQL, i18n `vi_VN.csv` + `en_US.csv`,
  README + CHANGELOG, test tại `Test/Unit` (tự nhặt bởi `dev/tests/unit/phpunit-secomm.xml`).
* Không commit/push — owner tự quản. Không sửa ShippingCore/VietNamAddress trong FEAT này
  (mọi gap = slice task riêng có TL review).
