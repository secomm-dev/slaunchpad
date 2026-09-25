---
id: DEC-TASKDFGFZ9-003
title: 'COD product final state (pre-release): isCod mặc định cashondelivery hardcode (Magento_OfflinePayments), bỏ config surface + DataPatch migration, collection ledger secomm_cod_collection chống bypass + cross-carrier, fresh-install không migration'
status: accepted             # user acting as SA/TL — plan approval 2026-09-23 (r3)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-23
created: 2026-09-23
last_verified: 2026-09-23
verified_against_commit:
supersedes:
  - 'DEC-TASKDFGFZ9-001 items 3/5/6 — CHỈ PHẦN: "GHN giữ deliberately-no-COD" (đã đảo ở DEC-002), config path move + admin section/ACL `Secomm_Cod::config` (bỏ — không còn field), DataPatch migration (bỏ — chưa có client, staging là dữ liệu dev)'
  - 'DEC-TASKDFGFZ9-002 decision 1 mệnh đề prior-VO ("caller-supplied context … CALLER owns same/different shipment identity") — contract đổi: caller chỉ supply ATTEMPT identity; frozen replay + prior check do RESOLVER tự đọc ledger, caller không thể bypass'
  - 'DEC-TASKDFGFZ9-002 consequences dòng "Task-DFGFZ9 phase 1 results (identification ownership, config path, migration) giữ nguyên" — config path + migration không còn tồn tại'
superseded_by:
  - 'DEC-TASKDFGFZ9-004 — PARTIAL: VND gate location (resolver → carrier adapters), recordPending semantics (INSERT-first + active_order_claim UNIQUE + CodClaimConflictException), zero-amount classification (COLLECTIBLE 0.0; âm → REJECTED invalid_order_amount)'
work_items: [TASK-DFGFZ9]
---

# Decision Record: COD Product Final State (Pre-release)

## Status

Accepted (2026-09-23 — user acting as SA/TL, plan approval r3 sau audit source hiện tại:
cross-carrier hole + caller-null-prior bypass CONFIRMED cả 2 hướng; sandbox credentials absent).
Tier-2 review toàn bộ change set trước merge. Plan: phase 3
(`/home/secomm/.claude/plans/h-y-audit-v-x-polished-pixel.md`).

## Decision Type

Architecture + product scoping (address-shipping.md Rev v12 → v13)

## Decisions

1. **Identification mặc định = Magento core `cashondelivery`** — `DefaultCodPaymentMethodResolver`
   hardcode `DEFAULT_COD_METHODS = ['cashondelivery']`, KHÔNG đọc config, KHÔNG có admin field.
   `Secomm_Cod` khai báo dependency tường minh `Magento_OfflinePayments` (module cung cấp method;
   method INACTIVE mặc định — merchant tự bật). KHÔNG tạo payment method riêng. Customization
   (CODRisk tương lai) = DI preference trên `CodPaymentMethodResolverInterface`.

2. **Bỏ toàn bộ config surface + migration** — xoá `etc/adminhtml/system.xml` (section
   "COD Settings"), `etc/adminhtml/acl.xml` (`Secomm_Cod::config`), `etc/config.xml` (empty
   default), DataPatch `MigrateLegacyCodPaymentMethodConfig` + tests. Lý do: chưa có client sử
   dụng; staging chứa dữ liệu development, không phải baseline cần bảo toàn. Runtime
   `Secomm_Cod` KHÔNG phụ thuộc row config nào. Physical Package Defaults của ShippingCore
   giữ nguyên.

3. **Collection ledger `secomm_cod_collection` do `Secomm_Cod` sở hữu** — sửa 2 holes audit
   xác nhận: (a) cross-carrier (mỗi carrier chỉ query anchor bảng của mình → shipment thứ 2
   qua carrier kia thu lại cả đơn); (b) caller-null-prior (resolver không có storage → prior
   null đi thẳng qua gate). Contract đổi: `resolve(Order, Shipment, ?CodCollectionAttemptInterface)`
   — caller chỉ supply ĐỊNH DANH attempt (carrier + provider reference); frozen replay +
   prior check do RESOLVER tự đọc ledger. Carriers REPORT (`recordPending` khi amount > 0,
   trước anchor insert + POST; mirror markSubmitted/markNotSubmitted). Null attempt an toàn:
   prior check vẫn chạy (exclude nothing) — không thể bypass. Ledger KHÔNG bao giờ reconcile
   tiền: provider nhận đơn COD ≠ Magento đã nhận tiền — không mark order paid.

4. **Anchor tables = provider facts only** — `secomm_ghtk_shipment` / `secomm_ghn_shipment`
   giữ idempotency + label/tracking/fee recovery; cột `cod_amount` chỉ còn audit (ghi, không
   đọc). Xoá `findFrozenAmount`/`findCollectedPrior` (Ghtk anchor repo) và
   `findCollectedPrior`/`updateCodAmount` (Ghn repo).

5. **Fresh-install là trạng thái chuẩn** — cài mới: `setup:upgrade` tạo ledger table, KHÔNG có
   migration step, KHÔNG cần config. Bằng chứng trên scratch DB thật (row counts, không tin
   exit code — LESSONS_LEARNED). Staging dev-data cleanup = 1 file SQL manual riêng
   (`staging-cleanup.sql`), chạy tay sau review SELECT — KHÔNG nhúng vào patch/runtime; KHÔNG
   xoá anchors đang phục vụ flow active.

6. **Sandbox gate** — GHN create với `cod_amount > 0`: BLOCKED_BY_CREDENTIAL (0 rows
   `carriers/secomm_ghn/*`); QC gate document hoá request/expected/evidence; KHÔNG ghi E2E
   pass khi chưa có response thật. Cap GHN 50M giữ nguyên (GHTK không có cap — không thêm khi
   chưa có provider fact).

## Consequences

- Anchor `cod_amount` trên các row pre-deploy (đều = 0) có thể lệch với ledger trên retry —
  ledger là authoritative; dev rows được cleanup SQL xử lý.
- Crash window giữa `recordPending` và anchor insert: ledger đã armed → chặn (hướng an toàn).
- Bỏ admin field: đổi danh sách COD methods đòi hỏi deploy (preference override) — chấp nhận
  cho P1 vì Launchpad chỉ dùng core COD; giải pháp per-client khi cần.
- Row config cũ `secomm_shippingcore/cod/payment_methods` (nếu còn đâu đó) hoàn toàn inert —
  staging cleanup có thể xoá.
- DEC-001/002 còn lại (owner duy nhất `Secomm_Cod`, safe-false semantics → giờ là
  hardcode-default semantics, P1 policy một-thu-một-lần grand_total VND-only, partial/second
  rejected, không allocation framework, ShippingCore ↛ Cod, Cod ↛ carriers) giữ nguyên.

---

## Supersession note (DEC-TASKDFGFZ9-004, 2026-09-23)

Các mệnh đề bị partial-supersede: currency gate (decision trả ORDER currency — carrier gate
VND trước recordPending/POST; resolver không gate currency); recordPending (INSERT-first,
`active_order_claim` UNIQUE engine-enforced per-order claim, `CodClaimConflictException`);
zero-amount COD (grand_total = 0 → COLLECTIBLE 0.0 — KHÔNG đổi thành NOT_COD; âm →
REJECTED `invalid_order_amount`). Các mệnh đề còn lại của DEC-003 giữ nguyên (hardcode
`cashondelivery`, bỏ config surface/migration, ledger ownership, anchor = provider facts,
fresh-install standard).
