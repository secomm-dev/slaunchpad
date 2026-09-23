# Kế hoạch triển khai: TASK-3083BD — MoMo ReturnProcessor resultCode fail-safe (MOMO-04)

| Field | Value |
|---|---|
| Specification | Full Spec — [SPEC-TASK-3083BD-momo-returnprocessor-resultcode-fail-safe.md](../specs/SPEC-TASK-3083BD-momo-returnprocessor-resultcode-fail-safe.md) (VALID) |
| Record | [TASK-3083BD](../records/tasks/TASK-3083BD.md) · Issue github:thanhle74/slaunchpad#16 |
| Workflow mode | A (payment state classification → high-risk, Tier 2; Owner authorization GRANTED trên issue #16) |
| Risk | high — mitigation: chỉ đổi ĐỘ PHÂN LOẠI, mutation path (lifecycle + finalizer) giữ nguyên; classifier extract là pure logic + full regression suite 200 tests |
| Date | 2026-09-21 · RUN_ID RUN-20260921-TASK3083BD-887acd · BASE `a87255f8` · host thanhle-aloha |

## 1. Hướng tiếp cận

Return đang dùng legacy classification "non-zero = failure". Thay bằng shared
fail-safe classifier (issue DESIGN CONSTRAINT — tránh copy thứ hai của MOMO-03
semantics):

- `Service/PurchaseQueryOutcome` — value object: category (`PAID/PENDING/
  FINAL_FAILURE/AMBIGUOUS`) + resultCode đã parse (`?int`) + reason
  (unparseable/request-system/unmapped cho AMBIGUOUS; null cho 3 category kia).
- `Service/PurchaseQueryClassifier::classify(mixed): PurchaseQueryOutcome` —
  4 allowlist private, parse grammar `is_scalar + trim + ^-?\d+$`, precedence
  PAID → PENDING → REQUEST_ERROR → FINAL_FAILURE → **default AMBIGUOUS**.
- `ReturnProcessor` — inject classifier, route theo category: PENDING →
  "still being processed" (giữ message); AMBIGUOUS → "could not be verified
  right now" + log context, ZERO mutation; FINAL_FAILURE → giữ nguyên
  `recordAuthoritativeFailure`; PAID → giữ nguyên `finalizeVerifiedPaid`.
- `PaymentRecovery` — inject classifier, thay 4 const + parse block; giữ nguyên
  3 log messages phân biệt AMBIGUOUS cause qua `getReason()`.

Các bước:

1. **Classifier** — `Service/PurchaseQueryOutcome.php` (VO) +
   `Service/PurchaseQueryClassifier.php` (allowlists + classify()).
2. **ReturnProcessor** — refactor route + docblock (header items 4–6).
3. **PaymentRecovery** — refactor recoverAttempt + docblock.
4. **Tests** — `PurchaseQueryClassifierTest` (table-driven AC1–AC8);
   `ReturnProcessorTest` (+classifier dep, `700`→`1001`, 6 test mới:
   1000-pending, 9000-paid, 10-ambiguous, missing-ambiguous,
   unparseable-ambiguous, unknown-ambiguous); `PaymentRecoveryTest`
   (+classifier dep real instance).
5. **Docs** — README.md (mục classifier), CHANGELOG.md (entry MOMO-04).
6. **Validation** — php -l → PHPCS ≥6 → PHPUnit full `Secomm\MoMo` →
   setup:di:compile (container m2r-php, throwaway /tmp/m2r) →
   project-ai-validate; evidence vào `.ai/evidence/TASK-3083BD/`.
7. **Handoff** — commit local, non-force push branch
   `thanhle74/momo-momo-04-returnprocessor-resultcode-fail-safe` (theo
   OWNER_AUTHORIZATION issue #16), comment READY_FOR_REVIEW với branch/base/TIP.

## 2. Rủi ro & biện pháp

| Rủi ro | Biện pháp |
|---|---|
| Regression paid-path (tiền thật) | `finalizeVerifiedPaid` giữ nguyên từng guard (amount → transId → lifecycle); tests hiện có + test 9000 mới phải pass không sửa assertions |
| Classifier sai precedence làm fail nhầm | Default clause là AMBIGUOUS (fail-safe theo cấu trúc); table-driven tests phủ mọi allowlist + unmapped + missing + unparseable |
| Drift MOMO-03 semantics | Single source: 2 callers cùng 1 class; PaymentRecoveryTest hiện có (16 tests) là regression gate |
| Quá scope | Mutation scope lock: 2 class mới + 2 service + 3 test + docs/evidence/artifacts; không đụng refund/IPN/schema/config |

## 3. Non-scope

MOMO-05 config-key drift; refund semantics; IPN path; schema/migration; new
payment states; checkout UX; generic retry framework; ZaloPay; Bitbucket sync.

## 4. Outcomes / handoff

- READY_FOR_REVIEW comment lên issue #16 (branch/base/TIP + evidence tóm tắt).
- Không merge; không push Bitbucket; TL review TIP trước khi integrate vào
  `dev/development/thanhle`.
