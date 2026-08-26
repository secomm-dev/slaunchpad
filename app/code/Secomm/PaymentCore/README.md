# Secomm_PaymentCore

FEAT-CSWYEJ — Payment Core: quản lý tập trung lifecycle pending payment (redirect/online payment methods) cho Secomm Launchpad. Spec: [SPEC-FEAT-CSWYEJ](../../../.ai/specs/SPEC-FEAT-CSWYEJ-payment-core.md) (VALID, DEC-FEATCSWYEJ-001).

## Purpose

- Order đặt bằng payment method được admin assign → snapshot `expires_at` = TTL provider session (mặc định 15' — DEC-003) tính từ lần generate URL cuối (place order hoặc Continue Payment). Mỗi lần Continue Payment cấp token mới → window restart.
- My Account > Order Detail → **Continue Payment** (generate checkout URL mới từ provider) khi chưa hết hạn.
- Cron 5 phút: order hết hạn → verify provider (querydr) → chỉ cancel khi **chắc chắn** chưa thanh toán → cancel qua Magento lifecycle chuẩn → MSI reservation release tự chạy.

## How it works

```
place order (managed) ─observer─► secomm_paymentcore_payment {expires_at snapshot, active}
order view ─CanContinuePayment─► POST paymentcore/payment/retry ─► adapter→provider URL
cron secomm_paymentcore (5') ─► lock + state check + querydr ─► cancel | skip | retry
```

- **Adapter contract** `Model/Adapter/PaymentProviderAdapterInterface.php` — provider module tự implement + tự đăng ký map trong di.xml của provider (DEC D1). Core không reference provider nào.
- **VerifyResult** `PAID | NOT_PAID | UNKNOWN` — UNKNOWN → không cancel, retry (DEC D3).
- Config: Stores > Configuration > **Secomm > Payment Core**.
- Log: `var/log/secomm_paymentcore.log` (masked — không hash secret/TmnCode).

## Cron & QC command

- Cron group `secomm_paymentcore`, mỗi 5 phút (đăng ký trong `etc/crontab.xml`). Cron không đọc enabled flag (DEC D5 snapshot semantics) — disable config chỉ dừng tạo record mới; tắt hẳn → disable module.
- **QC manual trigger** (TASK-Q2BAHW — cùng một code path với cron):
  ```bash
  bin/magento paymentcore:expire:run --dry-run      # xem candidate, không xử lý
  bin/magento paymentcore:expire:run                # xử lý như cron
  bin/magento paymentcore:expire:run <order_id>     # chỉ 1 order (bỏ qua window check, giữ nguyên race guard)
  ```

## First adapter — VNPAY (inside this module, DEC-FEATCSWYEJ-002)

`Model/Provider/VnpayAdapter` + `Model/Provider/VnpayCheckoutUrl`. `Vnpayment_VNPAY` extension stays **pristine** (zero changes): the adapter reads the extension's own config paths (`payment/vnpay/{payment_url,tmn_code,hash_code}`) as a string contract and speaks the VNPAY wire protocol directly (VND conversion via `Magento_Directory`).

- **Continue Payment needs NO extra config** — checkout URL is rebuilt from the 3 existing VNPAY configs + increment id (TxnRef unchanged; VNPAY issues a fresh 15-minute token each time).
- DEC-FEATCSWYEJ-004: **expired + still pending = not paid → cancel**. The order state is the payment truth (IPN moves it out of pending within seconds of a real payment); no provider verify API is involved. Mollie: Phase 2 (implement the same contract in `Model/Provider/`).
