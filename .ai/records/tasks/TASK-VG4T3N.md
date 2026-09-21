---
id: TASK-VG4T3N
type: task
title: Add VNPAY Online Refund Via Credit Memo
project_code: SLP
parent:
external_refs: {}
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-17
updated: 2026-09-17
ticket_ref:
affects_version: Magento 2.4.8-p5
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/VNPAY
source_areas:
  - payment
changes_project_state: true
changes_architecture: false
changes_integration: true
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-17
supersedes: []
---

# [SLP][TASK-VG4T3N] Add VNPAY Online Refund Via Credit Memo

<!-- Mode B — refund online qua Refund API chuẩn VNPAY (techspec 2.1.0 §2.5.5, đã verify bằng Postman 2026-09-17). Payment-adjacent → TL review bắt buộc trước merge. Spec VALID 2026-09-17 — user acting as SA/TL in chat ("dev làm cho tôi đi" sau khi duyệt solution + test Postman thành công). -->

## Summary

Thêm **refund online** cho phương thức `vnpay` qua VNPAY Refund API (`vnp_Command = refund`, endpoint `merchant_webapi/api/transaction`, `vnp_TransactionType 02` toàn phần / `03` một phần), tích hợp vào luồng Credit Memo chuẩn của Magento (nút **Refund** online). Refund Offline (hoàn tay) vẫn giữ làm fallback.

## Mini Spec

### Goal

Admin tạo Credit Memo (toàn phần/một phần) → Magento gọi VNPAY Refund API → tiền hoàn về tài khoản khách qua VNPAY.

### Expected Behavior

- Credit memo toàn phần → `vnp_TransactionType 02`, amount = toàn bộ đã trả.
- Credit memo một phần → `03`, amount = phần hoàn.
- VNPAY từ chối (95/91/97...) → credit memo fail, admin thấy message lỗi.
- `00`/`94` → refund được ghi nhận (94 = đã gửi trước đó đang xử lý).

### Constraints / Rules

- Checksum refund: join 13 trường bằng `|` (thứ tự spec: RequestId|Version|Command|TmnCode|TransactionType|TxnRef|Amount|TransactionNo|TransactionDate|CreateBy|CreateDate|IpAddr|OrderInfo) + HMAC-SHA512 — **khác algorithm payment**.
- Thời gian GMT+7 (`Asia/Ho_Chi_Minh`) — bài học code=15.
- Refund là ASYNC: `00`/`94` chỉ là "ghi nhận" — kết quả cuối theo QueryDR/portal (`TransactionStatus` 05→06 hoàn xong, 09 từ chối) — Phase 2.
- Điều kiện nghiệp vụ VNPAY: chỉ refund GD thành công; TmnCode phải được VNPAY bật refund; IP server whitelist.

### Out of Scope

- QueryDR đối soát tự động (Phase 2 — optional).
- Refund offline flow (giữ nguyên của core).
- Auto-refund khi cancel đơn.

### Acceptance Criteria

- AC-001: Ipn/Pay confirm thành công → order payment lưu `vnp_transaction_no` + `vnp_pay_date`.
- AC-002: Credit Memo toàn phần → VNPAY nhận request type `02`, response `00` → credit memo tạo OK.
- AC-003: Credit memo một phần → type `03`.
- AC-004: VNPAY từ chối (95/91...) → credit memo fail + message lỗi hiển thị.
- AC-005: Config `api_url` (default sandbox endpoint) đọc đúng.
- AC-006: Regression — pay flow, các method khác không đổi.

## Implementation

| File | Change |
|------|--------|
| `Controller/Order/Ipn.php` | Capture `vnp_transaction_no` + `vnp_pay_date` vào order payment lúc confirm; invoice **setTransactionId** (bắt buộc cho nút Refund online) |
| `Controller/Order/Pay.php` | Như trên (return path) + **tạo invoice** cho order (đơn confirm qua return trước đó không có invoice → không refund online được) |
| `Model/VnpayRefundService.php` | **new** — build request + checksum `|` HMAC-SHA512 + POST JSON (`Curl` client) + parse response (`00`/`94` OK; còn lại throw) + log |
| `Model/Vnpay.php` | + `_canRefund`/`_canRefundInvoicePartial` + `refund()` gọi service + constructor pass-through `AbstractMethod` args |
| `etc/adminhtml/system.xml` + `etc/config.xml` | + `api_url` (default sandbox `merchant_webapi/api/transaction`) + `version` (default `2.1.0`) — vnp_Version đọc từ config, không hardcode ở Info/Refund |
| `i18n/en_US.csv`, `vi_VN.csv` | +2 message refund |
| `etc/db_schema.xml` + `etc/db_schema_whitelist.json` | **new** — bảng `vn_pay_refund`: order_id, increment_id, txn_ref, transaction_no, pay_date, refund_type (02/03), amount, request_id, response_code, vnp_transaction_status (05/06/09), timestamps; index order_id + txn_ref (không có creditmemo_id — tại thời điểm insert creditmemo chưa persist, link qua order_id) |
| `Model/VnpayRefund.php` + `Model/ResourceModel/VnpayRefund.php` | **new** — model/resource ghi nhận refund request (mirror `zalo_pay_refund` convention) |
| `Model/ResourceModel/VnpayRefund/Collection.php` | **new** — collection cho cron chọn các row pending |
| `Model/VnpayQueryService.php` | **new** — QueryDR client (checksum 9 trường, GMT+7, IPv4 + User-Agent) |
| `Cron/RefundStatusSync.php` + `etc/crontab.xml` | **new** — cron 15 phút/lần: sync `vnp_TransactionStatus` qua QueryDR; final (06/09) → comment vào order; row quá 3 ngày chưa final → log critical nhắc check portal |

Side-effect analysis: flags refund chỉ trên method `vnpay`; service chỉ gọi từ `refund()` của method này — Mollie/COD/method khác không đụng.

## Phase 2 — QueryDR status tracking (IMPLEMENTED 2026-09-17)

Cron `secomm_vnpay_refund_status_sync` chạy 15 phút/lần: chọn row pending (`response_code 00/94`, chưa final) → QueryDR → cập nhật `vnp_transaction_status`; khi `06` → comment order; khi `09` → log critical + comment cảnh báo. Row quá 3 ngày chưa final → log critical nhắc check portal (chống poll vô hạn).

## Known limitations

1. Refund ASYNC — response `00` chưa chắc tiền đã về (theo dõi qua portal VNPAY / Phase 2 QueryDR).
2. Refund amount tính trên **base currency** — store đa currency cần chú ý (giống limitation amount-check của BUG-4WYXCB).
3. `vnp_CreateBy` hardcode `admin` (chưa lấy username admin đang login).
4. `creditmemo_id` trong row `vn_pay_refund` đang null tại thời điểm gọi (creditmemo persist sau refund) — backfill sau nếu cần.
5. Sandbox test refund cần VNPAY bật cho TmnCode + IP whitelist (đã work bằng Postman 2026-09-17).

## Test Plan (QC)

| # | Scenario | Kỳ vọng |
|---|----------|---------|
| T1 | Credit memo toàn phần trên đơn VNPAY đã paid | VNPAY nhận type 02, response 00, credit memo OK |
| T2 | Credit memo một phần | Type 03, amount đúng |
| T3 | Refund đơn chưa thành công | Bị chặn (95) + message lỗi |
| T4 | Refund 2 lần liên tiếp | Lần 2 nhận 94 (đang xử lý) |
| T5 | Regression pay flow + method khác | Không đổi |

## Breakdown & Estimates (cho TL review — 2026-09-17)

**Solution tóm tắt:** Credit Memo (toàn phần/một phần) → `Payment::refund()` → `VnpayRefundService` → VNPAY Refund API (`vnp_Command=refund`, type 02/03, checksum HMAC `|`, GMT+7) → response `00`/`94` = ghi nhận → credit memo hoàn tất. Transaction data (`vnp_TransactionNo`, `vnp_PayDate`) được capture vào order payment lúc confirm (Ipn/Pay). Bảng `vn_pay_refund` track từng refund request (async status + chống trùng). Refund Offline giữ làm fallback.

| # | Task | Trạng thái | Est |
|---|------|-----------|-----|
| 1 | Capture `vnp_transaction_no` + `vnp_pay_date` lúc confirm (Ipn/Pay) | ✅ Done | 0.5d (đã tiêu) |
| 2 | Refund API client (checksum `\|` HMAC-SHA512, POST JSON, response map) — **đã verify thật: ResponseCode 00 "Refund success" qua admin flow** | ✅ Done | 1d (đã tiêu) |
| 3 | Payment model `_canRefund`/`_canRefundInvoicePartial` + `refund()` + Credit Memo admin integration | ✅ Done | 0.5d (đã tiêu) |
| 4 | Bảng `vn_pay_refund` + model/resource + row tracking | ✅ Code done — **1 bug mở**: row bị rollback/không visible dù `getId()` trả id (3 nghi phạm: DB client snapshot cũ / rollback do exception sau save / query nhầm DB — diagnostic steps đã định nghĩa trong chat 2026-09-17) | 0.5d (đã tiêu) + 0.5d fix & verify |
| 5 | Config `api_url` + `version` + i18n | ✅ Done | 0.25d (đã tiêu) |
| 6 | QC sandbox: T1 full refund, T2 partial, T3 refund đơn fail (95), T4 double refund (94), T5 regression | ⏳ Pending | 1d |
| 7 | TL review + commit | ⏳ Pending | — |
| 8 | *(Phase 2 — optional)* QueryDR status tracking (cập nhật `vnp_transaction_status` qua cron/button) | Chưa làm | 1d |

**Tổng Phase 1 còn lại: ~1.5 dev-day** (0.5 fix bug row + 1 QC). Phase 2: +1d (optional).

**Dependencies / cần xác nhận (ngoài code):**
- VNPAY bật refund cho TmnCode sandbox — ✅ (verify 2026-09-17)
- IP whitelist production server — ⏳ ops (chưa whitelist sẽ 403 trên prod)
- Endpoint + chính sách thời hạn refund production — ⏳ xác nhận với VNPAY theo hợp đồng merchant

**Risks cho TL:**
- Refund async — `00` chưa chắc tiền về (ATM nội địa 1–3 ngày) — SOP theo dõi portal/QueryDR
- Base currency assumption (giống BUG-4WYXCB limitation #1)
- `vnp_CreateBy` hardcode `admin`

## Verification

- Fixed confirmed: ⏳ chờ QC (Postman refund API đã verify work 2026-09-17)
- Regression checked: ⏳ T5
