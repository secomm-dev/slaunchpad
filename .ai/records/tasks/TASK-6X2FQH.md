---
id: TASK-6X2FQH
type: task
title: 'VietQR auto-cancel cron — cancel overdue unpaid vietqr_pending orders after configurable timeout'
project_code: SLP
parent: {type: feature, id: FEAT-ZKD4VA}
mode: A
specification_level: FULL
spec_status: VALID            # spec updated 2026-09-04 (AC-024..AC-027 + §4.11); TL approved
specification_ref: .ai/specs/SPEC-FEAT-ZKD4VA-vietqr-payment.md
risk: high
status: in-progress
priority: high
decision_assessment: material
decisions: []
components:
  - CMP-VIETQR
changes_project_state: true
source_areas:
  - app/code/Secomm/VietQr/
created: 2026-09-04
updated: 2026-09-04
owner: [dev]
related_tickets: [TASK-N35E28]
---

# [SLP][FEAT-ZKD4VA][TASK-6X2FQH] VietQR auto-cancel cron — cancel overdue unpaid vietqr_pending orders after configurable timeout

Follow-up scope split out of TASK-N35E28 (whose original AC-001..AC-023 scope is code-complete, pending TL review of that batch). Implements spec **AC-024..AC-027 + §4.11**.

## Description

Unpaid VietQR orders (`vietqr_pending`) stay open forever unless a merchant cancels them manually. Add a cron that auto-cancels them after a configurable payment timeout, with admin config for enable/disable, cron frequency (preset-interval dropdown), timeout (minutes), and cancel reason. Canceled orders must no longer be confirmable on the custom VietQR page.

## Scope (from spec §3)

- **AC-024** — Admin config under `payment/secomm_vietqr/*`: `autocancel_active` (default 0), `autocancel_timeout` minutes (default 1440), `autocancel_reason` (default `Canceled automatically because payment timeout exceeded.`), Cron Frequency dropdown (Every 5/10/15/30/60 minutes, values are literal cron expressions; default `*/5 * * * *`).
- **AC-025** — Cron cancels only `secomm_vietqr` orders with status = configured New Order Status (`vietqr_pending`), created before `now (UTC) − timeout`. Never touches `vietqr_awaiting_payment_confirm` or non-VietQR orders. Reason saved as status-history comment (not visible on front).
- **AC-026** — Re-verify method + status on a fresh load immediately before each cancel (race vs customer Submit). Idempotent by construction; batched (100); per-order try/catch; all results logged to `var/log/secomm_vietqr.log`.
- **AC-027** — Custom VietQR page hides the Submit form + shows a closed-order notice when status ≠ configured New Order Status. (My Orders button + direct-POST guard already exist — QC verify only.)
- **AC-028** — Payment deadline messaging: static deadline (`created_at` + timeout) shown on the custom page + order email when auto-cancel is enabled; past-deadline (pre-cron) hides the form; nothing shown when disabled.

## Technical Approach

- `etc/crontab.xml` — job `secomm_vietqr_cancel_pending` with `<config_path>payment/secomm_vietqr/autocancel_frequency</config_path>` — schedule resolved at runtime from the frequency field's saved value.
- `system.xml` — frequency field stores literal cron expressions (`Secomm\VietQr\Model\Source\CronFrequency`), default scope only. The crontab path itself cannot be the field's `config_path` (`system_file.xsd` limits config paths to 3 segments — caught by `setup:upgrade` XSD validation, fixed 2026-09-04).
- `Cron/CancelPendingOrders.php` — per-store loop (per-store timeout/status), UTC cutoff, id collection (ceiling 500/store/run) → per-order fresh load + verify + `registerCancellation(reason)` + save.
- `Model/Config.php` — `isAutoCancelEnabled`, `getAutoCancelTimeout`, `getAutoCancelReason` (+ `?int $storeId` on `getNewOrderStatus`).
- `Block/PaymentInfo::isPaymentClosed()` + template notice/hiding.

## Files/Areas Affected

- `app/code/Secomm/VietQr/etc/crontab.xml` — **NEW**
- `app/code/Secomm/VietQr/Cron/CancelPendingOrders.php` — **NEW**
- `app/code/Secomm/VietQr/Model/Source/CronFrequency.php` — **NEW**
- `app/code/Secomm/VietQr/etc/adminhtml/system.xml` — Auto-Cancel fields
- `app/code/Secomm/VietQr/etc/config.xml` — defaults (+ crontab default expr)
- `app/code/Secomm/VietQr/etc/di.xml` — custom logger binding for cron class
- `app/code/Secomm/VietQr/Model/Config.php` — getters
- `app/code/Secomm/VietQr/Block/PaymentInfo.php` + `view/frontend/templates/payment/view.phtml` — AC-027
- `app/code/Secomm/VietQr/i18n/` — vi_VN + en_US

## Constraints / Rules

- Tier 2 (payment) — TL review required (approved 2026-09-04).
- Do NOT cancel `vietqr_awaiting_payment_confirm` orders (money may have arrived — merchant reconciles manually).
- Do NOT restore customer quotes (stale sessions; out of scope).
- All config read at store scope; multi-store timeouts resolved per order's store.

## Out of Scope

- QR expiry at the VietQR API level (spec §7).
- Auto-confirm / reconciliation of canceled orders.

## Risks

- UTC vs store-local cutoff — computed in UTC (`created_at` is UTC); QC case covers it.
- Cron frequency change lags ~1h via pre-generated schedule entries (documented in spec §4.11).
- Race cancel vs Submit — guarded by fresh per-order status re-check (AC-026 test case).

## Definition of Done

- [x] Code complete
- [x] AI pre-review pass (php -l, XML well-formed, job registered w/ resolved schedule `*/5 * * * *`, config defaults resolve, cron class DI-execute OK, 5 frequency options)
- [ ] TL review approved (Tier 2)
- [x] Tests pass — scripted QC 2026-09-04: happy path + UTC cutoff + skip confirmed + method guard + idempotency + log all PASS (see `.ai/evidence/TASK-6X2FQH/qc-scripted-2026-09-04.md`; browser AC-027 check remains manual)
- [ ] QC verified (Mode A)
