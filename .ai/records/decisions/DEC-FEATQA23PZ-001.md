---
id: DEC-FEATQA23PZ-001
title: 'ShippingCore Canonical Zone Admin — schema secomm_shipping_zone + persisted-wins registry precedence + DESTINATION_NOT_IN_SCOPE shared reason + coordinator zone-miss guard + Secomm menu placement'
status: accepted             # user acting as SA/TL — approval 2026-09-21 ("approve" trên 5 điểm); Tier-2 review trước merge
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-21
created: 2026-09-21
last_verified: 2026-09-21
verified_against_commit:
supersedes: []
superseded_by:
work_items: [FEAT-QA23PZ, TASK-1EK2MW, TASK-ZA10BT, TASK-BYT2WK]
---

# Decision Record: ShippingCore Canonical Zone Admin & Carrier Assignment

## Status

Accepted (2026-09-21 — user approval trên 5 điểm sau audit FEAT-QA23PZ; user acting as SA/TL).
Tier-2 (CTO/SA) review toàn bộ change set trước merge. Spec:
`SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md`.

## Decision Type

Architecture (operational completion của v10 §35 — KHÔNG tạo v11)

## Decisions

1. **DB schema** — table mới `secomm_shipping_zone` trong `Secomm_ShippingCore` (declarative +
   whitelist): zone_id PK, code varchar(64) UNIQUE, label, enabled, 3 cột JSON code lists
   (include_province/include_ward/exclude_ward — canonical `VN-XX`/`VNA25-*`), created_at/
   updated_at. JSON column đủ cho P1; KHÔNG relational geography tables.

2. **Registry source precedence** — `PersistentCanonicalZoneRegistry` (DI preference mới cho
   `CanonicalZoneRegistryInterface`): persisted zone **authoritative** cho mọi code tồn tại
   trong DB; DI/static zone chỉ fallback khi không có persisted zone cùng code. Deterministic,
   không throw cross-source. Duplicate nội bộ từng nguồn vẫn fail-fast. Lazy-load per request +
   cache type `secomm_shippingcore_zones`, invalidate qua repository mutations.

3. **Shared contract constant** — `ShippingFailureReason::DESTINATION_NOT_IN_SCOPE`
   (additive, owner duy nhất, string-aligned `CarrierEligibilityResultInterface::REASON_…`).
   KHÔNG thêm vào `SafeDegradationEligibilityPolicy` defaults (fail-closed giữ nguyên).

4. **FallbackCoordinator zone-miss guard** — `Launchpad_MageplazaTableRate`
   `FallbackCoordinator::isMemberEligible()` thêm guard
   `failureReason === DESTINATION_NOT_IN_SCOPE → false` TRƯỚC mode branches. Bắt buộc: nhánh
   FALLBACK_ONLY hiện trả true vô điều kiện — không guard thì §20 (fallback không được bypass
   service-area) bị vi phạm cho member FALLBACK_ONLY.

5. **Menu placement + calculate() boundary** — Admin Shipping Zones gắn menu **Secomm**
   (`MenuSecomm_Base::menu`) theo convention hiện hữu (mọi grid Secomm), deviate khỏi gợi ý
   "Stores/Sales/Shipping" của directive. `GhnRateCalculator::calculate()/resolveAndQuote()`
   thành standalone-only (giữ code + test, docblock note, không xoá).

## Consequences

- Production GHN RATE path đi qua `CarrierRateExecutionService` (audit 2026-09-21: TEST_ONLY →
  RUNTIME_WIRED); scope ALL giữ behavior hiện tại (Magento default `shipping/origin/country_id=US`
  ⇒ origin-readiness gate pass).
- Zone stale khi scheme swap = no-match + reader warning (documented P1 boundary, không
  auto-migrate).
- GHTK regression-only trong FEAT này; generic pattern sẵn sàng cho GHTK adoption sau.
