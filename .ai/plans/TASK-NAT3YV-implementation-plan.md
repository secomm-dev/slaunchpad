# Implementation Plan: TASK-NAT3YV — Phase E-C1 carrier rate outcome semantics

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-NAT3YV (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract — generic risk category) |
| Specification | [specs/SPEC-TASK-NAT3YV-shippingcore-carrier-rate-outcome.md](../specs/SPEC-TASK-NAT3YV-shippingcore-carrier-rate-outcome.md) — FULL, VALID (approved E-C1 directive; TL review chạy cùng pre-review) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md); E-C1 không có architecture decision mới (failure model + 3-state đã approved SPIKE-YH439T; service-level/carrier-identity Option B theo directive) |
| Architecture basis | [SPIKE-YH439T](../research/SPIKE-YH439T-shippingcore-carrier-runtime-handoff.md) §8–§10 failure model + mixed-outcome examples |
| Contract basis | TASK-XXBN5X (E-SL0 FallbackRate — asymmetric amount rule documented) · TASK-T78YH6 (E-C0 handoff — reason values carry over) |
| Risk | Medium — additive contracts/VOs; chưa có runtime consumer; 0 carrier code |

## Approach

Contracts-only trong namespace mới `Api\Rate` + `Model\Rate`: `CarrierRateInterface` + VO
(amount >= 0 — zero hợp lệ chủ đích, asymmetric `FallbackRate` documented; currency nullable) ·
`CarrierRateOutcomeInterface` + VO (3 STATUS + 5 shared REASON constants; 2 giá trị address-stage
trùng handoff constants; public constructor + guards + 3 named factories mirror
`ResolvedShippingAddress` precedent; `isSuccessful()` hard-guard). Service level + carrier
identity ĐỨNG NGOÀI outcome (Option B — directive §6/§7). Không orchestration/logging-in-VO/
Magento RateResult dependency (§15/§16/§19). DI: 2 preference (mirror E-A).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT `ticket_ref` += TASK-NAT3YV | spec-first TRƯỚC code |
| 2 | Rate | `Api/Rate/CarrierRateInterface.php` + `Model/Rate/CarrierRate.php` | amount >= 0; currency nullable |
| 3 | Outcome | `Api/Rate/CarrierRateOutcomeInterface.php` + `Model/Rate/CarrierRateOutcome.php` | constants + invariants + factories |
| 4 | DI | `etc/di.xml` | 2 preference VO interfaces |
| 5 | Tests | `Test/Unit/Model/Rate/{CarrierRateTest, CarrierRateOutcomeTest}.php` | §7 spec |
| 6 | Docs | `README.md` + `CHANGELOG.md` | 3-state rule + auth/config non-technical + Stage 1/2/3 + mixed-outcome examples + fallback≠SUCCESS |
| 7 | Validation | phpunit secomm · validator · compile · grep (không RateResult/logger/metadata) | AC-4/AC-5 |
| 8 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-NAT3YV/` | |

## Test plan

Rate: positive/zero valid · negative reject · currency nullable/transport. Outcome: 3 factories +
constructor happy · SUCCESS thiếu rate → LogicException · SUCCESS có reason → reject ·
UNAVAILABLE/TECHNICAL có rate → reject · unknown status → reject · isSuccessful matrix ·
reason ''→null normalize · factories Return type.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Zero-amount bị lạm dụng làm sentinel (như fallback) | Documented asymmetry + test; carrier adapter review khi adopt |
| Reason constants phình thành taxonomy | Chốt 5 shared; carrier-specific là free string — README engineering rule |
| Outcome nhét thêm serviceLevel/carrierCode sau này | Spec §4 Option B documented — chỉ mở khi orchestration chứng minh |

Rollback: revert — không DB, không config, không carrier code.
