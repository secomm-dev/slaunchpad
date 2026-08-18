# SL-002 — Launchpad OSC Address Integration (Mageplaza OSC)

**Type:** Task (project-specific integration) — Feature
**Priority:** High
**Estimate:** ~16–32h (sau research OSC seam — SA/TL confirm)
**Mode:** A
**Spec:** [launchpad-osc-address.md](../specs/SPEC-SL-002-launchpad-osc-address.md) (backfilled 2026-08-18 — draft; giữ TBD research points)
**Decisions:** [DEC-7](../project-context/memory/DECISIONS.md) (Strategy B) · [DEC-8](../project-context/memory/DECISIONS.md) (module/Launchpad boundary)
**Depends on:** [SL-001](SL-001-apply-hyva-theme-addressdropdown.md) (module phải expose Hyvä address component/dữ liệu)
**Approval:** ✅ Approved 2026-07-16 (user as SA/TL) — AC testable; **caveat**: plan contingent on OSC seam research (state 3)
**Author:** AI draft · **Date:** 2026-07-16 · **Risk tier:** Tier 2 (§12 — Mageplaza OSC checkout)

## Description

Tích hợp hierarchical VN address dropdown (Hyvä-native, từ `Secomm_AddressDropdown` module) vào **Mageplaza One Step Checkout** (shipping + billing address) trong **package Launchpad** (project layer: `app/design/frontend/Secomm/launchpad/` + code project-owned). Vì OSC là third-party + choice theo project, integration này **không nằm trong module tái dụng** (DEC-8) — chỉ Launchpad được coupling OSC.

## Acceptance Criteria

- [ ] **AC-002** (OSC — shipping address): customer ở OSC checkout → VN address dropdown render **Hyvä-native** trong OSC shipping form, cascade hoạt động, "set shipping information" thành công → shipping methods + Mageplaza TableRate load đúng. (BR-002, BR-004)
- [ ] **AC-003** (OSC — billing address): tương tự cho billing; billing address persist đúng khi place order. (BR-004)
- [ ] **AC-004** (OSC — no regression): ExtraFee (BR-006) + DeliveryTime (BR-006) + Mollie payment vẫn render + hoạt động — không regression do integration. (BR-003/BR-006)
- [ ] **AC-O1** (Boundary): KHÔNG sửa/thêm code coupling OSC vào module `Secomm_AddressDropdown` — toàn bộ nằm trong Launchpad (theme override `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...` hoặc project integration module). (DEC-8)
- [ ] **AC-O2** (No in-place edit Mageplaza): extend Mageplaza OSC qua **theme override/plugin/preference** — KHÔNG edit `app/code/Mageplaza/*` in-place. (04 §Areas to Avoid)
- [ ] **AC-O3** (i18n — BR-001): chuỗi mới có entry `vi_VN.csv` + `en_US.csv`.

## Technical Notes

- OSC hiện là **Knockout-based** (35 template `.html` + `requirejs-config.js`), chạy qua compat `hyva-themes/magento2-luma-checkout` 1.1.7; **dispatch JS events riêng** (không trùng Hyva/Luma).
- **Research cần làm (state 3):**
  - **OSC seam**: OSC render shipping/billing address form ở component/layout nào (`onestepcheckout_index_index.xml` + `Osc/view/frontend`) → chốt điểm cắm Hyvä/Magewire component.
  - **OSC submit mechanism**: cơ chế set shipping/billing information của OSC → Magewire sync address field mà không break totals/payment.
- Strategy B (DEC-7): address UI Hyvä-native (Alpine/Magewire/.phtml) từ SL-001; Launchpad wire vào OSC address region.
- KHÔNG edit Mageplaza in-place — theme override trong `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...`.

## Files/Areas Affected

- `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...` — theme override address templates/layout.
- Có thể: project integration code (theme-level Magewire/.phtml) wire module's address component vào OSC.
- i18n `vi_VN.csv` + `en_US.csv`.
- **KHÔNG affect**: `app/code/Secomm/AddressDropdown` (module giữ reusable, DEC-8), `app/code/Mageplaza/*` (in-place edit forbidden).

## Risks

- **Tier 2 (§12)**: Mageplaza OSC checkout flow → escalate SA/TL; **QC end-to-end checkout + payment bắt buộc** (BR-004).
- OSC seam khó/không sạch (Knockout qua compat) → fallback theme override.
- OSC JS events lệch → submit/totals/payment regression (AC-004).

## Open Questions (Level 2 — cần research state 3)

- **OSC seam**: address form render ở đâu → điểm cắm Hyvä component?
- **OSC submit mechanism**: Magewire sync address với set shipping/billing info thế nào?
- Module (SL-001) expose gì cho Launchpad wire (Magewire component public API / data) — interface contract giữa 2 ticket.

## Dependencies

- **SL-001** (module Hyvä address component — phải hoàn thành/tr expose interface trước).
- Mageplaza OSC (`Osc/OscPro/OscUltimate`), `hyva-themes/magento2-luma-checkout` 1.1.7 (compat).
- Magewire 1.13, core Magento GraphQL (Country/Region) + module GraphQL (City/SubCity).

## Definition of Done

- [ ] Code complete + matches approved plan
- [ ] AI pre-review pass
- [ ] **TL review approved** (Tier 2)
- [ ] Tests pass + Hyvä build OK
- [ ] **QC end-to-end checkout**: OSC VN address dropdown (shipping+billing) + place order Mollie + ExtraFee/DeliveryTime không regression (Mode A)
- [ ] Evidence `.ai/evidence/SL-002/`
- [ ] project-context updated
