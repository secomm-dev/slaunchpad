# Changelog — Secomm_ShippingCore

## 0.18.0 — 2026-09-18 (TASK-8MQHJX Phase A+B+C — destination-scope eligibility + execution gating + fallback source amendment)

### Added — shared carrier rate execution gating (Phase C, architecture v10 §35)
- `Api\Rate\CarrierRateExecutionServiceInterface` + `Model\Rate\CarrierRateExecutionService`:
  ONE provider-neutral runtime path per carrier RATE operation, hard-ordered — eligibility →
  RateSourceMode → origin readiness → AddressResolutionPolicy (through the shared handoff
  service) → realtime contributor → contribution emission. Ineligible carriers short-circuit
  everything (no mode/policy/handoff/realtime/fallback). FALLBACK_ONLY skips origin, address
  policy and realtime entirely and emits LEGACY_ADDRESS_FALLBACK for eligible carriers.
  Realtime modes with an unresolved origin fail closed WITHOUT re-moding (CARRIER_ONLY →
  nothing; CARRIER_WITH_FALLBACK → judged by the shared policy on UNAVAILABLE +
  INVALID_CONFIGURATION, default not eligible).
- `Api\Rate\CarrierRateExecutionDecisionInterface` + `Model\Rate\CarrierRateExecutionDecision`:
  immutable composition of existing domain values (outcome + handoff + fallback eligibility +
  path reason) — no parallel domain model; realtime/outcome consistency guarded.
- `Api\Rate\CarrierRateExecutionRequestInterface` + `Model\Rate\CarrierRateExecutionRequest`:
  provider-neutral input (generic concepts only — no provider IDs, candidate lists or
  is_primary metadata); scope/mode/policy validated fail-fast at construction.
- `Api\Rate\RealtimeCarrierRateContributorInterface`: the smallest carrier-invocation seam —
  carrier modules implement it (no default: reverse dependency forbidden) and close over their
  own runtime context; input is the FINAL carrier-facing handoff, output the shared outcome.
- Address policy blocks: STRICT → no realtime, no fallback (either mode); FALLBACK + AMBIGUOUS
  → no realtime, LEGACY_ADDRESS_FALLBACK for CARRIER_WITH_FALLBACK only; PICK_PRIMARY
  selection success reaches realtime as a plain resolved handoff (no candidates/order/rank
  ever cross the boundary); PICK_PRIMARY selection failure behaves like the other blocks.
- UNMAPPED (non-ambiguous) handoffs still reach realtime — carrier-side textual strategy
  keeps working; execution never gates it away.
- DI: request/decision/service preferences. Contributor deliberately has NO preference.

### Fixed — Phase D integration/runtime defects
- `Model\ServiceLevel\ServiceLevelRateOrchestrator`: reads the INTEGRATION_LIMITATION
  eligibility source (Phase C amendment) so UNAVAILABLE+PROVIDER_MAPPING_MISSING can actually
  reach the final fallback dispatch (§35.5 E2E); ownership unchanged.
- `Model\Fallback\FallbackEligibility::__set_state()`: var_export round-trip for the DI
  compiler's object default (CarrierRateExecutionDecision constructor) — without it every
  generated-config include fataled at CLI/runtime bootstrap.
- Added `Test/Unit/Model/Rate/CarrierRateExecutionFlowIntegrationTest` (15 tests): END-TO-END
  flow over REAL ShippingCore + REAL Secomm_VietNamAddress implementations proving the frozen
  ordering (eligibility → mode → origin → policy → realtime → aggregate → orchestrator), the
  §5 case matrix, address-policy paths, multi-carrier realtime-success suppression and the
  INTEGRATION_LIMITATION mapping — with the fallback provider mock as the ONLY dispatch seam.

### Added — TL-approved contract amendment (closes the representational gap, no v11)
- `Api\Fallback\FallbackEligibilitySource.INTEGRATION_LIMITATION`: third frozen source —
  realtime contribution could not be produced because shared/provider integration data,
  mapping or adapter readiness is insufficient, AND it is NOT a confirmed carrier
  business/service rejection. §35.5 named this source since Rev v9 ("supersede §14: 2 nguồn")
  but the two-source contract could not represent it without mislabeling.
- `Api\Fallback\FallbackEligibilityInterface::hasIntegrationLimitationEligibility()` +
  `Model\Fallback\FallbackEligibility::integrationLimitation()`; `getSources()` order is
  TECHNICAL_FALLBACK → LEGACY_ADDRESS_FALLBACK → INTEGRATION_LIMITATION.
- Execution wiring: UNAVAILABLE+PROVIDER_MAPPING_MISSING under CARRIER_WITH_FALLBACK →
  INTEGRATION_LIMITATION eligibility (outcome stays UNAVAILABLE, never reclassified).
  `INVALID_CONFIGURATION` deliberately NOT mapped — frozen reason contract defines it as
  merchant-side carrier configuration → fail closed even under policy opt-in (the §35.5
  configurable auth/config seam needs its own provider-auth reason + mandatory warning seam;
  narrower ambiguity recorded for Phase D, eligibility never broadened to make tests pass).

### Added — destination-scope carrier eligibility (Phase A+B, architecture v10 §35)
- `Api\Address\DestinationScope`: `ALL` | `SELECTED_ZONES` (+ `exists()` / `assertKnown()`).
  Zone identities are merchant data — ShippingCore never hardcodes them.
- `Api\Address\CanonicalZoneInterface` + `Model\Address\CanonicalZone`: immutable zone VO
  (code, label, enabled, includeProvinceCodes, includeWardCodes, excludeWardCodes); empty
  code/label fail fast.
- `Api\Address\CanonicalZoneRegistryInterface` + `Model\Address\CanonicalZoneRegistry`:
  DI-contributed zones (`canonicalZones` item entries; zero zones valid); duplicate/empty
  codes fail fast; `getByCode` / `getAll` / `getEnabled`.
- `Api\Address\CanonicalZoneMatcherInterface` + `Model\Address\CanonicalZoneMatcher`:
  deterministic fail-closed matching — disabled → false; province gate; include-ward gate
  (empty = no positive ward restriction; missing ward fails); exclude-ward wins.
- `Api\Rate\CarrierEligibilityResultInterface` + `Model\Rate\CarrierEligibilityResult`:
  eligible/ineligible + `REASON_ELIGIBLE` / `REASON_DESTINATION_NOT_IN_SCOPE` + matched zone.
- `Api\Rate\CarrierEligibilityEvaluatorInterface` + `Model\Rate\CarrierEligibilityEvaluator`:
  `ALL` short-circuit (no zone lookups); `SELECTED_ZONES` first-match-wins in configuration
  order skipping unknown/disabled zones; unknown scope fail-closed ineligible.
- DI: preferences for matcher/registry/evaluator; registry accepts item entries.

Eligibility is destination-scope-only — RateSourceMode / AddressResolutionPolicy / fallback
dispatch remain the execution layer's job (next slice of this task).

## 0.17.0 — 2026-09-16 (TASK-GKHXY1 r2 — smallest shared delta, E1 consumer)

### Changed — tracking taxonomy + occurrence-aware dedupe
- `Model\Tracking\NormalizedTrackingStatus`: + `LOST`, `DAMAGED` — both **terminal** (sticky in
  `terminal()`) and commentable; `all()` now 13 states. Distinct semantics preserved: a provider
  "lost"/"damage" event is no longer flattened into `DELIVERY_FAILED` (which stays a live,
  re-attemptable state); after LOST/DAMAGED the pipeline refuses later stale carriers events
  (terminal-sticky guard).
- `Model\Tracking\ShipmentTrackingProcessor::shouldApply()`: duplicate guard is now
  **occurrence-aware** — same normalized status + same carrier status code but a DIFFERENT
  provider `occurredAt` is a DISTINCT event (re-applied: row timestamp/message refresh + event
  re-emission); an exact re-send (same occurredAt) remains a no-op; `occurredAt = null` keeps the
  legacy behavior (existing Ghtk consumers unaffected — webhooks without Time dedupe as before).

## 0.16.0 — 2026-09-15 (TASK-5XQXZK / DEC-TASK5XQXZK-001)

### Added — carrier outcome collection + safe-degradation eligibility policy
- `Api\Rate\CarrierRateOutcomeCollectorInterface` + `Model\Rate\CarrierRateOutcomeCollector`:
  carrier-neutral, request-scoped outcome reporting bracketed per
  `RateCollectorInterface::collectRates()` execution (Magento may collect many times per HTTP
  request — no request-wide state). Identity = stable pair (carrier_code, method_code), never
  an underscore composite. No DB/session/cache persistence; orphan `record()` calls are dropped
  safely; last report per pair wins within one execution.
- `Api\Fallback\FallbackEligibilityPolicyInterface` + `Model\Fallback\SafeDegradationEligibilityPolicy`
  — ShippingCore owns the fallback eligibility POLICY (carriers report status + structured
  reason facts only; no arbitrary flags, no message parsing). Default map: TECHNICAL_FAILURE →
  eligible; UNAVAILABLE + CANONICAL_AMBIGUOUS → eligible; UNMAPPED / UNSUPPORTED_DESTINATION /
  PROVIDER_MAPPING_MISSING / INVALID_CONFIGURATION / SERVICE_UNAVAILABLE → NOT eligible.
  UNAVAILABLE reasons overridable via DI array; carrier-owned diagnostic strings can never
  unlock fallback.
- `Api\Failure\ShippingFailureReason`: + `CANONICAL_AMBIGUOUS`, `CANONICAL_UNMAPPED`,
  `INVALID_CONFIGURATION` (AMBIGUOUS stays semantically distinct from TECHNICAL — never
  reclassified).

## 0.15.0 — 2026-09-14 (TASK-5JQYMP / DEC-FEATYA2C0W-005 + architecture v5 §14/§15.1)

### Added — v5 fallback eligibility orchestration + legacy RATE strategy
- `Api\Fallback\FallbackEligibilitySource` (TECHNICAL_FALLBACK | LEGACY_ADDRESS_FALLBACK) —
  exactly two proven eligibility sources; extending requires a new architecture amendment.
- `Api\Fallback\FallbackEligibilityInterface` + immutable VO — explicit eligibility state
  (technical / legacyAddress flags, `isEligible()`, deterministic `getSources()`); never derived
  from failure reasons.
- `Api\Fallback\LegacyRateStrategy` — DIRECT_FALLBACK (≡ architecture §15.1 wording
  "FALLBACK_ONLY") | MAP_THEN_FALLBACK identities + validation.
- `ServiceLevelRateAggregator::aggregate(...)` + `ServiceLevelRateAggregate` — optional
  `FallbackEligibilityInterface` carried on the aggregate (BC-safe optional param).
- `ServiceLevelRateOrchestrator::decide(...)` — optional eligibility param; eligibility =
  aggregate technical failure OR caller-supplied sources; SUCCESS vẫn suppress; provider vẫn
  at-most-once trên eligible path.
- Unit tests (+16: eligibility sources matrix, strategy identities/validation, aggregator
  passthrough, legacy FALLBACK/REALTIME-suppress/both-sources/policy-disabled/provider-null).

### Changed — implementation naming (DEC-FEATYA2C0W-005 addendum)
- Legacy RATE strategy skip-mapping identity named `DIRECT_FALLBACK` in implementation ≡
  architecture §15.1 wording "FALLBACK_ONLY" (tránh collision với Bridge Operational Mode) —
  documented mapping, semantics không đổi.



## 0.14.0 — 2026-09-11 (TASK-Y3X6H5 / architecture v4 delta A–E — reopen-and-freeze)

### Added — per-operation capability + resolution snapshot
- `Api\Address\ShippingAddressOperation` (RATE | CREATE) +
  `Api\Address\AddressRepresentation` (UNIT_ID | TEXT_NAME) — P1 identity constants
  (GEOPOINT/CarrierType deliberately absent).
- `Api\Address\CarrierOperationAddressCapabilityInterface` — per-operation capability
  (scheme/representations/textual-fallback per op). The legacy per-carrier capability
  interface is deprecated (signature unchanged — carriers migrate in the adaptation task).
- Handoff per-operation: `DestinationContextBuilder::buildForOperation()`,
  `CarrierAddressHandoffService::{handoffForOperation, handoffContextForOperation}`
  (unknown operation / context-scheme mismatch fail fast),
  `CarrierAddressHandoff::getSupportedRepresentations()`.
- `Api\Address\CanonicalResolutionSnapshotInterface` + immutable VO — canonical identities
  only (provider IDs have no field), status/failure_class/source/provenance with fail-fast
  invariants (persistence is a separate task).
- `ExternalAddressResolverInterface` — AMBIGUOUS-only selector boundary made explicit
  (docblock; never UNMAPPED, never minting canonical units, never called in RATE fan-out).
- Unit tests (+20: RATE-vs-CREATE scheme/representation matrix, per-op textual fallback,
  unknown-op/mismatch guards, legacy-path regression, snapshot invariants).

### Notes
- Old handoff/capability paths keep exact prior behavior (regression-tested) — existing
  consumers (`GhnRateCalculator`, `GhtkAddressAdapter`) are unaffected at runtime; carrier
  migration to the per-operation interface is the follow-up task.
- **ShippingCore re-frozen after this task** — only real-consumer evidence reopens it.



## 0.13.0 — 2026-09-11 (TASK-STC3NB / architecture v4 §4.1)

### Added — COD payment identification
- `Api\Cod\CodPaymentMethodResolverInterface` + `Model\Cod\ConfiguredCodPaymentMethodResolver`
  — configuration-declared COD payment method codes (`secomm_shippingcore/cod/payment_methods`,
  comma-separated; empty = nothing is COD); exact case-sensitive match on trimmed codes;
  prefix/similar/unconfigured → false. Scalar-in/bool-out — no OrderInterface, no COD framework.
- `etc/adminhtml/system.xml` (section `secomm_shippingcore` → COD Payment Identification) +
  `etc/config.xml` default (empty) + DI preference.
- Unit tests (+8: single/multi match, unconfigured, empty/null, prefix/similar/case,
  whitespace normalization, queried-code trim).

### Notes
- Identification only — no eligibility/amounts/surcharges/reconciliation. Architecture doc
  Revision v4 §4.1 is the owning contract; carriers consume `isCod(...)` when building
  provider shipment/order requests.



## 0.12.0 — 2026-09-10 (TASK-7AJ3K8 / DEC-TASK7AJ3K8-001 — Phase E-C1 shared primitives + first carrier consumer)

### Added
- `Api\Address\RuntimeAddressContextBuilderInterface` + `Model\Address\RuntimeAddressContextBuilder`
  — scalar runtime address (country, regionId, cityId?, native-city locality, street) → resolution
  context: id-based bridge trước, name-based (`VnOperationalNameResolverInterface`) fallback,
  AMBIGUOUS candidates vào context (không identity, không pick). `DestinationContextBuilder`
  (quote, E-C0) refactor delegate builder này — 1 identity path chung.
- `ShippingAddressResolutionManager` — identity missing + context candidates → AMBIGUOUS
  passthrough (candidates là canonical active-scheme codes; cross-scheme candidate translation
  thuộc disambiguation phase sau). No-identity no-candidates vẫn UNMAPPED.
- `CarrierAddressHandoffServiceInterface::handoffContext()` + candidates trên
  `CarrierAddressHandoffInterface`/VO — entry thứ 2 cho non-quote sources (RateRequest, sales
  order address) và carrier disambiguation policy (phân biệt AMBIGUOUS vs UNMAPPED); `handoff()`
  giữ nguyên, delegate đường chung.
- `Api\CarrierApiProfileInterface` — minimal carrier API profile shape (code + addressScheme,
  D3); KHÔNG default preference (reverse dependency bị cấm).
- `Api\Http\{CarrierHttpErrorCategory, CarrierHttpException, CarrierHttpClientInterface}` +
  `Model\Http\{CarrierHttpRequest, CarrierHttpResponse, CurlCarrierHttpClient, RetryPolicy,
  RetryExecutor}` — shared transport primitive (DEC-TASK7AJ3K8-001 §1, siết D10): 1 exchange/call,
  taxonomy 6 category (TIMEOUT/NETWORK/RATE_LIMIT/SERVER_ERROR/CLIENT_ERROR/INVALID_RESPONSE),
  non-2xx là throw; retry do carrier policy quyết (safe-read retry NETWORK/SERVER_ERROR/TIMEOUT;
  create-order single-attempt). 429 classify nhưng không retry. Auth/endpoint/payload vẫn carrier.
- `Api\Tracking\CarrierTrackingFetcherInterface` + `Model\Tracking\TrackingReconciliationService`
  — carrier-agnostic reconciliation orchestration (state query, terminal exclude, stale filter,
  batch, per-item continue) qua DI (fetcher + carrierCode + batchSize); carrier config policy ở
  carrier. Consumer đầu tiên: `Secomm_Ghtk` (virtual type `GhtkTrackingReconciliation`).

### Changed
- `Model\OriginProvider\ShippingOriginProvider` — KHÔNG đổi behavior; `OriginInterface::getWard()`
  được định nghĩa lại contract-wise = generic locality slot mà AddressDropdown profile engine lưu
  ở native `city` field (platform convention — không phải VN semantics); canonical origin
  enrichment hoãn until-consumer (DEC-TASK7AJ3K8-001 §5).

### Tests
- +`RuntimeAddressContextBuilderTest` (8), `CurlCarrierHttpClientTest` (10), `RetryExecutorTest` (7),
  `TrackingReconciliationServiceTest` (7); +candidates/handoffContext cases vào
  `ShippingAddressResolutionManagerTest` / `CarrierAddressHandoffServiceTest`;
  `DestinationContextBuilderTest` viết lại cho delegate design.

## 0.11.0 — 2026-09-08 (TASK-M3ME32 — final ShippingCore foundation slice)

### Added — Phase E-SL2 service-level fallback decision
- `Api\Fallback\FallbackPolicyInterface` + `Model\Fallback\ConfigurableFallbackPolicy`
  (`fallbackEnabledByLevel` DI array) — per-level opt-in; unlisted levels DENIED; no rules
  engine.
- `Api\ServiceLevelRateDecisionInterface` + `Model\ServiceLevel\ServiceLevelRateDecision` —
  REALTIME / FALLBACK / UNAVAILABLE with mutually-exclusive invariants and named factories.
- `Api\ServiceLevelRateOrchestratorInterface` + `Model\ServiceLevel\ServiceLevelRateOrchestrator`
  (registry + policy + provider pool) — dynamic enabled enforcement (disabled level = UNAVAILABLE
  even with successes), SUCCESS suppresses fallback entirely (provider never called), plain
  UNAVAILABLE never triggers fallback, provider invoked at most once only on the eligible path;
  zero providers = normal UNAVAILABLE; >1 provider = fail-fast configuration ambiguity (no
  provider routing); zero-valued fallback rate = valid FALLBACK; code/aggregate mismatch and
  unknown codes fail fast.
- Unit tests (+22: decision VO invariants, full §23 decision matrix, provider invocation
  expectations incl. never-call branches and exactly-once paths, zero-provider/zero-rate,
  mismatch/unknown guards).

### Notes
- **ShippingCore foundation scope COMPLETE** — no further generic architecture without real
  consumer evidence; next phase = carrier adoption + Launchpad TableRate bridge validation.


## 0.10.0 — 2026-09-08 (TASK-32ACTR)

### Added — Phase E-SL1 service-level realtime rate aggregation
- `Api\ServiceLevelRateAggregatorInterface` + `Model\ServiceLevel\ServiceLevelRateAggregator`
  (DI `ShippingServiceLevelRegistry`) — `aggregate(serviceLevelCode, outcomes)`: dynamic
  registry validation (unknown code = explicit configuration error, no hardcoded taxonomy);
  outcomes keyed by carrier code (identity preserved into the aggregate); input order kept.
- `Api\ServiceLevelRateAggregateInterface` + `Model\ServiceLevel\ServiceLevelRateAggregate` —
  successful realtime rates keyed by carrier code, `hasSuccessfulRate()`,
  `hasTechnicalFailure()` (signal kept even alongside successes), `getOutcomeCount()`;
  zero-outcome state valid.
- Unit tests (+14: full 8-row outcome matrix, dynamic validation incl. disabled-but-registered
  level, carrier-key/order preservation, reason independence, no-fallback-dependency guard).

### Notes
- Aggregation only — no carrier selection/ranking, no fallback pricing or triggering, no
  reason-string inference; E-SL2 owns the fallback decision.


## 0.9.0 — 2026-09-08 (TASK-NAT3YV r1 + TASK-XXBN5X r2 — TL/SA review amendment)

### Changed — zero-rate alignment + shared failure-reason owner
- `FallbackRate` amount rule aligned with `CarrierRate`: **>= 0** — zero is a valid explicit
  configured rate (was: strictly positive); negative still rejected; `null` from a fallback
  provider remains the ONLY "no fallback rate" representation. Forbidding zero-valued
  emergency fallback (if ever needed) is project-level policy, not a core VO invariant.
- `Api\Failure\ShippingFailureReason` — the ONE canonical owner of shared cross-stage failure
  reasons (`UNSUPPORTED_DESTINATION`, `CANONICAL_UNRESOLVED`, `PROVIDER_MAPPING_MISSING`,
  `SERVICE_UNAVAILABLE`, `TECHNICAL_ERROR`). `CarrierAddressHandoffInterface` and
  `CarrierRateOutcomeInterface` no longer declare duplicate constants — both reference the
  shared owner; parity tests removed.
- Orchestration rule documented: outcome STATUS drives runtime behavior; reason strings are
  diagnostic only — fallback eligibility must never be inferred from reason strings.
  `unavailable(null)` / `technicalFailure(null)` remain structurally valid. 3-state taxonomy,
  auth/config→UNAVAILABLE and provider-mapping→UNAVAILABLE rules unchanged.

### Notes
- No behavior change beyond the two contract cleanups; 0 carrier/Mageplaza/Launchpad code.


## 0.8.0 — 2026-09-08 (TASK-NAT3YV / SPIKE-YH439T)

### Added — Phase E-C1 carrier rate outcome semantics
- `Api\Rate\CarrierRateInterface` + immutable VO — quoted amount (>= 0: zero is a valid
  promotional rate, negative rejected; deliberately asymmetric with the strictly-positive
  `FallbackRate`) + optional currency. No Magento rate objects, no service level, no carrier
  identity.
- `Api\Rate\CarrierRateOutcomeInterface` + immutable VO — SUCCESS / UNAVAILABLE /
  TECHNICAL_FAILURE with shared reason constants (`UNSUPPORTED_DESTINATION`,
  `CANONICAL_UNRESOLVED`, `PROVIDER_MAPPING_MISSING`, `SERVICE_UNAVAILABLE`, `TECHNICAL_ERROR`
  — address-stage values intentionally equal the E-C0 handoff reasons). Impossible
  combinations unconstructible (fail-fast); named factories `success()/unavailable()/
  technicalFailure()`; `isSuccessful()` hard-guard. No logger, no metadata bag.
- `etc/di.xml` preferences for both interfaces.
- Unit tests (+14: rate values incl. zero-valid/negative-reject, outcome happy paths,
  impossible-combination rejects, reason normalization, shared-reason parity with handoff).

### Notes
- Contracts only — no carrier adoption, no service-level aggregation, no fallback trigger
  policy; only TECHNICAL_FAILURE may later contribute to fallback eligibility. Auth/config
  failures are UNAVAILABLE by rule (fallback must not hide merchant misconfiguration).


## 0.7.0 — 2026-09-08 (TASK-T78YH6 / SPIKE-YH439T)

### Added — Phase E-C0 carrier-facing address handoff
- `Api\Address\DestinationContextBuilderInterface` + `Model\Address\DestinationContextBuilder`
  — Magento `Quote\Address` → resolution context; source canonical identity via the
  VietNamAddress operational bridge (`resolveFromRuntime` — first production consumer; bridge
  miss → null identity → UNMAPPED); target scheme applied once from the carrier capability;
  context candidates always empty (manager-produced only); no recipient PII.
- `Api\Address\CarrierAddressHandoffInterface` + immutable VO — carrier-facing Stage-1 result:
  `isApplicable()`, `getResolvedAddress()`, `isTextualFallbackEligible()` (ALLOW-only),
  `getFailureReason()` (`UNSUPPORTED_DESTINATION` | `CANONICAL_UNRESOLVED`); no PII, no
  provider-stage reasons, no `isResolved()`/`getUnresolved()` redundancy.
- `Api\Address\CarrierAddressHandoffServiceInterface` + `Model\Address\CarrierAddressHandoffService`
  — builder → request-cached resolution manager → handoff; translates the internal
  `UnsupportedDestinationException`; single resolution path; external resolver pool untouched.
- `etc/di.xml` preferences (service + builder).
- Unit tests (+22: builder mapping/bridge-miss/candidates-empty, handoff VO invariants,
  service matrix incl. non-VN translation and manager-delegation assertions).

### Notes
- Stage 1 (canonical handoff) vs Stage 2 (carrier provider mapping) boundary documented — a
  Stage-2 failure is carrier-owned and never reported as `CANONICAL_UNRESOLVED`.


## 0.6.0 — 2026-09-08 (TASK-XXBN5X / SPIKE-WHHEZV + SPIKE-YH439T)

### Added — Phase E-SL0 service-level + fallback contracts (foundation only)
- `Api\ShippingServiceLevelInterface` + `Model\ServiceLevel\ShippingServiceLevel` — one
  service-level definition (machine `code` identity, configurable `label`, `enabled`,
  `sortOrder`); r1: ShippingCore embeds NO business taxonomy — project/composition modules
  register their own levels dynamically.
- `Model\ServiceLevel\ShippingServiceLevelRegistry` (`serviceLevels` DI array) — dynamic
  lookup/validation (`getByCode`/`has`/`getEnabled`/`getAll`/`assertKnown`); zero-level state
  valid; duplicate/empty code fails fast; unknown code = explicit configuration error. r1:
  replaces the initially proposed hardcoded `ShippingServiceLevel` constants class
  (EXPRESS/SAME_DAY/STANDARD) rejected in TL/SA review.
- `Api\CarrierServiceLevelInterface` — carrier-declared service-level machine codes
  (`getServiceLevels(): string[]` matched against registered codes).
- `Api\Fallback\FallbackRateRequestInterface` + immutable VO — provider-neutral fallback
  request (country/region/postcode/weight/subtotal/qty/store/customerGroup); no Magento
  RateRequest, no PII, no provider identifiers.
- `Api\Fallback\FallbackRateInterface` + immutable VO — service-level fallback price with a
  strictly positive amount (no-match ⇒ null, never zero fee), label, optional estimate;
  no carrier identity, no metadata dump.
- `Api\Fallback\FallbackRateProviderInterface` + `Model\Fallback\FallbackRateProviderPool`
  (`fallbackRateProviders` DI array) — optional single-provider registration, zero-provider
  valid, no competition/chaining.
- Unit tests (+20: identities/validation, request transport + invariants, rate invariants,
  provider stub semantics, pool registration).

### Notes
- Contracts only — no fallback orchestration/policy, no admin config, no checkout methods,
  no rate-outcome taxonomy; carriers/Mageplaza/Launchpad untouched.


## 0.5.0 — 2026-09-08 (TASK-5XDG1P / DEC-FEATYA2C0W-004)

### Added — Phase E-B local canonical address orchestration
- `Model\Address\ShippingAddressResolutionManager` — implements
  `ShippingAddressResolutionManagerInterface`: validate → request-scoped cache
  lookup → delegate canonical graph resolution to
  `Secomm\VietNamAddress\Api\VnAdminAddressResolverInterface` → 1-1 status
  conversion into `ResolvedShippingAddress` → cache. AMBIGUOUS is never
  auto-selected; no mapping/cardinality logic lives in ShippingCore.
- `Model\Address\Exception\UnsupportedDestinationException` — explicit
  not-applicable bypass for non-VN destinations (extends `LocalizedException`;
  never mislabels a non-VN address as UNMAPPED, no fifth canonical status).
- `etc/di.xml` preference for `ShippingAddressResolutionManagerInterface`.

### Removed — TL-review cleanup (API hygiene before contract freeze)
- `ShippingAddressResolutionContextInterface::getReceiverText()` + the
  concrete context property/constructor parameter — receiver name is recipient
  PII, not required for canonical administrative resolution. No replacement
  recipient identity field. `streetText` and `candidateCodes` stay available
  for future external disambiguation; manager orchestration, cache behavior
  and all four statuses unchanged.

### Notes
- Request-scoped in-memory cache keyed `sourceScheme|sourceUnitCode|
  targetScheme`; every outcome (AMBIGUOUS/UNMAPPED/missing-identity included)
  is cached — one canonical lookup per request, no external cache backend.
- Missing/invalid canonical identity resolves UNMAPPED without touching the
  resolver; unknown scheme codes propagate the resolver's
  `LocalizedException`; impossible canonical responses throw `LogicException`.
- External resolver pool + textual fallback are NOT invoked (later phases).
- Unit tests (+24: 4-state passthrough, no-first-candidate guard, cache
  hit/separation/unresolved-cache, non-VN bypass, invalid context, unknown
  scheme, manager↔real-resolver contract-fit suite).


## 0.4.0 — 2026-09-08 (TASK-AQT7V3 / DEC-FEATYA2C0W-004)

### Added — Phase E-A address resolution contracts (foundation only)
- `Api\Address\CarrierAddressCapabilityInterface` — required scheme + textual
  fallback declaration (read-only for orchestration).
- `Api\Address\ResolvedShippingAddressInterface` + immutable
  `Model\Address\ResolvedShippingAddress` — status semantics reused from
  `Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface::STATUS_*`
  (EXACT/MAPPED/AMBIGUOUS/UNMAPPED); constructor enforces all invariants
  (unresolved ⇒ no unitCode; AMBIGUOUS ⇒ full candidate list preserved).
- `Api\Address\ShippingAddressResolutionContextInterface` + immutable
  `Model\Address\ShippingAddressResolutionContext` — scalar-only destination
  transport (countryId, source canonical identity, target scheme, street
  text, candidate codes). Recipient identity is not part of the
  address-resolution contract (see 0.5.0 Removed).
- `Api\Address\ExternalAddressResolverInterface` — optional disambiguation
  provider contract (returns canonical unit_code or null, never provider ids).
- `Api\Address\ShippingAddressResolutionManagerInterface` — contract only;
  implementation deliberately deferred to Phase E-B.
- `Model\Address\ExternalAddressResolverPool` + `etc/di.xml` array argument
  `externalAddressResolvers` — zero-provider-valid DI extension point.
- `etc/module.xml` sequences `Secomm_VietNamAddress` (D1 dependency direction).
- Unit tests (+19: status matrix, candidate preservation, invariant rejections,
  empty pool validity, context transport).

### Notes
- No orchestration, no config, no persistence, no carrier/external-provider code
  touched (Phase E-B owns orchestration; GHN hardcoded fallback is a separate
  bug task — see SPEC-TASK-AQT7V3 §7).


## 0.3.0 — 2026-08-17 (SL-017 / DEC-SL017-001)

### Added — carrier tracking pipeline (shared, carrier-agnostic)
- `Api\Tracking\NormalizedTrackingStatus` (11 statuses; terminal = DELIVERED/RETURNED/CANCELLED),
`TrackingUpdateInterface`, `CarrierTrackingProcessorInterface`, `CarrierStatusMapperInterface`.
- `Model\Tracking\TrackingUpdate` immutable DTO (carrier + normalized + raw status + occurredAt + source).
- `Model\Tracking\ShipmentTrackingProcessor` — THE single pipeline: find track → duplicate no-op →
sticky-terminal + timestamp out-of-order guards (DELIVERY_FAILED→IN_TRANSIT reattempts allowed) →
persist state → native Track.description update → shipment comment on key transitions →
domain events (`secomm_shipping_tracking_updated` + delivered/returned/delivery_failed).
Never mutates Magento order state; never throws for ordinary conditions.
- `secomm_carrier_tracking_state` table (db_schema + whitelist; UNIQUE carrier_code+tracking_number).
- Unit tests (+15: processor ordering/idempotency matrix).


## 0.2.0 — 2026-08-17 (SL-016 / DEC-SL016-001)

### Added
- `ShippingContextFactory::fromShipment()` — the label-submit flow builds the
  same scalar context as the rate path, so both flows resolve the origin
  through one `OriginProviderInterface` (completes SL-015 AC-10).
- `etc/module.xml` sequences `Magento_Sales`.
- Factory unit tests for the shipment path.


## 0.1.0 — 2026-08-17 (SL-015 / DEC-SL015-001)

### Added
- `Api\ShippingContextInterface` + immutable `Model\ShippingContext` (scalar DTO:
  storeId, websiteId, carrierCode, quoteId, sourceCode).
- `Api\OriginInterface` + immutable `Model\Origin` (sourceCode, countryId, regionId,
  province, nullable district, ward, street, postcode, telephone, contactName,
  dotted-key carrier metadata).
- `Api\OriginProviderInterface` — the stable origin extension contract
  (`resolve(ShippingContextInterface): OriginInterface`).
- `Model\ShippingContextFactory` — `fromRateRequest()` + generic `create()`.
- `Model\OriginProvider\ShippingOriginProvider` — default provider normalizing the
  Magento Shipping Origin (`shipping/origin/*`, SCOPE_STORE); district/telephone/
  contactName null by design; never decides usability.
- `etc/di.xml` preferences (provider + DTOs).
- Unit tests: provider (6 cases), factory (3), origin metadata (1).
