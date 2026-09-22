# Task Spec: ShippingCore — Service-level fallback decision orchestration (Phase E-SL2, FINAL foundation slice)

Specification ID: SPEC-TASK-M3ME32

> Filename: `SPEC-TASK-M3ME32-shippingcore-service-level-fallback-decision.md` — standalone
> work-item spec (slice Phase E-SL2 của FEAT-YA2C0W; **E-SL2 là slice foundation ShippingCore
> CUỐI CÙNG cho scope Launchpad hiện tại — hard stop §29**: sau task này chỉ còn real-consumer
> validation (carrier adoption / bridge), KHÔNG thêm generic architecture khi chưa có evidence).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-M3ME32 |
| Feature ID | FEAT-YA2C0W (parent; slice Phase E-SL2) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ approved E-SL2 directive |
| Status | **VALID** — decision matrix theo directive §23; TL review spec text chạy cùng code pre-review |
| Date | 2026-09-08 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D2/D7/D10 — lean, no speculative abstraction) |
| Related Ticket(s) | TASK-M3ME32 · TASK-32ACTR (E-SL1 aggregate) · TASK-NAT3YV (E-C1 outcomes + r1 reasons) · TASK-XXBN5X (E-SL0 fallback contracts) |
| Workflow Mode | A (shipping shared-contract = generic risk category) |

## 1. Objective

Quyết định cuối cùng cho 1 service level: **REALTIME / FALLBACK / UNAVAILABLE** — từ
`ServiceLevelRateAggregate` (E-SL1) + fallback policy per-level + optional
`FallbackRateProviderInterface` + `FallbackRateRequestInterface` (caller-supplied). Đây là lần
đầu tiên `enabled` của service level được enforce ở ShippingCore runtime (§4).

## 2. Verified implementation basis

* `ShippingServiceLevelRegistry` (E-SL0): `getByCode()`/`assertKnown()`; `isEnabled()` CHƯA được
  đọc ở bất kỳ đâu — E-SL2 là reader đầu tiên (directive §4).
* `ServiceLevelRateAggregate` (E-SL1): public constructor — test dựng trực tiếp được;
  `hasSuccessfulRate()`/`hasTechnicalFailure()` là semantic DUY NHẤT được phép đọc (reason
  strings bị cấm — r1 rule NAT3YV).
* `FallbackRateProviderPool` (E-SL0): DI array, có thể hold nhiều provider — §15 cấm
  first-wins/chaining: **0 provider = UNAVAILABLE (không exception, §14); >1 provider =
  `LogicException` (ambiguity — report fail-fast theo project convention, KHÔNG chế routing)**;
  đúng 1 = dùng. REPORT TL (§15).
* `FallbackRate` (XXBN5X r2): amount >= 0, zero = valid explicit rate; provider null = no rate.
* Không có policy abstraction hiện hữu (grep 0) → tạo lean per §5.

## 3. Scope

### 3.1 `Api\Fallback\FallbackPolicyInterface` (+ `Model\Fallback\ConfigurableFallbackPolicy`)

```php
interface FallbackPolicyInterface
{
    /** fallback enabled cho service level? (không đọc reason/aggregate — policy thuần) */
    public function isEnabled(string $serviceLevelCode): bool;
}
```

Impl: DI array `fallbackEnabledByLevel` (`['LEVEL_A' => true, …]`) — **default DENY cho code
không liệt kê** (emergency pricing phải opt-in per level — SLA-sensitive levels mặc định tắt,
SPIKE-WHHEZV §13). KHÔNG: priority/rules engine/conditions/allow-list/time windows/price caps/
customer-group policy. Storage = DI (§7); upgrade path admin config = document-only (thay impl
qua preference, không đổi contract).

### 3.2 `Api\ServiceLevelRateDecisionInterface` (+ `Model\ServiceLevel\ServiceLevelRateDecision`)

```php
public const SOURCE_REALTIME    = 'REALTIME';
public const SOURCE_FALLBACK    = 'FALLBACK';
public const SOURCE_UNAVAILABLE = 'UNAVAILABLE';

getServiceLevelCode(): string;
getSource(): string;                                   // SOURCE_*
getRealtimeRates(): array;   // array<string, CarrierRateInterface> — as-is từ E-SL1 (§10)
getFallbackRate(): ?FallbackRateInterface;
isAvailable(): bool;
```

Invariants (VO constructor + factories `realtime(code, rates)` / `fallback(code, rate)` /
`unavailable(code)`): REALTIME ⇒ rates non-empty + fallback null + available; FALLBACK ⇒ rates
EMPTY + fallback non-null + available; UNAVAILABLE ⇒ rates empty + fallback null + !available.
KHÔNG reason field (§26 — 3 status đủ cho Launchpad scope).

### 3.3 `Api\ServiceLevelRateOrchestratorInterface` (+ `Model\ServiceLevel\ServiceLevelRateOrchestrator`)

```php
public function decide(
    string $serviceLevelCode,
    ServiceLevelRateAggregateInterface $aggregate,
    FallbackRateRequestInterface $fallbackRequest
): ServiceLevelRateDecisionInterface;
```

DI: `ShippingServiceLevelRegistry` + `FallbackPolicyInterface` + `FallbackRateProviderPool`.
KHÔNG Magento RateRequest (§20 — caller cấp ready FallbackRateRequest); KHÔNG retry/circuit
breaker (§18); KHÔNG address DTOs (§19); KHÔNG Mageplaza/carrier concepts (§16/§17).

### 3.4 Decision flow + matrix (directive §1/§23 — implement CHÍNH XÁC)

```text
1. registry.getByCode → null → LocalizedException (unknown code)
2. aggregate.getServiceLevelCode() ≠ code → LogicException (§22 consistency, fail-fast)
3. !definition.isEnabled() → UNAVAILABLE (realtime rates KHÔNG được expose kể cả aggregate
   có SUCCESS — §25; không provider call)
4. aggregate.hasSuccessfulRate() → REALTIME (rates as-is, không chọn — §11; KHÔNG provider call)
5. !hasTechnicalFailure() → UNAVAILABLE (UNAVAILABLE alone không bao giờ fallback)
6. !policy.isEnabled(code) → UNAVAILABLE (không provider call)
7. providers: 0 → UNAVAILABLE; >1 → LogicException (ambiguity §15)
8. provider.getRate(code, request) gọi ĐÚNG 1 LẦN → null → UNAVAILABLE; rate (kể cả 0đ) → FALLBACK
```

| Level | Success | Tech | Fallback enabled | Provider | Result | Decision |
|---|---|---|---|---|---|---|
| disabled | any | any | any | any | any | UNAVAILABLE |
| enabled | yes | no | any | any | n/a | REALTIME |
| enabled | yes | yes | any | any | n/a | REALTIME |
| enabled | no | no | true | yes | rate | UNAVAILABLE |
| enabled | no | yes | false | yes | rate | UNAVAILABLE |
| enabled | no | yes | true | no | n/a | UNAVAILABLE |
| enabled | no | yes | true | yes | null | UNAVAILABLE |
| enabled | no | yes | true | yes | rate > 0 | FALLBACK |
| enabled | no | yes | true | yes | rate = 0 | FALLBACK |

## 4. Out of scope

Mageplaza bridge/carrier adoption · selection/cheapest/preferred · checkout method/showmethod ·
OMS/routing/inventory · external resolver · retry/circuit breaker/backoff/health · admin CRUD/DB
· order metadata · reason taxonomy trên decision (§26).

## 5. Acceptance Criteria

* **AC-1**: Matrix §3.4 đủ 9 hàng; critical rule "any realtime SUCCESS ⇒ provider KHÔNG được
  call" test bằng mock expectation (cùng các nhánh disabled/no-technical/policy-disabled).
* **AC-2**: Provider gọi ĐÚNG 1 lần chỉ trên fallback path (kể cả path zero-rate).
* **AC-3**: Disabled level → UNAVAILABLE + realtime rates KHÔNG expose; aggregate không bị sửa.
* **AC-4**: Zero provider → UNAVAILABLE không exception; >1 provider → `LogicException` (§15
  ambiguity — REPORT TL); zero-rate fallback → FALLBACK (r2 semantics).
* **AC-5**: Code/aggregate mismatch → `LogicException` (§22); unknown code → `LocalizedException`.
* **AC-6**: 0 hardcoded taxonomy; 0 reason-string inspection (grep); 0 Magento RateRequest leak
  (grep); DI preferences (policy + orchestrator); compile + validator 0 new finding + phpunit
  pass; README (final flow + hard stop) + CHANGELOG + working memory sync; 0 carrier code.

## 6. Test plan (unit, AAA)

`ServiceLevelRateDecisionTest`: 3 factories + invariants + impossible combos reject.
`ServiceLevelRateOrchestratorTest` (registry thật LEVEL_A/LEVEL_B disabled + mock policy +
stub provider + real pool): 9 hàng matrix §3.4 · provider-call expectations (never ×4 nhánh;
exactly-once ×2 path) · zero provider · >1 provider → LogicException · zero-rate FALLBACK ·
code mismatch · unknown code · decision immutable.

## 7. Hard stop (§29 — document)

ShippingCore foundation scope **HOÀN THÀNH** tại E-SL2: flow đầy đủ
`address handoff → carrier API/provider mapping → CarrierRateOutcome → ServiceLevelRateAggregate
→ ServiceLevelRateDecision`. Không thêm generic architecture mới nếu không có evidence từ real
carrier/bridge integration; phase kế tiếp = real-consumer validation (GHN-C/GHTK-D/Ahamove-E +
LT-BRIDGE-1).
