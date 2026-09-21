---
id: TASK-XXBN5X
type: task
title: 'Phase E-SL0 — Shipping service-level + fallback contracts trong Secomm_ShippingCore (contracts only)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-XXBN5X — contract shapes theo approved E-SL0 directive (SPIKE-WHHEZV/YH439T basis); TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-XXBN5X-shippingcore-service-level-fallback-contracts.md
risk: medium                  # additive contracts; 0 carrier/Mageplaza/Launchpad code, 0 orchestration, chưa có runtime consumer
status: in_progress
priority: high
decision_assessment: none-material   # thực thi SPIKE-WHHEZV §7/§9/§13/§17 (đã approved); không có architecture decision mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [SPIKE-WHHEZV, SPIKE-YH439T, TASK-5XDG1P]
---

# [SLP][FEAT-YA2C0W][TASK-XXBN5X] Phase E-SL0 — Shipping service-level + fallback contracts trong Secomm_ShippingCore (contracts only)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-XXBN5X-shippingcore-service-level-fallback-contracts.md, FULL)*

### Goal

Đưa minimum reusable API cho mô hình `Carrier ≠ Service Level ≠ Rate Source`:
`ShippingServiceLevel` (EXPRESS/SAME_DAY/STANDARD + validate) · `CarrierServiceLevelInterface`
(carrier khai báo level) · `FallbackRateRequestInterface` (provider-neutral, KHÔNG RateRequest) ·
`FallbackRateInterface` (amount/label/estimate) · `FallbackRateProviderInterface` (?rate, null =
no rate) · `FallbackRateProviderPool` (zero/one provider, D7). Contracts ONLY — không orchestration.

### Expected Behavior

1. **r1 (dynamic model)**: `ShippingServiceLevelInterface` (code/label/enabled/sortOrder) + VO +
   `ShippingServiceLevelRegistry` (DI array, zero-level valid, duplicate/empty-code → LogicException,
   `getByCode`/`has`/`getEnabled`/`getAll`/`assertKnown`) — ShippingCore KHÔNG hardcode taxonomy;
   Launchpad_*/project composition sở hữu định nghĩa EXPRESS/SAME_DAY/STANDARD (không seed trong
   task này). Code = machine identity, label = configurable presentation.
2. `CarrierServiceLevelInterface::getServiceLevels(): string[]` — carrier khai báo (GHN/GHTK →
   STANDARD; Ahamove → EXPRESS/SAME_DAY — ví dụ, KHÔNG sửa carrier trong E-SL0); docblock ghi
   known limitation carrier-level declaration (directive §5).
3. `FallbackRateRequest` 8 trường neutral mapped 1-1 từ audit Mageplaza (countryId, regionId,
   postcode, weight, subtotal, qty, storeId, customerGroupId) — KHÔNG carrier code/method_id/
   Magento models/PII/canonical candidates; KHÔNG shipping-group data (profiles group-less đủ —
   extension follow-up nếu có evidence); region/postcode granularity chấp nhận được (§8: fallback
   pricing không phải eligibility engine); negative dims → `LogicException`.
4. `FallbackRate` amount>0 (zero/negative → `LogicException` — enforce "no-match ⇒ null, không
   zero-fee") + label non-empty + estimate nullable; KHÔNG carrierCode, KHÔNG metadata dump.
5. `FallbackRateProviderInterface::getRate(serviceLevel, request): ?FallbackRate` — provider
   CHỈ trả lời có giá configured/matched; KHÔNG quyết eligibility (directive §12).
6. `FallbackRateProviderPool` mirror `ExternalAddressResolverPool`: zero valid, order preserved,
   invalid entry → `LogicException`, không competition/chaining.

### Constraints / Rules

- KHÔNG raw `Magento\Quote\...\RateRequest` qua provider API (directive §6).
- KHÔNG: Mageplaza/TableRate/mptablerate/method_id reference trong ShippingCore (grep AC-6);
  Launchpad dependency; fallback trigger policy; per-level `fallback_enabled` config; rate outcome
  taxonomy; checkout methods; admin config; DB; external resolver.
- Namespace: `Api\ShippingServiceLevel` + `Api\CarrierServiceLevelInterface` (flat);
  `Api\Fallback\*` + `Model\Fallback\*` (sub-namespace mirror `Api\Address`/`Api\Tracking`).
- PHP 8.2+ strict_types; DI constructor; không ObjectManager; float theo monetary convention
  project (rate setPrice(float)).
- Architecture decisions mới: KHÔNG (thực thi SPIKE-WHHEZV đã approved; amount>0 enforcement là
  cấu trúc hóa rule §11 — report TL review).

### Out of Scope

Mageplaza bridge · fallback trigger/policy · realtime aggregation · carrier outcome model ·
carrier address handoff (E-C0) · checkout methods · external resolver · admin config · DB schema ·
fallback order metadata · carrier modules (Ghn/GiaoHangNhanh/GhnAddressMapper/Ghtk/Ahamove) ·
Mageplaza_TableRateShipping · Launchpad_*.

### Acceptance Criteria

AC-1..AC-8 của SPEC-TASK-XXBN5X (tóm tắt): constants + validate · carrier contract shape ·
request 8-trường neutral + invariants · rate amount>0 + no-metadata · provider ?rate semantics ·
pool zero/one · grep 0 Mageplaza/Launchpad/RateRequest · tests + compile + validator pass +
README/CHANGELOG (2 engineering rules) + working memory sync.

## Plan

`../plans/TASK-XXBN5X-implementation-plan.md`

## Review rounds

- **r2 (2026-09-08, TL/SA review TASK-NAT3YV)** — `FallbackRate` amount **>= 0**: zero là valid
  explicit rate (invariant `> 0` bị REJECT — xung đột rule domain ShippingCore "negative =
  invalid, zero = valid explicit rate, null = no fallback rate"); `null` vẫn là cách duy nhất
  diễn đạt "no fallback rate"; policy cấm zero-fallback (nếu cần) thuộc project/Launchpad
  fallback policy, không phải core VO invariant. Cập nhật SPEC §3.4/AC-4 r2 + VO + tests.
- **r1 (2026-09-08, TL/SA review)** — REJECT hardcoded service-level constants: ShippingCore là
  reusable foundation, không hardcode Launchpad taxonomy (`EXPRESS/SAME_DAY/STANDARD` = business
  defaults, không phải universal invariant). Thay bằng dynamic model: `Api\ShippingServiceLevelInterface`
  + `Model\ServiceLevel\{ShippingServiceLevel VO, ShippingServiceLevelRegistry}` (DI array,
  zero-level valid, validation dynamic qua registry — thay static `all/exists/assertKnown`).
  Storage = Option A DI registration (B = config rows là upgrade path, C = DB rejected).
  `fallback_enabled` KHÔNG thêm vào definition (tách generic identity khỏi fallback policy —
  recommendation report). Carrier contract giữ nguyên shape, values = dynamic codes + docblock
  portability implication. Fallback contracts không đổi signature (chỉ docblock bỏ constant ref).
  Launchpad defaults KHÔNG seed trong task này (out of scope §6).
