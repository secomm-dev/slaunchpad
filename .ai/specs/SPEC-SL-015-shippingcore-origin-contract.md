# Spec: Secomm ShippingCore Origin Contract (SL-015)

Specification ID: SPEC-SL-015
Feature ID: NONE
Specification Level: FULL

> **Status:** Implemented — retro-canonical (distilled from approved requirement 2026-08-17 + DEC-SL015-001 + delivery evidence; verified against working tree 2026-08-18).
> **Mode A** · Tier 2 · Tickets: SL-015 · Decision: DEC-SL015-001 (accepted)

# Purpose

Một contract chuẩn dùng chung cho mọi Secomm shipping carrier: origin runtime (nơi shipment xuất phát) được resolve qua một stable extension contract thay vì từng carrier tự đọc config. Chuẩn bị cho `Secomm_ShippingFulfillment` (MSI-source origin, multi-store routing) mà không phải sửa carrier.

# Scope

- Module `Secomm_ShippingCore`: `ShippingContextInterface`, `OriginInterface`, `OriginProviderInterface` + default provider đọc Magento Shipping Origin.
- Refactor `Secomm_Ghtk` consume contract; BC chain legacy `carriers/ghtk/pick_*`.

# Out of Scope

MSI source-based origin / nearest store / inventory routing / split fulfillment / Pancake / OMS (`Secomm_ShippingFulfillment` — ticket kế) · refactor `Secomm_Ahamove` · GHN module · SL-010 order sync · destination resolution (SL-008 giữ nguyên).

# Actors / Context

Carrier (GHTK hôm nay; GHN/Ahamove sau) · module fulfillment tương lai (thay provider qua DI) · merchant (cấu hình Shipping Origin / legacy pick_*).

# Business Rules

- BR-SC-01: Carrier KHÔNG sở hữu fulfillment/store-selection logic; carrier chỉ consume một normalized runtime origin qua stable contract.
- BR-SC-02: Default origin = Magento Shipping Origin (`shipping/origin/*`), store-scoped theo context.
- BR-SC-03 (BC GHTK): legacy `pick_*` set (bất kỳ field) thắng; TẤT CẢ trống mới fallback Shipping Origin; half-filled KHÔNG fallback im lặng (strict DEC-021 — tránh ship từ kho sai).
- BR-SC-04: Carrier metadata (vd `ghtk.pick_address_id`) đi qua generic dotted-key (`{carrierCode}.{key}`) — không hard-code field carrier vào common DTO.

# System Behaviour

- `ShippingContext` = immutable scalar DTO (storeId, websiteId, carrierCode, quoteId, sourceCode — nullable); factory `fromRateRequest()` / `fromShipment()` cho rate và label flow — hai path cùng origin abstraction.
- `Origin` = immutable VO: sourceCode, countryId, regionId (giữ nguyên để carrier normalize tên bằng id — DEC-020), province, **district (nullable — Launchpad VN model)**, ward, street, postcode, telephone, contactName, metadata.
- Provider trả **data snapshot**, KHÔNG quyết validity — usability là policy của carrier (DEC-021 gate nằm trong carrier).
- Extension: DI preference/plugin trên `OriginProviderInterface` — không sửa carrier, không observer mutate payload.

# Main Flows

1. Rate: `RateRequest → ShippingContext → OriginProvider chain → PickupAddressResolver (strict gate) → FeeRequestMapper → GhtkApiClient`.
2. Label submit (SL-016): `Shipment → fromShipment() → cùng chain`.

# Edge Cases

Half-filled legacy → carrier hide (không ambiguous request) · Shipping Origin ward không có mapping row → best-effort vi_VN → GHTK có thể reject → graceful no-rate · origin thiếu ward → hide + warning log.

# Contracts / Invariants

> Base carrier không sở hữu fulfillment/store-selection logic. Carrier consume một normalized runtime shipping origin thông qua một stable extension contract (`OriginProviderInterface`).
> Mọi Secomm carrier tương lai tuân cùng Shipping Core architecture.

Rate và shipment dùng cùng origin abstraction — không xảy ra rate origin A / submit origin B khi context không đổi.

# Failure Behaviour

Origin unusable → carrier hidden (no rate / no label), warning log masked — never crash checkout; provider không throw ra ngoài pipeline.

# Acceptance Criteria

- AC-1 Module generic, không depend carrier · AC-2 scalar DTO context · AC-3 Origin + nullable district + metadata · AC-4 provider default đọc Shipping Origin · AC-5 module ngoài thay provider không sửa Ghtk (unit-test proof) · AC-6 Ghtk không đọc `shipping/origin/*` trực tiếp · AC-7 BC chain như BR-SC-03 · AC-8 mapper tách client · AC-9-12 config BC + scope consistency · AC-13 unit tests · AC-14 docs.
(Chi tiết: [ticket SL-015](../tickets/SL-015-shippingcore-origin-contract.md); evidence: `.ai/runtime/evidence/SL-015/`)

# Related Decisions

DEC-SL015-001 (accepted) · DEC-020/021/022 · DEC-018/019 · SL-013/SL-014 (admin VN origin data).
