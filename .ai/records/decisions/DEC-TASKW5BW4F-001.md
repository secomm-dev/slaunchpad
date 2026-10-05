---
id: DEC-TASKW5BW4F-001
title: 'GHN create-failure surfacing 2 lớp: chặn pre-commit cho lỗi deterministic (INVALID_PARCEL-class), loud mọi lỗi transient (message + comment + section status); PENDING-first giữ nguyên'
status: accepted             # user decision 2026-09-30 (3 vòng AskUserQuestion) — TL gate tại review
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-30
created: 2026-09-30
last_verified: 2026-09-30
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-W5BW4F]
---

# Decision Record: GHN create-failure surfacing 2 lớp

## Status

Accepted (2026-09-30 — user decision qua 3 vòng hỏi đáp có trình bày trade-off; TL gate
Tier-2 tại code review. Bug thực tế: shipment 17 INVALID_PARCEL im lặng hoàn toàn).

## Decision Type

Architecture — thu hẹp scope "log-only" của create-failure (USER_GUIDE step 2) và bổ sung
bề mặt admin cho create state. PENDING-first (DEC-TASK9Q5ZAK-001 cluster) **giữ nguyên** cho
lỗi transient/provider.

## Decisions

1. **Lớp 1 — pre-commit block (deterministic only)**: fresh shipment save (entity_id chưa
   cấp) trên method `secomm_ghn_*` được validate qua `GhnCreateParcelValidator` (shared với
   creation service — row usability + per-package limits, single source). Deterministic fail
   (thiếu/zero rows, limit weight/side) → `GhnCreateValidationException` (LocalizedException)
   chặn save: 0 shipment row, 0 PENDING anchor, 0 snapshot rác (defuse thứ tự
   persist-before-interpret). Kênh: observer `sales_order_shipment_save_before`; re-save
   shipment hiện hữu **không bao giờ** bị chặn (comment/track/snapshot re-save phải luôn nổi).
2. **Lớp 2 — loud post-commit (mọi lỗi còn lại)**: attempt vẫn chạy post-commit trong cùng
   request (PENDING-first, idempotency `GHNS<shipment_id>` giữ nguyên). Outcome non-SUCCESS →
   admin error message (status + reason human + hint retry CLI) + durable shipment comment,
   qua `GhnCreateOutcomeNotifier` (presentation only). Observer vẫn never-throws.
3. **Bề mặt trạng thái**: section "GHN Shipment" read-only trên shipment view (provider row +
   snapshot packages qua `ShipmentPhysicalPersister::read()`); core modal "Show Packages" giữ
   hành vi BUG-74VGQX (marker không render vào core template).
4. **Alternatives bị loại** (đã trình bày với user): (a) full sync pre-commit create — đòi đổi
   idempotency key sang order-derived + `magento_shipment_id` nullable + schema migration +
   GHN outage khoá toàn bộ tạo shipment; (b) post-commit + auto-hủy shipment — đụng
   state-machine order/shipment, mất history/increment. Lý do chọn 2 lớp: đúng "không bao giờ
   silent" mà không đụng idempotency/schema, không khoá ops khi GHN outage.
5. **CANONICAL_UNRESOLVED (address)** không chặn pre-commit trong slice này — thuộc E-B/E-C
   (validation đòi invoke resolution pipeline lúc save); hiện rơi vào lớp 2 (loud).
6. **Retry UX**: giữ CLI (`secomm:ghn:shipment:retry`), section hiển thị hint; nút Retry
   admin = follow-up riêng (controller + ACL + CSRF).

## Consequences

- Dữ liệu deterministic-invalid không bao giờ sinh shipment/"zombie" FAILED row → không còn
  retry-vô-vịch replay snapshot lỗi.
- Save REST/API tạo shipment GHN thiếu packages giờ bị chặn tại save (fail-loud) — tích hợp
  programmatic cần gửi packages; đây là thay đổi hành vi có chủ đích.
- Snapshot post-creation vẫn không có UI sửa: shipment tồn tại với snapshot lỗi (legacy) chỉ
  được loud, không tự-heal — dọn data là việc tay dev db.
- `GhnShipmentCreationService` constructor đổi (validator vào, converter ra) — 2 test site
  cập nhật; suite Ghn giữ green.

## Work Items

- TASK-W5BW4F (implementation, FEAT-FQWEQ3 / GHN-D cluster).
