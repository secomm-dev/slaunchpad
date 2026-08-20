# TASK-NDASAD — Secomm ShippingCore origin contract + GHTK origin refactor (shared carrier contract, chuẩn bị fulfillment)

**Legacy ID:** SL-015 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (architecture enabler — không phải user-facing feature; không tách FEAT riêng theo taxonomy)
**Priority:** High
**Estimate:** ~14–20h
**Mode:** A (Tier-2: shipping carrier logic + shared contract/architecture + checkout-critical rate path — AGENTS §9/§11/§12)
**Placement:** `app/code/Secomm/ShippingCore/` (NEW) + `app/code/Secomm/Ghtk/` (refactor)
**Risk tier:** Tier 2
**Author:** AI draft · **Date:** 2026-08-17 · **Status:** Dev complete *(2026-08-17: Tasks 1-9 done, 84 unit tests green, DI compile OK — chờ TL code review (Tier 2) + QC manual; evidence: [TASK-NDASAD-evidence](../runtime/evidence/TASK-NDASAD/TASK-NDASAD-evidence.md))*

## Description

Tạo module **`Secomm_ShippingCore`** — contract dùng chung cho mọi Secomm carrier (GHTK hôm nay; GHN/Ahamove/refactor sau): normalized `ShippingContext` + `Origin` value object + `OriginProviderInterface` với default implementation đọc **Magento Shipping Origin**. Refactor `Secomm_Ghtk` để consume origin qua provider thay vì đọc config pickup trực tiếp trong carrier/request builder, giữ backward compatibility với config `carriers/ghtk/pick_*` hiện có (legacy override chain).

Mục tiêu architecture (invariant):

> Base carrier không sở hữu fulfillment/store-selection logic. Carrier consume một normalized runtime shipping origin thông qua một stable extension contract (`OriginProviderInterface`), để module tương lai `Secomm_ShippingFulfillment` (MSI source-based origin) chỉ cần swap/decorate provider — không sửa carrier.

 KHÔNG implement multi-store routing / MSI selection / nearest store trong ticket này.

## Acceptance Criteria

### Secomm_ShippingCore (contract + default provider)
- [x] **AC-1 (Module):** `Secomm_ShippingCore` đăng ký module, nhỏ/generic, KHÔNG chứa business logic carrier; KHÔNG depend vào bất kỳ carrier nào; deps chỉ Magento core (Framework/Store/Directory/Shipping).
- [x] **AC-2 (ShippingContext):** immutable scalar DTO `ShippingContextInterface` (storeId, websiteId, carrierCode, quoteId, sourceCode — nullable) + factory `fromRateRequest(RateRequest, carrierCode)`. Không nhồi Magento mutable model.
- [x] **AC-3 (Origin):** immutable `OriginInterface`: `sourceCode, countryId, regionId, province, district (NULLABLE), ward, street, postcode, telephone, contactName` + generic metadata (`getMetadata('ghtk.pick_address_id')` — dotted key `{carrierCode}.{key}`). KHÔNG hard-code field GHTK/GHN vào common DTO.
- [x] **AC-4 (OriginProvider):** `OriginProviderInterface::resolve(ShippingContextInterface): OriginInterface`; default `ShippingOriginProvider` đọc `shipping/origin/*` (SCOPE_STORE theo storeId của context) → normalized Origin (region_id giữ nguyên + province = region default name + ward = native city). Provider không quyết định validity — carrier tự đánh giá (ISP).
- [x] **AC-5 (Extension point):** module khác thay được default provider qua DI preference/plugin trên interface, KHÔNG sửa `Secomm_Ghtk`. Có unit test chứng minh (stub provider → GHTK fee request nhận origin từ stub).

### GHTK refactor
- [x] **AC-6 (Origin source):** `Secomm_Ghtk` KHÔNG đọc `shipping/origin/*` trực tiếp; pickup không còn được resolve từ config trong request builder/API client. Carrier nhận origin qua `OriginProviderInterface` chain.
- [x] **AC-7 (BC chain GHTK):** `GhtkOriginProvider` (trong Ghtk): legacy `carriers/ghtk/pick_*` (bất kỳ field nào set) → legacy Origin (metadata `ghtk.pick_address_id` khi có); TẤT CẢ trống → delegate inner `OriginProviderInterface` (default = Magento Shipping Origin). Half-filled legacy config KHÔNG được fallback im lặng (giữ strict DEC-TASKBRKHN4-001 — tránh ship từ kho sai).
- [x] **AC-8 (Pickup mapping + validity):** `PickupAddressResolver` refactor input `OriginInterface → ?PickupAddress`: metadata `ghtk.pick_address_id` ưu tiên (không cần mapping); có regionId+ward → normalize sang tên GHTK qua WardIdBridge + mapping table + best-effort (same machinery như destination); chỉ có names (legacy) → dùng as-is; không đủ → null → carrier hide (DEC-TASKBRKHN4-001 semantics giữ nguyên).
- [x] **AC-9 (Mapper tách client):** `FeeRequestMapper` (GHTK-local) build GHTK payload params từ (dest, pickup, weight, value, transport); `GhtkApiClient` chỉ còn HTTP/auth/timeout/retry/transport — không build business payload, không resolve origin.
- [x] **AC-10 (Rate + shipment cùng origin abstraction):** rate flow dùng provider chain; TASK-KV328X (order sync, parked) khi resume sẽ build ShippingContext từ shipment và gọi cùng `OriginProviderInterface` — ghi rõ trong plan/DEC để tránh origin lệch giữa rate vs submit.
- [x] **AC-11 (Config BC):** giữ nguyên config paths `carriers/ghtk/pick_*` (không breaking); label/comment system.xml cập nhật rõ ràng "legacy override — empty = fall back to Magento Shipping Origin". KHÔNG migrate/drop config. Behavior mới: legacy trống + Shipping Origin hợp lệ → carrier hoạt động (trước đây: inactive).
- [x] **AC-12 (Scope consistency):** GhtkApiClient đọc token/base URL/retry theo storeId của context (trước đây default-scope-only) — behavior superset, không breaking.

### Tests (tối thiểu)
- [x] **AC-13:** Unit tests pass (`vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist`): ShippingOriginProvider (country/province/ward/street/postcode/nullable district/telephone), GhtkOriginProvider (3 nhánh chain), PickupAddressResolver refactor, FeeRequestMapper (metadata priority + fallback address), extension-point test (custom provider thay default không sửa Ghtk). Existing Ghtk tests vẫn pass.

### Docs
- [ ] **AC-14 (partial):** README.md + CHANGELOG.md ×2 module DONE; DEC-TASKNDASAD-001 accepted DONE; còn project-context update (04/09/10) + COMPONENT_INDEX — thực hiện ở closure (sau TL review + QC), theo AGENTS §14.

## Out of Scope (explicit)

- MSI source-based origin / nearest store / inventory routing / split fulfillment / Pancake / OMS → `Secomm_ShippingFulfillment` (ticket kế tiếp).
- Refactor `Secomm_Ahamove` theo contract (module legacy, ticket riêng).
- GHN module (chưa tồn tại — chỉ đảm bảo contract sẵn sàng).
- TASK-KV328X order sync implementation (vẫn parked; chỉ chốt contract cho nó consume).
- Destination resolution (TASK-YJENM2 machinery) — giữ nguyên.

## Risks

- Tier-2: chạm shipping rate path + shared architecture → escalate SA/TL (DEC-TASKNDASAD-001 + plan approval).
- Behavior change AC-11 (legacy trống → dùng Shipping Origin): cần QC rõ — merchant cũ dùng pick_* không bị ảnh hưởng (legacy wins).
- Origin ward không có mapping row → best-effort vi_VN → GHTK có thể reject → no rate (graceful, same semantics như destination). Merchant nên thêm mapping hoặc dùng `pick_address_id`.
- Cache key format đổi → tối đa 1 chu kỳ cold (TTL ≤ 10 min) — negligible.

## Related

- Plan: [TASK-NDASAD plan](../plans/TASK-NDASAD-implementation-plan.md) · Spec: [shippingcore-origin-contract.md](../specs/SPEC-TASK-NDASAD-shippingcore-origin-contract.md) · Decision: [DEC-TASKNDASAD-001](../records/decisions/DEC-TASKNDASAD-001.md) (accepted)
- Builds on: [FEAT-AE761Z](../records/features/FEAT-AE761Z.md) (TASK-YJENM2/009) · DEC-TASKYJENM2-001/021/022/023 · TASK-8WSERX/TASK-2V0AEV (admin VN ward trên Shipping Origin + MSI Source)
