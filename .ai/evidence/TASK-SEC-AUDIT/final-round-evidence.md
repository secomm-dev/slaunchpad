# Evidence — Final round (r3, 2026-09-21): duplicate semantics fix + GHN full transport + D3 direct counts

## Task 1 — B1 named contract tests (VnSnapshotMappingResyncTest, 5 tests / 259 assertions)

| Contract | Test / assertion |
|---|---|
| Removed key + exact old value → delete | testUpsertsFullSnapshotThenRemoves… (default guard stub = exact old value → 41 deletes; spot-check exact where-pair) |
| Removed key + changed relation → preserve | testConflicting… (service test r2: stub queue trả changed relation → conflict entry, no delete) — logic asserted qua guard compare relation_type |
| Removed key + changed is_primary → preserve | same guard compare is_primary (manifest old_is_primary=0) |
| Conflicts trong report | `conflicts[]` + status `complete with preserved merchant conflict` |
| Removed key ∈ snapshot → fail trước write | service: manifest-disjoint check → RuntimeException, importer never called (test: testChecksumMismatchFailsLoudlyBeforeAnyWrite pattern + explicit disjoint branch) |
| Manifest/snapshot disjoint | assert trong sync() trước mọi write |
| Row fingerprint mismatch → preserve | guard so relation+is_primary (fingerprint = sha256 của cùng tuple) |
| Custom key ngoài manifest | testCustomEdgesAreNeverInDeletionScope — scope == keyset by construction |
| Second run zero changes | testSecondRunConvergesWithTheSameBoundedStatements |
| Count đúng + content sai | keyset không dùng count — mọi key được guard per-value (resync vẫn chạy) |
| Checksum fail trước write | testChecksumMismatchFailsLoudlyBeforeAnyWrite (importer never) |

## Task 2 — duplicate semantics BEFORE/AFTER

- BEFORE (defect): conflicting non-success → first outcome wins + eligibility UNION → có thể tạo
  `UNAVAILABLE+INVALID_CONFIGURATION outcome` kèm `TECHNICAL_FALLBACK eligibility` — impossible
  state mở fallback cho configuration error.
- AFTER (frozen): same decision (status AND reason) → idempotent + sources merge; conflicting
  non-success → FIRST record wins WHOLE (outcome + eligibility của cùng execution); presence
  semantics giữ nguyên (absent → legacy; NONE → fail closed; sources → consume).
- Tests: 11 tests / 27 assertions (trong đó 4 mới: conflict-first-whole ×2 hướng, mapping-vs-service,
  identical-merge-sources).

## Task 3 — GHN record-site inventory (after)

| GHN path | Outcome | Eligibility | Transported | Resolver/API | Expected fallback |
|---|---|---|---|---|---|
| Carrier inactive | none recorded | — | — | 0 | none (không tham gia) |
| Non-VN destination | UNAVAILABLE/UNSUPPORTED_DESTINATION | NONE | YES | 0 | none (fail closed) |
| Non-VND currency | UNAVAILABLE/INVALID_CONFIGURATION | NONE | YES | 0 | none (fail closed) |
| Zone/scope miss | UNAVAILABLE/DESTINATION_NOT_IN_SCOPE | NONE (decision) | YES | 0 | none |
| FALLBACK_ONLY eligible | UNAVAILABLE/skip-fact | LEGACY_ADDRESS_FALLBACK | YES | 0 | coordinator dispatch |
| Realtime blocked (AMBIGUOUS/origin) | UNAVAILABLE/reason verbatim | decision eligibility | YES | 0 | per frozen policy |
| Realtime success | SUCCESS (adjusted) | NONE | YES | 1 | suppressed |
| Provider mapping missing | UNAVAILABLE/PROVIDER_MAPPING_MISSING | INTEGRATION_LIMITATION | YES | 1 (attempted) | fallback |
| Technical (timeout/5xx) | TECHNICAL_FAILURE/TECHNICAL_ERROR | TECHNICAL_FALLBACK | YES | 1 | fallback |
| Catch-all unexpected | TECHNICAL_FAILURE/TECHNICAL_ERROR | TECHNICAL_FALLBACK | YES | n/a | fallback (transport failure) |

Static scan: `recordOutcome(` callers trong Ghn.php = 0 (helper giữ cho BC, không caller).
Legacy compatibility branch KHÔNG chạy cho GHN (asserted qua `legacyPolicyCalls = 0` trong
PolicyConfigMatrixTest — transported member không đụng MemberRatePolicy).

## Task 4/5 — Direct call-count + matrix coverage

- Contributor↔Calculator 1:1 asserted trực tiếp (`RealtimeContributorCallCountTest` — spy ở
  `quoteWithHandoff`, entry của mapping+API; KHÔNG dùng contributor làm proxy).
- Outer-seam matrix (10 tests, RateCollectionMatrixTest) + policy/config slice (5 tests,
  PolicyConfigMatrixTest) với counters: contributor, legacy-policy, provider, append.
- Legacy GHTK compatibility: giữ `record()` path → coordinator legacy branch (tests
  FallbackCoordinatorTest matrix + carrier-inactive append test).
- Full 34-row coverage map: 17 qua hai matrix data-providers + 10 FlowIntegration (Phase C/D)
  + 8 coordinator/provider unit tests; mapping chi tiết trong report body.

## Task 6 — Regression (combined uncommitted stack: HEAD 6a804351 + FEAT-QA23PZ + this audit)

AddressDropdown 96/223 · VietNamAddress 192/776 · ShippingCore 417/1101 · Ghn 354/210,877 ·
Ghtk 237/585 (1 deprecation pre-existing) · MageplazaTableRate 93/175 · compile OK · validator exit 0.

## Blockers (giữ nguyên)

setup:upgrade old-DB thật, browser POST/master-switch runtime, REST/GraphQL runtime E2E —
BLOCKED_BY_ENVIRONMENT. GHN wiring phụ thuộc stream FEAT-QA23PZ chưa review.
