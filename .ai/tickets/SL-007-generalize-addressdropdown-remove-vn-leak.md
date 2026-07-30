# SL-007 — Generalize Secomm_AddressDropdown: remove VN leak (move VN labels/behavior to Secomm_VietNamAddress)

**Type:** Refactor / Tech Debt (architecture-compliance cleanup)
**Priority:** Medium
**Estimate:** ~8–16h
**Mode:** A (chạm checkout JS — Tier-2; + generic module)
**Feature:** compliance với [DEC-019](../records/decisions/DEC-019.md) (related: [FEAT-001](../records/features/FEAT-001.md), [FEAT-005](../records/features/FEAT-005.md))
**Spec ref:** Phase-1 audit U5 (FEAT-005)
**Risk tier:** Tier 2 (checkout)
**Author:** AI draft · **Date:** 2026-07-29 · **Status:** Proposed

## Description

`Secomm_AddressDropdown` phải là **generic, country-agnostic** (DEC-8/DEC-019): chỉ dùng label **Magento-default** (Country / State/Province / City / Sub-City…) và KHÔNG chứa logic/label country-specific. Audit Phase-1 (FEAT-005) phát hiện module đang **leak VN** ở checkout + customer-form + ACL — vi phạm DEC-019. Ticket này dọn leak: generalize lại theo Magento-default, **move behavior/label VN sang `Secomm_VietNamAddress`**.

Tách riêng khỏi task cart (SL-003) theo nguyên tắc "no broad refactor outside this requirement".

## Evidence — VN leak trong `Secomm_AddressDropdown` (file:line)

| File:line | Vi phạm |
|---|---|
| `view/frontend/web/js/action/shipping-address-dropdown.js:43-45,47-59,105,113,160-170,182,212,219,252,275,283,312,334,366` | `isVietnamCountry()` (`=== 'VN'`), `applyNonVietnamUiState()`, nhánh `'VN'`, label hard-code `'Ward/Commune'` (`:164`,`:182`) |
| `view/frontend/web/js/action/billing-address-dropdown.js:42-43,46,117,125,172,176,224,231,259,283,291,323,352,385` | cùng pattern `isVietnamCountry`/`'VN'`/`'Ward/Commune'` (`:176`) |
| `view/frontend/templates/hyva/address/edit.phtml:8,238,261,353,367,413,442,456` | comment + logic "VN cascade", "province → city", "sub-city / ward" (customer address form Hyva) |
| `etc/acl.xml:13` | ACL title `"Manage VN Address"` |
| `CHANGELOG.md:8,10` | claim "VN labels via Secomm_VietNamAddress" + mapping "VN 3-tier: city = phường/xã" — documentation drift |

> Luma **cart** estimator (`cart/shipping-estimation-mixin.js` + `Plugin/Cart/LayoutProcessorPlugin.php`) **đã generic** (không leak VN) — không động.

## Target (DEC-019)

- Generic module: label → Magento default (Country / State/Province / City / Sub-City); bỏ nhánh `isVietnamCountry()`/`=== 'VN'` → **data-driven** (level config, không hard-code country).
- VN labels (Tỉnh/Thành phố, Phường/Xã) + VN-specific behavior (postcode-required toggle, ward mapping) → `Secomm_VietNamAddress` (i18n + override/adapter).
- Customer-form Hyva cascade (`hyva/address/edit.phtml`): tách phần VN ra VietNamAddress (hoặc generalize + VN override) — giữ generic fallback cho country khác.

## Acceptance Criteria

- [ ] AC-1: `Secomm_AddressDropdown` không còn string `'VN'`, `isVietnamCountry`, `'Ward/Commune'`, `'Manage VN Address'`, hay comment VN (grep sạch).
- [ ] AC-2: label generic = Magento default; behavior country-specific do country module (VietNamAddress) override, KHÔNG hard-code trong generic.
- [ ] AC-3: checkout VN (OSC) vẫn render đúng Tỉnh/Thành phố + Phường/Xã sau khi move logic sang VietNamAddress.
- [ ] AC-4: customer address form (Luma + Hyva) vẫn cascade đúng cho VN; non-VN giữ Magento default.
- [ ] AC-5: regression — checkout end-to-end (OSC), customer address book, admin (ACL title hợp lý), quote/order address persistence (`sub_city`) nguyên vẹn.
- [ ] AC-6: DEC-019 compliance pass (generic country-agnostic).

## Files/Areas Affected

- `Secomm_AddressDropdown/view/frontend/web/js/action/shipping-address-dropdown.js`
- `Secomm_AddressDropdown/view/frontend/web/js/action/billing-address-dropdown.js`
- `Secomm_AddressDropdown/view/frontend/templates/hyva/address/edit.phtml`
- `Secomm_AddressDropdown/etc/acl.xml`
- `Secomm_VietNamAddress/` (mở rộng: nhận VN labels + behavior move sang — i18n + adapter/override)
- **KHÔNG affect**: Luma cart estimator (đã generic), backend `Api/`/`Model`/`Setup`, schema, GraphQL.

## Risks

- Tier-2 (checkout — §12): `shipping/billing-address-dropdown.js` là OSC flow → regress checkout/payment. Cần end-to-end checkout QC + payment test.
- Di chuyển logic VN sang VietNamAddress phải preserve behavior chính xác (postcode-required, ward mapping) — rủi ro subtle bug.
- Customer-form Hyva phtml tách VN có thể phình scope → giới hạn trong "move + relabel", không rewrite.
- Phụ thuộc SL-003 (cart) nếu chia sẻ pattern — nên làm SAU hoặc song song có coordination.

## Open Questions

- Q1: `isVietnamCountry()` generalize thành gì? (data-driven country config trong AddressDropdown, hay VietNamAddress override entirely?) — SA.
- Q2: customer-form Hyva cascade — move hẳn sang VietNamAddress hay generalize + VN override? (SA — boundary精细)

## Definition of Done

- [ ] Grep sạch VN leak trong generic (AC-1)
- [ ] AI pre-review pass
- [ ] TL review approved (Tier 2)
- [ ] QC: checkout OSC + customer form (Luma/Hyva) vi/en + admin + persistence
- [ ] DEC-019 compliance
