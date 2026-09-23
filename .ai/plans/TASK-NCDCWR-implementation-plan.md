# Kế hoạch triển khai: TASK-NCDCWR — MoMo payment-action config key alignment (MOMO-05)

| Field | Value |
|---|---|
| Specification | Full Spec — [SPEC-TASK-NCDCWR-momo-payment-action-config-key.md](../specs/SPEC-TASK-NCDCWR-momo-payment-action-config-key.md) (VALID) |
| Record | [TASK-NCDCWR](../records/tasks/TASK-NCDCWR.md) · Issue github:thanhle74/slaunchpad#17 |
| Workflow mode | A (payment capture configuration → high-risk, Tier 2; Owner authorization on issue #17) |
| Risk | high — mitigation: chỉ đổi key contract (1 XML field id + 1 constant + 1 reference), behavior gate giữ nguyên strict-comparison; legacy-install behavior provably unchanged; regression suite 253 tests làm gate |
| Date | 2026-09-21 · RUN_ID RUN-20260921-TASKNCDCWR-ad6e2d · BASE `ad6e2d7f` · host thanhle-aloha |

## 1. Hướng tiếp cận

Canonical key = `payment/momo_payment/payment_action` (chuẩn Magento —
`Order\Payment::place()` + `OrderFinalizer` đã đọc key này; config.xml default
đã đứng đúng). Chỉ đổi PHẦN VIẾT + dọn dead constant:

1. **system.xml** — field id `momo_payment_action` → `payment_action`
   (label/source model/sortOrder/showIn* giữ nguyên).
2. **Model/Config.php** — `KEY_PAYMENT_ACTION = 'payment_action'`.
3. **OrderFinalizer.php** — `captureOrder()` dùng `Config::KEY_PAYMENT_ACTION`
   thay raw literal; import `Secomm\MoMo\Model\Config`; docblock ghi contract
   key (`payment/momo_payment/payment_action`, MOMO-05).
4. **Tests** — `OrderFinalizerTest` +2 (non-capturing action → finalize
   WITHOUT capture; null → no capture, vẫn finalize). Existing mocks giữ
   nguyên.
5. **Docs** — README bảng Config (dòng Payment Action + legacy note);
   CHANGELOG `[2.3.2]` MOMO-05.
6. **Validation** — xmllint → php -l → PHPCS Magento2 ≥6 (changed files) →
   PHPUnit full `Secomm\MoMo` → `setup:di:compile` (container m2r-php,
   throwaway /tmp/m2r) → `project-ai-validate`; evidence vào
   `.ai/evidence/TASK-NCDCWR/`.
7. **Handoff** — commit local; KHÔNG push (chờ user yêu cầu — global git
   rule); KHÔNG merge; TL review TIP trước khi integrate
   `dev/development/thanhle`.

## 2. Rủi ro & biện pháp

| Rủi ro | Biện pháp |
|---|---|
| Admin value mới vô hiệu (fix không "ăn") | Test khẳng định config value điều khiển capture gate; DI compile + manual admin save check (QC) |
| Legacy install đổi behavior | Provably unchanged (spec §2.2: zero-reader + single-option proof); AC4 documented |
| Regression capture path (tiền thật) | Strict comparison giữ nguyên; existing test `testPlacesOrderFromVerifiedQuote` phải pass không sửa assertion |
| Quá scope | Mutation scope: 3 code files + 1 test + 2 docs + artifacts/evidence |

## 3. Non-scope

MOMO-04 classification; provider API semantics; refund redesign; schema/
migration; source-model option expansion; ZaloPay; Bitbucket sync.

## 4. Outcomes / handoff

- Commit local trên branch `thanhle74/momo-momo-05-align-payment-action-config`
  @ BASE `ad6e2d7f`; evidence đầy đủ trong `.ai/evidence/TASK-NCDCWR/`.
- Báo user: READY_FOR_REVIEW (branch/base/TIP + evidence tóm tắt); push +
  comment lên issue #17 chỉ khi user yêu cầu.
