# Evidence — r4 code-closure (2026-09-22)

## Task 1 — full decision identity (r4)

BEFORE: identity = status + reason → two executions differing ONLY in eligibility (NONE vs
LEGACY_ADDRESS_FALLBACK under the same UNAVAILABLE/CANONICAL_AMBIGUOUS) or ONLY in rate
amount/currency merged wrongly (eligibility union / price swap by plugin order).
AFTER: identity = status + failure reason + complete eligibility source set (order-insensitive
sort) + rate amount + rate currency (successes). Identical → idempotent; anything else →
first record wins WHOLE (no synthetic mixed records, no union across executions).
Tests: 15/32 (`CarrierRateOutcomeCollectorDecisionTest`) — incl. NONE-vs-technical conflict,
amount conflict, currency conflict, source-order determinism, legacy/transported both orders,
success terminal both directions, member isolation, bracket cleanup.

## Task 2 — GHN transport contract (tripwire)

`GhnTransportContractTest` (source-contract): 0 legacy `recordOutcome(` CALL SITES trong
carrier; ≥4 `recordDecision(` sites; mọi call mang explicit eligibility argument. Inventory
(after): inactive = no record · non-VN = NONE · non-VND = NONE · zone miss = NONE ·
FALLBACK_ONLY = LEGACY_ADDRESS (0 resolver/handoff/contributor/API) · success = NONE ·
mapping-missing = INTEGRATION_LIMITATION · technical = TECHNICAL_FALLBACK · catch-all =
TECHNICAL_FALLBACK (see Task 3 conflict) · blocked = decision eligibility verbatim.

## Task 3 — catch-all policy verdict: DECISION CONFLICT (STOP, giữ nguyên code)

Contributor đã translate timeout/5xx/429/malformed/auth/business TRƯỚC catch-all — catch-all
chỉ còn unexpected runtime/programming errors. SSOT §35.5 liệt kê technical timeout/5xx/outage
→ fallback YES nhưng KHÔNG liệt kê programming failure. Current code maps catch-all →
TECHNICAL_FALLBACK (eligible). Audit KHÔNG tự đổi policy: giữ hành vi hiện có (graceful
checkout; error/critical log không swallow — `ghnLogger->error` + exception message logged),
và ghi DECISION CONFLICT cho TL: (A) giữ technical fallback cho unexpected throw (status quo),
hay (B) fail-closed (NONE) cho programming defects. Không record hai lần: inner layer đã
return decision → catch-all chỉ fire khi KHÔNG có decision (exception trước return).

## Task 4/5 — call-count + production-composed: PARTIAL (trung thực)

- Direct counts có: contributor↔calculator 1:1 spy (`RealtimeContributorCallCountTest`,
  `quoteWithHandoff` là entry mapping+API); legacy-policy/provider/append counters per case
  (hai matrix files); collector lifecycle counters.
- API-client-level spy (GhnApiClientInterface::calculateFee trong GhnRateCalculator thật) và
  full production-composed chain (real Ghn carrier + real calculator + real client-spy +
  Launchpad coordinator trong 1 fixture): CHƯA dựng trong lượt này — fixture phức tạp
  (GhnRateCalculator constructor 7 deps + mapping resolver) — ghi BLOCKED_BY_TEST_FIXTURE,
  KHÔNG gọi các matrix hiện tại là E2E. Classification: matrix tests = integration
  composition; FlowIntegrationTest = service integration; unit = policy proof.

## Task 7 — regression (combined uncommitted stack)

AddressDropdown 96/223 · VietNamAddress 192/776 · ShippingCore 424/1113 · Ghn 359/210,893 ·
Ghtk 237/585 · MageplazaTableRate 93/175 (0 fail/error toàn bộ) · compile OK · validator exit 0.

## Review packages

`review-packages.md` — P1 FEAT-QA23PZ wiring (review first) · P2 eligibility transport
(second) · P3a/b/c audit batches (independent). Overlap hunks ghi rõ (Ghn.php hai concern;
FallbackCoordinator hai hunk; Collector chỉ P2).


---

# r5 (2026-09-22) — TL decision applied: catch-all FAIL-CLOSED

- `ShippingFailureReason::UNEXPECTED_RUNTIME_FAILURE` (additive constant, carrier-neutral).
- `Ghn::collectRates()` catch-all: log error với exception_class + sanitized message +
  carrier/method (không token/PII) → recordDecision(TECHNICAL_FAILURE/UNEXPECTED_RUNTIME_FAILURE,
  eligibility NONE) → hide. Không legacy policy, không double-record (success-terminal merge
  giữ earlier record), không rethrow ra checkout.
- GHN suite OK 368/210,924 (catch-all test assert transported NONE + log context).
- API-client fixture (`CalculatorApiClientFixtureTest` 9/27): success 1 call + payload fields
  (service_type_id, to_district_id, to_ward_code, không sensitive); mapping-missing 0 calls;
  empty parcel 0 calls; timeout/5xx 1 call → TECHNICAL; malformed 1 call → TECHNICAL (never
  zero rate); auth 1 call → fail-closed business; TypeError propagate tới carrier catch-all.
- Regression r5: AD 96 · VN 192/776 · SC 424/1113 · Ghn 368/210,924 · Ghtk 237/585 · MT 93/175 —
  0 fail/error · compile OK · validator exit 0 · diff 74 files +1,978/−738.
- Runtime runbook: `runtime-verification-runbook.md` (DB/browser/REST-GraphQL — chưa chạy).
