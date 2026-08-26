# TASK-KKPDNZ — VNPAY Payment Core adapter (PaymentUrlBuilder + querydr)

**Type:** Task (slice của FEAT-CSWYEJ)
**Mode:** A (payment wire protocol + đọc config extension — Tier 2; **rev 2026-08-25 theo DEC-FEATCSWYEJ-002**: toàn bộ nằm trong Secomm_PaymentCore, `Vnpayment_VNPAY` giữ pristine)
**Placement:** `app/code/Secomm/PaymentCore/Model/Provider/`: `VnpayCheckoutUrl.php` (URL builder — replicate contract của extension, KHÔNG import class của nó), `VnpayAdapter.php`; đăng ký trong `etc/di.xml` của PaymentCore; config `secomm_paymentcore/vnpay/querydr_url` (system.xml group VNPAY, section Secomm > Payment Core).
**Risk tier:** Tier 2 (payment wire protocol — SA review)
**Author:** AI draft · **Date:** 2026-08-25 · **Status:** Dev complete — rev theo DEC-002 (extension reverted pristine; chờ TL/SA review + QC sandbox)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-CSWYEJ](../specs/SPEC-FEAT-CSWYEJ-payment-core.md) §4.4, DEC D1-rev/D6/D7 (DEC-FEATCSWYEJ-002) · Plan: plans/TASK-KKPDNZ-implementation-plan.md

## Mini Spec

### Goal

VNPAY implement `PaymentProviderAdapterInterface` **bên trong Payment Core** (`Model/Provider/` — DEC-FEATCSWYEJ-002): extension `Vnpayment_VNPAY` không bị sửa file nào; adapter đọc config paths `payment/vnpay/*` của extension như string contract.

### Expected Behavior

- **`VnpayCheckoutUrl::build(OrderInterface): ?string`** — replicate URL contract của extension (params, HMAC-SHA512, ksort/urlencode, TxnRef = increment id — D6) đọc 3 config sẵn có `payment/vnpay/{payment_url,tmn_code,hash_code}`; VND conversion tự triển khai qua `Magento_Directory\Helper\Data::currencyConvert` (semantics tương đương `Helper\Rate` của extension). Checkout flow hiện tại của extension KHÔNG bị đụng.
- **`VnpayAdapter`**:
  - `getCheckoutUrl`: builder + ghi `additional_information['vnpay_initiated_at']` (YmdHis) cho querydr; `vnp_Amount` = `total_due` (order pending chưa invoice).
  - `isPaymentCompleted`: **querydr** (`vnp_Command=querydr`, transDate từ initiated_at || order created_at; hash cùng scheme). Mapping: HTTP OK + `vnp_ResponseCode==00 && vnp_TransactionStatus==00` → `PAID`; HTTP OK + mã khác → `NOT_PAID`; **network/parse/timeout hoặc querydr_url chưa cấu hình hoặc order age > 24h (transDate quá hạn query)** → `UNKNOWN` (conservative — D3).
  - TxnRef giữ = increment id (D6 — sandbox verified: token 15' do VNPAY sinh, nhiều session cùng TxnRef OK).
- Config mới: `secomm_paymentcore/vnpay/querydr_url` (text, default rỗng → verify UNKNOWN → cron không bao giờ cancel khi chưa cấu hình — auto-expiry off an toàn; Continue Payment KHÔNG cần nó).

### Constraints / Rules

- `Vnpayment_VNPAY` zero changes (pristine — DEC-002); KHÔNG import class của extension, chỉ đọc config path string.
- Signing/URL contract replicate nguyên vẹn từ Info.php (HMAC-SHA512, TxnRef = increment id).
- Không log hash_code/TmnCode.

### Acceptance Criteria

- [ ] AC-1: Checkout hiện tại của extension (place order → redirect VNPAY) hoạt động identically — extension không bị đụng (git diff trống).
- [ ] AC-2: `getCheckoutUrl(order pending cũ)` trả URL signed mới hợp lệ (sandbox mở OK).
- [ ] AC-3: querydr verify trả đúng 3 nhãn với: giao dịch đã pay / chưa pay / endpoint sai (UNKNOWN).

### Out of Scope

IPN controller logic · Pay return controller · signing scheme (giữ nguyên tuyệt đối).

## Approach

Replicate-don't-import: builder service thuần (scopeConfig + storeManager + directoryData) đọc config của extension; adapter dùng builder + LaminasClient cho querydr POST; logging vào channel secomm_paymentcore.log (không log hash/TmnCode).