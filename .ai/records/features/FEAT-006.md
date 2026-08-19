---
id: FEAT-006
title: 'GHTK shipping carrier module (Secomm_Ghtk) — services.giaohangtietkiem.vn'
mode: A                      # Tier-2: shipping carrier + external API + secret token + order/shipment sync
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-SL-009-ghtk-carrier-rate.md
risk: high
status: proposed
created: 2026-07-30
updated: 2026-07-30
ticket_ref:
decisions:
  - DEC-018                  # per-carrier customization → project/Secomm package (accepted)
  - DEC-019                  # carrier code/name mapping owned by carrier; generic stays clean (accepted)
  - DEC-8                    # generic vs project boundary (accepted, legacy ADR)
  - DEC-020                  # canonical mapping key (country_id, region_id, ward_id) + fallback semantics (proposed)
  - DEC-021                  # destination vs pickup resolver split + pickup validity (proposed)
  - DEC-022                  # weight contract + rate composition (proposed)
  - DEC-023                  # idempotent async order sync architecture (proposed)
  - DEC-024                  # order sync business rules bundle (proposed)
decision_assessment: material
decision_refs: [DEC-018, DEC-019, DEC-8, DEC-020, DEC-021, DEC-022, DEC-023, DEC-024]
decision_approval_summary:
  total: 8
  pending_approval: [DEC-024]
  approved: [DEC-018, DEC-019, DEC-8, DEC-020, DEC-021, DEC-022, DEC-023]
  rejected: []
  superseded: []
  last_synced: 2026-07-30
verified_against_commit:
# Knowledge-consolidation contract (RM-07)
components:
  - CMP-GHTK                 # Secomm_Ghtk (stable ID, see COMPONENT_INDEX)
source_areas:
  - app/code/Secomm/Ghtk/                                                       # NEW carrier module (project layer)
  - app/code/Secomm/VietNamAddress/                                             # fallback vi_VN name data (reuse, no mod)
  - app/code/Secomm/AddressDropdown/                                            # address data layer (reuse; ward_id source of truth — see DEC-020)
  - app/code/Secomm/ZaloPay/                                                    # cron-backed retry precedent (reuse pattern, no mod)
changes_project_state: true
changes_architecture: true   # new shipping carrier module + mapping table + outbox table
changes_integration: true    # external GHTK API (fee + order)
changes_known_limitations: true
last_verified: 2026-07-30
supersedes: []
---

# Feature Record: GHTK shipping carrier module (Secomm_Ghtk)

<!-- CANONICAL RECORD — large feature (shipping carrier); decomposes into sub-tickets SL-008/009/010. -->
<!-- Consumes the VN 2-level address from FEAT-005 (cart estimator SL-003 + checkout). -->
<!-- 2026-07-30: requirement tightened across 12 areas (fallback semantics, canonical key, resolver split, API base, -->
<!-- weight contract, rate composition, reliability, CSV import, secrets/logging, async order sync, business rules, tier/scope). -->
<!-- 2026-07-30 approvals: DEC-020/021/022/023 ACCEPTED (DEC-020 ward_id=path B bridge; DEC-022 rate composition=config multi-select; DEC-023 webhook-first inbound). DEC-024 partial (pick_money/is_freeship deferred, cancel out-of-phase); B1/B4/B8/B14 still pending → SL-010 stays proposed. SL-008/SL-009 → ready. FEAT-006 stays proposed (DEC-024 + SL-010 pending). -->
<!-- 2026-07-30 SCOPE CUT (user): order sync (SL-010) PARKED — chỉ làm phần fee trước. Current scope = fee only = SL-008 (mapping) + SL-009 (carrier+rate), cả hai `ready`. Order sync (SL-010) + DEC-023/024 deferred (không block fee delivery). FEAT-006 stays `proposed` (incomplete) nhưng fee scope implement-able now. -->

## Context

> **SCOPE (2026-07-30, user):** chỉ làm **phần fee trước** — SL-008 (mapping) + SL-009 (carrier+rate), cả hai `ready` và implement-able now. **Order sync (SL-010) PARKED** (deferred) — DEC-023/024 + COD/pick_money tension không block fee delivery. FEAT-006 stays `proposed` (feature incomplete) nhưng fee scope đã đủ chốt để hiện thực.

Build a Magento shipping carrier `Secomm_Ghtk` (`app/code/Secomm/Ghtk/`) integrating **GHTK (Giao Hàng Tiết Kiệm)** for rate calculation + order/shipment sync. The carrier **consumes** the VN 2-level address already collected by FEAT-005 (province = Magento region, ward/sub-city) and **does not own address UI** (DEC-019). Carrier-specific name/code mapping is **GHTK-owned** in `Secomm_Ghtk` (DEC-018: per-carrier → project/Secomm package; not in generic `Secomm_AddressDropdown`).

A shipping method is a **large feature** → decomposed into sub-tickets (mapping first, then carrier+rate, then order sync).

**Risk:** Tier-2 (shipping carrier + external API + secret token + order/shipment lifecycle — §12) → Mode A, escalate SA/TL.

### Audit findings (2026-07-30) — drive the tightened requirements

- **VN 2-level model**: ward (phường/xã) = the **city** level (`directory_region_city`, PK `city_id`); sub-city (`directory_city_sub_city`) is the deprecated 3rd level → NOT used (FEAT-005 U3: `GetListCity` → Ward). Persistence stores ward as a **name string** (`sub_city`/`city` varchar) → `ward_id` (= `city_id`) not yet on the address payload = compatibility/technical-debt gap (DEC-020).
- **No weight-unit config** in project; `Mageplaza_TableRateShipping` reads raw unitless `weight` attribute. → "weight = kg" is an unverified assumption (DEC-022).
- **No queue/MQ** configured (only `consumers_wait_for_messages`); no publisher/consumer in `app/code`. Cron retry precedent exists in `Secomm_ZaloPay` (`RefundCronjob`) → cron-backed outbox is the lean async option (DEC-023).
- **CSV import** in `Secomm_AddressDropdown` uses Magento ImportExport entity (`etc/import.xml`), NOT an ACL'd upload controller, NOT transactional. → GHTK CSV importer is a new stricter pattern (SL-008).

## GHTK API contract (locked facts — VN doc hiện hành; external verify where flagged)

- **Base URI (runtime default):** `https://services.giaohangtietkiem.vn` — KHÔNG dùng `https://api.ghtk.vn/` làm runtime base. (Khóa trong một API client/config abstraction duy nhất — DEC/SL-009.)
- **Fee** `GET /services/shipment/fee`: `province` (req, name) + `ward` (req, name) + `weight` (req, **gram**) + `value` + `transport` (road/fly); `district` optional. Response: `fee.fee`, `fee.insurance_fee`, `fee.extFees`, `fee.delivery`, `fee.name` (area1/2/3).
- **Order** `POST /services/shipment/order`: `province` + `ward` req, `district` opt, `pick_address_id` ưu tiên; COD/`pick_money`/`is_freeship`/declared value (field contract — external verify, DEC-024).
- **Auth headers:** `Token: {API_TOKEN}` + `X-Client-Source: {PARTNER_CODE}`.
- **Level = 2 cấp thực dụng** (province + ward required; district optional) → trùng project 2-level.
- **Override base URI** cho test/proxy allowed (single abstraction) — không hard-code rải rác, không expose như merchant setting phổ thông nếu không cần.

> **External verification needed:** exact order-API field names/types (COD, `pick_money`, `is_freeship`, declared value, label return) — verify from GHTK doc VN hiện hành / sandbox before implementing the order mapper. KHÔNG bịa field/behavior (DEC-024 Q-EXT, DEC-022 Q2).

## Mapping architecture (mapping-first; canonical key + correct fallback semantics)

**Mapping-first**: mọi data đưa vào GHTK API đi qua **bảng mapping** `secomm_ghtk_address_map`:

1. **Canonical key = `(country_id, region_id, ward_id)`** (DEC-020). `ward_id` = `directory_region_city.city_id` (VN 2-level ward = city level; stable). `ward_name` chỉ dùng display/audit/import (`source_province_name`, `source_ward_name` lưu để trace, không làm join key). `UNIQUE(country_id, region_id, ward_id)`.
2. **CSV upload trong admin config** cập nhật/seed bảng mapping theo canonical key (SL-008).
3. **Fallback vi_VN khi thiếu mapping — semantics đã sửa (DEC-020):** mapping miss → resolver lấy tên vi_VN chuẩn từ data layer (`Secomm_AddressDropdown`/`VietNamAddress`) để build **best-effort request**. Fallback chỉ đảm bảo **có dữ liệu hợp lệ để build request**; **KHÔNG đảm bảo** GHTK nhận diện địa chỉ. API có thể reject (naming convention, địa giới thay đổi, khu vực không hỗ trợ, pickup/destination không hợp lệ). Mapping/API failure phải **graceful**: không crash cart/checkout; không trả rate nếu GHTK không phục vụ; log đủ dữ liệu đã mask để bổ sung mapping.
4. **Resolver flow (mọi call API):** prefer stable `ward_id` → lookup map → hit: GHTK names | miss: best-effort vi_VN names → build request. Name fallback chỉ khi request cũ không có `ward_id` (compatibility path).

## Destination vs Pickup resolver split (DEC-021)

- **`DestinationAddressResolver`** — resolve `country_id + region_id + ward_id → GHTK mapping → best-effort vi_VN fallback` (mapping miss không fatal).
- **`PickupAddressResolver`** — resolve pickup từ admin config. Có `pick_address_id` → ưu tiên gửi, không cần pickup mapping. Không có `pick_address_id` → bắt buộc resolve `pick_province` + `pick_ward` (`pick_district` optional); config thiếu/resolve fail → **carrier invalid/inactive theo scope** (không gửi request mơ hồ). Có admin connectivity/health check.

## Requirements (feature-level; chi tiết + AC trong sub-ticket)

- **AC-001 (Carrier):** module `Secomm_Ghtk` đăng ký; carrier `ghtk` trong `config.xml`; admin config `system.xml` (API Token **encrypted** backend, X-Client-Source, pickup/`pick_address_id`, transport default, `weight_unit` + min/default weight).
- **AC-002 (Mapping data layer — SL-008):** bảng `secomm_ghtk_address_map` (canonical key `(country_id, region_id, ward_id)`) + resolver mapping-first + **best-effort** vi_VN fallback (semantics đúng) + CSV upload admin (ACL/form-key/MIME/UTF-8/transactional).
- **AC-003 (Rate — SL-009):** `collectRates` → dest+pickup resolver → `fee` API → rate; `ShipmentWeightCalculator` (unit config → gram; shippable only; configurable/bundle; missing/decimal/qty; min fallback); **rate composition** (`fee.fee`/`insurance_fee`/`extFees`/`delivery`, `delivery` denies → no method, no silent drop) trong response mapper; **displayed amount = base `fee.fee` + config multi-select "include" (`insurance_fee`, `extFees`)** (DEC-022 Q1 accepted); reliability (timeout/retry/no-4xx-retry/graceful) + short-TTL cache; error-graceful (no rate, no crash).
- **AC-004 (Token/security):** API Token + `X-Client-Source` trong admin config (`\Magento\Config\Model\Config\Backend\Encrypted` cho Token); KHÔNG commit code; mask log (Token, phone, email, full address, customer PII); production log chỉ correlation_id/Magento IDs/HTTP status/GHTK error/masked destination/retry attempt.
- **AC-005 (i18n):** carrier/method title vi_VN + en_US.
- **AC-006 (Integration):** hoạt động với FEAT-005 cart estimate + checkout; non-VN → carrier inactive (gate).
- **AC-007 (Order sync — SL-010, ⛔ PARKED 2026-07-30):** out of current scope (fee-first). Khi resume: **idempotent async** order/shipment sync — outbound cron-backed outbox + inbound webhook (DEC-023 accepted) + business rules (DEC-024 partial). Hiện KHÔNG hiện thực — chỉ giữ requirement/design parked.

## Tier / scope (Launchpad principle — core phải vận hành hoàn chỉnh)

- **Current scope (fee-first, 2026-07-30):** **GHTK rate calculation only** = mapping (SL-008) + carrier/rate (SL-009) + graceful API failure + encrypted token + admin mapping import. Implement-able now.
- **Deferred / parked (order sync — SL-010):** GHTK shipment/order creation (outbound outbox) · webhook status sync · tracking-number persistence · retry + manual retry · **toàn bộ order sync (DEC-023/024)** · MQ queue · cancel API · print label · `pick_money`/`is_freeship` · advanced reconciliation. (Delivery sequencing — KHÔNG tier exclusion; không dùng Growth/Omni để che Core thiếu.)
- **Growth/Omni:** KHÔNG dùng để che Core thiếu integration shipping cơ bản.

## Approach & Decisions

- **DEC-018 / DEC-019 / DEC-8 (accepted):** per-carrier customization + carrier-owned code/name mapping → `Secomm_Ghtk` (project); generic module sạch.
- **DEC-020 (accepted 2026-07-30):** canonical key `(country_id, region_id, ward_id)` + correct **best-effort** fallback semantics; ward_id = `city_id` (2-level ward); ward_id path = **B (GHTK-internal name→`city_id` bridge)**; path A = long-term follow-up. → SL-008 unblocked.
- **DEC-021 (accepted 2026-07-30):** destination vs pickup resolver split + pickup validity gate.
- **DEC-022 (accepted 2026-07-30):** `ShipmentWeightCalculator` (unit config, not assumed kg); **displayed amount = base `fee.fee` + config multi-select "include" (`insurance_fee`, `extFees`)** (Q1 resolved); order API same unit gram (Q2 resolved). → SL-009 unblocked.
- **DEC-023 (accepted 2026-07-30 — subject PARKED):** idempotent async sync — webhook-first inbound + outbox/cron outbound + `secomm_ghtk_shipment` + state machine; MQ queue deferred. Architecture decided nhưng **order sync (SL-010) parked** (fee-first) → DEC-023 không block fee delivery.
- **DEC-024 (proposed — partial — subject PARKED):** order-sync business rules — `pick_money`/`is_freeship` (B2/B3) deferred, cancel (B9) out-of-phase; B1/B4/B8/B14 pending. **Parked cùng SL-010** (fee-first) → KHÔNG block SL-008/009.

## Sub-ticket breakdown + readiness gating

- **[SL-008](../../tickets/SL-008-ghtk-address-mapping.md)** — GHTK address mapping: bảng `secomm_ghtk_address_map` (canonical key) + resolver (mapping-first + best-effort fallback) + admin CSV upload (hardened). → **`ready`** (DEC-020 accepted, path B; CSV contract + fallback semantics đã chốt; ward_id source verify qua bridge).
- **[SL-009](../../tickets/SL-009-ghtk-carrier-rate.md)** — Carrier skeleton + rate collection (`collectRates` → dest+pickup resolver → `fee` API → rate) + `ShipmentWeightCalculator` + reliability/cache + rate composition (config multi-select) + admin config. → **`ready`** (DEC-021 + DEC-022 accepted; pickup/weight/rate-composition/timeout-cache-error/config-security AC đầy đủ).
- **[SL-010](../../tickets/SL-010-ghtk-order-sync.md)** — Order sync: idempotent async (webhook-first inbound + outbox outbound) + persistence model + business rules (DEC-023/024). → **⛔ PARKED 2026-07-30 (fee-first)** — KHÔNG hiện thực phase này; DEC-023/024 giữ parked. Khi resume: cần chốt DEC-024 B1/B4/B8/B14 + COD/pick_money tension.

> Status gating: KHÔNG đánh dấu `ready` chỉ vì file requirement đã viết. SL-008/009 → `ready` (DEC-020/021/022 accepted + dependency verify) → **implement-able now (fee scope)**. SL-010 PARKED (order sync deferred). **FEAT-006 stays `proposed`** (feature incomplete — order sync chưa làm) nhưng fee scope đủ chốt.

## Implementation Notes

Chưa bắt đầu (proposed). **Scope hiện tại = fee-first:** SL-008 (mapping) → SL-009 (carrier+rate) — cả hai `ready`, implement-able now. **SL-010 (order sync) PARKED** (fee-first; resume sau).
- Resolver phụ thuộc data vi_VN từ `Secomm_VietNamAddress`/`Secomm_AddressDropdown` cho fallback — **reuse, không duplicate** VN master data trong `Secomm_Ghtk`.
- ward_id: xử lý gap DEC-020 trước khi SL-008 ready.
- Weight: đọc `weight_unit` config → normalize gram; min/default fallback; không assume kg.
- CSV upload: hardened importer (ACL + form key + MIME + UTF-8/BOM + size + full-file validate before commit + transactional + row validate + duplicate detect + upsert theo stable key + summary + sample download + audit timestamp/identity). Lean — không bắt buộc Magento Import Framework nếu importer service nhỏ testable transactional phù hợp hơn.
- Async: cron-backed outbox (precedent `Secomm_ZaloPay`); không gọi GHTK order API trong shipment-save transaction.

## Test Summary

Pending (proposed). QC covers: rate GHTK đúng trên cart estimate + checkout (VN); mapping hit + miss (best-effort fallback, không "luôn work"); CSV upload upsert + validation; API fail graceful (no crash, no rate); non-VN inactive; token encrypted; weight (simple/configurable/bundle/qty>1/missing/decimal/virtual/min-fallback); rate composition (insurance/surcharge, high order value); reliability (timeout/retry/no-4xx); order sync idempotency + retry + manual retry + `ORDER_ID_EXIST` reconcile.

## Compatibility Conclusions

- **Themes:** Hyvä ✅ (carrier = backend rate logic, theme-agnostic); Luma ✅.
- **Modules:** `Secomm_Ghtk` (new); reuse `Secomm_AddressDropdown` + `Secomm_VietNamAddress` (no mod); reuse cron pattern from `Secomm_ZaloPay` (no mod).
- **API:** GHTK fee + order (external, `services.giaohangtietkiem.vn`); auth Token + X-Client-Source.
- **Upgrade notes:** GHTK doc drift (EN lỗi thời) → re-verify doc VN + sandbox khi hiện thực; re-verify order-API field contract.

## References

- Sub-tickets: [SL-008](../../tickets/SL-008-ghtk-address-mapping.md) (mapping) · [SL-009](../../tickets/SL-009-ghtk-carrier-rate.md) (carrier+rate) · [SL-010](../../tickets/SL-010-ghtk-order-sync.md) (order sync — ⛔ parked)
- Specs (fee scope): [ghtk-address-mapping](../../specs/SPEC-SL-008-ghtk-address-mapping.md) (SL-008) · [ghtk-carrier-rate](../../specs/SPEC-SL-009-ghtk-carrier-rate.md) (SL-009)
- Plans (fee scope): [SL-008 plan](../../plans/SL-008-implementation-plan.md) · [SL-009 plan](../../plans/SL-009-implementation-plan.md)
- Related: [FEAT-005](FEAT-005.md) (VN shipping address — carrier consume address; ward_id canonical note via DEC-020)
- Decisions: [DEC-018](../decisions/DEC-018.md) · [DEC-019](../decisions/DEC-019.md) · DEC-8 (accepted) · [DEC-020](../decisions/DEC-020.md) · [DEC-021](../decisions/DEC-021.md) · [DEC-022](../decisions/DEC-022.md) · [DEC-023](../decisions/DEC-023.md) · [DEC-024](../decisions/DEC-024.md) (proposed)
- API docs (VN hiện hành): [fee](https://api.ghtk.vn/docs/submit-order/calculate-shipping-fee/) · [order](https://api.ghtk.vn/docs/submit-order/submit-order-express/) — runtime base = `services.giaohangtietkiem.vn`; re-verify field contract
- Risk tier: AGENTS.md §12 (shipping + secret + order — Tier 2)
- Toolkit version: v4.0
