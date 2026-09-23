# SPEC-TASK-3083BD — MoMo ReturnProcessor resultCode fail-safe classification (MOMO-04)

Specification ID: SPEC-TASK-3083BD
Specification Level: FULL

- Issue: github:thanhle74/slaunchpad#16
- Lane: MoMo · Epic #1 · Depends on #5 (DONE — MOMO-03 đã chuẩn hoá purchase-query semantics trong `PaymentRecovery`)
- Mode: A (payment state transitions → high-risk, Tier 2 — Owner authorization GRANTED trên issue #16)
- Risk: high (authoritative browser-Return money-state classification)
- Branch: `thanhle74/momo-momo-04-returnprocessor-resultcode-fail-safe`
- BASE: `a87255f8c895b6f59ae23bb3ce776aa3d665a430` (verified: HEAD == BASE khi start, worktree clean)
- RUN_ID: RUN-20260921-TASK3083BD-887acd · host thanhle-aloha

## 1. Goal

Browser Return của payment-first (MOMO-01) hiện phân loại kết quả `v2/query` theo
quy tắc legacy "non-zero = failure": mọi resultCode ngoài `0/7000/7002` — kể cả
`1000`, `9000`, request/system codes, missing/unparseable, và mã chưa tài liệu hoá —
đều rơi vào `recordAuthoritativeFailure()` ⇒ **false-fail mất tiền**: attempt bị
đánh FAILED cho tiền thực tế đang xử lý/đã trả. Task này thay phân loại đó bằng
**fail-safe classification nhất quán với MOMO-03**: chỉ explicit allowlist
provider-documented mới được phép kết luận, mọi thứ khác → AMBIGUOUS (không
mutation, khách được mời check back/retry).

## 2. Current state (verified at BASE `a87255f8`)

- `Service/ReturnProcessor.php:109` — missing resultCode bị coerce `(int)$query['resultCode'] ?? -1`
  rồi rơi vào failure branch (`-1` cũng là unmapped code, không có nghĩa "failure").
- `ReturnProcessor.php:57` — `QUERY_PENDING = [7000, 7002]` (thiếu `1000` initiated).
- `ReturnProcessor.php:123` — PAID chỉ `resultCode === 0` (thiếu `9000` authorized
  cho contract 1-step `captureWallet`/default autoCapture=true).
- `ReturnProcessor.php:127` — mọi code khác → `recordAuthoritativeFailure()`
  (không có AMBIGUOUS branch; request/system `10–47` và unmapped đều false-fail).
- `Service/PaymentRecovery.php` (MOMO-03, đã review) chứa đúng semantics đích:
  PAID `[0, 9000]`, PENDING `[1000, 7000, 7002]`, FAILURE allowlist
  `[98, 99, 1001–1007, 1017, 1026, 2019, 4001, 4002, 4100]`,
  REQUEST_ERROR `[10–13, 20–22, 40–43, 45, 47]`, unmapped/unparseable/exception →
  AMBIGUOUS. Đây là bản copy private thứ nhất — ReturnProcessor cần bản copy thứ
  hai nếu không extract.
- Test hiện tại: `ReturnProcessorTest` dùng resultCode `700` làm ví dụ failure —
  `700` không nằm trong allowlist final-failure → sẽ được migrate sang `1001`.
- Guards không đổi: amount lock với frozen snapshot, positive-transId, echo
  identity trong `QueryValidator` (bên trong command), lifecycle guards
  (không regress PAID/FINALIZED).

## 3. Design decisions

1. **Fail-safe classifier tách chung (issue DESIGN CONSTRAINT)**: extract một class
   nhỏ `Service/PurchaseQueryClassifier` + value object `Service/PurchaseQueryOutcome`
   là **single source of truth** cho phân loại PURCHASE query outcome, dùng chung
   `ReturnProcessor` + `PaymentRecovery`. Lý do: hai bản copy private allowlist là
   chính là drift risk mà MOMO-03 correction round 2 phải sửa ở tầng tài liệu; examiner
   chi phí extract nhỏ (2 callers, có regression tests sẵn 200 tests) trong khi lợi ích
   là bất biến "unmapped ≠ FAILED" được đảm bảo một chỗ. Trả về value object (category
   + resultCode đã parse + reason) để `PaymentRecovery` giữ được 3 log messages riêng
   cho các cause AMBIGUOUS (unparseable/request-system/unmapped) — log distinction là
   evidence cho TL/ops, không được mất khi refactor.
2. **Parse grammar chung**: `is_scalar + trim + ^-?\d+$` → `?int` (mirror MOMO-03).
   Missing/unparseable ⇒ AMBIGUOUS, không bao giờ `-1`-coerce.
3. **Precedence classify**: PAID → PENDING → REQUEST_ERROR (AMBIGUOUS) →
   FINAL_FAILURE → default AMBIGUOUS. Default cuối cùng **luôn** AMBIGUOUS.
4. **ReturnProcessor giữ nguyên kiến trúc mutation**: mọi mutation vẫn qua
   `PaymentAttemptLifecycle`; order duy nhất qua `OrderFinalizer`; success session
   qua `SuccessSessionPreparer`. Chỉ thay đổi ĐỘ PHÂN LOẠI trước khi route.
5. **Customer-safe outcomes**: PENDING (`1000/7000/7002`) → thông điệp hiện tại
   "still being processed. Please check back shortly." (giữ nguyên, `1000` gia nhập
   branch); AMBIGUOUS → thông điệp verification-unavailable/retry ("could not be
   verified right now", trùng message với query-transport failure) — không mutation,
   không order, không fail.
6. **Không đụng refund semantics** (`RefundResultClassifier` — riêng, `9000` nghĩa
   khác), không đụng IPN path (`IpnProcessor` dùng NotifyValidator/lifecycle riêng),
   không schema, không config key.

## 4. Components

| File | Thay đổi |
|---|---|
| `Service/PurchaseQueryOutcome.php` | NEW — value object: category constants (`PAID/PENDING/FINAL_FAILURE/AMBIGUOUS`), reason constants (`REASON_UNPARSEABLE/REASON_REQUEST_SYSTEM/REASON_UNMAPPED`), `getCategory(): string`, `getResultCode(): ?int`, `getReason(): ?string` |
| `Service/PurchaseQueryClassifier.php` | NEW — 4 allowlist constants (private) + `classify(mixed $raw): PurchaseQueryOutcome` |
| `Service/ReturnProcessor.php` | Inject classifier; xoá `QUERY_PENDING` const + int-coerce; route theo category; PENDING message giữ nguyên + `1000`; AMBIGUOUS → throw verification-unavailable, ZERO mutation; FINAL_FAILURE → `recordAuthoritativeFailure` (giữ nguyên); PAID → `finalizeVerifiedPaid` (giữ nguyên guards); update class docblock |
| `Service/PaymentRecovery.php` | Inject classifier; xoá 4 private allowlist consts + parse block; `recoverAttempt` route theo category + log per reason (3 messages giữ nguyên ngữ nghĩa); docblock update |
| `Test/Unit/Service/PurchaseQueryClassifierTest.php` | NEW — table-driven AC1–AC8 classification |
| `Test/Unit/Service/ReturnProcessorTest.php` | Constructor + classifier; `700` → `1001` (2 tests); +tests: 1000→pending, 9000→paid, 10→ambiguous, missing→ambiguous, unparseable→ambiguous, unknown 424242→ambiguous |
| `Test/Unit/Service/PaymentRecoveryTest.php` | Constructor + classifier (real instance — pure logic) |
| `README.md`, `CHANGELOG.md` | mục classifier + entry MOMO-04 |

## 5. Flow (sau thay đổi)

```
ReturnAction.execute → ReturnProcessor.process(params)
  ├─ order_ref rỗng / attempt không tồn tại → customer-safe refusal (giữ nguyên)
  ├─ queryTransaction (command `query_transaction`, echo validation trong command)
  │    └─ exception → AMBIGUOUS: "could not be verified right now", ZERO mutation (giữ nguyên)
  ├─ outcome = PurchaseQueryClassifier.classify(query['resultCode'] ?? null)
  ├─ PAID (0, 9000) → finalizeVerifiedPaid:
  │    amount lock → positive-transId → recordVerifiedPaid → (PAID/FINALIZED check)
  │    → finalizeOrRecover → SuccessSessionPreparer → 'checkout/onepage/success'
  ├─ PENDING (1000, 7000, 7002) → throw "still being processed. Please check back shortly."
  │    (ZERO mutation — IPN/return sau/cron MOMO-03 resolve)
  ├─ FINAL_FAILURE (98, 99, 1001–1007, 1017, 1026, 2019, 4001, 4002, 4100)
  │    → recordAuthoritativeFailure (lifecycle; FINALIZED-recovery branch giữ nguyên)
  └─ AMBIGUOUS (10–13, 20–22, 40–43, 45, 47 + missing + unparseable + unmapped)
       → log error có context (reason + resultCode khi parse được) →
         throw "could not be verified right now. Please try again or contact support."
         (ZERO mutation, ZERO order, ZERO fail)
```

## 6. Acceptance criteria (issue #16) → cơ chế + kiểm chứng

- **AC1** `1000` → pending/no mutation → `ReturnProcessorTest`: expect "still being processed", lifecycle never.
- **AC2** `7000/7002` → pending/no mutation → 2 tests hiện có (giữ nguyên).
- **AC3** `9000` → paid path như `0` → test: finalizer đúng 1 lần với transId + success session.
- **AC4** `10` → ambiguous/no failure mutation → test: lifecycle never + "could not be verified".
- **AC5** missing resultCode → ambiguous/no mutation → test.
- **AC6** unparseable (`"12abc"`) → ambiguous/no mutation → test.
- **AC7** unknown `424242` → ambiguous/no mutation → test.
- **AC8** `1001` → `recordVerifiedFailure`, no order → test (migrate từ `700`).
- **AC9** success session rebuild giữ nguyên → test paid-path hiện có.
- **AC10** browser GET params non-authoritative → kiến trúc không đổi; test existing (query-only evidence).
- **AC11** concurrency safety → lifecycle/finalizer không đụng đến; tests hiện có (paid-on-terminal, failure-after-finalize) giữ nguyên pass.
- **AC12** php -l + PHPCS ≥6 + PHPUnit `Secomm\MoMo` + `setup:di:compile` pass → evidence.

## 7. Assumption / limitation log (cho TL review)

- `9000` PAID áp cho contract hiện tại (1-step `captureWallet`, default
  autoCapture=true) — nếu config `payment_action` chuyển sang 2-step, map này cần
  re-verify (đã ghi trong MOMO-03; MOMO-05 theo dõi config-key drift).
- AMBIGUOUS trên Return KHÔNG retry tự động (khác cron MOMO-03) — khách là trigger
  retry (hit return lại / IPN / cron). Đúng issue REQUIRED BEHAVIOR.
- Log AMBIGUOUS ở mức `error` (đồng bộ PaymentRecovery) — không `critical` vì không
  phải money-real anomaly, nhưng đủ nổi để diagnose.
- Classifier chỉ phục vụ PURCHASE query; refund classifier riêng biệt (9000 khác
  nghĩa) — không merge.

## 8. Validation plan

1. php -l toàn bộ file PHP mới/sửa.
2. PHPCS Magento2 severity ≥ 6 trên các file đã đổi.
3. PHPUnit `Secomm\MoMo` full suite (baseline 200/562 + deprecations pre-existing).
4. `setup:di:compile` (throwaway env, container m2r-php).
5. `bin/project-ai-validate --check-specs --check-identity --check-records` —
   0 finding cho TASK-3083BD.
6. Evidence: `.ai/evidence/TASK-3083BD/` (php-lint, phpcs, phpunit, compile, validators).
7. E2E sandbox MoMo: không chạy (credentials không có trên host — manual QA path).
