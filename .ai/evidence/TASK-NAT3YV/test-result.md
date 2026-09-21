# Evidence — TASK-NAT3YV (Phase E-C1 carrier rate outcome semantics, ShippingCore)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "CarrierRate"
OK — 14 tests, 33 assertions, 0 failure/error (5 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 275 tests, 819 assertions (0 failure/error; 5 deprecations pre-existing)
```

Test mới (`Test/Unit/Model/Rate/`):

- `CarrierRateTest` (3): positive + currency transport · **zero amount HỢP LỆ** (promotion) ·
  negative → `LogicException`.
- `CarrierRateOutcomeTest` (11): `success()/unavailable()/technicalFailure()` happy ·
  unavailable không/có reason (`PROVIDER_MAPPING_MISSING`) · `TECHNICAL_ERROR` shared reason ·
  reason `'   '` normalize → null · constructor parity với factories · **impossible combos
  reject**: SUCCESS thiếu rate / SUCCESS có reason / UNAVAILABLE có rate / TECHNICAL_FAILURE có
  rate / unknown status (`BUSINESS_REJECTION`) · shared reason values === handoff constants
  (CANONICAL_UNRESOLVED + UNSUPPORTED_DESTINATION — translate giữ nguyên).

Lỗi tự-fix trong review: promoted readonly `$failureReason` bị gán lần 2 trong constructor
(PHP Error) → chuyển `rate`/`failureReason` sang non-promoted readonly gán 1 lần sau guards.

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 17 FAIL, 0 WARN — 0 finding TASK-NAT3YV (15 baseline + 2 BUG records stream
song song, ngoài scope).
```

## Verification greps

```text
$ grep RateResult|Psr\Log|LoggerInterface|metadata (code lines) Api/Rate Model/Rate → 0 hit
  (không Magento rate object dependency, không logger trong VO, không metadata bag — §16/§19)
$ git status app/code/Secomm/{Ghtk,Ahamove} app/code/Mageplaza → 0 (0 carrier/Mageplaza code)
```

## Files changed (đề xuất commit — KHÔNG tự commit)

- `Api/Rate/{CarrierRateInterface, CarrierRateOutcomeInterface}.php` (mới)
- `Model/Rate/{CarrierRate, CarrierRateOutcome}.php` (mới)
- `etc/di.xml` (2 preference)
- `Test/Unit/Model/Rate/{CarrierRateTest, CarrierRateOutcomeTest}.php` (mới)
- `README.md` + `CHANGELOG.md` (0.8.0)
- Governance: SPEC + plan + TASK record + FEAT-YA2C0W ticket_ref + CURRENT_STATE
