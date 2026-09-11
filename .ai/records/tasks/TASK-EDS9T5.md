---
id: TASK-EDS9T5
type: task
title: 'ZaloPay payment-first only — không Sales Order trước khi thanh toán được xác thực (IPN-first finalize + placeOrder authorization/guard)'
project_code: SLP
parent: null
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-EDS9T5-zalopay-payment-first-only.md
risk: high
status: done
created: 2026-09-10
updated: 2026-09-10
legacy_ids: []
decisions: [DEC-TASKEDS9T5-001, DEC-TASKEDS9T5-002]
decision_assessment: architecture-material
components:
  - Secomm_ZaloPay
source_areas:
  - app/code/Secomm/ZaloPay/Service/OrderPlacementAuthorization.php
  - app/code/Secomm/ZaloPay/Plugin/Quote/CartManagementPlaceOrderGuard.php
  - app/code/Secomm/ZaloPay/Controller/Payment/Start.php
  - app/code/Secomm/ZaloPay/Controller/Payment/ReturnAction.php
  - app/code/Secomm/ZaloPay/Controller/Payment/Ipn.php
  - app/code/Secomm/ZaloPay/Service/IpnProcessor.php
  - app/code/Secomm/ZaloPay/Service/OrderFinalizer.php
  - app/code/Secomm/ZaloPay/Service/ReturnProcessor.php
  - app/code/Secomm/ZaloPay/Model/ZaloPayConfigProvider.php
  - app/code/Secomm/ZaloPay/view/frontend/web/js/view/payment/method-renderer/zalopay-wallet.js
  - app/code/Secomm/ZaloPay/etc/config.xml
  - app/code/Secomm/ZaloPay/etc/di.xml
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit: 3b26189e5d19e60ea3b778baa56bcc9650d69355
last_verified: 2026-09-10
supersedes: []
---

# [SLP][TASK-EDS9T5] ZaloPay payment-first only — không Sales Order trước khi thanh toán được xác thực

## Bối cảnh (Context)

Ticket `[ZaloPay][Create order]`: redirect qua ZaloPay gateway **tạo Sales Order sẵn** (`pending_payment`)
dù chưa thanh toán; khách huỷ/không thanh toán để lại đơn rác. Audit từ clean base `3b26189e`
(workspace `../slaunchpad-workspaces/zalopay-payment-first`, branch `task/zalopay-payment-first`) cho thấy
payment-first đã partial-implement nhưng: (1) còn feature flag `payment_first` (default 0 = order-first) +
legacy fallback trong cả 3 controller + renderer; (2) IPN chỉ mark PAID rồi chờ browser Return (không có
cron reconcile ⇒ tiền thật, không order nếu khách đóng browser); (3) không có authorization/guard —
mọi đường generic `CartManagementInterface::placeOrder` tạo được order cho quote ZaloPay chưa thanh toán.

Root cause: order creation bị gắn vào checkout session/browser thay vì sở hữu bởi duy nhất một finalizer
được kích hoạt bởi payment verification authoritative.

## Specification & Plan

- **Spec (FULL, VALID)**: [SPEC-TASK-EDS9T5-zalopay-payment-first-only.md](../../specs/SPEC-TASK-EDS9T5-zalopay-payment-first-only.md)
- **Plan**: [PLAN-TASK-EDS9T5-zalopay-payment-first.md](../../plans/PLAN-TASK-EDS9T5-zalopay-payment-first.md)
- **Decision**: [DEC-TASKEDS9T5-001](../../records/decisions/DEC-TASKEDS9T5-001.md)

## Mode & Approach

Mode A (payment + checkout + order lifecycle — §12 AGENTS.md, Tier-2). Approach: loại bỏ hoàn toàn
order-first (flag/legacy/admin toggle), IPN tự finalize qua `OrderFinalizer`, thêm
`Service/OrderPlacementAuthorization` (grant single-use request-scoped) + guard plugin trên
`CartManagementInterface::placeOrder` chặn caller generic cho quote ZaloPay.

## Kết quả (implement + validate xong 2026-09-10)

- **Implementation**: payment-first ONLY hoàn tất — 16 file production viết lại/thêm/xoá,
  chi tiết tại `.ai/evidence/TASK-EDS9T5/diff-summary.md`.
- **Validation** (docker PHP 8.3.20, `.ai/evidence/TASK-EDS9T5/command-output.txt`):
  - `php -l`: 92/92 file OK.
  - Unit ZaloPay: **78/78 pass** (base 61 → +17 test mới, 0 fail).
  - Full suite: 866 tests / 15 errors — **giống hệt baseline clean base 3b26189e** (849/15,
    đo lại trên detached worktree của SHA đó); 15 lỗi pre-existing Tracking/PromotionMaxDiscount,
    0 regression.
  - PHPCS Magento2 severity 10: 0 errors (1 warning pre-existing trong file không đụng).
  - `setup:di:compile`: OK.
  - Plugin-list compiled: guard `zalopay_place_order_guard` trên `Magento\Quote\Model\QuoteManagement`
    có mặt frontend/webapi_rest/graphql/webapi_soap (+global/adminhtml/crontab vô hại).
  - §19 proof: call placeOrder production DUY NHẤT trong module = `OrderFinalizer.php:223`
    (grant + finally clear); JS renderer chỉ gọi `set-payment-information` (không order);
    admin dùng `submit()` — không bị guard.
- **Status: done** — branch `task/zalopay-payment-first` đã push GitHub (origin). KHÔNG merge
  (đúng yêu cầu §22/§23).
- Hạn chế: `Test/Integration` không chạy được local (ENVIRONMENT-BLOCKED — cần Magento test
  framework + DB); source đã cập nhật nhất quán flow mới; hành vi phủ bởi unit suite.

## Kết quả corrective round 2 (TL review 4 BLOCKER — hoàn tất + validate 2026-09-10)

TL review tại HEAD `66f68446` kết luận 4 BLOCKER (Return fail sớm trước v2/query; mutate
không khoá row; guard quote-only; finalizer giữ Checkout Session) + claim cron reconcile
không có thật. Chi tiết quyết định: [DEC-TASKEDS9T5-002](../../records/decisions/DEC-TASKEDS9T5-002.md).

- **Production**: NEW `Service/PaymentAttemptLifecycle` (canonical, short tx + FOR UPDATE +
  re-evaluate fresh state; `recordVerifiedPaid/recordVerifiedFailure/recordAmountMismatch`);
  NEW `Service/SuccessSessionPreparer`; REWRITE `ReturnProcessor` (quyết định CHỈ bằng v2/query,
  browser status/checksum chỉ là evidence); REWRITE `IpnProcessor` (delegate lifecycle, không
  session); EDIT `OrderFinalizer` (bỏ Checkout\Session + alias `finalize()`); EDIT
  `OrderPlacementAuthorization` (xoá `consumeForQuote`, thêm `peekForQuote`); EDIT guard
  (validate triple với attempt ĐÃ PERSIST trước consume). Chi tiết:
  `.ai/evidence/TASK-EDS9T5/diff-summary.md` § corrective.
- **Reconciliation**: xoá toàn bộ claim "Phase 2 cron"; 4 case reconcile THỦ CÔNG thật ghi trong
  CHANGELOG 1.1.1 (amount mismatch, contract mismatch, late PAID trên terminal, evidence mâu thuẫn).
- **Validation** (docker PHP 8.3.20, `.ai/evidence/TASK-EDS9T5/command-output.txt` § corrective):
  - Unit ZaloPay: **112/112 pass** (78 → +34).
  - Full suite: **900 tests / 7 errors = GIỐNG HỆT base 3b26189e đo cùng điều kiện**
    (849/7 — đo lại trên detached worktree của base CÓ chạy `setup:di:compile`; 7 lỗi pre-existing
    Tracking. 8 lỗi MaxDiscountCap trước đó là artifact do base worktree thiếu `generated/`
    compiled — Interface `CartItemExtensionInterface` là generated code).
  - php -l 97/97 OK; PHPCS Magento2 severity 10: 0 errors (1 warning pre-existing, file không đụng);
  - `setup:di:compile`: OK; plugin-list compiled: guard có mặt mọi area.
  - §19 re-proof: placeOrder production DUY NHẤT = `OrderFinalizer.php:207`; writers của
    `payment_status`/`order_id`/`provider_transaction_id`/`last_error` = Lifecycle + Finalizer +
    initiation-side PaymentAttemptManagement (audit grep, xem command-output.txt).
  - Integration suite: ENVIRONMENT-BLOCKED (cần Magento test framework + DB) — KHÔNG claim PASS.
- **Status: done (corrective round 2)** — corrective commits trên `task/zalopay-payment-first`,
  push GitHub (origin). KHÔNG merge.


## Kết quả corrective round 3 (TL review 6 BLOCKER — hoàn tất + validate 2026-09-10)

TL review tại HEAD `da510930` kết luận 6 BLOCKER (checksum cản query; stale save sau rollback;
IPN sai contract; amount 0/thiếu lọt qua; mismatch/conflict auto-finalize sau; thiếu cron
recovery). Chi tiết quyết định:
[DEC-TASKEDS9T5-003](../../records/decisions/DEC-TASKEDS9T5-003.md); bằng chứng contract
chính thức: [provider-contract-round3.md](../../evidence/TASK-EDS9T5/provider-contract-round3.md).

- **Production**: `ReturnProcessor` (checksum = tamper evidence, query LUÔN chạy);
  `PaymentAttemptLifecycle::recordContractMismatch` (re-lock fresh row, không bao giờ regress
  FINALIZED / xoá order_id / xoá provider id) + quarantine conflict zp_trans_id; `OrderFinalizer`
  (gate `requires_reconciliation`, xoá stale-save, delegate evidence qua lifecycle); `IpnProcessor`
  REWRITE (4 domain outcome; amount thiếu/0 ⇒ authoritative v2/query exact; cấu trúc quarantine);
  `Controller/Payment/Ipn` REWRITE (HTTP 200 `{return_code, return_message}` 1/2/0, POST-only);
  NEW `Service/PaymentRecovery` + `Cron/PaymentRecoveryCronjob` (cron */5, bound batch 25,
  window 15 phút đúng docs, max 5 lần/attempt, claim UPDATE atomic TRƯỚC HTTP — không lock qua
  HTTP, tái dùng Lifecycle + OrderFinalizer); schema 1.2.0 (`requires_reconciliation`,
  `reconciliation_code`, `recovery_attempts` + whitelist).
- **Validation** (docker PHP 8.3.20, `.ai/evidence/TASK-EDS9T5/command-output.txt` § round-3):
  - Unit ZaloPay: **157/157 pass, 530 assertions** (112 → +45).
  - Full suite: **945 tests / 7 errors** — 7 lỗi pre-existing Tracking, GIỐNG base 3b26189e
    (849/7) và round 2 (900/7); 0 regression.
  - php -l: ALL CLEAN (mọi file đổi); PHPCS Magento2 severity 10: **0 errors** (1 warning
    pre-existing `zalopay.html`, file không đụng).
  - `setup:di:compile`: **OK (9/9)** — full compile fail TRƯỚC ĐÓ là pre-existing: vendor của
    workspace chính thiếu `hybridauth` (commit "fix auth composer") làm Mageplaza SocialLogin(Pro)
    chết tại class scan; CHỨNG MINH bằng compile y hệt tại da510930 → cùng lỗi; compile thành công
    ở CẢ HEAD và da510930 khi park 2 module đó (file restore nguyên vẹn, diff không đụng).
  - §19 audit writers: `repository->save` = Lifecycle (inside lock tx) + Finalizer (locked row,
    inside tx) + initiation-side PaymentAttemptManagement; raw UPDATE duy nhất = PaymentRecovery
    claim (điều kiện WHERE, không đụng status/order_id/quarantine); `lockByAppTransId` = đúng 2
    caller production (Lifecycle:481, Finalizer:134) đều trong tx; placeOrder production duy nhất
    = `OrderFinalizer.php:235` dưới grant; VNPAY có placeOrder riêng cho quote VNPAY (không thuộc
    scope guard ZaloPay).
  - Integration suite: ENVIRONMENT-BLOCKED (cần Magento test framework + DB) — KHÔNG claim PASS.
- **Status: done (corrective round 3)** — corrective commits trên `task/zalopay-payment-first`,
  push GitHub (origin). KHÔNG merge.

## Kết quả corrective round 4 (TL review 4 BLOCKER — hoàn tất + validate 2026-09-10)

TL review tại HEAD `5285ab78` (round 3) kết luận 4 BLOCKER: (1) callback thiếu
strict payment identity (type/zp_trans_id/app_id, conflict callback-vs-query);
(2) Start có thể mở provider transaction THỨ HAI cho quote đã có money-real
evidence; (3) conflict chưa structured + sticky; (4) recovery exhaustion chưa
explicit trong DB/log. Chi tiết quyết định:
[DEC-TASKEDS9T5-004](../../records/decisions/DEC-TASKEDS9T5-004.md); bằng chứng
contract chính thức (fetch lại docs.zalopay.vn verbatim):
[provider-contract-round4.md](../../evidence/TASK-EDS9T5/provider-contract-round4.md).

- **Production**:
  - B1 — `IpnProcessor`: gate `type === 1` ĐẦU TIÊN (envelope, ngoài signed
    data; 1=Order/2=Agreement theo docs) ⇒ sai ⇒ INVALID (rc 2) zero-mutation;
    gate app_id defensive (MAC key2 ký toàn bộ data đã binding app_id — minh
    bạch trong evidence — vẫn so khớp configured để lỗi config không poison
    state); parse strict trichotomy amount/zp_trans_id (absent/zero ⇒ v2/query;
    positive ⇒ direct; malformed ⇒ INVALID, KHÔNG cast-mù "abc"⇒0); finalize
    tự động ĐÒI zp_trans_id>0 proven — verified money không identity ⇒
    quarantine `provider_transaction_unavailable`; callback id ≠ query id ⇒
    quarantine `provider_transaction_conflict` giữ CẢ HAI id.
  - B2 — `PaymentAttemptManagement` + `PaymentAttemptRepository::
    getBlockingAttemptByQuoteId` (interface mới, OR-filter structured, LIMIT 1):
    dưới quote FOR UPDATE lock, TRƯỚC reuse/stale/mint — PAID/FINALIZED/
    quarantined ⇒ KHÔNG attempt mới KHÔNG provider transaction mới, message
    customer-safe (quarantine ⇒ "under review — do not pay twice"); KHÔNG gọi
    OrderFinalizer khi giữ quote lock. INITIATED chưa expire (dưới lock) = in-flight
    của Start khác ⇒ refuse "being initialized" thay vì stale-mark ⇒ tối đa 1
    provider transaction/payment (#22). FAILED/STALE/EXPIRED THẬT unpaid vẫn retry được.
  - B3 — `PaymentAttemptLifecycle`: PAID + FAIL ⇒ giữ PAID + quarantine
    `provider_state_conflict`; late-PAID trên FAILED/STALE/EXPIRED ⇒
    `late_paid_terminal_state` (structured, status không broadened, id backfill);
    STICKY — `markRequiresReconciliation` giữ code ĐẦU TIÊN, grep chứng minh
    KHÔNG đường production xóa flag.
  - B4 — `PaymentRecovery` + schema 1.3.0: cột `recovery_exhausted`
    (declarative + whitelist + interface/model); claim UPDATE 1 statement
    (`IF(recovery_attempts >= max, 1, recovery_exhausted)` — MySQL SET trái→phải
    thấy giá trị đã tăng) + WHERE + selection chặn hàng exhausted ⇒ không còn
    cron query tự động; log CRITICAL khi cạn budget; marker OPERATIONAL —
    KHÔNG money-real, KHÔNG quarantine, IPN hợp lệ sau đó vẫn resolve.
- **Validation** (docker PHP 8.3.20, `.ai/evidence/TASK-EDS9T5/command-output.txt` § round-4):
  - Unit ZaloPay: **188/188 pass, 669 assertions** (157 → +31, toàn bộ test cũ giữ nguyên).
  - Full suite: **976 tests / 7 errors** — 7 lỗi pre-existing Tracking, GIỐNG
    base 3b26189e (849/7), round 2 (900/7), round 3 (945/7); 0 regression.
  - php -l: ALL CLEAN; PHPCS Magento2 severity 10 toàn module: **0 errors**
    (1 warning pre-existing `zalopay.html`, file không đụng).
  - `setup:di:compile`: **OK** (park Mageplaza SocialLogin(Pro) — nguyên nhân
    pre-existing đã chứng minh round 3; restore nguyên vẹn; worktree chỉ còn
    đúng 17 entry của round 4).
  - §19 audit (CodeGraph + grep): `->placeOrder(` production DUY NHẤT =
    `OrderFinalizer.php:235` dưới grant; KHÔNG `QuoteManagement::submit`;
    guard chặn mọi placeOrder generic cho quote ZaloPay. Đường quote-has-PAID ⇒
    Start ⇒ provider transaction thứ hai: KHÔNG TỒN TẠI — consumer duy nhất của
    `getBlockingAttemptByQuoteId` = `PaymentAttemptManagement` (trong lock).
    Writers/readers: `requires_reconciliation`/`reconciliation_code` single-writer =
    Lifecycle; `markPaid` chỉ Lifecycle, `markFinalized` chỉ Finalizer;
    `recovery_attempts`/`recovery_exhausted` chỉ claim SQL của PaymentRecovery;
    `order_id` chỉ `markFinalized` (setOrderId của RefundCommand là object
    refund transaction của Sales, không phải attempt row).
  - Integration suite: ENVIRONMENT-BLOCKED — KHÔNG claim PASS.
- **Status: done (corrective round 4)** — corrective commits trên
  `task/zalopay-payment-first`, push GitHub (origin). KHÔNG merge.

## Kết quả corrective round 5 (small corrective trên reviewed HEAD 1c6fe65b — 2026-09-10)

TL review HEAD `1c6fe65b` kết luận 2 blocker nhỏ; fix KHÔNG đổi thiết kế round 4:

1. **Blocker 1 — signature OR-filter sai trên AbstractDb**:
   `getBlockingAttemptByQuoteId` dùng form `[['attribute' => …]]` (EAV-only).
   Trên `AbstractCollection` (Db) form này phá vỡ lúc build query (thực nghiệm:
   `Array to string conversion` trong `quoteIdentifier`). Đổi sang signature
   song song hợp lệ: `addFieldToFilter([payment_status, requires_reconciliation],
   [['in' => [paid, finalized]], ['eq' => 1]])` ⇒
   `quote_id = ? AND (payment_status IN ('paid','finalized') OR requires_reconciliation = 1)`;
   giữ quote_id filter + entity_id DESC + LIMIT 1 + structured-flags-only.
2. **Bug 2 — sticky quarantine có thể mất**: `recordVerifiedFailure` PAID-branch
   bỏ qua kết quả `markRequiresReconciliation` ⇒ flag flip false→true với
   last_error đã đúng message ⇒ return false ⇒ không save. Fix: tổng hợp
   `$changed` (pattern chung). Audit 6 mutator callsites còn lại: KHÔNG có
   instance thứ hai của class-bug.

REAL-QUERY test (mức collection/SQL thật, không chỉ mock):
`PaymentAttemptRepositoryTest` — collection thật + `Pdo\Mysql` partial
(chỉ stub `select`/`_connect`/`_quote`) ⇒ production method render WHERE parts
thật: `` `quote_id` = 42 ``, `` (`payment_status` IN('paid','finalized')) OR
(`requires_reconciliation` = 1) ``, KHÔNG 'failed'/'expired'/'stale'.
Two-direction proof: cả 3 test mới FAIL/ERROR trên code cũ (signature khác;
Array-to-string; save không gọi), PASS sau fix.

Validate: ZaloPay unit **192/690 OK** (188/669 → +4); php -l 5/5; PHPCS
Magento2 severity=10 **0E/0W** (112 PHP file); setup:di:compile PASS
(park Mageplaza — pre-existing); full regression **980/3659/7 errors**
(baseline 976/7, +4, 7 errors Tracking pre-existing). DB integration (live
MySQL): ENVIRONMENT_BLOCKED — không claim; mức thật nhất khả dụng trong môi
trường = real-collection SQL ở trên.

- **Status: done (corrective round 5)** — commit trên
  `task/zalopay-payment-first`, push GitHub (origin). KHÔNG merge.

## Addendum (continuation 2026-09-11): DB integration thật

Trạng thái "ENVIRONMENT_BLOCKED" ở validate bên trên đã bị thay thế: MariaDB
thật của stack (slaunchpad-db-1) available trong phiên continuation, nên
`getBlockingAttemptByQuoteId()` đã chạy end-to-end trên DB throwaway
`zalopay_r5_it` qua `Pdo\Mysql` thật — **15/15 PASS** (PAID/FINALIZED/
requires_reconciliation chặn; FAILED/EXPIRED/STALE + recovery_exhausted đơn
thuần không chặn; entity_id DESC + LIMIT 1 xác nhận trên SQL thật; row
hydrated round-trip đúng). Query captured từ repository:

`WHERE (quote_id = '990101') AND ((payment_status IN('paid','finalized'))
OR (requires_reconciliation = 1)) ORDER BY entity_id DESC LIMIT 1`

Evidence + repro: `.ai/evidence/TASK-EDS9T5/db-integration/` (README,
run.sh, run_it.php, ddl.sql, final-output.txt, classes/). Worktree sạch
trước/sau; DB `magento` shared không đụng; KHÔNG merge.
