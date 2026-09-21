---
id: SPIKE-WHHEZV
type: spike
title: 'Mageplaza_TableRateShipping làm fallback engine theo service level cho ShippingCore — audit module + đề xuất bridge Launchpad_MageplazaTableRate (pre-implementation)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: VALID            # user-directed architecture task 2026-09-08 (scope + 23 mục directive + DoD trong request); TL/SA review report pending
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-08
updated: 2026-09-08
decisions: [DEC-FEATYA2C0W-004]
decision_assessment: none-material   # audit source Mageplaza thật; service-level contracts + bridge boundary là ĐỀ XUẤT chờ TL/SA — không DEC mới
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Mageplaza/TableRateShipping/
  - app/code/Secomm/ShippingCore/
changes_project_state: true
changes_architecture: false   # analysis/design only — 0 production code
changes_integration: false
changes_known_limitations: true
last_verified: 2026-09-08
supersedes: []
---

# [SLP][FEAT-YA2C0W][SPIKE-WHHEZV] Mageplaza_TableRateShipping làm fallback engine theo service level cho ShippingCore — audit module + đề xuất bridge Launchpad_MageplazaTableRate (pre-implementation)

## Mini Spec

### Goal

Audit Mageplaza_TableRateShipping v4.0.8 (source thật, không docs) để xác định kiến trúc nhỏ nhất
cho service-level fallback (EXPRESS/SAME_DAY/STANDARD): service level → fallback request →
Mageplaza calculator → fallback price, KHÔNG expose methods Mageplaza trực tiếp ở checkout khi
FALLBACK_ONLY, giữ STANDALONE hoạt động native — analysis/design only, 0 production code.

### Expected Behavior

1. Report 15 mục: kiến trúc module, exposure mechanism, Case A/B/C (file:line), integration
   option A/B/C, suppression, service-level model, mapping, provider contract, failure matrix,
   no-match, recollection, dependency graph, phases, TL/SA decisions.
2. Kết luận chính (verified): Case A/B/C đều YES — `carriers/mptablerate/active` chỉ gate
   `collectRates()` (TableRate.php:181-183) trong khi calculation pipeline public compose được
   (Method::isActive + Rate\Collection::filterByRequest + getCartData/validateShippingGroup/
   calculatePrice); no-match = không rate (không 0-fee/default/first-match).
3. Kiến nghị: **Option A internal calculator**; suppression = config `active=0` (chính) + guard
   plugin (phụ); identity mapping = `method_id` (int PK — chính là method code Mageplaza dùng);
   service-level identities owned ShippingCore; carrier khai báo level qua interface mới
   `CarrierServiceLevelInterface`; bridge mới `Launchpad_MageplazaTableRate` implement
   `FallbackRateProviderInterface` (optional single provider, D7 pool pattern); eligibility do
   ShippingCore quyết — Mageplaza match chỉ = "có giá fallback".
4. Addendum cập nhật forward architecture SPIKE-YH439T (per-service-level fallback superseded
   single emergency table rate) — không rewrite lịch sử.

### Constraints / Rules

- KHÔNG production code: không bridge module thật, không contracts mới implement, không carrier
  change, không external resolver/VietMap, không aggregation engine, không OrderOperations.
- KHÔNG sửa Mageplaza vendor/module source; KHÔNG rewrite SPIKE-YH439T.
- Dependency direction bắt buộc: ShippingCore ↛ Mageplaza ↛ Launchpad; carrier → ShippingCore only.
- Reference: SPIKE-YH439T + TASK-5XDG1P + TASK-AQT7V3 + DEC-FEATYA2C0W-004.

### Out of Scope

Implement Launchpad_MageplazaTableRate · FallbackRateProvider thật · service-level model thật ·
GHN/GHTK/Ahamove changes · external address resolver · rate aggregation engine · carrier
selection/optimization · OrderOperations integration · DB/config thật.

### Acceptance Criteria

AC-1 source Mageplaza audited (file:line) · AC-2 internal calculation path identified (Case B
pipeline public) · AC-3 exposure mechanism hiểu đúng (active config × method status) · AC-4
FALLBACK_ONLY proven feasible (Case A + config-first suppression) · AC-5 STANDALONE defined ·
AC-6 service-level fallback architecture + matrix · AC-7 carrier→Mageplaza dependency bị loại ·
AC-8 bridge ownership + mapping strategy (method_id) · AC-9 phases nhỏ · AC-10 0 production code.

## Plan

Embedded Mini-Spec (analysis-only; report là deliverable):
`../../research/SPIKE-WHHEZV-mptablerate-service-level-fallback.md`
