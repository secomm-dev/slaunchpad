# Implementation Plan: TASK-6YG3HP — GHTK structural alignment lên ShippingCore v5

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-6YG3HP (parent FEAT-YA2C0W) |
| Mode | B (structural migration — v5 contracts hiện hữu, không architecture mới) |
| Specification | Embedded Mini-Spec trong [records/tasks/TASK-6YG3HP.md](../records/tasks/TASK-6YG3HP.md) — MINI, VALID |
| Contract basis | TASK-Y3X6H5 (per-operation capability + operation handoff) · TASK-STC3NB (shared COD resolver) — actual code review 2026-09-14 |
| Blockers honored | TASK-44F7V7 BLOCKED_BY_CREDENTIAL → scheme values KHÔNG freeze (single seam) |
| Risk | Medium — structural runtime change; KCXKVR correctness được bảo vệ bằng regression |

## Approach

Dual-shim capability (`GhtkOperationAddressCapability` implements Operation contract + deprecated
per-carrier contract): ShippingCore `RuntimeAddressContextBuilderInterface` vẫn type-hint per-carrier
(hard stop — GHTK không thể pass Operation capability vào builder), nên shim là unavoidable +
documented (removal condition: ShippingCore per-op builder entry). Adapter nhận `$operation`,
gọi `handoffContextForOperation`. COD detection migrate sang shared resolver; carrier config
clean-remove (pre-stable). Scheme values = profile candidate, PENDING probe, 1 seam.

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | record + plan | spec-first |
| 2 | Capability | NEW `Model/Address/GhtkOperationAddressCapability.php`; DEL `GhtkAddressCapability.php` | dual-implement shim documented |
| 3 | Adapter | `GhtkAddressAdapter.php` | `resolve(…, string $operation)` + `handoffContextForOperation` |
| 4 | Consumers | `Carrier/Ghtk.php` (RATE), `OrderSubmitService.php` (CREATE), `PickupAddressResolver.php` (+op) | operation explicit |
| 5 | COD | `DefaultCodAmountResolver.php`, `GhtkConfig.php`, `etc/config.xml`, `etc/adminhtml/system.xml` | shared resolver; clean-remove carrier config |
| 6 | Tests | capability (mới), adapter, pickup, carrier, order submit, cod resolver, ghtk config | §28/§29/§30/§31 |
| 7 | Gates | grep deprecated refs = 1 shim class; ShippingCore/VN diff 0; scoped + full suite; validator | §32/§28 |
| 8 | Evidence + report | `.ai/evidence/TASK-6YG3HP/` | delta matrix §36 |

## Test plan

Capability: per-op scheme/representation/fallback; deprecated shim delegate; LogicException path.
Adapter: operation param lan truyền tới handoffContextForOperation (mock assert từng op);
AMBIGUOUS/UNMAPPED → null không gọi API (giữ); non-VN → null. Pickup: operation passthrough.
COD: shared isCod true/false → collect/0; partial-COD guard giữ; 0 carrier config read.
Regression: KCXKVR tests (mapper/webhook/status/payload/weight) + GhtkTest/OrderSubmitServiceTest.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Shim giữ deprecated reference | unavoidable (ShippingCore builder type-hint); documented + removal condition; §32 exception |
| COD config remove làm mất COD khi enable | operator migration note (set secomm_shippingcore/cod/payment_methods); pre-stable clean migration documented |
| Operation param sai truyền | explicit params + tests assert per-op handoff call |

## Validation gates

Scoped suite · full suite (pre-existing GHN/Tracking/FulfillmentCore tách riêng) · grep gates ·
`project-ai-validate` · ShippingCore/VietNamAddress `git diff` = r0-only (không file mới).
