# Task Spec: ShippingCore — v5 fallback eligibility orchestration + legacy RATE strategy (Delta C/E-SL2 v5)

Specification ID: SPEC-TASK-5JQYMP

> Filename: `SPEC-TASK-5JQYMP-shippingcore-v5-fallback-eligibility.md`. Implement Delta C/E-SL2 v5
> (DEC-FEATYA2C0W-005 + architecture Revision v5 §14/§15.1) — fallback eligibility tách khỏi
> failure semantics. Các delta A/B/D đã implement (TASK-Y3X6H5); Delta E COD đã implement
> (TASK-STC3NB) — verify-only.

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-5JQYMP |
| Feature ID | FEAT-YA2C0W (parent — chuỗi ShippingCore) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ implementation directive; architecture basis = address-shipping.md **Revision v5** §14/§15.1 + DEC-FEATYA2C0W-005 |
| Status | **VALID** — eligibility semantics theo DEC-005 nguyên văn; naming DIRECT_FALLBACK là implementation clarification (amend DEC-005 addendum + doc note) |
| Date | 2026-09-11 |
| Related Ticket(s) | TASK-5JQYMP · TASK-M3ME32 (E-SL2 base) · TASK-32ACTR (E-SL1) · TASK-NQT782 (bridge) |
| Workflow Mode | A (shipping shared-contract) |

## 1. Objective

E-SL2 decision hết giả định "fallback eligible IFF hasTechnicalFailure". Eligibility = explicit
orchestration state từ **ĐÚNG 2 nguồn** (DEC-005):

```text
TECHNICAL_FALLBACK        — aggregate có TECHNICAL_FAILURE (giữ nguyên hiện trạng)
LEGACY_ADDRESS_FALLBACK   — carrier RATE legacy strategy opt-in (DIRECT_FALLBACK / MAP_THEN_FALLBACK
                            + AMBIGUOUS unresolved / + UNMAPPED) — input caller-supplied
```

Bất kỳ realtime SUCCESS nào → REALTIME + suppress fallback (bất kể eligibility).

## 2. Audit (delta matrix — code 2026-09-11)

| Area | Current | Required v5 | Action |
|---|---|---|---|
| per-operation capability | `CarrierOperationAddressCapabilityInterface` + handoff per-op (Y3X6H5) | như architecture §5 | MATCH — không refactor |
| snapshot/failure_class | `CanonicalResolutionSnapshotInterface` + VO (Y3X6H5) | §5.1 + v5 snapshot integrity | MATCH — không refactor |
| fallback eligibility | Orchestrator chỉ `hasTechnicalFailure()`; không explicit eligibility state | Eligibility 2 nguồn explicit | **MISSING → IMPLEMENT** |
| legacy RATE strategy | Không có constants/seam | `DIRECT_FALLBACK`/`MAP_THEN_FALLBACK` + strategy identity | **MISSING → IMPLEMENT** |
| COD identification | `CodPaymentMethodResolverInterface` + config (STC3NB) | §4.1 v4 | MATCH — leave unchanged |

## 3. Scope

### 3.1 `Api\Fallback\FallbackEligibilitySource` (constants)

```text
TECHNICAL_FALLBACK = 'TECHNICAL_FALLBACK'
LEGACY_ADDRESS_FALLBACK = 'LEGACY_ADDRESS_FALLBACK'
```

### 3.2 `Api\Fallback\FallbackEligibilityInterface` + `Model\Fallback\FallbackEligibility` (VO)

```text
hasTechnicalFallbackEligibility(): bool
hasLegacyAddressFallbackEligibility(): bool
isEligible(): bool                    // any nguồn
getSources(): string[]                // FallbackEligibilitySource::* (deterministic thứ tự)
```

Static factories: `technical()`, `legacyAddress()`, `none()`. Immutable, fail-light (bool state).

### 3.3 `Api\Fallback\LegacyRateStrategy` (constants, implementation naming)

```text
DIRECT_FALLBACK      // ≡ architecture §15.1 wording "FALLBACK_ONLY" (legacy RATE) — mapping documented
MAP_THEN_FALLBACK
+ all()/exists()/assertKnown (unknown → LocalizedException)
```

Chỉ identity constants + validation — KHÔNG resolver interface trong task này (consumer supply
config sau, directive §16).

### 3.4 Aggregation carry (BC-safe)

`ServiceLevelRateAggregatorInterface::aggregate(code, outcomes, ?FallbackEligibilityInterface = null)`
(tham số optional — BC với callers hiện hữu) + `ServiceLevelRateAggregate` carries eligibility
(`getFallbackEligibility(): ?…`, `hasLegacyAddressFallbackEligibility()`).

### 3.5 Orchestrator (BC-safe)

`ServiceLevelRateOrchestratorInterface::decide(code, aggregate, fallbackRequest, ?FallbackEligibilityInterface = null)`:

```text
!enabled → UNAVAILABLE
SUCCESS → REALTIME (provider never called)
eligible = technical(aggregate) OR legacy(input)
!eligible → UNAVAILABLE
policy disabled → UNAVAILABLE
providers 0 → UNAVAILABLE / >1 → LogicException (giữ nguyên)
provider once → null → UNAVAILABLE / rate → FALLBACK
```

Không đổi bất kỳ nhánh hiện có nào; chỉ thêm nguồn legacy.

## 4. Out of scope

Strategy resolver interface/config seam (consumer supply sau — directive §16) · snapshot
persistence · external resolver provider · carrier changes · bridge changes · rules engine ·
ranking/retry.

## 5. Acceptance Criteria

* **AC-1**: Eligibility VO: sources deterministic, isEligible matrix, factories.
* **AC-2**: `LegacyRateStrategy` constants + assertKnown; KHÔNG dùng wording FALLBACK_ONLY cho
  strategy (tránh ambiguity bridge mode — directive §2).
* **AC-3**: Aggregator/orchestrator BC-safe (optional params); legacy eligibility → FALLBACK khi
  policy+provider cho phép; SUCCESS vẫn suppress (provider never called); eligibility + policy
  disabled → UNAVAILABLE; provider null → UNAVAILABLE; both sources → 1 fallback call.
* **AC-4**: Naming mapping documented (DEC-005 addendum + architecture §15.1 note).
* **AC-5**: compile + validator 0 new finding + regression pass; README/CHANGELOG + memory sync.

## 6. Test plan

`FallbackEligibilityTest` · `LegacyRateStrategyTest` · orchestrator additions: legacy+no-success+
policy → FALLBACK (provider once) · legacy+SUCCESS → REALTIME never-call · both sources → 1 call ·
eligibility+policy-disabled → UNAVAILABLE · provider-null → UNAVAILABLE · aggregator passthrough.
