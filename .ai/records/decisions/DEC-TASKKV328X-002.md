---
id: DEC-TASKKV328X-002
legacy_ids: [DEC-024]
title: GHTK order-sync business rules (COD source field, declared value, is_freeship, partial shipment, partner order ID, cancel boundary, sync trigger, label persistence) — bundle of pending-approval business decisions
status: proposed
owners: [sa, tl, pm]
decision_type: contract
approval_date:
created: 2026-07-30
last_verified: 2026-07-30
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-KV328X, FEAT-AE761Z]
---

# Decision Record: GHTK order-sync business rules

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). PROPOSED — partial (business rules still pending approval). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- These are NOT yet decided — recorded as pending so TASK-KV328X is not silently closed. Do NOT treat as accepted. -->
<!-- 2026-07-30 partial resolution: pick_money/is_freeship (B2/B3) → DEFERRED; cancel API (B9) → OUT OF PHASE (open). Rest (B1/B4/B6/B7/B8/B10–B14) still pending → TASK-KV328X stays `proposed`. -->
<!-- 2026-07-30 PARKED: order sync (TASK-KV328X) deferred (fee-first) → DEC-TASKKV328X-002 not blocking fee delivery. -->
<!-- 2026-07-30 COD dependency (user): COD payment = BACKLOG (đang giai đoạn BA). pick_money (B2) + COD amount (B1) chỉ quyết định được SAU khi BA chốt COD workflow (order status flow + COD tiền mặt vs COD chuyển khoản cho shop). → B1/B2 blocked on external COD BA outcome, KHÔNG phải pending-approval thuần. -->
<!-- GHTK order API contract fields below are from VN doc; actual field contract needs external verification (see Open Questions). -->

## Context

TASK-KV328X (order sync) chưa đủ ready vì nhiều business rule chưa khóa. GHTK order API (`POST /services/shipment/order`) cần các giá trị cụ thể mà mapping từ Magento chưa xác định. Ghi lại thành một bundle decision với mỗi điểm ở trạng thái **pending approval** — không tự suy đoán rồi ghi như đã approved.

> **COD dependency (user 2026-07-30):** payment method **COD đang ở BACKLOG (giai đoạn BA)**. `pick_money` (B2) và COD amount (B1) **chỉ quyết định được sau khi BA chốt COD workflow** — bao gồm **order status flow** và lựa chọn **COD tiền mặt vs COD chuyển khoản cho chủ shop** (cash-on-delivery cho shipper vs transfer-to-shop). → B1/B2 **blocked trên outcome của COD BA**, không phải pending-approval thuần.pick_money = (cod cash) ? cod_amount : (cod transfer) ? 0|transfer_ref : 0 — concrete value phụ thuộc lựa chọn COD mode mà BA chưa chốt.

Tier-2 (order management + payment-interaction via COD + external contract — §12) → Level-2/3 decision, SA/TL + business.

## Decision (proposed — each item pending approval)

Mỗi dòng dưới là một **open business/contract decision**. Đề xuất mặc định (default) chỉ để có điểm khởi đầu; **không chốt** cho đến khi SA/TL + business approve.

| # | Point | Default đề xuất | Owner | Status |
|---|---|---|---|---|
| B1 | COD amount lấy từ Magento field nào | `base_cod_amount` / payment method == COD → order `grand_total` (else 0) | SA/TL + payment + **COD BA** | **blocked** — phụ thuộc COD workflow (BA chưa chốt: order status + COD mode) |
| B2 | `pick_money` (tiền thu hộ) gửi GHTK | COD tiền mặt → `pick_money = cod_amount`; COD chuyển khoản cho shop → `pick_money = 0` (hoặc transfer ref); prepaid → `0` | SA/TL + **COD BA** | **blocked** — phụ thuộc COD mode (tiền mặt vs chuyển khoản) mà BA chốt |
| B3 | `is_freeship` mapping | `is_freeship = 1` khi shipping amount = 0 (freeship rule/discount) else 0 | SA/TL + business | **deferred** — implement sau nếu khách yêu cầu (Q-EXT) |
| B4 | Declared value (khai giá) source | subtotal (đề xuất) vs row total vs insured amount config | business | pending |
| B5 | street/hamlet lấy từ address field nào | `street[0]` (+ `street[1]` → hamlet) | SA/TL | pending |
| B6 | Partial shipment | mỗi Magento shipment = 1 GHTK order (đề xuất); vs gộp | business | pending |
| B7 | Một Magento shipment hỗ trợ nhiều package | KHÔNG (1 shipment = 1 package) release đầu | SA/TL | pending |
| B8 | Partner order ID format | deterministic: `ghtk-{magento_order_increment}-{shipment_id}` (stable qua retry) | SA/TL | pending |
| B9 | Cancel shipment → gọi GHTK cancel API | có (nếu GHTK chưa pick) | business | **out of phase** — cancel API chưa xử lý giai đoạn này (để ngỏ) |
| B10 | Label/tracking persistence | persist `ghtk_label` + tracking vào `secomm_ghtk_shipment` + Magento shipment track | SA/TL | pending |
| B11 | Manual retry | admin action retry (tái submit với cùng partner_order_id) — reuse DEC-TASKKV328X-001 outbox | SA/TL | pending |
| B12 | Order edit/recreate behavior | KHÔNG tự recreate; manual retry/reconcile only | SA/TL | pending |
| B13 | Return/RMA | **OUT of scope** GHTK release-1 | business | pending |
| B14 | Sync timing trigger | khi shipment created (outbox write) — KHÔNG đợi invoice paid | SA/TL | pending |

> **External verification needed:** giá trị field COD/`pick_money`/`is_freeship`/`order_fee`/label của GHTK order API cần verify từ doc/API thực tế (VN hiện hành) — không bịa field name hoặc behavior (Open Question Q-EXT).

## Alternatives

- **Tự chọn ngầm mỗi rule để "đóng" TASK-KV328X** — rejected: sai (user yêu cầu tạo pending decision, không tự chốt); rủi ro tài chính/operational nếu đoán sai COD/declared value.
- **Hoãn toàn bộ đến khi implement** — rejected: requirement không chặt → rework ở rate/order sync.

## Consequences

- (+) Mỗi business rule có owner + trạng thái rõ; không che gap bằng assumption.
- (+) B2/B3 (pick_money/is_freeship) deferred + B9 (cancel) out-of-phase → scope release-1 rõ hơn.
- (+) Trace: khi approve từng dòng còn lại, flip status + approval_date.
- (−) **TASK-KV328X PARKED (fee-first)**; khi resume: B1/B2 (COD/pick_money) **blocked trên COD BA outcome** (order status + tiền mặt vs chuyển khoản); B4 (declared value) / B8 (partner order ID) / B14 (sync trigger) pending approval; B3/B9 defer/out-of-phase.
- Follow-up: track COD BA workflow (backlog) → khi chốt COD mode + order status, quay lại B1/B2; GHTK order API field contract external verify (COD/declared value/label) trước khi hiện thực mapper.

## Open Questions

- ~~**Q-EXT (pick_money):** implement khi nào?~~ **RESOLVED 2026-07-30 (blocked on COD BA):** `pick_money` (B2) **phụ thuộc COD workflow** (BA backlog — order status + COD tiền mặt vs chuyển khoản cho shop). Release-1 (fee-first, không COD) → `pick_money=0` default. Concrete `pick_money` chỉ chốt sau COD BA.
- ~~**Q-EXT (is_freeship):**~~ **deferred** — implement sau nếu khách yêu cầu; release-1 `is_freeship=0` default.
- **Q-EXT (external verify — still open):** tên + type các field order API thực tế (COD, declared value, label return) từ GHTK doc VN / sandbox.
- ~~**Q-B9 (cancel boundary):** cancel API trong core hay limitation?~~ **RESOLVED 2026-07-30 (out of phase):** cancel API **chưa xử lý giai đoạn này** (để ngỏ) → known-limitation release-1.
- ~~**COD/pick_money tension (FLAG):**~~ **RESOLVED 2026-07-30:** COD = backlog/BA (KHÔNG trong release-1 fee-first) → release-1 **prepaid-only** (`pick_money=0` luôn); tension dissolve. B1/B2 concrete value blocked trên COD BA outcome (cash vs transfer + order status).

## Affected components

- `CMP-GHTK` — `Secomm_Ghtk` (order request builder, mapper).
- Sub-tickets: TASK-KV328X.

## Related records

- Features: [FEAT-AE761Z](../features/FEAT-AE761Z.md)
- Decisions: [DEC-TASKKV328X-001](DEC-TASKKV328X-001.md) (async/idempotency architecture this bundle rides on) · [DEC-TASKBRKHN4-001](DEC-TASKBRKHN4-001.md) · [DEC-TASKBRKHN4-002](DEC-TASKBRKHN4-002.md)
- DECISIONS.md index: DEC-TASKKV328X-002
