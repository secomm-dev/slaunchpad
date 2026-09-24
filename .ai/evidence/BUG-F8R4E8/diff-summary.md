# BUG-F8R4E8 — Diff Summary

## Files changed

1. `app/code/Launchpad/Osc/view/frontend/requirejs-config.js` (new)
   - Registers requirejs mixin: `Mageplaza_Osc/js/model/shipping-rate-service` → `Launchpad_Osc/js/model/shipping-rate-service-mixin`.

2. `app/code/Launchpad/Osc/view/frontend/web/js/model/shipping-rate-service-mixin.js` (new)
   - Module-level `inFlight` map keyed by `quote.shippingAddress().getCacheKey()`.
   - Wraps `estimateShippingMethod`: same-key-in-flight → return (skip duplicate); else set key, call original, delete key on synchronous throw.
   - Releases keys via `shippingService.isLoading.subscribe(false)` — processor clears the flag in the request's `.always()`, after `rateRegistry` is populated (so later same-key calls hit the cache path anyway). Best-effort: shared flag may release early if a different-address request completes first → at most one extra request, never a missed estimation.
   - Null-safe: no `getCacheKey` → guard bypassed, call passes through.

3. `app/code/Launchpad/Osc/CHANGELOG.md`
   - `[Unreleased]` → new `### Fixed` entry (BUG-F8R4E8 / SLP-214).

## Why

`rateRegistry` only caches after the first response lands, so the two init-time OSC triggers (afterResolveDocument + validator 200 ms timer) both POSTed the same payload. Guard collapses concurrent duplicates; key change (real address edit) still refetches.

## Venue note

Interim attempt in `Secomm_Ahamove`'s global map override (`new-address-mixins.js`) + Ahamove CHANGELOG 1.0.4 was **reverted to HEAD** (wrong module ownership for an OSC fix; user decision 2026-09-22). Those files carry no trace of this bug now.

## Pending verification

AC-001 (2→1 request on hard reload) and AC-002 (fresh request on ward/region change) need a logged-in session with a cart — DevTools Network check, see record.
