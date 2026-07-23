# SL-001 — Apply Hyvä theme cho Secomm_AddressDropdown (MODULE layer)

> ⚠️ **LEGACY (Phase 1a) — superseded by [`records/features/FEAT-001.md`](../records/features/FEAT-001.md).** Read-only; excluded from default context loading; pending equivalence validation. Material knowledge consolidated into the canonical feature record. Do not edit — update FEAT-001 instead.

**Type:** Task (Hyvä frontend migration) — Refactor
**Priority:** High
**Estimate:** ~16–24h (module-only, sau research — SA/TL confirm)
**Mode:** A
**Spec:** [.ai/specs/addressdropdown-hyva.md](../specs/addressdropdown-hyva.md)
**Decisions:** [DEC-7](../project-context/memory/DECISIONS.md) (Strategy B, refined by DEC-9) · [DEC-8](../project-context/memory/DECISIONS.md) (module/Launchpad boundary) · [DEC-9](../project-context/memory/DECISIONS.md) (dual-theme Luma + Hyva)
**Approval:** ✅ Approved 2026-07-16 (user as SA/TL) — spec approved, AC testable, dependencies identified → **Definition of Ready met**
**Related:** SL-002 (Launchpad OSC integration — phụ thuộc SL-001)
**Author:** AI draft · **Date:** 2026-07-16 · **Risk tier:** Tier 2 (§12 — `Secomm_AddressDropdown` GraphQL surface)

## SCOPE BOUNDARY (DEC-8)

SL-001 = **MODULE layer only** (reusable `Secomm_AddressDropdown`):
- **IN**: customer address form + cart shipping estimation → Hyvä-native. Default/Luma checkout giữ nguyên (dormant).
- **OUT → SL-002**: Mageplaza OSC integration. Module **không coupling** OSC/Ahamave.

## Description

Migrate **generic frontend surfaces** của `Secomm_AddressDropdown` (customer address form, cart estimation) từ Luma/Knockout/RequireJS sang **Hyvä-native** (Alpine.js + Magewire 1.13 + `.phtml` + Tailwind v4). Backend (`Api/`, `Model`, `Setup`, import, admin UI) + default/Luma checkout code **giữ nguyên**. Thỏa mãn BR-002 (module portion) trên storefront Hyvä. OSC integration tách sang SL-002 (Launchpad).

## Acceptance Criteria

- [ ] **AC-001** (Customer address form — Hyvä): Country=VN → Province→District→Ward render Alpine/Magewire (.phtml), cascade GraphQL, lưu thành công. (BR-002)
- [ ] **AC-005** (Cart shipping estimation): dropdown Hyvä-native, estimate cập nhật.
- [ ] **AC-006** (No Luma leak): surface đã migrate không load RequireJS/Knockout của module trên storefront.
- [ ] **AC-007** (i18n — BR-001): chuỗi mới có entry `vi_VN.csv` + `en_US.csv`; cả 2 locale render đúng.
- [ ] **AC-008** (Tailwind v4): CSS-first `@theme`/`@source`; không tạo `tailwind.config.js`.
- [ ] **AC-009** (Admin/backend regression): admin CRUD + CSV import/export nguyên vẹn.
- [ ] **AC-010** (Project-leak cleanup): remove dead ref `Secomm_Ahamave/...` trong `requirejs-config.js`.
- [ ] **AC-011** (Performance): không N+1 trên cascade (cache endpoint nơi hợp lý).
- [ ] **AC-012** (Default-checkout dormant): default/Luma checkout code giữ, không leak RequireJS lên storefront.

_(OSC shipping/billing/totals AC → SL-002.)_

## Technical Notes

- Customer form đã là Block + `.phtml` (`Block/Customer/Address/Edit.php` + `templates/address/edit.phtml`) → rewrite Hyvä + Magewire component (gần Hyvä hơn).
- Cart estimation hiện là Knockout mixin (`view/frontend/web/js/view/cart/shipping-estimation-mixin.js`) → replace Hyvä-native.
- **Data tái dùng**: GraphQL `GetListCity`/`GetListSubCity` (đang `@deprecated` + `@cache(false)`) + core Magento GraphQL (Country/Region). Xem Q1.
- Module hỗ trợ Hyvä qua template của chính nó (Hyvä = theme, OK cho module tái dụng); **không** coupling OSC.
- Default/Luma checkout code (`checkout_index_index.xml` + checkout mixins): giữ dormant (Q-DC).

## Files/Areas Affected

- `app/code/Secomm/AddressDropdown/view/frontend/templates/address/edit.phtml` + `layout/customer_address_form.xml`.
- `app/code/Secomm/AddressDropdown/view/frontend/layout/checkout_cart_index.xml` + cart Knockout mixin.
- `app/code/Secomm/AddressDropdown/view/frontend/requirejs-config.js` (remove Ahamove ref + migrated mixins).
- Mới (dự kiến): Magewire component + (Q1) Resolver cache.
- i18n `vi_VN.csv` + `en_US.csv`.
- **KHÔNG affect**: `view/adminhtml/*`, `Api/`/`Model`/`Setup`/import, default-checkout code, OSC.

## Risks

- Tier 2 (§12): `Secomm_AddressDropdown` GraphQL surface → escalate SA/TL.
- N+1 cascade (AC-011); default-checkout dormant leak (AC-012); Tailwind `@source` purge.
- (OSC/checkout-payment risk → SL-002, không thuộc ticket này.)

## Open Questions (Level 2)

- **Q1**: undeprecate + cache resolver `GetListCity`/`GetListSubCity`? (SA — GraphQL Tier 2)
- **Q-DC**: default/Luma checkout code — giữ dormant hay Hyvä-migrate? (SA)
- Multi-store (BR-TBD-001) theme scope.

## Dependencies

- GraphQL resolvers (reuse), core Magento GraphQL, Magewire 1.13, `Secomm_VietNamAddress`.
- **SL-002 phụ thuộc SL-001** (module phải expose Hyvä component/dữ liệu để Launchpad wire vào OSC).

## Definition of Done

- [ ] Code complete + matches approved plan
- [ ] AI pre-review pass
- [ ] **TL review approved** (Tier 2)
- [ ] Tests pass + Hyvä build OK (`npm run build`)
- [ ] QC verified: customer form + cart cascade vi/en (Mode A)
- [ ] Evidence `.ai/evidence/SL-001/`
- [ ] project-context updated (`CURRENT_STATE.md`, `NEXT_TASK.md`, `estimation-tracking.csv`)
