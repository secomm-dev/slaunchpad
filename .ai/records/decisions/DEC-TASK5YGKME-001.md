---
id: DEC-TASK5YGKME-001
title: 'PICK_PRIMARY auto-first: không có curated primary (is_primary=1) thì selector tự chọn candidate đầu theo code sort (deterministic) thay vì fail-closed NO_DESIGNATED_PRIMARY'
status: accepted             # user directive 2026-09-25 ("hệ thống tự chọn 1 cái, lấy top cho nhanh") + plan approval
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-25
created: 2026-09-25
last_verified: 2026-09-25
verified_against_commit:
supersedes: [DEC-FEATYA2C0W-006]
superseded_by:
work_items: [TASK-5YGKME]
---

# Decision Record: PICK_PRIMARY auto-first (bỏ fail-closed NO_DESIGNATED_PRIMARY)

## Status

Accepted (2026-09-25 — user directive: intent gốc của "primary" là top của list; hệ thống
tự chọn 1 cái "lấy top cho nhanh"). Plan approval cùng ngày.

## Decision Type

Architecture — amendment của resolution policy semantics trong ShippingCore v10 runtime
(freeze v10 được amend CÓ CHỦ ĐÍCH tại đúng điểm này; mọi freeze guarantee khác giữ nguyên).

## Decisions

1. **Auto-first fallback trong `VnPrimaryCandidateSelector`** — khi candidate set AMBIGUOUS
   (≥2) và KHÔNG có curated primary (`is_primary=1` theo hướng resolution) → trả
   `STATUS_SELECTED` với candidate **đầu theo thứ tự `sort($codes, SORT_STRING)`**
   (deterministic, independent của input order). Thay vì `STATUS_NO_DESIGNATED_PRIMARY`
   fail-closed.
2. **Curated primary vẫn thắng** — có đúng 1 edge `is_primary=1` → chọn primary như cũ.
   Việc populate `is_primary` sau này không cần đổi code.
3. **MULTIPLE_PRIMARY giữ fail-closed** — ≥2 curated primary là data-integrity defect,
   không tie-break, phải sửa tay.
4. **NOT_APPLICABLE (<2 candidates) không đổi.**
5. **`STATUS_NO_DESIGNATED_PRIMARY` constant giữ cho BC** — default implementation không
   còn trả về nó; handoff service vẫn handle defensively (non-SELECTED → fail-closed) cho
   mọi implementation khác của selector interface.

## Supersede

`DEC-FEATYA2C0W-006` — amendment "never a first-candidate guess" (dòng 111:
"`$candidates[0]`/first-sorted/db-row vẫn FORBIDDEN") **bị supercede đúng tại điểm này**.
Các phần còn lại của DEC-006 giữ nguyên hiệu lực.

## Rationale

- Directive user 2026-09-25: tự chọn top nhanh hơn — chấp nhận fee xấp xỉ cho ward merger.
- Dữ liệu thực: `is_primary=1` = 0 dòng; gần như mọi ward 2025 là merger multi-edge
  (9.654 MERGED_INTO) → fail-closed hiện tại = GHN không bao giờ price trên checkout thực
  (93+ CANONICAL_UNRESOLVED trong secomm_ghn.log) → checkout luôn fallback.
- Deterministic (code sort) → stable pricing per address, không phụ thuộc input order.

## Consequences

- Ward merger trải trên N vùng giao GHN cũ: fee tính theo đúng 1 vùng (top-sorted code) —
  có thể lệch với vùng thực của một số khách. Refine path: populate `is_primary=1`
  (curated tự thắng, không đổi code).
- Shared seam: GHTK (pipeline E) hưởng cùng behavior + cùng trade-off.
- Tests: selector test đổi expectation 0-curated → SELECTED-top + determinism; handoff/
  execution-flow tests giữ nguyên (mock-based defensive contracts).

## Verification

- VietNamAddress + ShippingCore + Ghn + Ghtk unit suites green sau change.
- REST E2E: Đa Phúc / Bát Tràng (multi-edge) dưới PICK_PRIMARY → fee call thay vì
  CANONICAL_UNRESOLVED; Bát Mọt (1:1) regression.
