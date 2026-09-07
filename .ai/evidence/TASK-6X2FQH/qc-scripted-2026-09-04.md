# TASK-6X2FQH — Scripted QC evidence (2026-09-04)

Environment: launchpad-docker-phpfpm-1 (PHP 8.3, Magento 2.4.8-p5), store 1 (default scope config: `autocancel_active=1`, `autocancel_timeout=1380`).

## Data at start

| Order | Status | Created (UTC) | Method |
|---|---|---|---|
| #32 / 000000029 | `vietqr_pending` | 2026-09-03 08:04 (~23.6h old) | secomm_vietqr |
| #33 / 000000030 | `vietqr_awaiting_payment_confirm` | 2026-09-04 06:10 | secomm_vietqr |
| #30 | `pending` | (existing) | checkmo |

## Results

| Spec §8 case | Result |
|---|---|
| Happy path: #32 (23.6h > 23h timeout) → cron → `canceled`/`canceled` + history comment `Canceled automatically because payment timeout exceeded.` | **PASS** |
| UTC cutoff: #32 canceled only after crossing 23h boundary (cutoff computed UTC, not store-local UTC+7) | **PASS** |
| Skip confirmed: #33 `awaiting_payment_confirm` past timeout → NOT canceled | **PASS** |
| Method guard: #30 (checkmo) artificially wearing `vietqr_pending` → skipped, then restored to `pending` | **PASS** |
| Idempotency: second cron run → 0 new history comments on #32 | **PASS** |
| Log: `var/log/secomm_vietqr.log` → `VietQR auto-canceled order 000000029 (payment timeout exceeded).` + summary `canceled 1 order(s) for store 1 (timeout 1380 min).` | **PASS** |
| Cron registration: `cron_schedule` rows for `secomm_vietqr_cancel_pending` generated at */5 (07:10/07:15/07:20 UTC) | **PASS** |

## AC-028 — deadline messaging (added 2026-09-04)

- `PaymentInfo::getPaymentDeadline/isPastDeadline/getFormattedDeadline` — DI-resolved OK after `setup:di:compile` (constructor gained `TimezoneInterface`; stale compiled DI map caused a transient `TypeError` until recompile).
- Formula verified on real orders: `created_at (UTC) + timeout`; display format `13:10 5 thg 9, 2026` (store locale vi_VN, GMT+7).
- Config gate verified: with `autocancel_active=0` the deadline resolves `null` → no messaging rendered anywhere (per AC).
- Email template computes the deadline inline (snapshot style, session-decoupled) — same formula, gated on the same config.
- Browser check (manual): deadline amber note above the Submit form on the custom page; past-deadline red notice + hidden form.

## Review fix — email template fatal (found 2026-09-04)

`email/payment-info.phtml` used `$parentBlock->getOrder()` — the PHP template engine only extracts `_viewVars` + `$block`/`$escaper`/`$viewModel` (`TemplateEngine/Php.php:63-67`), so `$parentBlock` was undefined → **fatal on every render** (pre-existing from TASK-N35E28; `OrderSender` swallows + logs, so it failed silently). Fixed to the core `$block->getOrder()` idiom (same as `Magento_Sales::email/items.phtml`).

Render-verified after fix: `#33` (pending + autocancel on) → deadline row shown; `#32` (canceled) → hidden (status gate works); order + config restored afterwards.

## Note

- Cron execution via direct `CancelPendingOrders::execute()` (DI-resolved); scheduled-group execution also exercised via `bin/magento cron:run --group=default`.
- Remaining manual (browser): canceled order #32 → `/vietqr/payment/view/order_id/32` shows closed-order notice, Submit form hidden, My Orders button hidden; direct POST to `vietqr/payment/submit` rejected.
- Config left **enabled** (timeout 1380) on this dev store for continued testing — reset `payment/secomm_vietqr/autocancel_active=0` if unwanted.
