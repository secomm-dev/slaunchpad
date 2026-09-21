# Evidence — TASK-M3ME32 (Phase E-SL2 fallback decision — FINAL ShippingCore foundation slice)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ServiceLevelRate"
OK — 36 tests, 101 assertions, 0 failure/error (5 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 319 tests, 933 assertions (0 failure/error; 5 deprecations pre-existing)
```

Test mới:

- `ServiceLevelRateDecisionTest` (10): REALTIME/FALLBACK/UNAVAILABLE shapes · constructor parity ·
  6 invariant rejects (REALTIME thiếu rates / mang fallback; FALLBACK mang realtime / thiếu
  fallback; UNAVAILABLE mang rate; unknown source `CARRIER_RATE`) · zero-rate fallback shape.
- `ServiceLevelRateOrchestratorTest` (12 — registry thật LEVEL_A/LEVEL_OFF + mock policy +
  stub provider + real pool; aggregates dựng qua E-SL1 aggregator thật, contract-fit):
  **matrix §23 đủ 9 hàng** (disabled→UNAVAILABLE; SUCCESS±technical→REALTIME; no-tech→UNAVAILABLE;
  policy-disabled→UNAVAILABLE; zero-provider→UNAVAILABLE không throw; provider-null→UNAVAILABLE;
  fallback rate>0→FALLBACK; **rate=0→FALLBACK**) · **provider invocation rules**: never-call
  ×4 (disabled / SUCCESS / SUCCESS+technical / policy-disabled — policy thậm chí không được
  hỏi khi disabled hoặc SUCCESS), exactly-once ×2 (fallback path + zero-rate path) ·
  >1 provider → `LogicException` (không first-wins — §15) · aggregate/code mismatch →
  `LogicException` · unknown code → `LocalizedException`.

Lỗi tự-fix trong review: orchestrator + test import sai namespace (`FallbackRateProviderPool`
là Model class; `CarrierRate` là Model class); assertSame trên instance mới tạo trong assertion;
decision test gọi factory với sai arity (đổi sang constructor cho invariant-reject cases);
mismatch test dùng level không có trong registry.

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 26 FAIL, 0 WARN — 0 finding TASK-M3ME32 (15 baseline + 11 BUG records các
stream song song, ngoài scope).
```

## Verification greps

```text
$ grep Mageplaza|mptablerate|RateRequest|CarrierAddressHandoff|ResolvedShippingAddress|
      EXPRESS|SAME_DAY|STANDARD (E-SL2 production files)
  → 5 hits TẤT CẢ là substring "RateRequest" của ShippingCore contract FallbackRateRequestInterface
    — 0 Magento Quote RateRequest leak, 0 Mageplaza/carrier/address dependency, 0 hardcoded taxonomy
$ git status Ghtk/Ahamove/Mageplaza → 0 (0 carrier/Mageplaza code)
```

## Files changed (đề xuất commit — KHÔNG tự commit)

- `Api/Fallback/FallbackPolicyInterface.php` (mới) + `Model/Fallback/ConfigurableFallbackPolicy.php` (mới)
- `Api/ServiceLevelRateDecisionInterface.php` (mới) + `Model/ServiceLevel/ServiceLevelRateDecision.php` (mới)
- `Api/ServiceLevelRateOrchestratorInterface.php` (mới) + `Model/ServiceLevel/ServiceLevelRateOrchestrator.php` (mới)
- `etc/di.xml` (2 preference + policy array)
- `Test/Unit/Model/ServiceLevel/{ServiceLevelRateDecisionTest, ServiceLevelRateOrchestratorTest}.php` (mới)
- `README.md` + `CHANGELOG.md` (0.11.0 — final flow + hard stop)
- Governance: SPEC + plan + TASK record + FEAT-YA2C0W ticket_ref + CURRENT_STATE
