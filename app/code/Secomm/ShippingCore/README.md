# Secomm_ShippingCore

Shared shipping contracts for all Secomm carriers (SL-015 / DEC-SL015-001). Small,
generic, carrier-agnostic — contains **no** GHTK/GHN/Ahamove business logic and
depends on no Secomm carrier.

## Purpose

Every Secomm shipping carrier consumes its **runtime shipping origin** (where a
shipment physically leaves from) through one stable extension contract, so a
future fulfillment module (`Secomm_ShippingFulfillment`: MSI-source-based origin,
multi-store routing) can change *where shipments originate from* without touching
any carrier.

## How it works

```
Carrier (e.g. Secomm_Ghtk)
  └─ ShippingContextInterface            # scalar snapshot: storeId/websiteId/carrierCode/quoteId/sourceCode
       └─ OriginProviderInterface        # THE extension point
            → resolve(context): OriginInterface
                 default preference: ShippingOriginProvider (Magento Shipping Origin,
                 store-scoped by context; ward = native city, regionId preserved)
```

- `Api\ShippingContextInterface` + `Model\ShippingContext` — immutable scalar DTO;
  `Model\ShippingContextFactory::fromRateRequest()` for the rate path (the future
  shipment path builds the same context so rate and submit share one origin).
- `Api\OriginInterface` + `Model\Origin` — immutable origin VO: sourceCode,
  countryId, regionId, province, **district (nullable — VN Launchpad model)**,
  ward, street, postcode, telephone, contactName + generic carrier metadata via
  dotted keys (`ghtk.pick_address_id`, `ghn.shop_id`) — no carrier fields are
  hard-coded in the shared DTO.
- `Api\OriginProviderInterface` + `Model\OriginProvider\ShippingOriginProvider` —
  default provider; returns a data snapshot, never decides usability
  (carrier-side policy, e.g. GHTK's strict pickup gate DEC-021).

## Extension points

- **Replace:** DI preference on `OriginProviderInterface` (see `etc/di.xml`).
- **Decorate:** plugin on the provider.
- No observers mutate request payloads; no Magento core rewrites/preferences.

## Tests

`Test/Unit` — provider normalization (country/province/ward/street/postcode,
nullable district/telephone, blank-string normalization, store scoping), factory
mapping, metadata behavior. Run:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --testsuite Magento_Unit_Tests_App_Code --filter 'Secomm\\ShippingCore'
```

## Dependencies

Magento Framework / Store / Directory / Shipping only. Loaded by
`Secomm_Ghtk` (module.xml sequence).

## Carrier tracking (SL-017 / DEC-SL017-001)

Shared tracking pipeline — webhook receivers, Tracking API fetchers and
reconciliation crons all build a `TrackingUpdateInterface` (carrier status +
mapper-normalized status + raw fields) and feed the ONE
`CarrierTrackingProcessorInterface` (`Model\Tracking\ShipmentTrackingProcessor`):

- find Magento track by carrier_code + track_number;
- duplicate no-op; sticky terminal (DELIVERED/RETURNED/CANCELLED never
  downgraded); timestamp-priority out-of-order guard; reattempts
  (DELIVERY_FAILED → IN_TRANSIT) allowed;
- persist `secomm_carrier_tracking_state` (normalized + raw side by side);
- native visibility: Track.description + shipment comments on key transitions;
- domain events `secomm_shipping_tracking_updated`,
  `secomm_shipment_carrier_{delivered,returned,delivery_failed}`.

Never mutates Magento order state (carrier shipment state ≠ order lifecycle).
Carrier modules only implement a `CarrierStatusMapperInterface` + webhook/API
parser — no carrier status codes live here.

## Vietnam address resolution contracts + local orchestration (TASK-AQT7V3 + TASK-5XDG1P / DEC-FEATYA2C0W-004, Phase E-A/E-B)

Foundation contracts (E-A) + local canonical manager (E-B) for shipping-side
canonical address orchestration (external disambiguation + carrier integration
are later phases; nothing carrier-side consumes the manager yet):

- `Api\Address\CarrierAddressCapabilityInterface` — what a carrier declares:
  `getRequiredScheme()` (canonical scheme from `Secomm_VietNamAddress`, never a
  ShippingCore constant) + `supportsTextualFallback()` (declaration only in E-A).
- `Api\Address\ResolvedShippingAddressInterface` + `Model\Address\ResolvedShippingAddress` —
  resolution outcome. Status semantics are **reused** from
  `Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface::STATUS_*`
  (EXACT / MAPPED / AMBIGUOUS / UNMAPPED — never auto-picking a candidate).
  The VO enforces every invariant in its constructor: unresolved statuses can
  never expose a unit code, AMBIGUOUS always preserves the full candidate list.
  Region/district are derivable via `Secomm_VietNamAddress` and deliberately
  absent — no names, no provider IDs (`scheme_code + unit_code` is the boundary).
- `Api\Address\ShippingAddressResolutionContextInterface` + `Model\Address\ShippingAddressResolutionContext` —
  scalar-only destination view for resolution/disambiguation (countryId, current
  canonical identity, target scheme, street text, candidate codes).
  Magento address/quote objects are never exposed. External address
  disambiguation may use address-related textual context such as street text
  and canonical candidates — recipient identity is not part of the
  address-resolution contract.
- `Api\Address\ExternalAddressResolverInterface` — optional disambiguation
  providers (e.g. Secomm_VietMap) return a canonical target unit_code or null —
  never a provider-specific id.
- `Api\Address\ShippingAddressResolutionManagerInterface` — one-method
  orchestration entry point.
- `Model\Address\ExternalAddressResolverPool` — DI array extension point
  (`externalAddressResolvers` argument in `etc/di.xml`); zero registered
  providers is a valid state, ShippingCore depends on no provider module.

### Local canonical orchestration (Phase E-B, TASK-5XDG1P)

`Model\Address\ShippingAddressResolutionManager` (preference for the manager
interface in `etc/di.xml`) is orchestration-only — all canonical semantics stay
in `Secomm_VietNamAddress`:

1. **Bypass**: non-VN `countryId` (case-insensitive) never reaches the Vietnam
   resolver — the manager throws
   `Model\Address\Exception\UnsupportedDestinationException` (explicit
   not-applicable channel; a non-VN destination is never mislabeled UNMAPPED
   and no fifth status exists). `countryId = null` does NOT bypass — the
   canonical identity stays authoritative.
2. **Delegation**: one `VnAdminAddressResolverInterface::resolve(scheme, unit,
   target)` call — same-scheme EXACT, cross-scheme cardinality-authoritative
   MAPPED/AMBIGUOUS/UNMAPPED; AMBIGUOUS candidates pass through untouched
   (never auto-selected). Unknown scheme codes propagate the resolver's
   `LocalizedException` (configuration fault).
3. **Invalid context**: missing/empty `sourceScheme`/`sourceUnitCode` resolves
   to UNMAPPED without touching the resolver — no region_id/city_id/name
   fallbacks.
4. **Request-scoped cache**: plain in-memory array on the shared DI instance,
   keyed by canonical identity only (`sourceScheme|sourceUnitCode|
   targetScheme` — never street/candidate text; recipient identity is not
   carried by the context at all). Every outcome
   (including AMBIGUOUS/UNMAPPED/missing-identity) is cached, so one canonical
   lookup costs exactly one resolver call per request. No Redis, no Magento
   cache frontend, no persistence.
5. `supportsTextualFallback()` and the external resolver pool are **never**
   consulted by the local manager (later phases).

## Shipping service levels + fallback rate contracts (TASK-XXBN5X / SPIKE-WHHEZV, Phase E-SL0, r1)

Reusable contracts for the `carrier ≠ service level ≠ rate source` model — customer-facing
shipping promises are represented by service level, not carrier identity. **ShippingCore
supports dynamically registered/configured shipping service levels; project/composition
modules define the actual service-level taxonomy** (Launchpad is expected to register its
defaults — e.g. EXPRESS/SAME_DAY/STANDARD — separately; ShippingCore embeds no business
taxonomy and runs validly with zero registered levels):

- `Api\ShippingServiceLevelInterface` + `Model\ServiceLevel\ShippingServiceLevel` — one
  service-level definition: stable machine `code` (the identity, survives label changes),
  configurable `label`, `enabled`, `sortOrder`. No SLA semantics in the core ("EXPRESS = 2
  hours" is project/config concern); display labels ("Giao nhanh 2 giờ") are presentation
  only, never machine keys.
- `Model\ServiceLevel\ShippingServiceLevelRegistry` — dynamic lookup/validation over
  definitions registered via the `serviceLevels` DI array argument (`getByCode`/`has`/
  `getEnabled`/`getAll`/`assertKnown`). Zero registered levels is a valid state; duplicate
  or empty codes fail fast as misconfiguration; an unknown code is an explicit configuration
  error.
- `Api\CarrierServiceLevelInterface` — a carrier DECLARES the service-level machine codes it
  can provide (`getServiceLevels(): string[]`, values must match registered codes). Carriers
  declare levels but do not know fallback provider implementations. Portability note: a
  literal code inside reusable carrier code is a project/business declaration — the long-term
  preferred model is configured carrier→service-level membership.
- `Api\Fallback\FallbackRateRequestInterface` (+ VO) — provider-neutral fallback request
  (country, region, postcode, weight, subtotal, qty, store, customer group). No Magento
  RateRequest crosses the provider API; region/postcode granularity is sufficient because
  fallback pricing is not the service-eligibility engine.
- `Api\Fallback\FallbackRateInterface` (+ VO) — a service-level fallback price: non-negative
  `amount` (**zero is a valid explicit configured rate; negative is rejected** — "no fallback
  rate" is expressed exclusively as `null` from the provider, never as a zero-fee sentinel),
  `label`, optional `deliveryEstimate`. No carrier code, no metadata dump. Forbidding
  zero-valued emergency fallback, if a project ever needs it, is fallback POLICY owned by
  project composition/orchestration — not a core VO invariant.
- `Api\Fallback\FallbackRateProviderInterface` + `Model\Fallback\FallbackRateProviderPool` —
  optional providers registered via the `fallbackRateProviders` DI array argument; zero
  providers is a valid state. The provider answers only "is there a configured fallback rate
  for this level?" — ShippingCore orchestration (later phase) decides when fallback is
  eligible; a rate match never establishes shipping-service eligibility.

**Engineering rules**: carrier modules declare service levels but do not know fallback provider
implementations. Fallback providers calculate price only; ShippingCore orchestration decides
when fallback is eligible. ShippingCore has no third-party/Launchpad dependency in this
surface — bridge modules register themselves via DI and are never named here.

## Carrier address handoff (TASK-T78YH6 / SPIKE-YH439T, Phase E-C0)

The single carrier-facing Stage-1 entry point — carriers never construct resolution contexts,
catch ShippingCore exceptions, or interpret EXACT/MAPPED/AMBIGUOUS/UNMAPPED themselves:

- `Api\Address\DestinationContextBuilderInterface` + `Model\Address\DestinationContextBuilder` —
  translates a Magento `Quote\Address` into the resolution context: source canonical identity
  comes exclusively from the VietNamAddress operational bridge
  (`VnOperationalAddressResolverInterface::resolveFromRuntime` — a bridge miss yields null
  identity → the manager reports UNMAPPED); target scheme is applied once from
  `CarrierAddressCapabilityInterface::getRequiredScheme()`; context candidate codes stay
  empty (canonical candidates come from the manager, never trusted from the caller). No
  recipient PII is extracted; street text is kept only for the future external-disambiguation
  flow.
- `Api\Address\CarrierAddressHandoffInterface` + `Model\Address\CarrierAddressHandoff` —
  `isApplicable()` (false only for non-VN — the internal `UnsupportedDestinationException` is
  translated by the service, carriers never catch it), `getResolvedAddress()` (EXACT/MAPPED
  result; null for unresolved — a candidate is never exposed as resolved),
  `isTextualFallbackEligible()` (ALLOWED only — the carrier builds and executes its own
  provider-specific textual payload; ShippingCore never builds provider payloads), and
  `getFailureReason()` (shared `ShippingFailureReason` values: `UNSUPPORTED_DESTINATION` |
  `CANONICAL_UNRESOLVED`).
- `Api\Address\CarrierAddressHandoffServiceInterface` + `Model\Address\CarrierAddressHandoffService` —
  one runtime path: builder → request-cached `ShippingAddressResolutionManagerInterface` (no
  direct resolver calls, no double resolution); the external resolver pool is never invoked.

**Stage boundary (engineering rule)**: Stage 1 = ShippingCore canonical handoff (runtime address
→ target canonical identity, this surface). Stage 2 = carrier provider mapping (canonical
identity → provider IDs/text). A Stage-2 failure (provider mapping missing, provider API
failure) is carrier-owned and must NEVER be translated back into `CANONICAL_UNRESOLVED`.

**Engineering rule**: every Secomm-built Vietnam carrier must consume canonical destination
resolution through Secomm_ShippingCore and must not independently translate Vietnam
administrative schemes, select ambiguous canonical candidates, or invoke external
address-disambiguation providers. Provider-specific location mapping and provider-specific
textual representations remain carrier-owned.

## Carrier rate outcome (TASK-NAT3YV / SPIKE-YH439T, Phase E-C1)

Realtime carrier integrations report a common rate outcome: **SUCCESS**, **UNAVAILABLE**, or
**TECHNICAL_FAILURE** — replacing the ad-hoc `false/null/throw/fake-rate/log-and-continue`
patterns:

- `Api\Rate\CarrierRateInterface` + `Model\Rate\CarrierRate` — quoted amount (**>= 0: zero is
  a domain-valid promotional/configured rate; only negative is rejected** — same rule as
  `FallbackRate`; a fallback provider's `null` is the only "no rate" representation) +
  optional currency. No Magento rate objects, no service level, no carrier identity — the
  collector already knows which carrier and service-level bucket it is polling.
- `Api\Rate\CarrierRateOutcomeInterface` + `Model\Rate\CarrierRateOutcome` — `getStatus()` /
  `getRate()` / `getFailureReason()` / `isSuccessful()`. Impossible combinations are
  unconstructible (SUCCESS without a rate or with a reason; UNAVAILABLE/TECHNICAL_FAILURE with
  a rate; unknown status → `LogicException`). Named factories: `success($rate)`,
  `unavailable(?reason)`, `technicalFailure(?reason)` — null reasons stay structurally valid;
  fallback gating keys on STATUS, never on reason presence.
- Shared cross-stage failure reasons have ONE ShippingCore owner:
  `Api\Failure\ShippingFailureReason` (`UNSUPPORTED_DESTINATION`, `CANONICAL_UNRESOLVED`,
  `PROVIDER_MAPPING_MISSING`, `SERVICE_UNAVAILABLE`, `TECHNICAL_ERROR`) — referenced by both
  the address handoff and the rate outcome contracts (no duplicate constants). Carrier-specific
  detailed codes stay carrier-owned free-form strings. **Outcome status drives orchestration;
  reason strings are diagnostic** — e.g. an UNAVAILABLE outcome carrying `TECHNICAL_ERROR`
  remains UNAVAILABLE (never fallback-triggering).

**Classification ownership**: the carrier adapter interprets raw provider errors —
timeout/connection/HTTP 5xx/outage → `TECHNICAL_FAILURE`; route-not-supported, invalid
dimensions, destination not serviceable, **provider mapping missing** (deterministic data/config
state → `REASON_PROVIDER_MAPPING_MISSING`), and **authentication/configuration errors** →
`UNAVAILABLE`. Config failures must never be classified technical: emergency fallback must not
hide a merchant misconfiguration indefinitely — they fail loudly in logs/admin diagnostics.
ShippingCore never interprets GHN/GHTK/Ahamove raw error codes.

**Stage boundary**: Stage 1 = canonical handoff (E-C0) · Stage 2 = carrier provider mapping ·
Stage 3 = realtime rate outcome (this surface). Only temporary technical failures may
contribute to emergency service-level fallback; business unavailability, address-resolution
failure, provider-mapping gaps, and configuration errors must not be treated as temporary.
Fallback pricing itself (later) is a separate rate source at service-level orchestration and is
never reported as a carrier `SUCCESS`.

## Service-level rate aggregation (TASK-32ACTR, Phase E-SL1)

Service-level realtime aggregation groups already-classified carrier outcomes. It preserves
successful realtime rates and exposes whether any temporary technical failure occurred:

- `Api\ServiceLevelRateAggregatorInterface` + `Model\ServiceLevel\ServiceLevelRateAggregator` —
  `aggregate(serviceLevelCode, outcomes)` validates the code dynamically against
  `ShippingServiceLevelRegistry` (unknown code = explicit configuration error; no hardcoded
  taxonomy) and groups `CarrierRateOutcomeInterface` entries that the caller keyed by carrier
  code — carrier identity is preserved into the aggregate (Option A keyed array; a wrapper DTO
  is reserved until real orchestration needs one).
- `Api\ServiceLevelRateAggregateInterface` + `Model\ServiceLevel\ServiceLevelRateAggregate` —
  `getServiceLevelCode()`, `getSuccessfulRates(): array<string, CarrierRateInterface>`
  (carrier-code keyed, input order preserved — no ranking), `hasSuccessfulRate()`,
  `hasTechnicalFailure()` (true whenever any outcome was TECHNICAL_FAILURE, even alongside
  successes — the "any SUCCESS ⇒ no fallback" rule belongs to E-SL2), `getOutcomeCount()`.
  Zero outcomes is a valid aggregate (service level configured, no carrier adapter yet).

Aggregation does not select a carrier, calculate fallback pricing, or infer failure semantics
from reason strings. Future relationship: **E-SL1 aggregates realtime outcomes; E-SL2 decides
whether fallback may be requested**.

## Service-level fallback decision (TASK-M3ME32, Phase E-SL2 — final foundation slice)

`Api\ServiceLevelRateOrchestratorInterface` + `Model\ServiceLevel\ServiceLevelRateOrchestrator` —
`decide(serviceLevelCode, aggregate, fallbackRequest)` returns
`ServiceLevelRateDecisionInterface` (**REALTIME | FALLBACK | UNAVAILABLE**):

- **Any realtime SUCCESS suppresses fallback** — the fallback provider is never called when a
  usable realtime rate exists; all realtime rates are exposed unranked with carrier identity.
- **UNAVAILABLE alone never triggers fallback**; only a TECHNICAL_FAILURE on an ENABLED,
  policy-allowed service level reaches the fallback provider — called at most once. A provider
  `null` result means unavailable; a zero-valued fallback rate is a valid explicit FALLBACK.
- **`enabled` is the service-level exposure policy**: a disabled level is UNAVAILABLE even when
  the aggregate carries successes (the aggregate itself is never modified).
- `Api\Fallback\FallbackPolicyInterface` + `Model\Fallback\ConfigurableFallbackPolicy` —
  per-level opt-in via the `fallbackEnabledByLevel` DI array; unlisted levels are DENIED
  (emergency pricing must be opted into). No rules engine/priority/conditions. Admin-config
  backing can replace the implementation via preference later.
- Exactly zero or one fallback provider is supported: zero providers with an otherwise-eligible
  fallback is a normal UNAVAILABLE; MORE THAN ONE registered provider fails fast as a
  configuration ambiguity — ShippingCore never routes between providers.
- Code/aggregate mismatch and unknown codes fail fast (programming/configuration errors).

**Fallback is emergency pricing for a service level — not an eligibility engine and not a
carrier replacement.** Fallback rates are never attached to a carrier identity.

## Fallback eligibility — v5 (TASK-5JQYMP / DEC-FEATYA2C0W-005)

E-SL2 fallback eligibility là explicit orchestration state với ĐÚNG 2 nguồn
(`FallbackEligibilitySource`): `TECHNICAL_FALLBACK` (temporary technical/provider failure) và
`LEGACY_ADDRESS_FALLBACK` (merchant opt-in legacy RATE strategy — `LegacyRateStrategy`:
`DIRECT_FALLBACK` | `MAP_THEN_FALLBACK`; implementation naming `DIRECT_FALLBACK` ≡ architecture
wording "FALLBACK_ONLY", documented mapping). Aggregation carries eligibility qua optional
`FallbackEligibilityInterface` param; orchestrator merge: eligible = technical (aggregate) OR
legacy (input) — SUCCESS vẫn suppress; provider called at-most-once trên eligible path;
eligibility + policy-disabled → UNAVAILABLE; provider null → UNAVAILABLE. Không parse
failure reasons; extending the source list requires an architecture amendment
(DEC-FEATYA2C0W-005).

## ShippingCore foundation — complete flow (hard stop)

```text
address handoff (E-C0)
  → carrier API / provider mapping (Stage 2, carrier-owned)
  → CarrierRateOutcome (E-C1)
  → ServiceLevelRateAggregate (E-SL1)
  → ServiceLevelRateDecision (E-SL2: REALTIME | FALLBACK | UNAVAILABLE)
```

**The ShippingCore foundation scope is COMPLETE with E-SL2.** No further generic architecture
is added without evidence from real carrier/bridge integration; the next phase validates these
contracts with real consumers (carrier adoption + the Launchpad TableRate bridge).

## COD payment identification (TASK-STC3NB, architecture v4 §4.1)

`Api\Cod\CodPaymentMethodResolverInterface` + `Model\Cod\ConfiguredCodPaymentMethodResolver` —
ShippingCore owns the CONFIGURATION declaring which Magento payment method codes are treated as
Cash On Delivery (`secomm_shippingcore/cod/payment_methods`, comma-separated, default empty =
nothing is COD) plus a provider-neutral resolver: `isCod(paymentMethodCode): bool`.

- Exact, case-sensitive match on trimmed codes — prefix/similar codes are NOT matches.
- Empty/malformed config → `false`; no default COD method exists (explicit declaration only).

## Per-operation address capability + resolution snapshot (TASK-Y3X6H5, architecture v4 §5/§5.1/§6)

Address capability is PER-OPERATION — the same carrier may need a different canonical scheme
and a different representation for RATE vs CREATE (GHN: RATE = PRE-2025 + UNIT_ID, CREATE =
2025 + TEXT_NAME):

- `Api\Address\ShippingAddressOperation` — RATE | CREATE (P1 scope; CANCEL/TRACK have no
  address capability). `Api\Address\AddressRepresentation` — UNIT_ID | TEXT_NAME (categories
  only; GEOPOINT is deferred P2). ShippingCore owns the scheme + representation CATEGORY —
  the concrete provider values are rendered carrier-owned at Stage 2.
- `Api\Address\CarrierOperationAddressCapabilityInterface` — the per-operation capability
  (`getRequiredScheme(op)`, `getSupportedRepresentations(op)`, `supportsTextualFallback(op)`).
  The legacy per-carrier `CarrierAddressCapabilityInterface` is deprecated (kept compiling
  until carriers migrate).
- Handoff per-operation: `handoffForOperation(destination, capability, operation)` /
  `handoffContextForOperation(context, capability, operation)` resolve against the operation's
  scheme and expose `getSupportedRepresentations()` on the handoff. Unknown operations and
  context/capability scheme mismatches fail fast. Legacy `handoff()`/`handoffContext()` keep
  their exact previous behavior.
- `Api\Address\CanonicalResolutionSnapshotInterface` + VO — the shift-left resolution snapshot
  (canonical 2025 identity + Secomm PRE-2025 unit codes + status + failure_class
  NONE|AMBIGUOUS|UNMAPPED|TECHNICAL + source + provenance). Canonical codes only — provider
  IDs have no field, structurally. External-source snapshots must name their resolver.
  Persistence is implementation-owned (separate task). External resolver providers stay
  AMBIGUOUS-only selectors (never UNMAPPED, never minting canonical units).
- Identification ONLY: no COD eligibility/amounts/surcharges/reconciliation — carriers read
  order monetary data themselves when building provider requests; they ask ShippingCore only
  "is this payment method COD?" before mapping provider-specific COD fields
  (GHN `cod_amount`; GHTK `pick_money` + `pick_option: cod`).

## Destination-scope carrier eligibility (TASK-8MQHJX Phase A+B, architecture v10 §35)

ShippingCore owns the GENERIC evaluation answering "may this carrier serve this canonical
destination?" — zone definitions are merchant/composition data, never hardcoded
(`HCM_INNER` etc. are examples, not domain enums).

- `Api\Address\DestinationScope` — `ALL` (every valid canonical destination) |
  `SELECTED_ZONES` (destination must match ≥ 1 allowed zone code).
- `Api\Address\CanonicalZoneInterface` + `Model\Address\CanonicalZone` — immutable zone VO:
  code, label, enabled, includeProvinceCodes, includeWardCodes, excludeWardCodes.
  Empty code/label fail fast.
- `Api\Address\CanonicalZoneRegistryInterface` + `Model\Address\CanonicalZoneRegistry` —
  DI-contributed zone definitions (`canonicalZones` item entries; zero zones is valid);
  duplicate/empty zone codes fail fast.
- `Api\Address\CanonicalZoneMatcherInterface` + `Model\Address\CanonicalZoneMatcher` —
  deterministic fail-closed precedence: disabled → false; province gate; include-ward gate
  (empty includeWards = no positive ward restriction; `null` ward fails the gate);
  exclude-ward wins; case-sensitive strict comparison, canonical codes only.
- `Api\Rate\CarrierEligibilityEvaluatorInterface` + `Model\Rate\CarrierEligibilityEvaluator` —
  `ALL` short-circuits eligible (no zone lookups); `SELECTED_ZONES` walks allowed codes in
  configuration order, skipping unknown/disabled zones, first matcher hit wins (its zone code
  is reported); no hit / unknown scope → ineligible `DESTINATION_NOT_IN_SCOPE` (fail-closed).

Eligibility is destination-scope-ONLY: it never decides RateSourceMode, AddressResolutionPolicy,
fallback dispatch, provider mapping, or carrier API calls. An ineligible carrier contributes
NEITHER realtime rates NOR fallback eligibility (wired up in the execution layer — next slice).

### Shared execution gating (Phase C)

`Api\Rate\CarrierRateExecutionServiceInterface` + `Model\Rate\CarrierRateExecutionService` —
ONE provider-neutral runtime path per carrier RATE operation, hard-ordered (v10 §35):
eligibility → RateSourceMode → origin readiness → AddressResolutionPolicy (through the shared
handoff service — no address resolution duplicated here) → realtime contributor → one
contribution (outcome + fallback eligibility metadata).

- Mode semantics: `FALLBACK_ONLY` skips origin/policy/handoff/realtime entirely (eligible
  carrier keeps `LEGACY_ADDRESS_FALLBACK`); `CARRIER_ONLY` never emits fallback eligibility;
  `CARRIER_WITH_FALLBACK` emits eligibility only where the shared
  `FallbackEligibilityPolicyInterface` judges the failure eligible (TECHNICAL_FAILURE →
  `TECHNICAL_FALLBACK`; UNAVAILABLE+CANONICAL_AMBIGUOUS → `LEGACY_ADDRESS_FALLBACK`).
- Origin readiness: the minimal shared gate only (a snapshot without a country is not a
  resolvable origin). A realtime mode with an unresolved origin is NOT executable and is never
  re-moded: fail closed (CARRIER_ONLY → nothing; CARRIER_WITH_FALLBACK → judged by the shared
  policy on UNAVAILABLE+INVALID_CONFIGURATION — default not eligible, opt-in via DI).
- Address policy blocks (unresolved handoff): STRICT → no realtime, no fallback (either
  mode); FALLBACK + AMBIGUOUS (candidates retained) → no realtime, legacy-address eligibility
  for CARRIER_WITH_FALLBACK only; PICK_PRIMARY success reaches realtime as a plain resolved
  handoff — candidates, order and rank never cross the boundary; PICK_PRIMARY failure blocks
  like STRICT. UNMAPPED (non-ambiguous, textual-eligible) handoffs still reach realtime —
  carrier-side textual strategy keeps working.
- `CarrierRateExecutionDecision` composes EXISTING domain values (outcome + handoff +
  fallback eligibility + path reason) — no parallel domain model. The service NEVER
  aggregates carriers, dispatches fallback, suppresses fallback globally, or selects
  methods — those stay with the service-level orchestrator and the composition layer.
- `RealtimeCarrierRateContributorInterface` has NO default implementation: carrier modules
  implement it and close over their own runtime context (quote/rate-request data, provider
  clients); input is the FINAL carrier-facing handoff, output the shared
  `CarrierRateOutcome` domain.

Fallback source amendment (TL-approved, same task — closes an implementation-discovered gap,
no v11): `FallbackEligibilitySource.INTEGRATION_LIMITATION` — realtime blocked by insufficient
shared/provider integration data, mapping or adapter readiness, and NOT a confirmed business
rejection. Frozen mapping: UNAVAILABLE+PROVIDER_MAPPING_MISSING (CARRIER_WITH_FALLBACK) →
`integrationLimitation()` eligibility, outcome stays UNAVAILABLE — and
`ServiceLevelRateOrchestrator` (final owner) reads this source, so the fallback can actually
dispatch. End-to-end proof lives in `CarrierRateExecutionFlowIntegrationTest`. `INVALID_CONFIGURATION` is
merchant-side configuration and stays fail-closed even under policy opt-in; §35.5's
configurable auth/config seam needs a provider-auth reason + warning seam of its own.

## Dependencies

Magento Framework / Store / Directory / Shipping + `Secomm_VietNamAddress`
(scheme/unit status semantics — DEC-FEATYA2C0W-004 D1, one-way). Depends on NO
Secomm carrier and NO external resolver module. Loaded by `Secomm_Ghtk`,
`Secomm_GiaoHangNhanh`, `Secomm_Ahamove` (module.xml sequences).


## Shared carrier runtime primitives (TASK-7AJ3K8 / DEC-TASK7AJ3K8-001, Phase E-C1)

Consumer runtime đầu tiên của pipeline E-A/E-B/E-C0 là `Secomm_Ghtk`. Ba primitive dùng chung:

1. **Runtime address context** — `RuntimeAddressContextBuilderInterface` nhận scalar runtime
   address (country, regionId, cityId?, native-city locality, street): id-based bridge trước,
   name-based fallback (`VnOperationalNameResolverInterface` — transitional D5 entry), AMBIGUOUS
   candidates vào context (không identity — manager surfaces AMBIGUOUS, không pick).
   `DestinationContextBuilder` (quote) delegate builder này — 1 identity path chung. Carriers
   non-quote (RateRequest / sales order address) dùng `CarrierAddressHandoffServiceInterface::
   handoffContext()` — không fake `Quote\Address`. `CarrierAddressHandoff::getCandidateCodes()`
   cho phép carrier policy phân biệt AMBIGUOUS (không gửi request) vs UNMAPPED (textual fallback).
2. **HTTP transport** — `CarrierHttpClientInterface` (send/sendJson) + 6-category error taxonomy
   (`CarrierHttpErrorCategory`) + `RetryPolicy`/`RetryExecutor`. MỘT exchange mỗi call; non-2xx =
   throw có category; KHÔNG tự retry, KHÔNG log payload. Retry là policy carrier: safe read retry
   NETWORK/SERVER_ERROR/TIMEOUT; create-order single-attempt cho đến khi carrier chứng minh
   idempotency. 429 classify nhưng không retry. Auth/endpoint/payload thuộc carrier.
3. **Tracking reconciliation** — `CarrierTrackingFetcherInterface` (carrier: tracking number →
   `TrackingUpdate`) + `TrackingReconciliationService` (shared: terminal exclude, stale filter,
   batch, per-item continue) → cùng `CarrierTrackingProcessorInterface` với webhook. Wire per
   carrier bằng DI virtual type (fetcher + carrierCode + batchSize); config gate/threshold ở
   carrier.

Engineering rule: ShippingCore không biết carrier internals; carrier không query directory
datasets; profile (`CarrierApiProfileInterface`) là bundle hoàn chỉnh của carrier (code +
addressScheme + endpoints), không có default preference (reverse dependency bị cấm).
