# Evidence — TASK-T78YH6 (Phase E-C0 carrier address handoff, ShippingCore)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "CarrierAddressHandoff|DestinationContextBuilder"
OK — 22 tests, 62 assertions, 0 failure/error (5 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 261 tests, 786 assertions (0 failure/error; 5 deprecations pre-existing)
```

Test mới (`Secomm_ShippingCore/Test/Unit/Model/Address/`):

- `DestinationContextBuilderTest` (5): map destination + bridge-resolved identity (region 521/
  city_id 12345 → resolveFromRuntime(521, 12345) → scheme/unit vào context) · bridge unresolved →
  null source identity (không throw) · candidateCodes luôn [] dù destination mang candidate-like
  field (willReturnMap city_id + candidate_codes) · street không dòng hợp lệ → null · ids 0
  pass-through cho bridge tự validate (resolveFromRuntime(0, 0)).
- `CarrierAddressHandoffTest` (9): 4 shape (resolved / unresolved±fallback / not-applicable) +
  5 invariant reject (resolved+fallback, resolved+reason, unresolved sai reason, not-applicable
  có address, not-applicable thiếu reason); assert contract KHÔNG có `isResolved()` member.
- `CarrierAddressHandoffServiceTest` (8): delegate-once qua manager (builder context + capability
  instance) · non-VN `UnsupportedDestinationException` → applicable=false + UNSUPPORTED_DESTINATION
  (carrier không catch) · EXACT · MAPPED · AMBIGUOUS ± fallback · UNMAPPED ± fallback —
  resolvedAddress null cho unresolved (không auto-select), fallback chỉ ALLOW.

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 17 FAIL, 0 WARN — 0 finding TASK-T78YH6 (15 baseline + 2 BUG records stream
song song, ngoài scope).
```

## Verification greps

```text
$ grep -rn "ExternalAddressResolverPool|PROVIDER_MAPPING|PROVIDER_API|VnAdminAddressResolver|MappingCandidateFinder" \
    Model/Address/{CarrierAddressHandoffService,DestinationContextBuilder}.php   → 0 hit
  (single resolution path qua manager; external pool không invoke; không provider-stage reason)
$ git status app/code/Secomm/{Ghtk,Ahamove} app/code/Mageplaza → 0 (0 carrier/Mageplaza code)
```

## Files changed (đề xuất commit — KHÔNG tự commit)

- `Api/Address/{DestinationContextBuilderInterface, CarrierAddressHandoffInterface, CarrierAddressHandoffServiceInterface}.php` (mới)
- `Model/Address/{DestinationContextBuilder, CarrierAddressHandoff, CarrierAddressHandoffService}.php` (mới)
- `etc/di.xml` (2 preference)
- `Test/Unit/Model/Address/{3 test files}` (mới)
- `README.md` + `CHANGELOG.md` (0.7.0)
- Governance: SPEC + plan + TASK record + FEAT-YA2C0W ticket_ref + CURRENT_STATE
