# Task Spec: ShippingCore — Service-level realtime rate aggregation (Phase E-SL1)

Specification ID: SPEC-TASK-32ACTR

> Filename: `SPEC-TASK-32ACTR-shippingcore-service-level-rate-aggregation.md` — standalone
> work-item spec (slice Phase E-SL1 của FEAT-YA2C0W; aggregation step only — KHÔNG phải routing
> engine; thực thi SPIKE-YH439T mixed-outcome model + E-C1 outcome semantics).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-32ACTR |
| Feature ID | FEAT-YA2C0W (parent; slice Phase E-SL1) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ approved E-SL1 directive |
| Status | **VALID** — aggregation shape theo directive; TL review spec text chạy cùng code pre-review |
| Date | 2026-09-08 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D2/D10 — lean, no speculative abstraction) |
| Related Ticket(s) | TASK-32ACTR · TASK-NAT3YV (E-C1 outcomes) · TASK-XXBN5X (E-SL0 registry) · TASK-T78YH6 (E-C0 handoff) |
| Workflow Mode | A (shipping shared-contract = generic risk category) |

## 1. Objective

Một bước aggregation duy nhất: cho **1 dynamic service-level code** + **tập realtime carrier
outcomes đã được classify** (E-C1), trả aggregate nhỏ trả lời đúng 2 câu hỏi cho E-SL2:

```text
Are there any usable realtime rates?   → hasSuccessfulRate() + successful rates
Are there any technical failures?      → hasTechnicalFailure()
```

E-SL1 **KHÔNG**: chọn carrier (cheapest/preferred/fastest/SLA), tính/trigger fallback, đánh giá
cut-off/routing, inspect reason strings, invoke `FallbackRateProviderInterface`, đụng checkout
method. Scope = "lean Launchpad aggregation, not a generic routing platform".

## 2. Verified implementation basis

* `ShippingServiceLevelRegistry` (E-SL0): `assertKnown(code)` → `LocalizedException` cho unknown
  (current project convention cho config/programming error) — dynamic validation, không constants.
* `CarrierRateOutcomeInterface` (E-C1): SUCCESS ⇒ rate non-null đã được VO guarantee — aggregate
  REUSE invariant, không duplicate validation; status là orchestration semantic, reason diagnostic
  (r1 rule) — aggregator KHÔNG đọc reason bất kỳ đâu.
* Carrier identity (directive §12/§13 audit): `CarrierRateInterface` chỉ có amount/currency —
  nếu aggregate trả bare rates thì mất "ai sinh ra rate". **Option A (theo thứ tự ưu tiên
  directive): caller truyền outcomes KEYED theo carrier code** (`['ghn' => outcome, …]`) —
  aggregate giữ nguyên association; KHÔNG cần wrapper DTO. Option B (wrapper
  ServiceLevelCarrierRate) chỉ introdu khi E-SL2 chứng minh cần value object.

## 3. Scope — `Api\` + `Model\ServiceLevel\`

### 3.1 ServiceLevelRateAggregatorInterface (+ ServiceLevelRateAggregator)

```php
public function aggregate(
    string $serviceLevelCode,
    array $outcomes
): ServiceLevelRateAggregateInterface;
// $outcomes: array<string, CarrierRateOutcomeInterface> — KEY = carrier code (bắt buộc non-empty
// string, bảo toàn identity §13); value = CarrierRateOutcomeInterface. Entry sai → LogicException.
```

Constructor DI: `ShippingServiceLevelRegistry`. Flow: `assertKnown(serviceLevelCode)` (unknown →
`LocalizedException` — explicit config error, §3 directive) → iterate (không sort) → aggregate VO.

### 3.2 ServiceLevelRateAggregateInterface (+ ServiceLevelRateAggregate VO)

```php
interface ServiceLevelRateAggregateInterface
{
    public function getServiceLevelCode(): string;
    /** @return array<string, CarrierRateInterface> carrier-code keyed, input order preserved */
    public function getSuccessfulRates(): array;
    public function hasSuccessfulRate(): bool;
    public function hasTechnicalFailure(): bool;
    public function getOutcomeCount(): int;   // diagnostics/tests (directive §4 cho phép)
}
```

VO immutable; chỉ state đã aggregate — không metadata, không fallback fields, không service-level
definition object (chỉ code string).

### 3.3 Behavior matrix (directive §22 — implement CHÍNH XÁC)

| Outcomes | successfulRates | hasTechnicalFailure |
|---|---|---|
| [] | [] | false |
| UNAVAILABLE | [] | false |
| TECHNICAL_FAILURE | [] | true |
| SUCCESS | 1 | false |
| SUCCESS + UNAVAILABLE | 1 | false |
| SUCCESS + TECHNICAL_FAILURE | 1 | **true** (tín hiệu không bị suppress — E-SL2 rule "any SUCCESS ⇒ no fallback" thuộc E-SL2) |
| UNAVAILABLE + TECHNICAL_FAILURE | [] | true |
| SUCCESS + SUCCESS | 2 | false |

Reason strings KHÔNG được đọc bất kỳ đâu (§10/§11): UNAVAILABLE + TECHNICAL_ERROR/null như nhau;
TECHNICAL_FAILURE + null vẫn set hasTechnicalFailure. Zero outcomes là valid state (§9 — service
level configured nhưng chưa có carrier adapter nào).

## 4. Out of scope

Fallback trigger/`FallbackRateProvider` invocation · `FallbackPolicy`/decision engine · carrier
ranking/selection/priority · carrier membership config · checkout method/showmethod · carrier
adoption · provider API · address mapping/external resolver · OrderOperations/OMS · hardcoded
Launchpad taxonomy (production + tests dùng LEVEL_A/LEVEL_B-style dynamic codes — §18).

## 5. Acceptance Criteria

* **AC-1**: Dynamic validation qua registry (`assertKnown`); unknown code → `LocalizedException`;
  0 hardcoded EXPRESS/SAME_DAY/STANDARD (production + tests).
* **AC-2**: Matrix §3.3 đúng 8 hàng; successful rates giữ nguyên carrier-code keys + input order;
  mixed SUCCESS+TECHNICAL_FAILURE không suppress tín hiệu.
* **AC-3**: Reason strings không được đọc bất kỳ đâu trong aggregator (grep); zero outcomes valid.
* **AC-4**: Input validation: key non-empty string (carrier identity bắt buộc — §13), value
  `CarrierRateOutcomeInterface`; sai → `LogicException`.
* **AC-5**: Không dependency `FallbackRateProviderInterface` trong constructor/API (grep); không
  sort/ranking; `getOutcomeCount()` đúng.
* **AC-6**: DI preference; compile + validator 0 new finding + phpunit pass; README/CHANGELOG
  (aggregation rule + E-SL1→E-SL2 relationship) + working memory sync; 0 carrier code.

## 6. Test plan (unit, AAA)

`ServiceLevelRateAggregatorTest` (registry thật với LEVEL_A/LEVEL_B test-defined): 8 hàng matrix ·
unknown code → `LocalizedException` · zero outcomes valid · carrier keys + input order preserved ·
reason independence (UNAVAILABLE+TECHNICAL_ERROR → false; TECHNICAL_FAILURE+null → true) ·
non-string key / sai value type → `LogicException` · getOutcomeCount · no-FallbackProvider
dependency (constructor signature).
