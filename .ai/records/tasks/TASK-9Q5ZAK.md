---
id: TASK-9Q5ZAK
type: task
title: 'Phase GHN-D — Create Order (is_new_to_address=true) + cancel/return + MQ + secomm_ghn_shipment persistence'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-FEAT-FQWEQ3 — canonical Full Spec (slice reference; đặc biệt §16..§22, §31..§32, §44)
specification_ref: ../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md
plan: ../../plans/TASK-9Q5ZAK-implementation-plan.md   # GHN-D slice 2026-09-15: CREATE-only (TL brief §24/§34/§35/§38) — synchronous commit_after observer, cancel/return/MQ chuyển backlog
risk: high                    # order/shipment lifecycle + DB schema (Tier-2) + external money-related operation
status: in_progress           # activated 2026-09-15: CREATE-only slice (scope-narrow deviation REPORT TL — mini-spec async MQ/cancel/return deferred)
priority: medium
decision_assessment: material   # create payload theo DEC-FEATFQWEQ3-001; package/shipment ShippingCore slices cần TL review riêng
decisions: [DEC-FEATFQWEQ3-001, DEC-FEATYA2C0W-004]
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-10
updated: 2026-09-10
owner: [dev]
related_tickets: [TASK-FMBBSD, TASK-RR1ZFN]
---

# [SLP][FEAT-FQWEQ3][TASK-9Q5ZAK] Phase GHN-D — Create Order (is_new_to_address=true) + cancel/return + MQ + secomm_ghn_shipment persistence

> **SLICE 2026-09-15 (TL brief §24/§34/§35/§38 — scope thu hẹp, deviation REPORT TL):** slice này
> implement **CREATE ONLY** qua synchronous observer `sales_order_shipment_save_commit_after`
> (label capability giữ FALSE — GHN create response không có PDF; §35 option A). Cancel/return,
> MQ async (mini-spec điểm 4/5), tracking webhook → backlog (GHN-E/F). `client_order_code`
> chốt = `GHNS{shipment entity_id}`. COD: `cod_amount` omitted (=0) — KHÔNG inspect payment
> method, COD amount policy = upstream slice. Dimensions nguồn = config merchant default
> parcel (cm, empty → fail-closed). Gate DEC #4 (merged-ward): sandbox L8TL6B đã dùng "Xã Tân
> Thanh" (merged ward) — confirm lại trong slice, evidence → `.ai/evidence/TASK-9Q5ZAK/`.

**BLOCKED** tới khi: (1) GHN-C done — **DONE (QC r3 PASS)**; (2) staging evidence merged-ward — **DONE (sandbox L8TKYG + evidence slice)**; (3) ShippingCore slices — **KHÔNG cần (per-op contracts v5 đủ dùng, 0 edit)**; (4) `secomm_ghn_shipment` schema — **approved (plan 2026-09-15) + đã apply**. Original gates:

## Progress Log

- **2026-09-15 — r3 (TL review correction, sandbox-driven) dev-complete.** Probe thật 2 lần trên
  shipment#14: (1) type-5 KHÔNG root weight → HTTP 400 `ShiipCreate.Weight failed on the 'required'
  tag` ⇒ **root weight PROVIDER-MANDATORY**; (2) root weight Σ=60,000g (>50,000 cap) + items[] →
  **ACCEPTED** (L8TTRG 550,000 VND, đã cancel) ⇒ cap áp per-package, root Σ ok với items[]; (3)
  probe trước đó: root dims KHÔNG required (ACCEPTED không length/width/height). Kết luận r3:
  type-5 payload = root `weight` (factual Σ) + items[]; root dims OMITTED; toàn bộ synthetic
  aggregate (largest-by-volume/Σ-dims) đã xóa khỏi interpreter. `GhnParcelPlan` root fields nullable.
  Persister → **MERGE semantics** (giữ non-marker entries — forward-compat native label flow, audit
  r3: LabelGenerator là core writer duy nhất, gated isShippingLabelsAvailable=FALSE với GHN → 0
  collision; GHN-E phải merge khi bật). markSubmitted clear provider_reason_code. Payload tests
  chuyển absence-assertion (weight=Σ present; length/width/height absent). Tests cross-module
  **862 / 0F / 0E**. Persistence audit kết luận: **COMPATIBLE (B — conversion boundary GHN-E)**.

- **2026-09-15 — r2 Physical Package Alignment (TL-requested correction) dev-complete.** Root cause
  r1: parcel build GHN-specific (Σ items + GHN config dims → 1 parcel type-2-only). r2: ShippingCore
  owns carrier-neutral physical facts (DEC-TASK9Q5ZAK-001 proposed): `Api/Physical/` 3 contracts +
  VOs + `ShipmentPhysicalPersister` (sales_shipment.packages marker `secomm_physical` — Magento-native,
  0 bảng mới, label-flow exclusive) + `ConfiguredDefaultPackageDimensions` prefill-only (config move
  `secomm_shippingcore/physical/*`; GHN parcel_* REMOVED). GHN: `GhnPhysicalParcelInterpreter` +
  `GhnParcelPlan` + `GhnPhysicalLimit` (50,000g/200cm/multi) — type 2 root / type 5 items[]-per-
  physical-package ("Package N", qty 1, exact) — **type-5 CREATE giờ SUPPORTED**; multi-package =
  1 Shipment → N pkg → 1 GHN order. Observer: in-flight guard (persister save re-fire), HttpRequest
  concrete, POST `shipment[physical_packages]` pass-through; admin "Package Information" block
  (extra_shipment_info, prefill Σ-items + ShippingCore dims + limits + Add Package). Missing final
  physical data → fail-closed INVALID_PARCEL (merchant default không bao giờ auto-inject).
  Idempotency/address/COD/retry/log GIỮ NGUYÊN. Sandbox r2: **A** type-2 L8TWX8 122,100 · **B**
  type-5 heavy single L8TWAF 550,000 (items[0], price omitted accepted — probe flag ✓) · **C**
  multi-package 2 pkgs L8TWAP 550,000 (root Σ=50,000g biên accepted — probe flag ✓) · **D** pkg
  201cm → INVALID_PARCEL 0 HTTP · cả 3 order cancelled. Tests: cross-module **859 / 0F / 0E**
  (ShippingCore +physical suite; Ghn interpreter/builder/service/observer rewritten). RATE giữ
  option A (type-5 backlog). ShipmentPlane note: `getTotalWeight()` vendor-verified never populated
  — weight prefill = Σ items weight×qty.

- **2026-09-15 — GHN-D slice (CREATE-only) dev-complete, chờ TL review.** Trigger: observer
  `sales_order_shipment_save_commit_after` (post-commit, không lock) + `GhnShipmentCreationService`
  (PENDING anchor TRƯỚC API; SUBMITTED short-circuit; UNKNOWN cho kết quả uncertain — không bao giờ
  auto-retry/blind re-create) + `client_order_code = GHNS{shipment entity_id}` + `secomm_ghn_shipment`
  (SPEC §20, 0 column sales_order) + TrackAttacher native (dedupe) + CLI
  `secomm:ghn:shipment:retry` (§28 recovery) + `GhnCreateCapabilityAdapter`/`StoreWeightConverter`
  shared (RATE delegate) + parcel dims config fail-closed + type-5 fail-closed
  `GHN_HEAVY_PARCEL_UNSUPPORTED` + `cod_amount` KHÔNG gửi (0 payment inspection). **Sandbox runtime:
  create thật L8TKYG 122,100 VND qua merged ward "Xã Tân Thanh" (DEC gate #4), provider idempotency
  same-code → same-order PROVEN, INVALID_PARCEL/PROVIDER_MAPPING_MISSING 0 HTTP, L8TKYG đã cancel,
  log 0 PII.** Tests: Ghn **203 / 0F / 0E** (+25); regression 4 module sạch; grep gates 0.
  Deviations REPORT TL: CREATE-only (cancel/return/MQ → GHN-E/F); synchronous observer thay MQ;
  COD policy upstream; provider_reason_code = service token. Evidence:
  `.ai/evidence/TASK-9Q5ZAK/create-slice.md`.
## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md, FULL — đặc biệt §16 Package, §17 Create, §18 Sender, §19 Idempotency, §20 Persistence, §21 Trigger, §22 Queue, §44 AC-SHIP)*

### Goal

Lifecycle provider shipment: create GHN order dùng current administrative address (names, không
IDs), cancel + return là independent operations, chạy async qua MQ sau provider service boundary,
persist vào `secomm_ghn_shipment` (không columns GHN trên `sales_order`), idempotent retry-safe.

### Expected Behavior

1. Create payload: `is_new_to_address = true`; `to_province_name`/`to_ward_name` từ mapping
   `VN_ADMIN_2025 → GHN_ADMIN_2025` (`GhnMappingResolver` trả name); `to_district_name` empty;
   KHÔNG `to_district_id`/`to_ward_code` (AC-SHIP-001..003). Sender từ `OriginProviderInterface`
   (new model → `is_new_from_address=true`; hoặc omit `from_*` khi dùng ShopId default — config).
2. `client_order_code` shipment-derived (đề xuất `'GHNS' + shipment increment`, chốt tại plan) —
   stable retry, unique per shop; 1 order → N provider shipments (AC-SHIP-004); KHÔNG dùng order
   increment id universally.
3. Package thật từ ShippingCore package DTO (weight/dims/declared_value/cod/items) — cấm 1×1×1
   (AC-SHIP-005).
4. Trigger: shipment/fulfillment flow (KHÔNG checkout-success/sales_order_place_after làm primary
   contract); async qua MQ topics `secomm_ghn.shipment.{create,cancel,return}` connection `db`
   (lean default); consumers idempotent + retry + normalized errors + safe logging.
5. Cancel (`v2/switch-status/cancel`) + Return là operations riêng, async-capable, idempotent,
   chỉ hợp lệ ở provider states cho phép; KHÔNG model chung `changeStatus()`.
6. Persistence `secomm_ghn_shipment` (SPEC §20): shipping_reference, magento_order_id/shipment_id,
   shop_id, client_order_code, ghn_order_code, service_type_id, provider_status, provider_reason_code,
   quoted_fee/actual_fee/cod_amount, expected_delivery_at; KHÔNG mirror toàn bộ Order Info.
7. Admin actions (create/sync, refresh, cancel, return, label, view status/last error) qua
   contracts — controller không gọi `GhnApiClient` trực tiếp.

### Constraints / Rules

- Không columns GHN mới trên `sales_order`/`quote_address` (AC-ARCH-006, §38).
- Idempotency: retry create với cùng `client_order_code` không tạo duplicate GHN order (spec §19).
- COD/payment_type/required_note preserve business behavior (config GHN-A).
- MQ không assume RabbitMQ; db connection default; consumers không swallow exception.

### Out of Scope

Tracking webhook + status mapper (GHN-E) · cutover (GHN-F) · advanced COD OTP update (deferred
spec §27) · pickup scheduling · ShippingCore slice implementation (task riêng).

### Acceptance Criteria

- AC-D1: create order thật trên staging thành công với payload names + `is_new_to_address=true`
  (kèm evidence merged-ward gate).
- AC-D2: retry create (consumer re-run) không tạo duplicate — idempotency test.
- AC-D3: cancel + return độc lập, idempotent, state-guard test.
- AC-D4: `secomm_ghn_shipment` persist đúng; `sales_order` không có column mới.
- AC-D5: package dims thật trong payload (test assert không 1×1×1).
- AC-D6: phpunit scoped green; QC L3 order/shipment lifecycle staging.
