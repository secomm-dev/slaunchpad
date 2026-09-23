# Evidence — TASK-NAT3YV r1 + TASK-XXBN5X r2 (TL/SA amendment: zero-rate alignment + shared failure-reason owner)

Ngày: 2026-09-08 · Amendment trên contracts đang mở (trước closure/freeze).

## Part A — `FallbackRate` amount >= 0 (TASK-XXBN5X r2)

- `Model/Fallback/FallbackRate.php`: invariant `> 0` → **`< 0` reject**; zero = valid explicit
  rate; docblock: policy cấm zero-fallback (nếu cần) thuộc project/Launchpad fallback policy,
  không phải core VO invariant.
- `Api/Fallback/{FallbackRateInterface, FallbackRateProviderInterface}.php`: docblock — null từ
  provider là cách DUY NHẤT diễn đạt "no fallback rate"; 0đ không phải no-match sentinel.
- `FallbackRateTest`: `testRejectsZeroAmount` → **`testZeroAmountIsValidExplicitRate`** (0.0 +
  label valid); negative reject giữ nguyên (`must not be negative`); positive transport giữ
  nguyên. Provider no-match test (`provider returns null → null`) giữ nguyên ở PoolTest.

## Part B — `Api\Failure\ShippingFailureReason` (TASK-NAT3YV r1)

- **MỚI** `Api/Failure/ShippingFailureReason.php` — ONE canonical owner, 5 shared cross-stage
  constants; docblock ghi orchestration rule (status drives behavior; reason = diagnostic;
  không infer fallback từ reason string); không registry/DB/enum; carrier-specific strings stay
  carrier-owned.
- `CarrierAddressHandoffInterface`: XÓA 2 constants REASON_* → reference
  `ShippingFailureReason::{UNSUPPORTED_DESTINATION, CANONICAL_UNRESOLVED}` (docblock).
- `Model/Address/CarrierAddressHandoff.php` + `CarrierAddressHandoffService.php`: dùng shared
  constants. Semantics KHÔNG đổi (non-VN → UNSUPPORTED_DESTINATION; unresolved →
  CANONICAL_UNRESOLVED).
- `CarrierRateOutcomeInterface`: XÓA 5 constants REASON_* → docblock reference shared owner +
  status/reason rule + auth/config→UNAVAILABLE rule giữ nguyên.
- Tests: swap hết sang `ShippingFailureReason::*`; **XÓA parity test** (mục đích duy nhất là
  chứng minh duplicate bằng nhau); **THÊM** `testStatusRemainsAuthoritativeOverDiagnosticReason`
  (UNAVAILABLE + TECHNICAL_ERROR vẫn UNAVAILABLE, không fallback-triggering) + explicit
  `unavailable(null)` valid assertion.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "CarrierRate|FallbackRate|CarrierAddressHandoff|DestinationContextBuilder"
OK — 51 tests, 133 assertions, 0 failure/error (5 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 283 tests, 832 assertions (0 failure/error; 5 deprecations pre-existing)
```

## Greps

```text
$ grep duplicate reason literals trong Api/ (ngoài ShippingFailureReason.php) → 0 hit
$ grep RateResult|Logger|metadata code lines Api/Rate Model/Rate → 0 hit (giữ nguyên r0)
$ git status Ghtk/Ahamove/Mageplaza → 0 carrier/third-party code change
```

## Build + validator

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 26 FAIL — 0 finding NAT3YV/XXBN5X/T78YH6 (grep = 0). 15 baseline cũ + 11 FAIL
từ BUG records (BUG-AMRBJR/MNEZ92/NY0M3S/TK8C2Y/4WYXCB — checkout translate/UI) do CÁC STREAM
SONG SONG đang draft cùng lúc, ngoài scope amendment này.
```

## Files changed (amendment)

- `Api/Failure/ShippingFailureReason.php` (mới)
- `Model/Fallback/FallbackRate.php` + `Api/Fallback/{FallbackRateInterface, FallbackRateProviderInterface}.php` (Part A)
- `Api/Address/CarrierAddressHandoffInterface.php` + `Model/Address/{CarrierAddressHandoff, CarrierAddressHandoffService}.php` + `Api/Rate/CarrierRateOutcomeInterface.php` (Part B)
- `Test/Unit/Model/Fallback/FallbackRateTest.php` + `Test/Unit/Model/Address/{2}` + `Test/Unit/Model/Rate/CarrierRateOutcomeTest.php`
- `README.md` + `CHANGELOG.md` (0.9.0)
- Governance: SPEC-XXBN5X r2 (Status + §3.4 + AC-4), TASK-XXBN5X Review rounds r2, SPEC-NAT3YV r1 note (Status), TASK-NAT3YV Review rounds r1, CURRENT_STATE sync
