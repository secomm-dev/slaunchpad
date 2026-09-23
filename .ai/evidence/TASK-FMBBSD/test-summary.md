# Test summary — TASK-FMBBSD slice 1 + §38 stale-gate (2026-09-11)

## Secomm_Ghn suite (phpunit-secomm, filtered)

Command: `vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "Secomm\\Ghn"`

```
OK, but there were issues!
Tests: 148, Assertions: 210380, PHPUnit Deprecations: 6.
Time: 04:28.308
```

**148 tests / 210,380 assertions / 0 failures / 0 errors / 0 skips** (6 PHPUnit deprecations =
baseline PHP 8.3 pre-existing).

New tests this slice (+19 vs 129):
- `GhnRateCalculatorTest` — 16: zero-weight guard (no API call) · non-VN handoff reason passthrough ·
  canonical-unresolved · **PROVIDER_MAPPING_MISSING ≠ CANONICAL_UNRESOLVED (§28 boundary)** ·
  type-2 payload verbatim (service_type_id/weight/to_district_id/to_ward_code, KHÔNG from/dims/cod
  khi unset) · type-5 @20kg · origin+dims chỉ gửi khi cấu hình/có · collection_amount→`cod_value` ·
  zero collection omit · **missing fee total = TECHNICAL (không bao giờ zero-rate)** ·
  auth→UNAVAILABLE (never technical) · route-no-service→UNAVAILABLE · invalid-request→UNAVAILABLE ·
  5xx→TECHNICAL · timeout→TECHNICAL · malformed→TECHNICAL
- `MappingAuditorTest::testStaleRowsBlockProductionReadyEvenWhenCoverageIsComplete` — §38 gate
- `ConfigTest` — origin_district_id cast + unset→0

## Full app suite (baseline separation)

```
ERRORS!
Tests: 1235, Assertions: 214136, Errors: 7, PHPUnit Deprecations: 6.
```

7 errors = baseline Secomm_Tracking đã ghi trong CURRENT_STATE từ trước (stream song song;
phiên sáng cùng ngày full-suite có 15 errors = 8 PromotionMaxDiscount (đã được stream đó fix
trong ngày) + 7 Tracking). **0 failures; 0 error thuộc Secomm_Ghn hay thay đổi của slice này.**

## XML validation

- `system.xml` — XSD VALID (`vendor/magento/module-config/etc/system_file.xsd`)
- `config.xml` — Magento parses sạch (`cache:clean config` + `config:show` đọc được key mới)

## Sandbox validation

Xem `sandbox-validation.md` — fee/create/leadtime/info/cancel chạy LIVE qua `GhnApiClient`
trên sandbox gateway, sandbox shop để sạch (order đã cancel).
