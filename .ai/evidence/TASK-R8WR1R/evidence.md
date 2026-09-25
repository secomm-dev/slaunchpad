# Evidence — TASK-R8WR1R (DestinationScope ALL_EXCEPT_SELECTED_ZONES)

## r2 (2026-09-22) — merge-blocker fix: invalid scope fail closed

### Fix
- `CarrierDestinationScopeConfig::getDestinationScope()`: invalid non-empty value → trả VERBATIM
  + warning (message "…failing closed (carrier ineligible)"); bỏ coercion về ALL (fail-open).
  Missing/empty → ALL giữ nguyên (silent — missing ≠ invalid).
- `CarrierRateExecutionRequest`: bỏ `DestinationScope::assertKnown()` khỏi constructor — scope
  lạ phải REACH eligibility step; throw tại construction sẽ biến merchant config error thành
  TECHNICAL path (GHN catch-all record `technicalFailure(UNEXPECTED_RUNTIME_FAILURE)` — vi phạm
  ràng buộc TL). Mode/policy guards giữ nguyên fail-fast.
- Enforcement point: `CarrierEligibilityEvaluator` unknown-scope branch (đã có sẵn) → ineligible
  `DESTINATION_NOT_IN_SCOPE` — không TECHNICAL_FAILURE, không fallback-eligible.

### Tests r2 (7 passed, 24 assertions — targeted run)
- `CarrierDestinationScopeConfigTest`: testMissingScopeUsesDocumentedDefaultWithoutWarning,
  testEmptyScopeUsesDocumentedDefaultWithoutWarning,
  testUnrecognizedScopeReturnedVerbatimWithFailClosedWarning (thay test coercion cũ),
  testInvalidScopeNeverResolvesToAValidScopeValue (fail-open regression guard).
- `CarrierEligibilityEvaluatorTest`: testInvalidPersistedScopeValueCannotBecomeGloballyEligible
  (0 registry/matcher call — invalid scope không bao giờ eligible kể cả zone list khớp).
- `CarrierRateExecutionFlowIntegrationTest`: testInvalidPersistedScopeValueFailsClosedEndToEnd
  (REAL evaluator + REAL request + REAL service; scope 'SOME_ZONES' + zone list MATCH destination
  → vẫn không realtime, không fallback, reason DESTINATION_NOT_IN_SCOPE, final SOURCE_UNAVAILABLE).

### Full ShippingCore suite sau r2
```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Secomm/ShippingCore/Test/Unit
→ OK — Tests: 462, Assertions: 1212 (đã gồm test mới của TASK-G3K9V2 đã merge riêng)
```

### GHN consumer suite sau r2 (GHN không bị sửa — regression check)
```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Secomm/Ghn/Test/Unit
→ OK — Tests: 373, Assertions: 210947
```

### DI compile sau r2
```
bin/magento setup:di:compile → exit=0
"Generated code and dependency injection configuration successfully."
→ r1 blocker (CanonicalCsvProvider thiếu getByLevel/getByRegion) ĐÃ được TASK-G3K9V2 fix song song.
```

### Validator sau r2
```
.ai/bin/project-ai-validate --check-records --check-specs
→ result (project): 50 FAIL, 0 WARN — baseline legacy pre-existing KHÔNG đổi; 0 FAIL/WARN thuộc TASK-R8WR1R.
```

### php -l
6 file changed (config reader, request, 2 test files + docs) → all clean.

---

# r1 (2026-09-22) — original delivery

Date: 2026-09-22 · Branch: development · Mode C · Machine: WSL2, PHP 8.2, Magento 2.4.8-p5

## Commands + results

### 1. ShippingCore unit suite (module-scoped)
```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Secomm/ShippingCore/Test/Unit
→ OK — Tests: 436, Assertions: 1146, PHPUnit Warnings: 1 (allure config missing — pre-existing env noise)
```

### 2. Targeted new-test run
```
vendor/bin/phpunit ... --filter 'AllExcept|ExactlyThreeKnownScopes|AllExceptSelectedZones'
→ OK — Tests: 13, Assertions: 31
```

### 3. Full unit testsuite (all app/code, unfiltered baseline)
```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --testsuite Magento_Unit_Tests_App_Code
→ Tests: 20732, Assertions: 270908, Errors: 351, Failures: 3, Skipped: 125
→ 0 lỗi thuộc Secomm\ShippingCore / Secomm\Ghn / Launchpad scope do task này đụng tới.
→ TableRate Integration matrix (14 errors) + Magento core errors = PRE-EXISTING của working tree
  (in-flight uncommitted TASK-SEC / VietNamAddress work — gitStatus đầu session đã list các file
  FallbackCoordinator.php, VnAddressUnitProviderInterface.php… modified).
→ Counterfactual proof: stash 2 behavior file (DestinationScope, CarrierEligibilityEvaluator) →
  chạy lại TableRate Integration matrix → KẾT QUẢ GIỐNG HỆT (Tests: 15, Errors: 14, Assertions: 2).
  Thay đổi này không ảnh hưởng matrix test nào.
```

### 4. Syntax
```
php -l trên toàn bộ 6 file PHP changed → all clean
```

### 5. DI compile
```
bin/magento setup:di:compile → exit 255
Fatal: Secomm\Ghn\Model\Address\Mapping\CanonicalCsvProvider thiếu getByLevel/getByRegion của
Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface.
PRE-EXISTING: interface LÀ file modified uncommitted (in-flight); CanonicalCsvProvider là file
committed chưa được update — không liên quan diff này (compile log 0 mentions "ShippingCore").
→ Blocker chung của cả tree, cần owner task VietNamAddress/GHN dataset cập nhật CanonicalCsvProvider.
```

### 6. Project validator
```
.ai/bin/project-ai-validate --check-records --check-specs
→ result: 50 FAIL, 0 WARN (INVALID)
→ 0 FAIL/WARN thuộc TASK-R8WR1R (grep count = 0). Baseline không có record này vẫn y hệt 50 FAIL
  (legacy records cũ: BUG mini-spec thiếu section, SPEC naming mismatch, plan thiếu Specification row).
```

## Changed files (code)
- app/code/Secomm/ShippingCore/Api/Address/DestinationScope.php — +ALL_EXCEPT_SELECTED_ZONES
- app/code/Secomm/ShippingCore/Model/Rate/CarrierEligibilityEvaluator.php — +evaluateAllExceptSelectedZones
- app/code/Secomm/ShippingCore/Model/Config/CarrierDestinationScopeConfig.php — whitelist exists(), diagnostics cả 2 mode, hint theo mode
- Docblock: Api/Rate/CarrierEligibilityEvaluatorInterface.php, Api/Rate/CarrierRateExecutionRequestInterface.php, Api/Config/CarrierDestinationScopeConfigInterface.php, Api/Failure/ShippingFailureReason.php

## Changed files (tests)
- Test/Unit/Model/Address/DestinationScopeTest.php — 3-scope set + rejects variants
- Test/Unit/Model/Rate/CarrierEligibilityEvaluatorTest.php — +7 ALL_EXCEPT cases (matrix §6 của ticket)
- Test/Unit/Model/Config/CarrierDestinationScopeConfigTest.php — +5 ALL_EXCEPT reader cases

## Changed files (docs)
- .ai/project-context/architecture/address-shipping.md — §35.1 semantics + empty-zone lock
- app/code/Secomm/ShippingCore/CHANGELOG.md — 0.21.0
- app/code/Secomm/ShippingCore/docs/USER_GUIDE.md — §Rate pipeline 1, §2.4, §11.3
