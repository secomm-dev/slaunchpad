# Implementation Plan: TASK-NQT782 — LT-BRIDGE-1 Launchpad_MageplazaTableRate fallback bridge

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-NQT782 (parent FEAT-YA2C0W — chưa có FEAT Launchpad-integration riêng; mint riêng khi cụm LT-* lớn lên) |
| Mode | A (checkout/shipping integration — generic risk category) |
| Specification | [specs/SPEC-TASK-NQT782-launchpad-mptablerate-fallback-bridge.md](../specs/SPEC-TASK-NQT782-launchpad-mptablerate-fallback-bridge.md) — FULL, VALID |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md); không DEC mới — Mapping-via-DI + §R1 glue là kết quả audit (REPORT TL §R1/R2/R3) |
| Architecture basis | [SPIKE-WHHEZV](../research/SPIKE-WHHEZV-mptablerate-service-level-fallback.md) §3 Case B pipeline + §5 suppression + §12 identity |
| Contract basis | TASK-XXBN5X (fallback contracts, r2 zero-rate) · TASK-M3ME32 (E-SL2 decision/policy) |
| Risk | Medium — module mới, consumer thật đầu tiên của fallback provider contract; chưa wire vào checkout flow thật; vendor 0 edit |

## Approach

Module `Launchpad_MageplazaTableRate` (namespace khớp convention `Launchpad_MageplazaDeliveryTime`…):
`Model\Config` (mode ScopeConfig + mapping/labels DI) · `Model\FallbackRateProvider` (pipeline
nội bộ Mageplaza: Method load/isActive → RateCollection filterByRequest với RateRequest
bridge-only + cartData từ scalars → Rate::calculatePrice per rate → combine theo CalculateRule
→ FallbackRate; no-match/mode-sai → null; method missing/inactive → `FallbackConfigurationException`)
· `Plugin\Carrier\TableRate` (defense-in-depth FALLBACK_ONLY) · system.xml mode dropdown · DI
provider vào pool ShippingCore. Gaps §R1/R2/R3 REPORT (ShippingCore frozen).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT ticket_ref | spec-first TRƯỚC code |
| 2 | Scaffold | `registration.php`, `etc/{module.xml, di.xml, config.xml, adminhtml/system.xml}` | sequence Mageplaza_TableRateShipping + Secomm_ShippingCore |
| 3 | Config | `Model/Config.php`, `Model/Source/Mode.php` | mode/mapping/labels |
| 4 | Provider | `Model/FallbackRateProvider.php`, `Model/Exception/FallbackConfigurationException.php` | pipeline §3.2 spec |
| 5 | Plugin | `Plugin/Carrier/TableRate.php` | §11 defense-in-depth |
| 6 | Enable | `bin/magento module:enable Launchpad_MageplazaTableRate` | cập nhật config.php đúng cơ chế |
| 7 | Tests | `Test/Unit/Model/{ConfigTest, FallbackRateProviderTest}` + `Test/Unit/Plugin/Carrier/TableRatePluginTest` | §6 spec |
| 8 | Docs | module `README.md` + `CHANGELOG.md` | 2 mode + bridge rule §35 |
| 9 | Validation | phpunit · validator · compile · git status Mageplaza + ShippingCore | AC-4/AC-6 |
| 10 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-NQT782/` | |

## Test plan

Config: mode default FALLBACK_ONLY + scope override · mapping/labels DI round-trip · label
fallback về code. Provider: no-mapping → null · STANDALONE → null · match SUM/MIN/MAX ·
zero-price → FallbackRate(0) · no-match → null · method missing → exception · method inactive →
exception · request translation (dest fields + cartData + all_items=[]) · result thuần
FallbackRate. Plugin: FALLBACK_ONLY+active=1 → warning + false (proceed never) ·
FALLBACK_ONLY+active=0 → false · STANDALONE → proceed result.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Mageplaza internal API đổi version | Pipeline audit + tests mock theo contract thật; version-pin ghi CHANGELOG |
| Session dependency isActive (§R2) | Storefront path consistent; limitation documented; CLI/API test khi có consumer thật |
| Provider registration >1 | E-SL2 đã fail-fast; bridge là sole provider Launchpad |

Rollback: `module:disable` + revert — không DB schema, không vendor, không ShippingCore.
