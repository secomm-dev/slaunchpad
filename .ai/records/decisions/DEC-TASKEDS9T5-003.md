---
id: DEC-TASKEDS9T5-003
title: 'ZaloPay corrective 3: Return checksum chỉ là tamper evidence (v2/query luôn chạy); callback trả đúng contract chính thức {return_code, return_message}; amount thiếu/0 bắt buộc v2/query exact; quarantine cấu trúc requires_reconciliation; evidence contract-mismatch qua lifecycle re-lock; cron recovery bound theo hướng dẫn chính thức ZaloPay'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit: da510930b13aab5aa09d13977307b35faf7ff819
supersedes: []
superseded_by:
work_items: [TASK-EDS9T5]
---

# Decision: Corrective round 3 — provider-contract + amount + quarantine + recovery (TASK-EDS9T5)

## Bối cảnh

TL review tại HEAD `da510930` (round 2) kết luận 6 BLOCKER:
1. Return checksum mismatch **throw TRƯỚC** `v2/query` ⇒ query không bao giờ chạy khi checksum
   xấu/mất — lại tái tạo kiểu "browser cản quyết định payment" mà round 2 đã xoá.
2. `OrderFinalizer::recordContractMismatch($lockedObject)` gọi `repository->save($lockedObject)`
   sau rollback ⇒ persist stale copy pre-rollback (có thể đè FINALIZED mới, xoá order_id).
3. IPN trả `{errors, messages}` + HTTP 404/500 — KHÔNG phải contract ZaloPay
   (`{return_code, return_message}` HTTP 200).
4. `if ($paidAmount !== 0 && $paidAmount !== snapshot)` cho amount=0/thiếu rơi qua ⇒ PAID + order
   KHÔNG hề verify amount.
5. Amount mismatch ⇒ PAID + last_error free-text; callback hợp lệ đến sau có thể auto-finalize.
   Conflicting `zp_trans_id` (cùng app_trans_id, khác id authoritative) cũng thế.
6. Không có cron recovery cho callback mất — tiền thật không bao giờ thành order khi khách đóng
   browser trước Return và IPN thất bại (ZaloPay retry chỉ 3 lần).

## Quyết định

1. **Return checksum = tamper evidence CHỈ**: bad/missing browser checksum KHÔNG được chặn
   `v2/query` và KHÔNG mutate state. Cơ sở: query là server-to-server, ký key1, input duy nhất là
   `app_trans_id` — tham số browser (cả checksum) không thể ảnh hưởng kết quả; chặn query chỉ tạo
   lại blocker round-1 (đã trả tiền ⇒ không order). ZaloPay KB chính thức cũng hướng dẫn
   proactive QueryOrder như cơ chế đối soát. (ReturnProcessor)
2. **Contract-mismatch evidence chuyển về lifecycle**: `PaymentAttemptLifecycle::
   recordContractMismatch(appTransId, reason)` — short tx → FOR UPDATE → inspect FRESH state →
   append evidence không bao giờ regress FINALIZED / xoá order_id / xoá provider_transaction_id →
   save fresh. Finalizer KHÔNG bao giờ save attempt copy của nó sau khi release lock. Row
   FINALIZED nhận evidence-only (KHÔNG quarantine — không đầu độc order đã bound); row khác nhận
   quarantine `contract_mismatch`.
3. **IPN đúng contract chính thức**: `IpnProcessor` trả DOMAIN OUTCOME (`SUCCESS` /
   `ACK_RECONCILIATION` / `INVALID_CALLBACK` / `RETRYABLE_FAILURE`); controller serialize EXACT
   HTTP 200 `{return_code, return_message}` — 1 "Success", 2 "Invalid", 0 "Temporary failure,
   please retry." (official sample: callback again ≤ 3 lần). POST-only (xoá
   HttpGetActionInterface; contract chính thức là POST). Luôn HTTP 200 — protocol nằm trong body.
4. **Amount là bắt buộc cho tiền (Blocker 4)**: callback thiếu/amount=0 KHÔNG BAO GIỜ nghĩa là
   "continue anyway" — fallback AUTHORITATIVE `v2/query` yêu cầu return_code 1 AND amount EXACT
   snapshot trước PAID/finalize; query 3 ⇒ retryable; query ≠1 ⇒ authoritative failure; query
   paid không amount/amount khác ⇒ quarantine.
5. **Quarantine cấu trúc (Blocker 5)**: cột mới `requires_reconciliation` (bool) +
   `reconciliation_code` (machine-readable: `amount_mismatch` | `contract_mismatch` |
   `provider_transaction_conflict` | `amount_unavailable`) — KHÔNG parse last_error. Mismatch ⇒
   PAID money-real + quarantine + evidence exact, KHÔNG tự đặt hàng. Conflicting zp_trans_id ⇒
   giữ identity ĐẦU TIÊN + quarantine — không bao giờ auto-finalize. Finalizer thêm gate: row
   quarantined bị từ chối bởi MỌI caller generic; chỉ workflow reconcile thủ công được clear.
   Duplicate zp_trans_id đúng id ⇒ idempotent.
6. **Recovery worker bound nhỏ nhất (Blocker 6)**: cron `*/5` chạy `Service/PaymentRecovery` —
   selection deterministic bounded (non-terminal, chưa bound, chưa quarantine, quá 15 phút cửa sổ
   callback — đúng guidance chính thức, ORDER BY entity_id LIMIT batch) → atomic conditional
   UPDATE claim (tăng `recovery_attempts` TRƯỚC khi HTTP; thua race = skip) → `v2/query` NGOÀI tx
   → SUCCESS+exact amount ⇒ lifecycle recordVerifiedPaid → OrderFinalizer (cùng business services
   với IPN/Return — không có path đặt hàng thứ hai); 3 ⇒ để lần sau; ≠1 ⇒ recordVerifiedFailure;
   amount lệch ⇒ quarantine. Bounds: `recovery_window` (15 phút), `recovery_batch_size` (25),
   `recovery_max_attempts` (5). KHÔNG giữ row lock qua HTTP (rule bắt buộc).

## Hệ quả

- Cột mới `requires_reconciliation`, `reconciliation_code`, `recovery_attempts` +
  whitelist + setup_version 1.2.0 (db_schema 1.1.x → 1.2.0).
- Callback response thay đổi quan sát được từ phía ZaloPay (từ `{errors,messages}`/404/500 sang
  `{return_code, return_message}`/200) — ghi CHANGELOG 1.2.0.
- Quarantined attempts là nguyên liệu reconcile thủ công: full evidence, không tự hủy trạng thái
  nào, không bao giờ thành order tự động.

## Bằng chứng provider contract (đã tra cứu chính thức, ghi trong `.ai/evidence/TASK-EDS9T5/provider-contract-round3.md`)

- Callback API: POST, body `{data, mac, type}`, `data` JSON string chứa `amount` (long, VND,
  "Amount received") + `zp_trans_id`; response HTTP 200 `{return_code, return_message}`.
- Order status (v2/query): return_code 1/2/3; `amount` "only available when the payment is
  successful"; `zp_trans_id` int64.
- KB Callback: retry ≤ 3 lần; sau 15 phút không nhận callback ⇒ merchant proactive QueryOrder.

## Validation

ZaloPay unit 157/157 (+45 so với round 2); full suite 945/7 (7 lỗi pre-existing Tracking —
giống base); PHPCS severity 10: 0 errors; php -l clean; `setup:di:compile` OK (equal-condition —
full compile fail pre-existing do vendor workspace thiếu `hybridauth` cho Mageplaza SocialLogin,
đÃ CHỨNG MINH tái lập tại da510930 với cùng vendor mount). Integration: ENVIRONMENT-BLOCKED.
Chi tiết: `.ai/evidence/TASK-EDS9T5/command-output.txt` § round-3.
