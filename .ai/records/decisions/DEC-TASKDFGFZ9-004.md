---
id: DEC-TASKDFGFZ9-004
title: 'Phase 3 closure: atomic per-order claim (active_order_claim UNIQUE — engine-enforced), currency gate → carrier adapters, zero-amount COD giữ classification COD, claim-release rules (FAILED releases / UNKNOWN keeps)'
status: accepted             # user acting as SA/TL — plan approval 2026-09-23 (r4)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-23
created: 2026-09-23
last_verified: 2026-09-23
verified_against_commit:
supersedes:
  - 'DEC-TASKDFGFZ9-003 — CHỈ PHẦN: (a) VND gate nằm trong COD resolver (giờ: decision trả ORDER currency, carrier tự gate currency hỗ trợ trước recordPending/POST); (b) recordPending SELECT-then-INSERT (giờ: INSERT-first + active_order_claim UNIQUE + CodClaimConflictException); (c) "recordPending chỉ amount > 0" ngữ cảnh classification (zero-amount COD giờ là COLLECTIBLE 0.0 — vẫn không claim ledger); (d) grand_total ≤ 0 → NOT_COD (giờ: 0 → COLLECTIBLE 0.0; âm → REJECTED invalid_order_amount)'
superseded_by:
work_items: [TASK-DFGFZ9]
---

# Decision Record: Phase 3 Closure — Atomic Claim, Carrier Currency, Zero-Amount COD

## Status

Accepted (2026-09-23 — user acting as SA/TL, plan approval r4 sau khi audit xác nhận 4
blockers: claim không atomic, 2 stuck-PENDING bugs, currency sai chỗ, zero-amount
misclassification). Tier-2 review trước merge.

## Decision Type

Architecture (address-shipping.md v13 closure amendment — không mint v14)

## Decisions

1. **Atomic per-order claim, engine-enforced** — `secomm_cod_collection` thêm cột
   application-managed `active_order_claim` (bigint unsigned nullable) + UNIQUE constraint:
   mọi row live (PENDING|UNKNOWN|SUBMITTED|RECOVERED, amount > 0) mang claim =
   `magento_order_id`; MySQL unique cho phép nhiều NULL → **tối đa 1 active claim per order
   được enforce bởi engine** (không còn dựa vào read-then-act — audit CONFIRMED
   SELECT-then-INSERT cho phép 2 carrier cùng claim). `recordPending` = INSERT-first:
   duplicate-key → SELECT phân biệt — same (carrier, reference) → re-arm (SUBMITTED|
   RECOVERED no-downgrade; else refresh + restore PENDING); key absent →
   `CodClaimConflictException` (message cite holder qua lookup; holder không thấy → retryable).

2. **Claim-release rules** — `markNotSubmitted(FAILED)` đặt `active_order_claim = NULL`
   (definitive provider rejection collected nothing → slot mở cho attempt khác);
   UNKNOWN **giữ claim** (provider order có thể tồn tại — chặn cho đến khi reconcile);
   SUBMITTED/RECOVERED giữ. Claim-release fixes: GHTK arm ledger SAU payload mapping
   (trước đó arm trước buildProducts/map — throw để lại PENDING kẹt); GHN 3 fail sites
   pre-POST trong `resolveAndCreate` (handoff/resolved-null/mapping-missing) pass
   attempt/amount → FAILED → claim released (trước đó kẹt PENDING vĩnh viễn).

3. **Currency SUPPORT là carrier concern** — decision trả **ORDER currency**
   (`order_currency_code`); resolver KHÔNG gate currency (bỏ gate VND + const).
   Carriers gate trước recordPending/POST, không convert: GHTK → LocalizedException
   "COD currency unsupported"; GHN → `COD_REJECTED` outcome (reason
   `currency_unsupported` — comment VISIBLE, không anchor không POST).

4. **Zero-amount COD giữ classification** — `collectible()` cho amount ≥ 0: đơn
   `cashondelivery` grand_total = 0 → COLLECTIBLE 0.0 (CODRisk/consumer vẫn biết payment
   method); amount trên wire = 0 (pick_money/cod_amount). grand_total < 0 → REJECTED
   `invalid_order_amount` (data defect — fail-loud). Zero-amount không claim ledger
   (recordPending vẫn chỉ amount > 0 — thu 0 vô hại).

5. **Recovery timeline** — 5 điểm ngắt được chứng minh (integration proof 2 PDO
   connections, gồm transaction-blocked variant: B bị block 3s bởi lock của A rồi nhận
   1062 sau commit): trước claim / sau claim trước anchor / sau anchor trước POST / POST OK
   mất response (UNKNOWN giữ claim; retry same-reference → frozen replay + ORDER_ID_EXIST/
   idempotency → RECOVERED) / sau lưu response. Admin path: GHTK "retry safe" message +
   native label flow; GHN retry CLI; lookup bằng reference
   (`findByPartnerCode`/`findByClientOrderCode`/`findFrozenAmount`).

## Consequences

- An orphaned UNKNOWN row chặn các attempt khác cho đến khi reconcile — engine-enforced
  conservatism, đúng ý thiết kế; admin xử lý qua same-reference retry.
- Re-arm giờ restore PENDING (fix docblock/code mismatch cũ — FAILED row re-arm thành PENDING).
- Frozen replay của row non-VND (hypothetical) sẽ bị carrier VND gate chặn — safe.
- Fresh-install full-flow: empty-DB `setup:upgrade` blocker (third-party eager
  `Session\Config`) là N-Defect môi trường riêng — gate OPEN, structural proof không ghi pass.
- GHN sandbox `cod_amount > 0` probe: gate OPEN chờ credential — không ghi E2E pass.
- Giữ nguyên từ DEC-002/003: cashondelivery identification, ledger ownership, anchors =
  provider facts, P1 policy một-thu-một-lần grand_total VND-only-carrier-gated, không
  allocation, ShippingCore ↛ Cod, Cod ↛ carriers, không mark paid.
