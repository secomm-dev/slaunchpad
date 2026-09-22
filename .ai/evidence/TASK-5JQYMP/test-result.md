# Evidence — TASK-5JQYMP (ShippingCore v5 fallback eligibility orchestration + legacy RATE strategy)

Ngày: 2026-09-14 · Mode A · pre-review evidence cho TL review.

## Delta matrix (directive §21)

| Area | Current (trước task) | Required v5 | Action |
|---|---|---|---|
| per-operation capability | `CarrierOperationAddressCapabilityInterface` + handoff per-op (Y3X6H5) | architecture §5 | MATCH — không refactor |
| snapshot/failure_class | `CanonicalResolutionSnapshotInterface` + VO (Y3X6H5); persistence defer | §5.1 | MATCH (contract level) |
| fallback eligibility | Orchestrator chỉ `hasTechnicalFailure()`; không explicit state | 2 nguồn explicit (TECHNICAL_FALLBACK \| LEGACY_ADDRESS_FALLBACK) | **MISSING → IMPLEMENTED** |
| legacy RATE strategy | Không constants/seam | DIRECT_FALLBACK / MAP_THEN_FALLBACK identity | **MISSING → IMPLEMENTED** |
| COD identification | STC3NB implemented | §4.1 v4 | MATCH — leave unchanged |

## Implementation

```text
MỚI:
  Api/Fallback/FallbackEligibilitySource.php       TECHNICAL_FALLBACK | LEGACY_ADDRESS_FALLBACK
                                                   (extending = architecture amendment)
  Api/Fallback/LegacyRateStrategy.php              DIRECT_FALLBACK (≡ §15.1 "FALLBACK_ONLY") |
                                                   MAP_THEN_FALLBACK + exists/assertKnown
  Api/Fallback/FallbackEligibilityInterface.php    hasTechnical/hasLegacyAddress/isEligible/getSources
  Model/Fallback/FallbackEligibility.php           immutable VO + factories technical()/legacyAddress()/none()

BC-SAFE EXTENSIONS:
  ServiceLevelRateAggregatorInterface::aggregate(code, outcomes, ?FallbackEligibilityInterface = null)
  ServiceLevelRateAggregateInterface/VO            getFallbackEligibility() + hasLegacyAddressFallbackEligibility()
  ServiceLevelRateOrchestratorInterface::decide(code, aggregate, request, ?FallbackEligibilityInterface = null)

  eligible = aggregate.hasTechnicalFailure() OR eligibility.hasLegacyAddressFallbackEligibility()
  (technical eligibility VẪN derive từ aggregate — input eligibility bổ sung nguồn legacy;
   FallbackEligibility.technical flag merge không đổi behavior vì aggregate đã có signal)

DEC-FEATYA2C0W-005 ADDENDUM (naming):
  Implementation constant DIRECT_FALLBACK ≡ architecture §15.1 wording "FALLBACK_ONLY"
  (tránh collision Bridge Operational Mode). Semantics không đổi. Architecture doc §15.1 note.
```

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ServiceLevelRate|FallbackEligibility|LegacyRateStrategy"
OK — 53 tests, 150 assertions, 0 failure/error

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress|MageplazaTableRate"
OK — 417 tests, 1157 assertions, 0 failure/error (regression 0 new fail)
```

Test mới:

- `FallbackEligibilityTest` (4): technical/legacy/none/combined (sources deterministic order).
- `LegacyRateStrategyTest` (4): identities stable · exists matrix (FALLBACK_ONLY → false —
  không collision bridge mode) · assertKnown accept/reject.
- Orchestrator additions (5): legacy-eligible + no success + policy → FALLBACK (provider once) ·
  legacy + SUCCESS → REALTIME + provider never (Example F/G v5) · both sources → FALLBACK once
  (không duplicate call) · eligibility + policy disabled → UNAVAILABLE · legacy + provider null
  → UNAVAILABLE.
- Aggregator passthrough (2): eligibility carried trên aggregate · null mặc định.

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 40 FAIL, 0 WARN — 0 finding TASK-5JQYMP (grep = 0). 15 baseline + BUG/GHTK/
GHN-B2/ZaloPay/discount records của các stream song song đang draft, ngoài scope.
```

## Verification greps

```text
$ grep forbidden deps trong ShippingCore → 0 (Mageplaza/Launchpad/Ghn/Ghtk/Ahamove)
$ git status Ghtk/GiaoHangNhanh edits → CHỈ pre-existing stream khác (7AJ3K8/JBX3H9 từ trước)
$ grep LEGACY_ADDRESS_FAILURE outcome / reclassify → 0 (outcome taxonomy giữ nguyên 3-state)
```

## Files changed

- MỚI: `Api/Fallback/{FallbackEligibilitySource, LegacyRateStrategy, FallbackEligibilityInterface}.php`,
  `Model/Fallback/FallbackEligibility.php`
- UPDATE: `Api/{ServiceLevelRateAggregatorInterface, ServiceLevelRateAggregateInterface,
  ServiceLevelRateOrchestratorInterface}.php`, `Model/ServiceLevel/{ServiceLevelRateAggregator,
  ServiceLevelRateAggregate}.php`, `Model/ServiceLevel/ServiceLevelRateOrchestrator.php`
  (BC-safe optional params)
- `Test/Unit/{Model/Fallback/FallbackEligibilityTest, Api/Fallback/LegacyRateStrategyTest,
  Model/ServiceLevel/ServiceLevelRateOrchestratorTest (+5), ServiceLevelRateAggregatorTest (+2)}.php`
- `README.md` + `CHANGELOG.md` (0.15.0)
- Governance: SPEC + plan + TASK record + FEAT ticket_ref + DEC-005 addendum + architecture §15.1
  naming note + CURRENT_STATE

0 carrier / 0 Mageplaza / 0 vendor code thay đổi bởi task này.
