# DEC-TASKCG6BM7-008 — Round 6: LOCAL_READY grace/staleness trên `created_at`, grace 300s riêng biệt (F26)

Date: 2026-09-17. Status: accepted. Refines DEC-007 (D1: cron 0b abandon mọi initiating).

## D1 — Fresh LOCAL_READY là no-op hoàn toàn; chỉ stale mới nhả (F26)

Step 0b round 5 terminate mọi `initiating` ngay cron đầu tiên — giết được request A HỢP LỆ đang trong pha bind cục bộ (`acquireClaim` → save CM → bind → mark provider-start; thuần DB cục bộ, không network, hoàn tất < 1s) vì cron 15 phút/lần dễ rơi vào giữa cửa sổ đó. Chọn: fresh (tuổi < `LOCAL_READY_GRACE_SECONDS = 300`) ⇒ cron KHÔNG LÀM GÌ cả (không query, không terminate, không release); stale (tuổi ≥ 300) ⇒ owner chắc chắn đã crash trước provider-start ⇒ provider I/O bất khả thi theo cấu trúc ⇒ `confirmed_fail` + nhả claim (truth giữ nguyên của DEC-007, chỉ dịch mốc thời gian). `created_at` thiếu/trống ⇒ defensive `consumeQueryBudget(reconcile)` — bounded, không query, KHÔNG BAO GIỜ nhả claim sống chỉ vì data glitch.

## D2 — Tái dùng `created_at` làm anchor bền; KHÔNG thêm cột

`created_at`: timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `on_update` tắt — set đúng lúc INSERT claim, không bao giờ tự đổi; row không thể rời `initiating` rồi quay lại (mọi writer state đều một chiều; `acquireClaim` chỉ INSERT row mới) ⇒ tuổi `created_at` ≡ tuổi LOCAL_READY — ngữ nghĩa đủ. Magento init statement ép session UTC ⇒ parse `(new \DateTime($value, new \DateTimeZone('UTC')))->format('U')` so với `$dateTime->timestamp()` (pattern 0c round 5) — không lệch timezone. Không in-memory time, không `time()`. ⇒ **SCHEMA_CHANGED=NO**: không đụng db_schema.xml/whitelist/patch ⇒ không bắt buộc setup:upgrade lại; bằng chứng round-5 (P19) còn hiệu lực.

## D3 — `LOCAL_READY_GRACE_SECONDS = 300` ≠ `RECONCILIATION_GRACE_SECONDS = 120` (không gộp nghĩa)

Hai grace hai thế giới: reconciliation (120s) bound HTTP in-flight của A — trần cứng Laminas timeout 10s (TransferFactory không override) ⇒ 120 ≥ 12×; local-ready (300s) bound pha thuần cục bộ KHÔNG có network — hoàn tất < 1s ⇒ headroom >300×, cộng biên cho observer/plugin trên save CM. Không dùng chung giá trị/không dùng chung tên để hai quan hệ cứng không bị đổi lẫn khi refactor. 300s cũng ≪ 900s nhịp cron ⇒ claim sống luôn có ≥1 tick grace trước tick stale đầu tiên, trong khi tick stale chỉ đến sau khi crash đã kéo dài ít nhất 5 phút.

## D4 — Race proof tổng hợp real-DB + unit

Full sequence "A claim → save CM → bind → cron chạy giữa chừng → untouched → mark thành công → provider đúng 1 lần" được chứng minh bằng: real-DB R1/R3 (cron THẬT qua DI trên MariaDB thật: fresh ⇒ untouched, mark true trên bảng thật; stale ⇒ nhả, evidence abandoned ⇒ provider call count 0) + unit plugin REQUIRED round 5 (recorder `['save','bind','start','provider']`, `executePrepared` đúng 1 lần) + unit cron round 6 (composition test + 4 test fresh/stale × bound/unbound). HTTP `/refund` thật không chạy được trong sandbox (không credentials provider) — pin bằng unit ở biên HTTP, như mọi round trước; khai báo trung thực.
