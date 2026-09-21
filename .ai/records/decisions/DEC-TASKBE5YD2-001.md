---
id: DEC-TASKBE5YD2-001
title: 'GHTK ORDER_ID_EXIST = RECOVERED_EXISTING sau identity validation (partner_id === order.id + label); mismatch/missing → hard conflict; không blind POST retry'
status: accepted             # user acting as SA/TL — directive 2026-09-14 (task §5/§11/§12); runtime shape NEEDS_RUNTIME_VERIFICATION (TASK-44F7V7)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-14
created: 2026-09-14
last_verified: 2026-09-14
verified_against_commit:
supersedes: []   # thay REJECTION behavior của DEC-SL016-001 era (legacy SL-016) — ghi nhận而非 supersede DEC số (DEC cũ theo legacy id, semantics đã thay bởi các DEC sau)
superseded_by:
work_items: [TASK-BE5YD2]
---

# Decision Record: GHTK ORDER_ID_EXIST recovery semantics

## Status

Accepted (2026-09-14 — user directive trong TASK-BE5YD2 request; user acting as SA/TL).
Runtime response shape = NEEDS_RUNTIME_VERIFICATION (TASK-44F7V7 staging probe) — decision này
chính thức hóa semantics, evidence runtime sẽ validate shape.

## Decision Type

Architecture (CREATE lifecycle reliability)

## Context

Trước đây: `ORDER_ID_EXIST` bị coi là rejection cứng — merchant retry sau một failure bất định
(timeout sau khi provider đã tạo order) dẫn tới kẹt: submit lại → rejection, không có đường
phục hồi provider identity. Official CREATE docs (SPIKE-A1DGPY §2) xác nhận duplicate response
chứa đủ provider identity để reconcile: `partner_id`, `ghtk_label`, `created`, `status`.

## Decision

1. `ORDER_ID_EXIST` (error_code exact-match trên envelope field `error_code`) = **candidate
   RECOVERED_EXISTING**, không còn generic rejection.
2. Validation bắt buộc trước recovery: `parsed.partner_id === submitted order.id` (deterministic
   partner key) **VÀ** có provider label identity (`ghtk_label`) — khi đó reuse identity cho
   native shipment flow (KHÔNG tạo shipment thứ hai, KHÔNG submit lại).
3. Mismatch / missing `partner_id` / missing `ghtk_label` → **hard business conflict** — fail
   closed, merchant check dashboard GHTK; không bao giờ silent-recover.
4. **Không blind retry**: CREATE vẫn single attempt (DEC-TASK7AJ3K8-001 §1). Transport technical
   failure (timeout sau khi provider có thể đã tạo order) → "uncertain" — merchant retry với
   CÙNG deterministic `order.id` sẽ đi vào recovery path an toàn. Đây chính là vai trò
   idempotency của `order.id`.
5. Message technical cho merchant ghi rõ: "retry with the same reference is safe; an existing
   order will be recovered".

## Consequences

* Reliability: uncertain failure không còn "mất" đơn GHTK phía provider — retry sạch.
* Exact runtime response shape (field positions: top-level vs order block) = NEEDS_RUNTIME_VERIFICATION
  — parser defensive cả hai vị trí; sai khác thật → hard failure fail-closed, không recover sai.
* Nếu GHTK trả duplicate mà KHÔNG có `partner_id` (variant chưa thấy trong docs): hard failure +
  runtime-verification note — không recover âm thầm.

## Supersession note

Thay thế hành vi "ORDER_ID_EXIST → rejection" của implementation SL-016 cũ (documented trong
Q-EXT/DEC-SL016-001 era). Không supersede DEC hiện hành nào khác; DEC-TASK7AJ3K8-002 (TEXT_NATIVE)
giữ nguyên.
