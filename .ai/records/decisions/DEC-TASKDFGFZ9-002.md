---
id: DEC-TASKDFGFZ9-002
title: 'COD amount decision ownership → Secomm_Cod (collection-decision contract + P1 single-collection full-grand-total VND-only policy; carrier chỉ map kết quả; anchor persistence freeze retry; GHN consume Secomm_Cod — đảo ngược forbidden edge)'
status: accepted             # user acting as SA/TL — plan approval 2026-09-23 (+2 AskUserQuestion: GHTK anchor table; reject partial shipment)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-23
created: 2026-09-23
last_verified: 2026-09-23
verified_against_commit:
supersedes:
  - 'DEC-TASKKM6YAT-001 (DEC-SL016-001) items 4-5 — CHỈ PHẦN: amount base_total_due → grand_total theo order currency (deposit KHÔNG còn implicit support); partial-shipment fail-fast chuyển thành decision rejection reason. Item 8 (comment snapshot) GIỮ NGUYÊN làm audit trail.'
  - 'DEC-TASKDFGFZ9-001 consequences dòng "Amount conversion (collect) giữ carrier-owned" — amount decision giờ thuộc Secomm_Cod; carrier chỉ map.'
  - 'address-shipping.md Rev v11 forbidden edge Secomm_Ghn -X-> Secomm_Cod (GHN giờ CONSUME Secomm_Cod cho CREATE collection decision).'
superseded_by:
  - 'DEC-TASKDFGFZ9-003 (decision 1 prior-VO clause + consequences "phase 1 giữ nguyên" — caller-supplied prior thay bằng ledger; config/migration removed)'
work_items: [TASK-DFGFZ9]
---

# Decision Record: COD Amount Decision Ownership → Secomm_Cod

## Status

Accepted (2026-09-23 — user acting as SA/TL, plan approval sau audit GHTK/GHN CREATE flows).
Tier-2 review toàn bộ change set trước merge. Plan:
`/home/secomm/.claude/plans/h-y-audit-v-x-polished-pixel.md` (phase 2).

## Decision Type

Architecture (amendment address-shipping.md Rev v11 → v12)

## Decisions

1. **Secomm_Cod own COD amount DECISION** — contract
   `Secomm\Cod\Api\CodCollectionResolverInterface::resolve(Order, Shipment, ?CodCollectionPriorInterface): CodCollectionDecisionInterface`
   trả MỘT quyết định thống nhất (COLLECTIBLE|NOT_COD|REJECTED + amount + currency + reason).
   Carrier gọi contract này khi CREATE provider order và **chỉ map kết quả** (GHTK `pick_money`,
   GHN `cod_amount`) — KHÔNG carrier nào đọc `grand_total`/`base_total_due` hay tự nhận diện COD.
   Contract nhận prior-collection VO với **opaque `getProviderShipmentReference()`** — caller
   (carrier) owns same/different-shipment identity test trên persistence của mình, resolver owns
   policy → public contract KHÔNG hardcode giả định single-shipment.

2. **P1 policy** (`Model\SingleCollectionCodResolver`, swappable qua preference): COD thu
   **một lần** mỗi order, bằng **`order.grand_total` theo `order.order_currency_code`**;
   prior collection > 0 qua shipment provider khác → REJECTED `COD_ALREADY_COLLECTED`;
   order currency ≠ VND → REJECTED `CURRENCY_UNSUPPORTED` (GHTK/GHN là provider VND-only
   facts, KHÔNG convert âm thầm); partial shipment (qty-incomplete) → REJECTED
   `PARTIAL_SHIPMENT`; non-COD shipment không bao giờ bị chặn. KHÔNG deposit/partial-payment
   support (đơn COD đã trả một phần vẫn thu cả `grand_total` — merchant không dùng COD method
   cho đơn trả một phần). KHÔNG allocation framework P2.

3. **Retry freeze qua persistence có sẵn trước, anchor mới khi cần** — GHN: anchor row
   `secomm_ghn_shipment.cod_amount` (cột có sẵn) freeze amount; retry non-SUBMITTED row
   amount > 0 replay frozen (persisted-wins), amount 0 → re-resolve + `updateCodAmount`
   (guard `<> SUBMITTED`). GHTK: **anchor table mới `secomm_ghtk_shipment`** (chốt user,
   parity GHN — partner_order_code UNIQUE `ghtk-{inc}-{seq}`, cod_amount, status
   PENDING|SUBMITTED|RECOVERED|FAILED|UNKNOWN) — insertPending ngay trước POST; frozen replay
   qua `findFrozenAmount`; retry vẫn POST lại (label flow cần PDF bytes) + ORDER_ID_EXIST
   recovery giữ nguyên.

4. **Second-COD-shipment rejection** — detect qua anchor rows (GHN: `findCollectedPrior` exclude
   shipment hiện tại; GHTK: `findCollectedPrior` exclude partner code hiện tại), status
   PENDING/SUBMITTED/(RECOVERED)/UNKNOWN chặn conservative, FAILED không chặn (definitive
   rejection — không thu gì). GHTK surface = LocalizedException (native label flow abort);
   GHN surface = outcome `COD_REJECTED` riêng → observer log + shipment comment VISIBLE +
   KHÔNG tạo đơn, KHÔNG anchor row; retry CLI in reason riêng.

5. **GHN consume Secomm_Cod** — module.xml sequence += `Secomm_Cod` (đảo ngược forbidden edge
   Rev v11); builder LUÔN emit `cod_amount` (0 = non-COD — shape sandbox-verified; non-zero
   CHƯA sandbox verify — QC), guard provider cap 50,000,000 VND fail-closed
   `COD_AMOUNT_EXCEEDS_PROVIDER_LIMIT` trước mọi write; `insurance_value`/`order_value` vẫn absent.

6. **Xoá seam trùng** — `Secomm\Ghtk\Model\OrderSubmit\{CodAmountResolverInterface, DefaultCodAmountResolver}`
   + preference + test bị XOÁ (không giữ adapter: adapter không có partner-code identity để
   freeze amount và không thể diễn đạt rejection có cấu trúc). COD seam duy nhất =
   `Secomm\Cod\Api\CodCollectionResolverInterface` (override qua preference tại Secomm_Cod).

## Consequences

- Đơn COD có deposit/trả trước một phần: P1 thu CẢ `grand_total` tại cửa (đã cũ `base_total_due`)
  — merchant phải không dùng COD method cho đơn trả một phần; per-client solution sau này.
- Multi-shipment COD per order: chỉ shipment đầu được thu; các shipment sau REJECTED rõ ràng —
  merchant phải ship đủ trong 1 shipment cho đơn COD (partial bị chặn).
- `secomm_ghtk_shipment` là schema mới (additive — rollback = revert code, rows inert);
  `magento_shipment_id` luôn NULL (submit trước shipment save, không backfill hook).
- Currency ≠ VND: đơn COD không tạo được đơn provider (rejected rõ ràng) — KHÔNG có conversion
  ở P1 (precedent fail-loud: `VietQr\VndAmount`).
- Arch doc Rev v12: §4.1 collection-decision section; §8/§29 ("carrier hỏi Secomm_Cod, chỉ map");
  §21/§22 edges; §27 module map; §28 freeze; §32 GHN cod_amount max 50M + verification status.
- Task-DFGFZ9 phase 1 results (identification ownership, config path, migration) giữ nguyên.
