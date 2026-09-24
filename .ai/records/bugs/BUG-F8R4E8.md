---
id: BUG-F8R4E8
type: bug
title: Duplicate estimate-shipping-methods Request On OSC Checkout First Load
project_code: SLP
parent:
external_refs:
  tickets: SLP-214
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-22
updated: 2026-09-22
affects_version: Magento 2.4.8-p5 + Mageplaza OSC + Launchpad_Osc
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Launchpad/Osc/view/frontend/requirejs-config.js
  - app/code/Launchpad/Osc/view/frontend/web/js/model/shipping-rate-service-mixin.js
source_areas:
  - checkout
  - shipping-carrier
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-22
supersedes: []
---

# [SLP][BUG-F8R4E8] Duplicate estimate-shipping-methods Request On OSC Checkout First Load

<!-- External ticket: SLP-214 [Checkout page] Trang Checkout (OSC) bị nhấp nháy loading liên tục /tải lại toàn trang khi nhập dữ liệu hoặc chọn Shipping/Payment. -->
<!-- Scope of this record: the duplicate `POST /V1/carts/mine/estimate-shipping-methods` on checkout init (flicker contributor). Full-page-reload symptom tracked separately (see Out of Scope). -->

## Summary

On `/onestepcheckout`, the first page load fires `POST /V1/carts/mine/estimate-shipping-methods` **twice** with an identical payload. Each call toggles `shippingService.isLoading(true)` and shows the OSC spinner — a visible flicker, one of the loading-flicker symptoms reported in SLP-214.

## Mini Spec

### Goal

- Collapse duplicate same-address rate estimations into one network request.
- Do not suppress legitimate re-estimation after a real address change.

### Expected Behavior

- One `estimate-shipping-methods` request per distinct address per interaction burst.
- Address change (ward/province/country) still triggers a fresh request.

### Constraints / Rules

- Fix at the shared choke point; no per-trigger patches (Mageplaza vendor files stay untouched).
- Rate-processor contract unchanged (`rateRegistry` still the post-response cache; cart `/cart/` bypass kept).

### Out of Scope

- **Full-page reload on shipping/payment selection** (ticket symptom 2): driven by Mageplaza OSC Loading Speed admin config (`loadingSpeedConfig.refresh_page` / refresh options), not by duplicate calls. Config-side, separate follow-up.
- `estimate-shipping-methods-by-address-id` (`customer-address` processor): not reported; same class of race possible, revisit if reported.
- Mageplaza OSC trigger sources themselves (vendor + `Launchpad_Osc` cascades) — dedupe makes the double trigger harmless.

### Acceptance Criteria

- **AC-001**: Logged-in customer with a saved address, hard reload `/onestepcheckout` → exactly **1** `estimate-shipping-methods` request in DevTools Network (before: 2, identical payloads).
- **AC-002**: Changing ward/region/postcode after load → fresh estimation request fires (different `getCacheKey()`).
- **AC-003**: Request failure does not wedge the guard: `inFlight` key cleared in `.always()`, a retry re-POSTs.
- **AC-004**: Cart page estimation behavior unchanged (cache `/cart/` bypass preserved; dedupe only collapses concurrent duplicates there too).

## Steps to Reproduce

1. Log in as a customer with a saved (VN) shipping address, add a product to cart.
2. Open DevTools → Network, hard-reload `/onestepcheckout`.
3. Observe `estimate-shipping-methods` firing twice back-to-back with the same payload.

## Expected Behavior

One request.

## Actual Behavior (Before Fix)

Two identical requests; second one wastefully re-quotes and re-triggers the loading spinner.

## Root Cause Analysis

`Magento_Checkout/js/model/shipping-rate-processor/new-address` (globally map-overridden by `Secomm_Ahamove/.../new-address-mixins.js`) caches into `rateRegistry` only in the response handler. Mageplaza OSC fires two init-time estimations inside the first response's latency window:

1. `Mageplaza_Osc/js/view/shipping.js` → `afterResolveDocument()` — immediate, unconditional when the quote has a shipping address with `countryId`.
2. `Mageplaza_Osc/js/model/shipping-rates-validator.js` → `oscBindHandler` field `value` events (form prefilled from localStorage / `checkoutConfig.shippingAddressFromData`) → `selectShippingAddress` + 200 ms `setTimeout` → `estimateShippingMethod()`.

Contributing trigger (VN flow): `Launchpad_Osc/js/action/shipping-address-dropdown.js` ward restore sets `cityInputViewModel.value(...)` during `loadCities` callback, feeding trigger 2 again.

Second call sees an empty `rateRegistry` → re-POSTs the same payload.

## Affected Files

- `app/code/Launchpad/Osc/view/frontend/requirejs-config.js` — new; registers the mixin
- `app/code/Launchpad/Osc/view/frontend/web/js/model/shipping-rate-service-mixin.js` — new; in-flight guard keyed by `getCacheKey()`
- `app/code/Launchpad/Osc/CHANGELOG.md` — Unreleased Fixed entry

## Callers (blast radius)

Mixin targets `Mageplaza_Osc/js/model/shipping-rate-service` — the choke point every OSC checkout estimation trigger routes through (`shipping.js afterResolveDocument`, `shipping-rates-validator`, `address-renderer`, `payment/discount`, `Launchpad_Osc` dropdown cascades). Guard is transparent: same-key-in-flight → skip; any other key (real address change) → unchanged. Cart-page estimation goes through `Magento_Checkout/js/model/cart/estimate-service` directly and is untouched (not part of the bug).

## Approach (delivered)

1. New requirejs mixin on `Mageplaza_Osc/js/model/shipping-rate-service` (registered in Launchpad_Osc's requirejs-config).
2. Wrap `estimateShippingMethod`: skip when an identical `cacheKey` request is in flight; delete key on synchronous throw.
3. Release via `shippingService.isLoading.subscribe(false)` — the processor sets it false in the request's `.always()`, after `rateRegistry` is populated, so later same-key calls take the cache-hit path anyway. Best-effort by design (shared flag; early release costs at most one extra request, never a missed estimation).

## Verification & Test Results

See `.ai/evidence/BUG-F8R4E8/`. Lint + cache flush done; **AC-001/AC-002 browser verification pending** (needs a customer session with cart — QC/Dev to run the DevTools check).

## Notes for TL Review

> **TL: APPROVED in chat (2026-09-22)** — in-flight dedupe at the shared choke point.
> **Venue (final): `Launchpad_Osc` mixin on `Mageplaza_Osc/js/model/shipping-rate-service`** — the TL-approved venue, confirmed by the user after an interim attempt landed in `Secomm_Ahamove`'s global map override of `new-address` processor (rejected: wrong module ownership for an OSC fix; reverted before commit, never shipped).
