---
id: DEC-FEATCSWYEJ-004
legacy_ids: []
title: 'Payment Core D3 rev — expired + order vẫn pending = CHƯA thanh toán → cancel luôn, KHÔNG verify querydr trước cancel nữa; order state là nguồn chân lý payment'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-25
created: 2026-08-25
last_verified: 2026-08-25
verified_against_commit: 140a83e8
supersedes: []   # refines D3 của DEC-FEATCSWYEJ-001; DEC-001 không bị thay thế toàn bộ
superseded_by:
work_items: [FEAT-CSWYEJ]
---

# Decision Record: Payment Core — D3 revision (bỏ querydr pre-cancel verify)

## Context

QC sandbox 2026-08-25: querydr trả HTTP 500 (hash contract khác — đã fix) nhưng user TL quyết định đơn giản hoá semantics: cửa sổ expiry giờ = TTL provider session (DEC-003, mặc định 15'), rất ngắn. Nếu hết 15' mà order vẫn pending thì coi như KHÔNG thanh toán — cancel. Không cần hỏi lại provider.

## Decision

| # | Câu hỏi | Quyết định | Lý do / Alternatives |
|---|---|---|---|
| D3-rev | Verify provider (querydr) trước cancel? | **BỎ**. `expired + state pending (new) + canCancel()` → cancel qua lifecycle. Race guard còn 2 lớp: per-order lock + state reload. | (b) Giữ querydr 3 lớp — bảo vệ case IPN delay/mất trong khi khách đã trả. User TL chấp nhận trade-off này vì window 15' ngắn (IPN VNPAY thường về trong vài giây). |

## Consequences

- Cron đơn giản hơn, không phụ thuộc endpoint querydr hay credential API thêm — `querydr_url` config **không còn được đọc bởi cron** (giữ field + adapter method cho mục đích debug/tương lai).
- **Rủi ro chấp nhận**: khách trả tiền ở VNPAY đúng lúc cuối window, IPN delayed >window → cron cancel nhầm đơn đã trả. Cửa sổ hẹp (15') + IPN VNPAY nhanh làm xác suất rất thấp; nếu xảy ra, ops re-open order thủ công (Magento hỗ trợ Reorder/uncancel qua admin). Cần thông báo cho team ops về flow xử lý này.
- `retry_count` giờ gần như luôn 0 (chỉ tăng khi cancel throw) — field giữ nguyên cho tương lai.
- Unit test matrix cập nhật (bỏ provider-verify cases).

## Affected components

`Model/Lifecycle/CancelExpiredOrder.php` (bỏ AdapterPool + VerifyResult, đổi tên method nội bộ cancelIfStillUnpaid → cancelIfStillPending) · Test/Unit/.../CancelExpiredOrderTest.php.

## Related records

- Refines: DEC-FEATCSWYEJ-001 (D3), DEC-FEATCSWYEJ-003
- Feature: FEAT-CSWYEJ
