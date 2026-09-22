# Implementation Plan: TASK-T78YH6 — Phase E-C0 carrier-facing address handoff

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-T78YH6 (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract — generic risk category) |
| Specification | [specs/SPEC-TASK-T78YH6-shippingcore-carrier-address-handoff.md](../specs/SPEC-TASK-T78YH6-shippingcore-carrier-address-handoff.md) — FULL, VALID (approved E-C0 directive; TL review chạy cùng pre-review) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md) (D2/D5/D9); E-C0 không có architecture decision mới — thực thi SPIKE-YH439T §4 Option B-minimal đã approved |
| Architecture basis | [SPIKE-YH439T](../research/SPIKE-YH439T-shippingcore-carrier-runtime-handoff.md) §4/§5/§16 (handoff model, non-VN translation, single resolution path) |
| Contract basis | TASK-5XDG1P (E-B manager + context DTO) · TASK-XXBN5X (E-SL0 — không đụng, tách trục) |
| Risk | Medium — additive builder/contract/service; chưa có runtime consumer; 0 carrier code |

## Approach

Option B-minimal (SPIKE-YH439T): builder (`DestinationContextBuilder` — Quote\Address + capability
→ context; bridge `VnOperationalAddressResolverInterface::resolveFromRuntime` REUSE duy nhất,
unresolved → null identity) + handoff VO (`CarrierAddressHandoff` — 4 members + 2 reason
constants, invariants ở constructor) + service (`CarrierAddressHandoffService` — builder →
manager → catch UnsupportedDestinationException → matrix §3.4 spec; fallback eligibility =
capability.read 1 lần ở nhánh unresolved, chỉ ALLOW). Single resolution path qua manager (cache
hiệu lực, không double resolve). Không orchestration/external/provider mapping (directive §14/§20/§21).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT `ticket_ref` += TASK-T78YH6 | spec-first TRƯỚC code; ID mint idgen |
| 2 | Contracts | `Api/Address/{DestinationContextBuilderInterface, CarrierAddressHandoffInterface, CarrierAddressHandoffServiceInterface}.php` | reason constants trên handoff interface |
| 3 | Concrete | `Model/Address/{DestinationContextBuilder, CarrierAddressHandoff, CarrierAddressHandoffService}.php` | §3 spec; VO invariants |
| 4 | DI | `etc/di.xml` | 2 preference mới; pool/registry giữ nguyên |
| 5 | Tests | `Test/Unit/Model/Address/{DestinationContextBuilderTest, CarrierAddressHandoffTest, CarrierAddressHandoffServiceTest}.php` | §8 spec (real Quote\Address + mock bridge/manager) |
| 6 | Docs | `README.md` + `CHANGELOG.md` | engineering rule §24 + Stage 1/2 |
| 7 | Validation | phpunit secomm · validator · compile · grep (không provider-stage reason, không external invoke) | AC-5/AC-6 |
| 8 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-T78YH6/` | |

## Test plan

Builder: map đầy đủ (bridge resolved) · bridge unresolved → null identity · street join ",
" · targetScheme từ capability · candidateCodes [] · city_id qua getData · ids 0 pass-through.
Handoff VO: 4 nhánh invariant. Service: matrix 6 case (non-VN translate; EXACT; MAPPED; AMBIGUOUS
± fallback; UNMAPPED ± fallback) + delegate-once (manager mock, không resolver trực tiếp) +
capability fallback chỉ đọc nhánh unresolved (stub ném nếu đọc sai).

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Double resolution (service gọi resolver trực tiếp) | §16 — service chỉ inject manager; test delegate assert |
| Stage-2 failure lẫn vào CANONICAL_UNRESOLVED | Reason vocabulary chỉ 2 giá trị canonical-scope; engineering rule README |
| Builder duplicate runtime→canonical path | §5 — chỉ `VnOperationalAddressResolverInterface`; grep verify |

Rollback: revert — không DB, không config, không carrier code.
