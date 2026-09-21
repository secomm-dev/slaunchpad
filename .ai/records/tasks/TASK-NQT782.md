---
id: TASK-NQT782
type: task
title: 'LT-BRIDGE-1 — Launchpad_MageplazaTableRate: ShippingCore fallback provider bridge (FALLBACK_ONLY/STANDALONE)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-NQT782 — bridge shape theo approved LT-BRIDGE-1 directive (SPIKE-WHHEZV basis); TL review chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-NQT782-launchpad-mptablerate-fallback-bridge.md
risk: medium                  # module mới; consumer thật đầu tiên của fallback provider contract; chưa wire checkout thật; vendor 0 edit
status: in_progress
priority: high
decision_assessment: none-material   # thực thi SPIKE-WHHEZV (đã approved); mapping-via-DI + §R1 glue là kết quả audit — REPORT TL (§R1/R2/R3), không DEC mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Launchpad/MageplazaTableRate/
  - app/code/Mageplaza/TableRateShipping/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [TASK-M3ME32, TASK-XXBN5X, SPIKE-WHHEZV]
---

# [SLP][FEAT-YA2C0W][TASK-NQT782] LT-BRIDGE-1 — Launchpad_MageplazaTableRate: ShippingCore fallback provider bridge (FALLBACK_ONLY/STANDALONE)

## Embedded Mini-Spec

*(đầy đủ tại specs/SPEC-TASK-NQT782-launchpad-mptablerate-fallback-bridge.md, FULL)*

### Goal

Module `Launchpad_MageplazaTableRate` (khớp convention `Launchpad_MageplazaDeliveryTime`…)
implement `FallbackRateProviderInterface`: map dynamic service-level code → Mageplaza method_id
(DI) → pipeline nội bộ Mageplaza (KHÔNG collectRates) → `FallbackRateInterface`. 2 mode
FALLBACK_ONLY (Mageplaza ẩn checkout: active=0 + guard plugin) / STANDALONE (native). Bridge
KHÔNG quyết eligibility.

### Expected Behavior

1. `Model\Config`: mode ScopeConfig (default FALLBACK_ONLY) + mapping/labels DI (khuyến nghị §R3
   — không admin dynamic rows; label fallback = service-level code).
2. Provider: mode ≠ FALLBACK_ONLY → null; no mapping → null (§5); method missing/inactive →
   `FallbackConfigurationException` (§6/§30 fail loud — documented bundle semantic); pipeline:
   RateRequest bridge-only (dest fields + all_items=[]) + cartData từ request scalars →
   `Rate\Collection::filterByRequest` → `Rate::calculatePrice` per rate → combine theo
   `CalculateRule` (SUM/MIN/MAX — glue §R1) → `FallbackRate(amount kể cả 0đ, label bridge,
   estimate null)`; no-match → null.
3. `Plugin\Carrier\TableRate`: FALLBACK_ONLY + active=1 → warning + false; STANDALONE → proceed
   (chính vẫn là active=0 — plugin chỉ defense-in-depth §11).
4. DI: provider vào `FallbackRateProviderPool` (sole provider Launchpad); module sequence
   Mageplaza_TableRateShipping + Secomm_ShippingCore; `module:enable`.

### Constraints / Rules

- Dependency: bridge → Secomm_ShippingCore + Mageplaza_TableRateShipping; KHÔNG chiều ngược;
  KHÔNG đụng carrier modules; KHÔNG vendor edit (§34); KHÔNG Address/VietNamAddress dependency (§27).
- Reason strings không đọc; fallback = price source, KHÔNG eligibility engine (§25).
- ShippingCore FROZEN — gap phát hiện thì REPORT (§R1/R2/R3), không tự mở rộng core.
- KHÔNG: eligibility, trigger, selection, canonicalization, OMS, OrderOperations, DB CRUD.

### Out of Scope

Carrier adoption (GHN/GHTK/Ahamove) · fallback decision (E-SL2 đã có) · checkout method
rendering · admin mapping dynamic rows (§R3 defer) · ward/district granularity.

### Acceptance Criteria

AC-1..AC-6 của SPEC-TASK-NQT782 (tóm tắt): no-mapping/no-match → null · match → FallbackRate
(kể cả 0đ, label bridge) · method missing/inactive → exception · STANDALONE → null · request
translation đúng + 0 Mageplaza object leak · plugin 3 nhánh · module enable + compile +
phpunit + validator 0 new finding · README/CHANGELOG module + CURRENT_STATE · 0 carrier/vendor/
ShippingCore code đổi.

## Plan

`../plans/TASK-NQT782-implementation-plan.md`
