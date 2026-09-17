# DEC-TASKCG6BM7-010 — Round 7: CAS transitions toàn bộ lifecycle sau claim (F29/B1)

Date: 2026-09-17. Status: accepted. Extends round-3/5/6 claim-guard semantics to EVERY post-claim transition.

## D1 — Không còn blind `setData()+save()` cho state machine

Snapshot stale (cron load row R trước, owner đổi DB sau, cron ghi đè bằng model save cũ) là hỏng state machine tiền. Mọi transition sau claim chuyển sang conditional UPDATE trên connection: `UPDATE ... WHERE entity_id = ? AND refund_state = <expected> AND active_claim = 1` (expected theo bảng transition từng method); giá trị model in-memory được đồng bộ lại sau khi affected = 1. `consumeQueryBudget` dùng increment nguyên tử SQL (`query_attempts = query_attempts + 1`) — không read-modify-write.

## D2 — Bảng guard kỳ vọng (FROM-state) theo caller

- `bindCreditMemo`: `active_claim=1` (giữ nguyên).
- `markProviderRequestStarted`: thêm `refund_state='initiating'` cạnh `active_claim=1`.
- `markProcessing`: `(provider_request_started|processing) + claim`.
- `markUnknown`: `(provider_request_started|processing|unknown) + claim`.
- `markConfirmedFail`: `(initiating|provider_request_started|processing|unknown) + claim`; SET `active_claim=NULL`.
- `markProviderSuccessLocalPending`: `(provider_request_started|processing|unknown) + claim`.
- `markConfirmedSuccess`: `is_processed=0` (chạy dưới SELECT ... FOR UPDATE của finalize hoặc cron 2a).
- `terminate`: `active_claim=1` (cron stale-release lẫn terminal fail).
- Bookkeeping trong `finalizeSuccess`: thêm `AND is_processed=0` (exactly-once kép).

## D3 — Chính sách CAS-fail (affected = 0): người thắng giữ row

Reload row + critical-log + KHÔNG ghi đè / KHÔNG nhả claim / KHÔNG hạ state. Blocking forward transitions (`markProcessing`, `markUnknown`, `markProviderSuccessLocalPending`) ném `LocalizedException` customer-safe (admin phải biết finalize chưa hoàn tất); `markConfirmedFail`/`terminate` nuốt (winner sở hữu row, block/self-heal vẫn đúng). Không giữ DB transaction/lock vắt qua provider HTTP (mọi CAS là UPDATE autocommit đơn; HTTP chỉ chạy sau khi mọi CAS đã commit).

## D4 — Bằng chứng

Unit: mỗi transition assert đúng WHERE; affected=0 ⇒ không overwrite/không nhả/không hạ + reload + đúng hành vi throw/nuốt. Real MariaDB: Scenario A (cron snapshot `initiating`, owner đẩy `provider_request_started`, stale `terminate` ⇒ 0 rows, final `provider_request_started`); Scenario B (cron snapshot `processing`, owner đẩy `confirmed_success`, stale fail-transition ⇒ 0 rows, final `confirmed_success`) — chạy qua 2 process thật (`cas_race.php load` → owner đổi DB → `cas_race.php fire-*`) trên `zalo_pay_refund` thật.
