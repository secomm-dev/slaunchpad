---
id: FEAT-CSWYEJ
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: 'Payment Core — Manage Pending Payment Lifecycle & Retry Checkout (VNPAY first adapter)'
mode: A                      # payment (VNPAY) + order lifecycle + DB schema → Tier-2, §12
specification_level: FULL
spec_status: VALID           # approved 2026-08-25 (user acting as TL/SA, /approve D1–D6) — DEC-FEATCSWYEJ-001 accepted
specification_ref: ../../specs/SPEC-FEAT-CSWYEJ-payment-core.md
risk: high
status: dev-complete            # 6/6 tasks code xong + static checks pass; chờ runtime verify + phpunit + QC matrix + TL review (Tier-2)
created: 2026-08-25
updated: 2026-08-25
ticket_ref:
  external: null
  tasks:
    - TASK-NJ77PG                   # scaffold module + config + db_schema (Mode C, Tier-2 DB)
    - TASK-JSQN6P                   # assign observer + expiry snapshot (Mode B)
    - TASK-PMKWS6                   # expiry cron: 3-layer race guard + cancel (Mode A)
    - TASK-KKPDNZ                   # VNPAY adapter trong core: VnpayCheckoutUrl + querydr (Mode A; rev theo DEC-002)
    - TASK-7MHH19                   # Continue Payment button + controller (Mode B)
    - TASK-M20PT6                   # unit tests + QC matrix + context docs (Mode B)
    - TASK-Q2BAHW                   # console command QC trigger expiry cron (Mode C)
decisions: [DEC-FEATCSWYEJ-001, DEC-FEATCSWYEJ-002, DEC-FEATCSWYEJ-003, DEC-FEATCSWYEJ-004]
decision_assessment: material
decision_refs: [DEC-FEATCSWYEJ-001, DEC-FEATCSWYEJ-002, DEC-FEATCSWYEJ-003, DEC-FEATCSWYEJ-004]
decision_approval_summary:
  total: 4
  pending_approval: []
  approved: [DEC-FEATCSWYEJ-001, DEC-FEATCSWYEJ-002, DEC-FEATCSWYEJ-003, DEC-FEATCSWYEJ-004]
  rejected: []
  superseded: []
  last_synced: 2026-08-25
components:
  - CMP-PAYMENTCORE            # Secomm_PaymentCore — module mới (mới)
source_areas:
  - app/code/Secomm/PaymentCore/                  # NEW — toàn bộ module (kể cả VNPAY adapter, DEC-002)
  - app/design/frontend/Secomm/launchpad/         # order view button (QC regression)
  # app/code/Vnpayment/VNPAY — KHÔNG sửa (pristine, DEC-FEATCSWYEJ-002)
changes_project_state: true
changes_architecture: true    # module mới + adapter contract mới
changes_integration: true     # VNPAY querydr API (mới)
changes_known_limitations: true  # đóng gap "pending payment không ai quản lý"
verified_against_commit: 140a83e8
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-CSWYEJ] Payment Core — Manage Pending Payment Lifecycle & Retry Checkout

<!-- CANONICAL RECORD — Full spec: SPEC-FEAT-CSWYEJ-payment-core.md. Module mới Secomm_PaymentCore
     quản lý tập trung pending-payment lifecycle (active/payable → expired → cancel) qua adapter
     contract; VNPAY là adapter đầu tiên. KHÔNG code trước khi spec VALID (D1–D6 pending). -->

## Context

User request 2026-08-25: các payment method redirect (VNPAY, Mollie...) hiện tự xử lý timeout/retry/cancel
riêng lẻ hoặc không xử lý gì — order pending payment bị bỏ mặc: inventory reservation không bao giờ
được compensate, customer đóng trang thanh toán là mất đơn. Cần Payment Core dùng chung quản lý lifecycle
tập trung + Continue Payment trong My Account.

**Risk Tier-2** — chạm: payment logic (`Vnpayment_VNPAY` — SA review §12), order state transitions
(cancel qua cron), DB schema (table mới), integration mới (VNPAY querydr) → Mode A, spec-first.

## Hiện trạng VNPAY (verified 2026-08-25 @ 140a83e8)

- Checkout URL build fresh mỗi lần tại `Controller/Order/Info.php:48-98` (HMAC-SHA512, TxnRef = increment id).
- IPN `Controller/Order/Ipn.php:50-140`: match order theo TxnRef=incrementId, set state + invoice khi rsp 00.
- `Model/vnpay.php`: AbstractMethod, `active=0`, group offline, `_isOffline=true`.
- Không có timeout/expiry/cancel nào — order pending sống mãi.

## Kiến trúc (tóm tắt — canonical trong spec §4)

```
place order (managed method) ──observer──► payment record {expires_at snapshot, status=active}
My Account > Order Detail ──CanContinuePayment──► [Continue Payment] POST ──► adapter getCheckoutUrl → redirect
Cron 5' ──ExpirePayments──► lock per-order ──reload state──► adapter querydr verify
             ├─ PAID     → skip + warn (IPN delay)
             ├─ UNKNOWN  → skip + retry
             └─ NOT_PAID → OrderManagementInterface::cancel() → MSI reservation compensate (std flow)
```

Core không import class provider nào (AC-013 ở mức code dependency) — VNPAY adapter nằm trong Model/Provider của core (DEC-002), đọc config extension như string contract.

## Status

- 2026-08-25 (cuối ngày): **Dev complete 6/6 tasks** (spec DRAFT → VALID → approve D1–D6 → mint tasks → dev trong cùng run, §8.5 auto-continue).
  - Static checks: 34 PHP brace/paren + XML parse pass; core 0 VNPAY reference (AC-013); whitelist parity; i18n parity.
  - Chi tiết + files: `.ai/evidence/FEAT-CSWYEJ/dev-evidence.md`.
- **Chờ tiếp theo (user):** (1) enable + `setup:upgrade` + `setup:di:compile` · (2) `vendor/bin/phpunit app/code/Secomm/PaymentCore/Test/Unit` · (3) QC matrix `.ai/evidence/FEAT-CSWYEJ/qc-matrix.md` (VNPAY sandbox + querydr_url) · (4) TL/SA code review Tier-2 · (5) human review + commit context diffs.
- 2026-08-25 (đầu ngày): Spec DRAFT → VALID (DEC-FEATCSWYEJ-001 accepted, `/approve` D1–D6).