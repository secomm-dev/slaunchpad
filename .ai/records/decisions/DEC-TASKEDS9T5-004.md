---
id: DEC-TASKEDS9T5-004
title: 'ZaloPay corrective 4: callback BẮT BUỘC type=1 + zp_trans_id>0 (malformed KHÔNG cast); conflict zp_trans_id callback-vs-query ⇒ quarantine giữ CẢ HAI identity; app_id MAC-bound + so khớp defensive; Start chặn double-payment dưới quote lock bằng structured flags; PAID+FAIL ⇒ provider_state_conflict, late-PAID ⇒ late_paid_terminal_state (STICKY); recovery exhaustion = marker recovery_exhausted (operational, KHÔNG money-real)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit: 5285ab783184835760dd7524a2a7854a125d26fa
supersedes: []
superseded_by:
work_items: [TASK-EDS9T5]
---

# Decision: Corrective round 4 — strict callback identity + double-payment guard + sticky conflicts + explicit recovery exhaustion (TASK-EDS9T5)

## Bối cảnh

TL review tại HEAD `5285ab78` (round 3) kết luận 4 BLOCKER:
1. Callback KHÔNG kiểm tra `type` (envelope) ⇒ Agreement callback (type 2) có thể
   được xử lý như Order; `zp_trans_id` thiếu/0 vẫn có thể finalize; callback
   zp_trans_id=A vs query zp_trans_id=B chưa được phát hiện; `data.app_id` chưa
   được đối chiếu.
2. Start (tạo attempt mới) chưa chặn double-payment: quote có attempt PAID /
   quarantined vẫn có thể mở provider transaction thứ hai khi customer bấm Pay
   lần nữa (paid-chưa-finalize, quarantine, late-paid...).
3. PAID + authoritative FAIL giữ PAID nhưng chỉ free-text; FAILED/STALE/EXPIRED
   + late PAID chưa có mã quarantine cấu trúc ⇒ Start/blocking không thể đọc
   bằng structured flags; chưa chứng minh tính STICKY.
4. Recovery exhausted (hết 5 lần query) chưa có bằng chứng machine-readable
   trong DB/log.

## Quyết định

### 1. STRICT CALLBACK PAYMENT IDENTITY (Blocker 1) — `IpnProcessor`

- **`type` gate ĐẦU TIÊN** (trước lookup, trước MAC mutation): `type` là field
  TRÊN ENVELOPE (ngoài signed data) theo
  https://docs.zalopay.vn/docs/specs/callback-api/ — **1 = Order, 2 = Agreement**.
  Thiếu / bool / array / non-numeric / `(int)$type !== 1` ⇒
  `OUTCOME_INVALID_CALLBACK` (return_code 2 "Invalid"), KHÔNG lookup, KHÔNG
  mutation. Agreement/unknown KHÔNG BAO GIỜ được nhận ở endpoint order.
- **Trichotomy cho field signed** (`amount`, `zp_trans_id`) — KHÔNG BAO GIỜ cast
  mù "abc" ⇒ 0 rồi nhét vào fallback:
  - *absent/zero* ⇒ authoritative `v2/query` fallback (an toàn: query phải chứng
    minh exact amount + identity trước khi finalize);
  - *positive integer* (regex `/^-?\d+$/` trên raw, KHÔNG đụng locale) ⇒ dùng trực tiếp;
  - *malformed* (non-integer-numeric, bool, array) ⇒ `OUTCOME_INVALID_CALLBACK`
    + log critical, ZERO mutation.
- **zp_trans_id > 0 là BẮT BUỘC để finalize tự động** (Blocker 1b): amount EXACT
  nhưng thiếu id ⇒ `v2/query` phải chứng minh `zp_trans_id > 0`; CẢ hai proof
  đều không có id ⇒ verified money bị quarantine
  `provider_transaction_unavailable` (`RECON_PROVIDER_TX_UNAVAILABLE`) — KHÔNG
  order. Tài liệu chính thức: zp_trans_id int64, "initiate when users confirms
  payment at Zalopay site. Merchant uses this to request refund & reconciliation".
- **Conflict callback-vs-query** (Blocker 1c): callback id A ≠ query id B ⇒
  quarantine `provider_transaction_conflict` (`RECON_PROVIDER_TX_CONFLICT`)
  với CẢ HAI identity giữ nguyên verbatim trong `last_error` — không im lặng
  ưu tiên bên nào, KHÔNG order.
- **`app_id`** (Blocker 1d): MAC key2 ký TOÀN BỘ chuỗi data (doc chính thức:
  `mac = HMAC(HmacSHA256, key2, data)`) ⇒ app_id đã được cryptographic binding
  — chỉ ZaloPay (người giữ key2) tạo được payload. Vẫn thêm **defensive
  comparison** `data.app_id === configured app_id` (strict integer grammar):
  mismatch ⇒ INVALID + critical + zero mutation (lỗi config/cross-env KHÔNG
  được poison state; recovery worker vẫn hội tụ tiền). Config rỗng ⇒ bỏ gate.

### 2. DOUBLE-PAYMENT GUARD (Blocker 2) — `PaymentAttemptManagement` + `PaymentAttemptRepository::getBlockingAttemptByQuoteId`

- DƯỚI quote lock (SELECT ... FOR UPDATE), TRƯỚC mọi quyết định
  reuse/stale/mint: `getBlockingAttemptByQuoteId()` — query BOUNDED
  (OR-filter `payment_status IN (paid, finalized) OR requires_reconciliation = 1`,
  ORDER BY entity_id DESC, LIMIT 1), chỉ structured flags, KHÔNG parse
  last_error.
- Có blocker ⇒ throw `LocalizedException` với message customer-safe
  (quarantine ưu tiên TRƯỚC status: "under review — do not pay twice";
  PAID: "already received / being finalized"; FINALIZED: "order created")
  — KHÔNG attempt mới, KHÔNG provider transaction mới; KHÔNG gọi
  OrderFinalizer khi đang giữ quote lock (IPN/Return/Recovery owns convergence).
- **Concurrency (#22)**: attempt INITIATED chưa expire (dưới lock) = provider
  transaction in-flight của Start khác ⇒ refuse retry-safe ("being initialized")
  thay vì stale-mark + mint thứ hai ⇒ TỐI ĐA MỘT provider transaction/payment.
  INITIATED đã expire ⇒ stale + mint (giữ nguyên).
- Retry UX giữ nguyên: FAILED/STALE/EXPIRED THẬT SỰ unpaid (không money-real)
  ⇒ mint attempt mới hợp lệ.

### 3. STRUCTURED + STICKY CONFLICTS (Blocker 3) — `PaymentAttemptLifecycle`

- PAID + authoritative FAIL ⇒ giữ PAID (không bao giờ PAID→FAILED) +
  `requires_reconciliation=true` + code **`provider_state_conflict`**.
- FAILED/STALE/EXPIRED + authoritative PAID (recordTerminalPaidEvidence) ⇒
  quarantine cấu trúc **`late_paid_terminal_state`** + backfill provider id +
  evidence — status KHÔNG broadened.
- **STICKY**: `markRequiresReconciliation` idempotent giữ code ĐẦU TIÊN; không
  đường nào (IPN/Return/Recovery/Start, callback hợp lệ sau đó) xóa flag —
  grep chứng minh KHÔNG có `setRequiresReconciliation(false)` trong production.
- Mới: `recordProviderIdentityConflict(appTransId, firstId, secondId, source)`
  và `recordProviderIdentityUnavailable(appTransId, source)` — 2 identity
  outcome đi qua `applyMoneyRealQuarantine` chung (PAID nơi cho phép +
  quarantine + evidence).

### 4. EXPLICIT RECOVERY EXHAUSTION (Blocker 4) — `PaymentRecovery` + schema 1.3.0

- Cột mới `recovery_exhausted` (boolean, default false) + whitelist +
  `PaymentAttemptInterface::RECOVERY_EXHAUSTED` + getter/setter; module
  setup_version 1.2.0 → **1.3.0** (declarative, safe — chỉ ADD column).
- Claim UPDATE (single statement — giữ nguyên ordering test) thêm
  `recovery_exhausted = IF(recovery_attempts >= max, 1, recovery_exhausted)`
  SAU khi tăng attempts (MySQL đánh giá SET trái→phải, giá trị thứ hai thấy
  giá trị ĐÃ tăng ⇒ marker bật đúng trên claim cuối được phép); WHERE thêm
  `recovery_exhausted = 0`; selection thêm filter `neq 1` ⇒ hàng exhausted
  KHÔNG còn được chọn/claim tự động.
- Khi claim tiêu lần cuối ⇒ log CRITICAL (marker DB + log = evidence đủ).
- Marker **OPERATIONAL ONLY**: exhaustion KHÔNG phải money-real — KHÔNG nằm
  trong điều kiện blocking của Start, KHÔNG quarantine; IPN hợp lệ đúng
  (MAC + exact amount + identity) vẫn resolve payment bình thường
  (test `testRecoveryExhaustedAttemptIsStillResolvedByValidIpn`).

## Hệ quả

- Start không bao giờ mở provider transaction thứ hai cho quote đã có
  money-real/quarantined evidence; PAID-chưa-finalize không bao giờ double-charge.
- Mọi con đường auto-finalize đều phải qua: type=1 + MAC + app_id + exact
  amount + zp_trans_id>0 proven (callback HOẶC query), không conflict — nếu
  không: quarantine có code máy đọc được, sticky.
- Cron tự truy vấn có bounded budget hiển thị (`recovery_exhausted`) nhưng
  vẫn để IPN hội tụ tiền.
- Bounded queries: blocker lookup LIMIT 1; không load lịch sử attempt.

## Liên kết

- Contract chính thức: https://docs.zalopay.vn/docs/specs/callback-api/ (type
  envelope 1/2; mac key2 toàn data; response {return_code, return_message}),
  https://docs.zalopay.vn/docs/api/quick-start-api/ (v2/query: zp_trans_id
  int64, amount chỉ khi success) — bằng chứng verbatim:
  [provider-contract-round4.md](../../evidence/TASK-EDS9T5/provider-contract-round4.md)
- Supersedes: nothing — kế thừa DEC-TASKEDS9T5-003 (round 3), bổ sung identity
  strict + double-payment guard + sticky conflict + exhaustion marker.
