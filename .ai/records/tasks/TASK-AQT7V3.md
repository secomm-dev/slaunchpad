---
id: TASK-AQT7V3
type: task
title: 'Phase E-A — Lean shipping address resolution contracts trong Secomm_ShippingCore (contracts only)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-AQT7V3 — scope + contract shapes theo approved Phase E-A directive (SPIKE-W273TB basis); TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-AQT7V3-shippingcore-address-resolution-contracts.md
risk: medium                  # additive contracts; 0 carrier code, 0 schema, 0 runtime behavior change
status: in_progress
priority: high
decision_assessment: none-material   # thực thi DEC-FEATYA2C0W-004 (đã accepted); không có architecture decision mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [SPIKE-W273TB, TASK-Q4B98P]
---

# [SLP][FEAT-YA2C0W][TASK-AQT7V3] Phase E-A — Lean shipping address resolution contracts trong Secomm_ShippingCore (contracts only)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-AQT7V3-shippingcore-address-resolution-contracts.md, FULL)*

### Goal

Đưa minimum contracts vào `Secomm_ShippingCore` để Phase E-B (local canonical shipping address
orchestration) implement được mà không phải sửa API surface: carrier capability (required scheme +
textual fallback), shipping resolution result (EXACT/MAPPED/AMBIGUOUS/UNMAPPED), resolution context,
external resolver extension point + zero-provider pool. Contracts/foundation ONLY — không orchestration.

### Expected Behavior

1. `Secomm_ShippingCore` sequence `Secomm_VietNamAddress` (chiều DEC-FEATYA2C0W-004 D1); không
   reverse dependency; 0 carrier module thay đổi.
2. `Api\Address` contracts: `CarrierAddressCapabilityInterface` (getRequiredScheme,
   supportsTextualFallback — khai báo thôi, không trigger), `ResolvedShippingAddressInterface`
   (status REUSE `VnAddressResolutionInterface::STATUS_*`, schemeCode, unitCode nullable,
   candidateCodes, isResolved), `ShippingAddressResolutionContextInterface` (scalar-only:
   countryId, source scheme/unit identity, targetScheme, streetText, candidateCodes),
   `ExternalAddressResolverInterface` (isAvailable + resolve(context): ?string — chỉ trả canonical
   target unit_code), `ShippingAddressResolutionManagerInterface` (1 method — contract stabilizer
   cho E-B, KHÔNG implementation).
3. `Model\Address\ResolvedShippingAddress` VO enforce invariant: EXACT/MAPPED ⇒ unitCode non-empty +
   candidates rỗng; AMBIGUOUS ⇒ unitCode null + candidates đầy đủ giữ thứ tự; UNMAPPED ⇒ unitCode
   null + candidates rỗng; status lạ/scheme rỗng ⇒ LogicException.
4. `Model\Address\ExternalAddressResolverPool` — DI array argument rỗng mặc định; zero provider
   hợp lệ; provider optional đăng ký qua DI; ShippingCore không biết provider concrete nào.

### Constraints / Rules

- Status semantics REUSE từ `Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface` — không
  định nghĩa hằng status song song; AMBIGUOUS không bao giờ auto-pick (DEC-004 D9).
- KHÔNG regionCode trong result DTO (derive được qua `VnAddressUnitProviderInterface::getUnit()`
  → `getRegionCode()` — verified); KHÔNG district field (derive qua parent_code); KHÔNG tên địa
  danh; KHÔNG provider ID; KHÔNG capability matrix (D10).
- KHÔNG: orchestration logic, resolver call, config/credentials (OD-3), persistence (OD-2), origin
  migration (OD-1), cache, DB schema, carrier code (prompt §12), i18n mới.
- Namespace `Api\Address` + `Model\Address` — mirror precedent `Api\Tracking`; PHP 8.2+
  strict_types; DI constructor; không ObjectManager.
- Architecture decisions mới: KHÔNG (mọi quyết định đã ratified trong DEC-FEATYA2C0W-004 +
  approved Phase E-A directive; `getName()` trên external resolver DEFER đến khi E-B có selection
  config — report explicitly).

### Out of Scope

E-B orchestration · local resolver calls · context cache · external resolver calls · provider
selection config · VietMap/Google · GHN/GHTK/Ahamove migration · origin migration · quote/order
persistence · geocoding · name-based fallback · first-candidate fallback · textual fallback
execution · DB tables. GHN hardcoded fallback (1456/21511/'Phường 17' + is_develop_mode default 1)
chỉ REPORT + khuyến nghị BUG task riêng — vẫn live, đã re-verify 2026-09-08.

### Acceptance Criteria

AC-1..AC-7 của SPEC-TASK-AQT7V3 (nguyên văn — tóm tắt): dependency đúng chiều · contracts đúng
shape (≤4 members) · status reuse không song song · VO invariant (AMBIGUOUS không lộ unitCode,
UNMAPPED không unitCode) · pool rỗng hợp lệ + không dính provider/carrier concrete · unit tests
pass (4 status, candidates preserved, isResolved matrix, empty pool) · validator + di compile +
phpunit Secomm pass · README/CHANGELOG + working memory sync.

## Plan

`../plans/TASK-AQT7V3-implementation-plan.md`
