---
id: TASK-T63QVQ
type: task
title: Add configurable logo to VietQR payment method config
project_code: SLP
parent: null
external_refs:
  tickets: SLP-237
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: completed
created: 2026-09-16
updated: 2026-09-16
ticket_ref: SLP-237
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (Secomm_VietQr module)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/VietQr
source_areas:
  - vietqr-admin-config
  - vietqr-checkout-renderer
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-16
supersedes: []
---

# [SLP][TASK-T63QVQ] Add configurable logo to VietQR payment method config

<!-- External ticket: SLP-237. Follows the ZaloPay pattern (SLP-104 era): logo next to the payment title at checkout, backed by a checkoutConfig `logoSrc` entry. -->

## Summary

Add an admin-configurable logo to the VietQR payment method, mirroring how ZaloPay displays `logoSrc` next to the payment title at checkout. The logo is uploaded via a new system config image field (`payment/secomm_vietqr/logo`); when nothing is uploaded, no logo is rendered (optional — revised after review: no bundled fallback, logo comes only from config).

## Mini Spec

### Goal

Let the admin upload a payment-method logo for VietQR and render it on the checkout payment list, like ZaloPay.

### Expected Behavior

- Admin: Stores > Configuration > Sales > Payment Methods > VietQR shows a "Logo" image upload field (with preview + delete), stored under `media/vietqr/` with scope info.
- Checkout: the VietQR payment method title row renders the logo image (40px, `payment-icon` class) before the title, sourced from `window.checkoutConfig.payment.secomm_vietqr.logoSrc`.
- With no upload, no `logoSrc` image is rendered — the title row shows the title only. (AC-003/AC-004 revised 2026-09-16 after review: logo is optional, not forced.)

### Constraints / Rules

- Changes confined to `app/code/Secomm/VietQr`; additive only — no payment flow, IPN, order-state, or existing config keys touched.
- Use the standard Magento image upload backend (`Magento\Config\Model\Config\Backend\Image`), no new dependency.
- Renderer JS/template stay consistent with the existing `Secomm_ZaloPay` checkout pattern (same getter name `getPaymentAcceptanceMarkSrc`).

### Out of Scope

- Logos for other payment methods (Mollie, VNPAY, ZaloPay already has its own static logo).
- Rebranding the VietQR payment page / email templates.
- A shared "logo per payment method" abstraction.

### Acceptance Criteria

- AC-001: System config field `payment/secomm_vietqr/logo` (type `image`) exists with upload preview and delete in admin.
- AC-002: Uploaded file lands in `media/vietqr/<scope>/...` and the stored config value resolves to its public media URL at checkout.
- AC-003: `checkoutConfig.payment.secomm_vietqr.logoSrc` is the uploaded media URL when configured, empty string when not (method available).
- AC-004: Checkout renders the logo next to the VietQR title only when configured (ko-if guard in template); when the method is unavailable no config entry is emitted (existing behavior preserved for `instructions`).

## Approach

Mode C — copy the existing ZaloPay logo pattern, extended with an upload config per ticket:

- `etc/adminhtml/system.xml`: new `logo` field (type `image`, sortOrder 25, after Title) with `backend_model` `Magento\Config\Model\Config\Backend\Image`, `upload_dir` `vietqr` (`config="system/filesystem/media"`, `scope_info="1"`) and matching `base_url` for admin preview.
- `Model/Config.php`: `getLogo()` reads `payment/secomm_vietqr/logo`.
- `Model/InstructionsConfigProvider.php`: emit `payment.secomm_vietqr.logoSrc` alongside the existing `instructions` map — media URL of the upload when set, empty string when not (no bundled fallback).
- `vietqr-method.js`: `getPaymentAcceptanceMarkSrc()` (same name as ZaloPay's renderer getter).
- `vietqr.html`: `<img class="payment-icon" width="40">` in the label (ko-if guarded on non-empty logoSrc), copied from `zalopay.html`.

## Implementation Notes

- Files changed: `etc/adminhtml/system.xml`, `Model/Config.php`, `Model/InstructionsConfigProvider.php`, `view/frontend/web/js/view/payment/method-renderer/vietqr-method.js`, `view/frontend/web/template/payment/vietqr.html`. No bundled logo asset — the logo lives in `media/vietqr/` per environment (uploads via admin; media is not git-deployed, so upload once per environment).
- `InstructionsConfigProvider` constructor grew (Config, StoreManagerInterface — `assetRepository` was added then dropped again in the optional-logo revision); the `instructions` early-return-on-empty behavior was folded into a single config array so the logo is not coupled to instructions being non-empty.

## Verification

- [x] AC-003 verified — `InstructionsConfigProvider::getConfig()` booted in container: `logoSrc` resolves to the default asset URL, `instructions` key still present (no regression).
- [x] AC-002 verified — round-trip with config row `default/test-logo.png` + file in `media/vietqr/default/`: provider resolves the exact media URL, HTTP 200 through the live tunnel; test artifacts cleaned up afterwards.
- [x] AC-001 verified (de facto) — real admin upload 2026-09-16 16:04: `media/vietqr/default/VietQR_Logo.png` + config row `default/VietQR_Logo.png`; media URL serves HTTP 200.
- [x] `php -l` on both PHP files; `setup:di:compile` passes; `cache:flush config full_page` run.
- [ ] AC-004 verified — checkout DOM check pending QA (needs a browser session).

## Related records

- External ticket: SLP-237
- Pattern source: `Secomm_ZaloPay` (`ZaloPayConfigProvider`, `zalopay.html`, `zalopay-wallet.js`)
