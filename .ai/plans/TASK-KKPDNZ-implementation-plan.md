# TASK-KKPDNZ Implementation Plan — VNPAY adapter (trong Payment Core)

| Field | Value |
|---|---|
| Specification | tickets/TASK-KKPDNZ-vnpay-paymentcore-adapter.md (`## Mini Spec`) · canonical parent SPEC-FEAT-CSWYEJ §4.4 |
| Decisions | DEC-FEATCSWYEJ-001 (D6 TxnRef) → **DEC-FEATCSWYEJ-002 rev**: adapter nằm trong core, Vnpayment_VNPAY pristine → **DEC-FEATCSWYEJ-004**: bỏ isPaymentCompleted/querydr |

> **Mode A** · Tier 2 (payment wire protocol) · Status: **Retro-canonical** — Dev complete qua 3 lần rev (D1 trong extension → D1-rev trong core → bỏ verify); adapter giờ chỉ sinh URL.

---

## PART 1 — ANALYSIS

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Adapter đặt đâu? | `Secomm_PaymentCore/Model/Provider/` — user TL: extension third-party KHÔNG sửa; toàn bộ thay đổi trong extension đã `git checkout` revert | DEC-FEATCSWYEJ-002 |
| Build URL thế nào? | **Replicate-don't-import**: đọc 3 config path `payment/vnpay/{payment_url,tmn_code,hash_code}` (string contract), HMAC-SHA512 + ksort/urlencode giống Info.php:48-98, TxnRef = increment id | DEC-001 D6 + sandbox verified |
| VND conversion? | Tự triển khai qua `Magento_Directory\Helper\Data::currencyConvert` (semantics ≈ Helper\Rate của extension, không import class) | DEC-002 |
| querydr? | **REMOVED** (DEC-004) — từng build + fix hash contract (|-joined, vnp_TransactionDate, vnp_RequestId) sau HTTP 500, rồi user TL quyết định bỏ hẳn | DEC-004 |
| Token 15' VNPAY? | Do VNPAY sinh, không lưu không reuse — mỗi lần generate là session mới | sandbox facts 2026-08-25 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | URL builder (replicate contract extension) | Model/Provider/VnpayCheckoutUrl.php | ✅ (fix runtime: storeManager inject) |
| 2 | Adapter implement contract (URL-gen only sau DEC-004) | Model/Provider/VnpayAdapter.php | ✅ |
| 3 | DI registration | etc/di.xml (AdapterPool item) | ✅ |
| 4 | Revert extension về pristine | `git status app/code/Vnpayment/` = trống | ✅ |
| 5 | Dead-code cleanup (VerifyResult, querydr config, i18n) | rm VerifyResult.php · system/config/di.xml dọn | ✅ |

## Remaining steps (human)

1. QC S-3 (Continue Payment → sandbox mở token mới OK, kể cả sau khi token cũ hết 15').
2. QC S-12 (checkout bình thường của extension không đổi — pristine).

## Verification summary

| Mini-Spec clause | Bằng chứng |
|---|---|
| Extension zero changes | git porcelain rỗng |
| URL signed mới hợp lệ | S-3 pending QC (sandbox facts đã verify manual) |
| Core 0 class import Vnpayment | grep `use Vnpayment` = 0 |
