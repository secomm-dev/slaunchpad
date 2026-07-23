---
id: FEAT-004
title: VNPAY IPN callback processing — signature, status mapping, retry, idempotency, audit
mode: A                      # FULL WORKFLOW — multiple Tier-2 risk categories (payment/security/contract/data-integrity)
risk: high
status: proposed             # awaiting TL plan approval + SA (Tier-2) signoff before implementation
created: 2026-07-21
updated: 2026-07-21
ticket_ref:
decisions:                   # canonical decision records (Phase 1b correction — created in proposed status)
  - DEC-010
  - DEC-011
  - DEC-012
  - DEC-013
  - DEC-014
  - DEC-015
decision_assessment: material
decision_refs: [DEC-010, DEC-011, DEC-012, DEC-013, DEC-014, DEC-015]   # B1 explicit alias of decisions:
decision_approval_summary:   # DERIVED (B1) — single-read approval state; all 6 DEC are `proposed`
  total: 6
  pending_approval: [DEC-010, DEC-011, DEC-012, DEC-013, DEC-014, DEC-015]
  approved: []
  rejected: []
  superseded: []
  last_synced: 2026-07-22
  verified_against_commit: 1afdfc8
# Knowledge-consolidation contract (RM-07)
components:
  - CMP-VNPAY                 # placeholder stable ID (COMPONENT_INDEX = Phase 1c)
source_areas:
  - app/code/Vnpayment/VNPAY/Controller/Order/Ipn.php
  - app/code/Vnpayment/VNPAY/Model/vnpay.php
  - app/code/Vnpayment/VNPAY/etc/config.xml
  - app/code/Vnpayment/VNPAY/etc/adminhtml/system.xml
changes_project_state: true   # likely a new audit store (SA decision: table vs log)
changes_architecture: true    # callback flow redesign (retry queue / idempotency / payment-transaction update)
changes_integration: true     # VNPAY payload contract may change
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-07-21
supersedes: []
---

# Feature Record: VNPAY IPN callback processing hardening (Mode A)

<!-- CANONICAL RECORD (Phase 1a) — Mode A FULL CEREMONY. -->
<!-- GATE: per CLAUDE.md + AGENTS §11/§12, payment logic = Tier-2 → TL plan approval + SA signoff MANDATORY before implementation. RM-06: Mode A always needs pre-implementation TL approach approval. -->
<!-- VNPAY is default-INACTIVE in this project (AGENTS §5/§12) — blast radius limited pre-go-live, but data-integrity requirements are binding regardless. -->

## Classification

| Field | Value | Reason |
|---|---|---|
| **mode** | **A** (Full Workflow) | chạm ≥5 Tier-2 risk categories đồng thời (xem risk_categories) — bất kỳ 1 cũng đủ Mode A |
| **risk** | **high** | payment + data integrity + irreversible |
| **risk_categories** | `payment` · `security` (signature) · `external_api_contract` · `data integrity / irreversible operations` · (possibly `database/schema` if audit table — D5) | xem `core/risk-categories.md` + §Approach & risk mapping |
| **escalation_required** | **true** (Tier-2 SA/CTO) | payment logic — CLAUDE.md + AGENTS §11/§12 |
| **decision_assessment** | **required → done (`material`)** | 13-dimension assessment; material choices ⇒ DEC-010..DEC-015 (canonical, `proposed`) |

**Classification reason:** the callback chạm **payment state transitions** (order/payment/invoice state), **security / signature verification** (HMAC-SHA512), **external API contract** (VNPAY payload may change), **data integrity + irreversible operations** (duplicate → double invoice; production order/transaction corruption), and possibly **database/schema** (audit store). The expected minimum (`payment` + `external_api_contract`) is met and exceeded. → Mode A + Tier-2 escalation; not Mode B/C work.

## Context

Harden the VNPAY IPN callback (`Vnpayment_VNPAY/Controller/Order/Ipn.php`) end-to-end: signature validation, transaction-status mapping, retry for early callbacks, duplicate prevention, proper order+payment-transaction update, and an audit trail — without corrupting existing production orders/transactions. The VNPAY payload contract may change.

**Current callback (read 2026-07-21, `Ipn::execute()`):**
- Reads `vnp_SecureHash` + secret `payment/vnpay/hash_code`; builds hashData from sorted `urlencode(k)=urlencode(v)` joined by `&`; `hash_hmac('sha512', …)`; compares `$secureHash == $vnp_SecureHash` (**non-strict `==` — timing-unsafe**).
- Loads order by `vnp_TxnRef` (increment id); amount check `(int)($order->getBaseGrandTotal()*100)` vs `vnp_Amount`.
- Dedup = `order status == pending` only; maps `vnp_ResponseCode == '00'` → STATE_PROCESSING + invoice (manual `setTotalPaid` + `$order->save()`); else → STATE_CANCELED.
- Returns JSON `{RspCode, Message}`; logs `rspCode/msg` via `logger->debug`. `catch (Exception $e)` (no leading `\` → never catches in namespaced class).

**Why Mode A:** the requirements hit ≥5 generic risk categories simultaneously — see §Approach & risk mapping.

## Requirements (AC — testable)

- **AC-001 Signature:** validate `vnp_SecureHash` with **constant-time** compare (`hash_equals`), per current VNPAY HMAC-SHA512 spec (exact param set + encoding + ordering confirmed with SA). Reject → `RspCode '97'`; never disclose which field failed.
- **AC-002 Status mapping:** map **both** `vnp_ResponseCode` and `vnp_TransactionStatus` (00 success / 07 suspect-fraud / 09 + others fail) to correct Magento states (PROCESSING / PAYMENT_REVIEW-or-HOLD / CANCELED). Mapping table documented + reviewed by SA.
- **AC-003 Retry (early callback):** when the order/transaction is not yet available (race), respond so VNPAY retries (or queue locally) with **bounded retries**; no valid-payment IPN is silently lost. Order not-found is distinguishable from "not yet available".
- **AC-004 Idempotency (no duplicates):** use `vnp_TransactionNo` as idempotency key + **DB-level order lock** (SELECT FOR UPDATE / queue lock). Duplicate/re-played callback → `RspCode '02'` with **no re-invoice / no re-state**.
- **AC-005 Order + payment transaction update:** on success, update order state + create a real **payment transaction record** (via `registerCaptureNotification` / payment model, not manual `setTotalPaid`). Invoice + payment + order saved in one DB transaction.
- **AC-006 Audit trail:** persist an **immutable** audit record for every **rejected** ('97' invalid sig, '04' invalid amount, '01' not-found), **retried** (early callback queued/replayed), and **duplicate** ('02') callback — timestamp, `vnp_TxnRef`, `vnp_TransactionNo`, RspCode, reason, raw-payload hash. Storage = SA decision (new table vs append-only log).
- **AC-007 Payload contract versioning:** parse current VNPAY payload; tolerate anticipated v2 fields; **fail safe** on unknown mandatory fields; schema documented.
- **AC-008 Data integrity / backward-compat:** existing production orders + transactions **unchanged**; new logic applies only to new callbacks; a re-played historical callback cannot re-state a settled order.
- **AC-009 Secrets:** `hash_code`/secret never logged; error messages leak no signature/secret/PII.
- **AC-010 No regression:** Mollie (active gateway) unaffected; VNPAY default-inactive behaviour preserved until enabled.

## Approach & risk mapping (why Mode A)

| Requirement | Generic risk category (RM-04) | Current-code risk |
|---|---|---|
| Change signature validation | **Security** (signature), **Payment** | R1 `==` not `hash_equals` → timing attack |
| Change status mapping | **Payment**, **Order data integrity** | only `vnp_ResponseCode=='00'` checked; ignores `vnp_TransactionStatus`; auto-cancels on any non-00 |
| Retry before order available | **Payment**, **Irreversible/lost revenue** | R4 returns '01' + forgets → valid payment IPN lost → order stuck pending |
| Prevent duplicates | **Payment**, **Data integrity**, **Irreversible** | R2 dedup = status-check only → concurrent IPN race → **double invoice**; no tx-id idempotency; no DB lock |
| Update order + payment tx state | **Payment**, **Order data integrity** | R3 manual `setTotalPaid` + `$order->save()`; no payment transaction record; payment state inconsistent |
| Audit trail (rejected/duplicate) | **Security/compliance**, (schema if table) | only `logger->debug`; no immutable audit store |
| Payload contract may change | **External API contract** | R8 brittle param parsing; no versioning |
| Don't corrupt prod orders/tx | **Data migration/integrity**, **Irreversible** | R9 no back-compat guard; re-play can re-state |

Additional correctness risks found in the current code: **R5** `catch (Exception $e)` missing leading `\` → never catches in the namespaced class → uncaught exceptions surface as HTTP 500. **R6** amount compare `(int)(baseGrandTotal*100)` — float precision + base-vs-order currency mismatch. **R7** `hash_code` stored in scopeConfig — must never be logged.

→ 5+ Tier-2 categories → **Mode A + Tier-2 (SA) escalation**. This is not Mode B/C work.

## Open decisions (SA/TL — captured as canonical DEC-010..DEC-015, status `proposed`)

- **D1 Signature spec:** confirm exact current VNPAY HMAC-SHA512 contract (param set, `urlencode` vs `rawurldecode`, ordering, `vnp_SecureHashType`). → SA (Tier-2).
- **D2 Status mapping table:** final VNPAY code → Magento state map (esp. '07' suspect → PAYMENT_REVIEW/HOLD, not auto-process/cancel). → SA + PM.
- **D3 Retry strategy:** respond-with-VNPAY-retry-code vs internal queue/store-and-replay; retry bound + backoff. → SA.
- **D4 Idempotency mechanism:** idempotency key (`vnp_TransactionNo`) + lock (message-queue vs `SELECT FOR UPDATE` on order/tx). → SA.
- **D5 Audit-trail storage:** new DB table (**schema + migration → SA review**) vs append-only log file vs Magento native payment-transaction history. → SA.
- **D6 Payload versioning:** how to support current + anticipated v2 payload without breaking. → SA.

## Implementation plan (Mode A — in-record; NO separate plan file)

_Gated: proceeds only after TL plan approval + SA (Tier-2) signoff on D1–D6._

1. **Signature (AC-001, DEC-010):** extract a `SignatureVerifier` (`hash_equals` + confirmed VNPAY HMAC-SHA512 construction); reject → `RspCode '97'` + audit; never disclose which field failed.
2. **Idempotency + lock (AC-004/005, DEC-013):** wrap processing in a DB transaction + order/tx lock keyed on `vnp_TransactionNo`; register payment via `registerCaptureNotification` (no manual `setTotalPaid`); invoice+payment+order in one transaction.
3. **Status mapping (AC-002, DEC-011):** apply the SA-ratified `vnp_ResponseCode`/`vnp_TransactionStatus` → state table (07 suspect → PAYMENT_REVIEW/HOLD, not auto-process/cancel).
4. **Retry (AC-003, DEC-012):** early-callback path — respond retry-eligible OR enqueue with bounded retries + backoff; distinguish "order not found" (terminal) vs "not yet available" (retry).
5. **Audit (AC-006, DEC-014):** write immutable audit on every terminal branch (rejected / retried / duplicate / success).
6. **Payload compat (AC-007, DEC-015):** defensive parse + version detect + fail-safe on unknown mandatory fields.
7. **Hardening behind a config flag** (`payment/vnpay/hardened_ipn`, default **No**) so activation is reversible (see §Rollback & compatibility).
8. **Gates:** plan approval (TL) → implement behind flag → code approval (TL/SA) → QC L3 (valid / replay / concurrent / early / 07-suspect / Mollie-unaffected).

> The **formal specification** = §Context + §Requirements (AC) + §Approach & risk mapping (this record; **no separate spec file**). The **implementation plan** = this section (**no separate plan file**).

## Implementation Notes

_Status: **proposed — NOT implemented.**_ Implementation proceeds only after TL plan approval + SA (Tier-2) signoff on D1–D6. Mode A ceremony: formal spec (above) → plan section → plan approval (TL) → controlled implementation → code approval (TL/SA) → QC L3 (end-to-end checkout + payment + IPN replay). No code changes made in this pass.

## Test Summary

_Status: proposed._ Planned QC (L3, per AGENTS §7.3): valid IPN → order PROCESSING + invoice + payment transaction record; invalid signature → '97' + audit, no state change; invalid amount → '04' + audit; duplicate IPN → '02' + audit, no double invoice; **concurrent IPN** (race) → exactly one invoice (DB lock); early callback (order not yet committed) → retried, not lost; '07' suspect → review/hold (not cancel); re-played historical callback → no re-state; Mollie checkout unaffected. Raw evidence → `.ai/runtime/evidence/FEAT-004/`.

## Compatibility Conclusions

- **Modules affected:** `Vnpayment_VNPAY` (Ipn controller + payment model + config); possibly a new audit model/table (D5). **Mollie untouched** (AC-010).
- **API contracts:** VNPAY IPN payload contract — may change (D6); Magento-facing behaviour (response codes) preserved where possible.
- **Schema:** possibly a new audit table (D5) → migration + SA review; **no change to existing sales/order/payment tables** (AC-008).
- **Data integrity:** existing orders/transactions immutable by new logic (AC-008).
- **Upgrade notes:** VNPAY is default-inactive; coordinate activation with this hardening.

## Rollback & compatibility considerations

**Rollback strategy:**
- **Config-gated activation:** new callback logic behind `payment/vnpay/hardened_ipn` (default **No**); disable → reverts to current behavior instantly, no deploy. VNPAY is already default-inactive → minimal blast radius pre-go-live.
- **Code revert safety:** changes are additive (new verifier/idempotency/audit services) + a guarded controller branch; revert = remove the branch + unset the flag.
- **Transactional safety (AC-005):** order/payment/invoice saved in a single DB transaction → a failed callback leaves no partial state; no manual rollback of financial data needed.
- **Audit store (D5):** if a table is added, it is append-only/immutable — no rollback of audit rows.

**Compatibility with current callback behavior:**
- **Current behavior preserved** until the hardening flag is enabled; VNPAY-facing response `{RspCode, Message}` schema unchanged where possible (contract).
- **Back-compat guard (AC-008):** a re-played historical callback for an already-settled order → idempotent `RspCode '02'`, **no re-state / no re-invoice**. Existing production orders, payment transactions, and financial data are **never mutated** by the new logic.
- **Payload compatibility (AC-007, DEC-015):** current VNPAY payload parsed; unknown optional fields tolerated; unknown mandatory fields → fail-safe reject + audit (no silent corruption).
- **Activation sequencing:** hardening must land + pass QC L3 **before** VNPAY is activated for production (coordinate with D2 status map + D5 audit store).

## References

- Legacy/current source: [Ipn.php](../../../app/code/Vnpayment/VNPAY/Controller/Order/Ipn.php), [Model/vnpay.php](../../../app/code/Vnpayment/VNPAY/Model/vnpay.php)
- AGENTS §6 (VNPAY integration), §11 (Tier-2 escalation), §12 (high-risk: `Vnpayment_VNPAY`)
- CLAUDE.md / AGENTS §7.1: payment + signature + external contract = stop-and-request-TL-review
- Generic risk categories: `core/risk-categories.md` (Phase 1b)
- Related: FEAT-001 (address module — unrelated), FEAT-002/003 (storefront notice — unrelated)
- Decisions: **DEC-010..DEC-015** (canonical, status `proposed`) — see §Open decisions + [`.ai/records/decisions/`](../decisions/). Each DEC backlinks to FEAT-004 (Related records).
- Toolkit version: v4.0 · classified via Phase 1a/1b workflow 2026-07-21
