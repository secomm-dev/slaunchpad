# Implementation Plan — TASK-7HP4R0 (MOMO-01 payment-first conversion)

| Specification | .ai/specs/SPEC-TASK-7HP4R0-momo-payment-first-order-finalization.md (SPEC-TASK-7HP4R0, VALID) |
|---|---|
| Work item | TASK-7HP4R0 — GitHub issue #3 (MOMO-01), lane MoMo |
| Mode / Risk | A / high (payment — Tier 2) |
| Base SHA | 87f4db4459c67fe92ce6ba99a61c4d833ebde7bd (verified tại start gate) |
| Branch | thanhle74/momo-momo-01-convert-redirect-checkout-to-paymen (local-only, không push) |

## Steps

1. **Schema**: `etc/db_schema.xml` (bảng `secomm_momo_payment_attempt`) + `etc/db_schema_whitelist.json`.
2. **Api/Model**: `Api/Data/PaymentAttemptInterface`, `Model/PaymentAttempt` (state machine),
   `Model/PaymentAttemptFactory`, `Api/PaymentAttemptRepositoryInterface`, `Model/PaymentAttemptRepository`,
   `Model/ResourceModel/PaymentAttemptResource`, `Model/ResourceModel/PaymentAttempt/Collection`.
3. **Initiation**: `Model/OrderRefBuilder`, `Model/QuoteContractFingerprint`, `Model/PaymentAttemptManagement`.
4. **Guard**: `Service/OrderPlacementAuthorization` + `Plugin/Quote/CartManagementPlaceOrderGuard`.
5. **Gateway**: `Gateway/Command/InitializeCommand` (mới), `CreateOrderBuilder` (rewrite attempt-based),
   `NotifyValidator` (rewrite attempt-based), `QueryDataBuilder` + `QueryValidator` (mới); xoá
   `Gateway/Command/NotifyCommand` + `Gateway/Response/TransactionHandler`.
6. **Services**: `Service/PaymentAttemptLifecycle`, `Service/OrderFinalizer`, `Service/IpnProcessor`,
   `Service/SuccessSessionPreparer`, `Service/ReturnProcessor` (rewrite).
7. **Controllers**: `Redirect` (rewrite quote-first), `Notify` (thin → IpnProcessor), `ReturnAction` (giữ shape).
8. **DI/config**: `etc/di.xml` (guard plugin, pool initialize/query_transaction/refund/capture,
   bỏ pool notify), `etc/config.xml` (+attempt_ttl).
9. **Frontend JS**: `momo-method.js` rewrite (placeOrder → continueToMoMo, set-payment-information rồi
   redirect — không placeOrder); module sequence thêm `Magento_Quote` (đã có).
10. **Refund compat**: `RefundBuilder` đọc `momo_order_ref` (fallback increment id).
11. **Tests**: rewrite/add unit tests (attempt, lifecycle, finalizer, ipn, return, validators, guard,
    fingerprint, refbuilder, initialize); xoá test cũ của NotifyCommand/TransactionHandler (nếu có).
12. **Validation**: php -l (docker m2r-php), PHPCS Magento2 changed scope, phpunit unit (MoMo suite),
    setup:upgrade + setup:di:compile (qua main runtime khi khả dụng), ghi evidence
    `.ai/evidence/TASK-7HP4R0/`.
13. **Docs**: README/CHANGELOG update; project-context memory (CURRENT_STATE/NEXT_TASK diff đề xuất);
    comment EVIDENCE/HANDOFF lên issue #3; KHÔNG push, KHÔNG merge.

## Trình tự commit (local, khi user/TL yêu cầu commit mới commit — hiện KHÔNG commit)

Vì CLAUDE.md cấm commit/push khi chưa được yêu cầu → giữ working tree, report; TL quyết định commit split.
