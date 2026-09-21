# Implementation Plan: TASK-XXBN5X — Phase E-SL0 service-level + fallback contracts

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-XXBN5X (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract — generic risk category) |
| Specification | [specs/SPEC-TASK-XXBN5X-shippingcore-service-level-fallback-contracts.md](../specs/SPEC-TASK-XXBN5X-shippingcore-service-level-fallback-contracts.md) — FULL, VALID (approved E-SL0 directive; TL review chạy cùng pre-review) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md) — D7 DI-pool pattern; E-SL0 không có architecture decision mới (thực thi SPIKE-WHHEZV §7/§9/§13/§17 đã approved) |
| Architecture basis | [SPIKE-WHHEZV](../research/SPIKE-WHHEZV-mptablerate-service-level-fallback.md) §7/§9/§13 (service-level + fallback design, audit Mageplaza v4.0.8) + [SPIKE-YH439T](../research/SPIKE-YH439T-shippingcore-carrier-runtime-handoff.md) (failure model) |
| Contract basis | TASK-5XDG1P (E-B) — pool/VO conventions mirror |
| Risk | Medium — additive contracts only; 0 carrier/Mageplaza/Launchpad code; 0 orchestration; chưa có runtime consumer |
| Review round | **r1 (2026-09-08)** — TL/SA reject hardcoded constants → dynamic service-level model (interface + VO + registry, DI array); storage Option A; xem record "Review rounds" + SPEC §3.1-r1 |

## Approach

6 contracts-only additions (§3 spec): `Api\ShippingServiceLevel` (constants + `exists`/`assertKnown`
mirror `VnSchemes` precedent, không registry), `Api\CarrierServiceLevelInterface` (carrier khai
báo, ≤1 member), `Api\Fallback\{FallbackRateRequestInterface, FallbackRateInterface,
FallbackRateProviderInterface}` (provider-neutral; KHÔNG RateRequest/Mageplaza/PII; request 8
trường mapped 1-1 từ audit Mageplaza; rate amount>0 enforce rule "no-match ⇒ null, không zero-fee"
ở VO constructor), `Model\Fallback\{FallbackRateRequest, FallbackRate, FallbackRateProviderPool}`
(VOs immutable tự-guard + pool mirror `ExternalAddressResolverPool` zero-valid). DI: pool array
argument rỗng. KHÔNG orchestration/config/checkout-method/outcome-taxonomy (directive §16/§17/§18).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | `.ai/specs/SPEC-TASK-XXBN5X-…md`, `.ai/plans/TASK-XXBN5X-…md`, `.ai/records/tasks/TASK-XXBN5X.md`, FEAT-YA2C0W `ticket_ref` += TASK-XXBN5X | spec-first TRƯỚC code; ID mint qua idgen |
| 2 | Service-level | `ShippingCore/Api/{ShippingServiceLevel, CarrierServiceLevelInterface}.php` | constants + assertKnown; docblock known-limitation (directive §5) |
| 3 | Fallback contracts | `ShippingCore/Api/Fallback/{FallbackRateRequestInterface, FallbackRateInterface, FallbackRateProviderInterface}.php` | neutral; null = no rate; provider không quyết eligibility (§12) |
| 4 | VOs + pool | `ShippingCore/Model/Fallback/{FallbackRateRequest, FallbackRate, FallbackRateProviderPool}.php` | invariant ở constructor; pool mirror ExternalAddressResolverPool |
| 5 | DI | `ShippingCore/etc/di.xml` | pool array argument rỗng; không preference mới |
| 6 | Tests | `ShippingCore/Test/Unit/Api/ShippingServiceLevelTest.php` (dir mới) + `Test/Unit/Model/Fallback/{FallbackRateRequestTest, FallbackRateTest, FallbackRateProviderPoolTest}.php` | §8 spec test plan |
| 7 | Docs | `ShippingCore/{README.md, CHANGELOG.md}` | surface + 2 engineering rules (directive §20) |
| 8 | Validation | phpunit secomm · validator · `setup:di:compile` · grep Mageplaza/Launchpad/RateRequest (AC-6) | |
| 9 | Working memory | CURRENT_STATE + evidence `.ai/evidence/TASK-XXBN5X/` | sync sau validation |

## Test plan

- `ShippingServiceLevelTest`: 3 constants ổn định · `exists` matrix (3 true + lạ false) ·
  `assertKnown` lạ → `LocalizedException` · anonymous-class stub `CarrierServiceLevelInterface`
  confirm shape.
- `FallbackRateRequestTest`: transport 8 trường · nullable country/region/postcode/customerGroup ·
  negative weight/subtotal/qty → `LogicException` · không có getter carrier/method (transport
  test đủ, không reflection-heavy).
- `FallbackRateTest`: happy (amount/label/estimate) · estimate nullable · amount 0/negative →
  `LogicException` · label rỗng → `LogicException`.
- `FallbackRateProviderPoolTest`: zero valid · 1 retrievable · order preserved · invalid entry →
  `LogicException` (mirror ExternalAddressResolverPoolTest).
- Regression: compile + Secomm suite; 0 carrier/Mageplaza đổi (git status).

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Request DTO thiếu field khi bridge thật cần (vd shipping group) | Documented §3.3 — extension follow-up có evidence; KHÔNG speculative (directive §7) |
| amount>0 quá gắt (merchant muốn fallback 0đ) | Rule §11 bắt no-match ⇒ null; 0-fee fallback là revenue risk (silent free shipping) — fail loud là chủ đích, TL review |
| Pool bị dùng làm competition/chaining sau này | Docblock + spec §3.6 chốt zero/one; mở rộng chỉ khi có evidence |

Rollback: revert — không DB, không config, không carrier code.
