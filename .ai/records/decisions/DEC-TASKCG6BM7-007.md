# DEC-TASKCG6BM7-007 — Round 5: LOCAL_READY / PROVIDER_REQUEST_STARTED boundary, quoteInto một-placeholder, no-defer real-DB evidence (F23–F25)

Date: 2026-09-17. Status: accepted. Refines DEC-006 (D2 stale-claim policy, D5 real-DB defer).

## D1 — `initiating` = LOCAL_READY: cron KHÔNG BAO GIỜ query (F23)

Cửa sổ double-refund round 4: cron coi bound-INITIATING là provider-reconcilable trong khi HTTP `/refund` chưa hề gửi. Chọn: semantics của `initiating` siết thành **LOCAL_READY** — provider CHẮC CHẮN chưa được contact; cron step 0b terminate `abandoned_before_provider_io` → confirmed_fail cho MỌI initiating (bound hay không), nhả claim có chủ đích thay vì query_refund trên identity mà provider chưa từng thấy. Crash window thật duy nhất là giữa acquireClaim và mark provider-start — ở đó HTTP chưa bắt đầu nên "fail" là truth.

## D2 — State mới `provider_request_started` + `provider_request_started_at` là gate CUỐI trước HTTP (F23)

`markProviderRequestStarted()` = MỘT UPDATE có điều kiện `entity_id = ? AND active_claim = 1` pin state + timestamp UTC (không SELECT-then-UPDATE). Chọn UPDATE claim-guarded thay vì save() thường: cron stale-release trúng giữa chừng ⇒ affected 0 ⇒ plugin terminate + throw "could not be started", không bao giờ tới HTTP — race được giải bằng một câu SQL nguyên tử, không cần lock. Plugin order chốt: claim → save CM → bind → **markProviderRequestStarted** → executePrepared. Không DB transaction mở xuyên HTTP (UPDATE autocommit riêng).

## D3 — Reconciliation grace 120s: quan hệ cứng HTTP timeout < grace (F23)

Laminas HTTP client mặc định `timeout = 10` (TransferFactory không set clientConfig — verify vendor). `RECONCILIATION_GRACE_SECONDS = 120` (≥12× trần cứng). Cron step 0c với state `provider_request_started`: thiếu timestamp ⇒ `consumeQueryBudget(reconcile)` — bounded, không query, không nhả; trong grace ⇒ no-op toàn phần (request A giữ claim tới khi HTTP của A xong/timeout); hết grace ⇒ path identity-query CÙNG m_refund_id. Timestamp so UTC-thẳng-UTC (DateTime zone UTC vs Magento gmt timestamp) — không lệch timezone. Không dùng next_query_at riêng: timestamp start đủ cho quan hệ grace và ít một cột/di chuyển state.

## D4 — `quoteInto` một-placeholder-một-lời-gọi (F24)

Zend `quoteInto($text, $value)` với array KHÔNG bind tuần tự — một value được quote rồi thay vào MỌI `?`. Chọn ba mảnh: `quoteInto('is_processed = ?', 1)` + literal `' AND last_error IS NOT NULL'` + `quoteInto('?', 'refund_failed:%')`. Không đổi cohort semantics (F20 giữ nguyên) — chỉ sửa cơ chế build WHERE. Unit mock quoteInto phải trung thực Zend (int bare, string quoted) + pin số lời gọi (2) để regression detect được.

## D5 — NO-DEFER real-DB evidence trên stack disposable thu gọn (F25/F23/F24)

Chuỗi bắt buộc hoàn tất trên stack throwaway (MariaDB **10.6** — 10.11 ngoài policy của 2.4.8; config.php throwaway chỉ bật core + Secomm_ZaloPay vì CLI command list bên thứ 3 kéo Session\Config đọc default website trên DB rỗng): setup:install PASS → SHOW CREATE TABLE (4 điều kiện + cột mới) → drop-cột + xóa patch entry → setup:upgrade PASS (delta + patch re-run) → seed legacy cohorts + multi-row → verify SQL → claim proof bằng `acquireClaim` thật (bootstrap script) → F24 WHERE thật → setup:di:compile PASS. Bài học ghi nhận: DEFER evidence ở round trước tạo khoảng trống rất thật (unique index khác tên normalize, MariaDB version policy) — mọi evidence "sẽ chạy sau" phải coi là CHƯA PASS cho tới khi chạy thật.
