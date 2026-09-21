# Implementation Plan: TASK-AQT7V3 — Phase E-A shipping address resolution contracts

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-AQT7V3 (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract — generic risk category) |
| Specification | [specs/SPEC-TASK-AQT7V3-shippingcore-address-resolution-contracts.md](../specs/SPEC-TASK-AQT7V3-shippingcore-address-resolution-contracts.md) — FULL, VALID (approved Phase E-A directive; TL review chạy cùng pre-review) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md) — accepted 2026-09-03 (D1 dependency, D2 ownership, D9 ambiguity, D10 no-abstraction) |
| Architecture basis | [SPIKE-W273TB](../research/SPIKE-W273TB-shippingcore-address-orchestration.md) — audit + proposed contracts 2026-09-04 |
| Risk | Medium — additive-only; 0 carrier code, 0 schema, 0 runtime behavior change |

## Approach

Contracts-only theo approved directive: 5 interfaces trong `Api\Address` (sub-namespace mirror
precedent `Api\Tracking`) + 3 concrete class `Model\Address` (2 immutable scalar VO + 1 pool DI
array). Status semantics REUSE từ `VnAddressResolutionInterface::STATUS_*` (không hằng song song);
invariant center hóa ở VO constructor để AMBIGUOUS/UNMAPPED không thể lộ unitCode. Không có
orchestration/manager implementation (không dead code); manager chỉ có interface để E-B stable.
Chi lệch so với SPIKE (đề xuất 5-status RESOLVED_LOCAL/…): approved directive §4 chốt 4-status
REUSE từ VietNamAddress — external resolution sau này chuyển unresolved → MAPPED, không cần status
song song.

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Module dependency | `ShippingCore/etc/module.xml` | sequence += `Secomm_VietNamAddress` (D1); comment DEC reference |
| 2 | Contracts | `ShippingCore/Api/Address/{CarrierAddressCapabilityInterface,ResolvedShippingAddressInterface,ShippingAddressResolutionContextInterface,ExternalAddressResolverInterface,ShippingAddressResolutionManagerInterface}.php` | shape §3 spec; header docblock DEC-004 + TASK reference |
| 3 | VOs + pool | `ShippingCore/Model/Address/{ResolvedShippingAddress,ShippingAddressResolutionContext,ExternalAddressResolverPool}.php` | invariant §3.2; pool constructor `array $externalAddressResolvers` |
| 4 | DI | `ShippingCore/etc/di.xml` | preference 2 VO interface + pool array argument rỗng (D7 guards pattern); KHÔNG preference manager (chưa có impl) |
| 5 | Tests | `ShippingCore/Test/Unit/Model/Address/{ResolvedShippingAddressTest,ShippingAddressResolutionContextTest,ExternalAddressResolverPoolTest}.php` | AAA; anonymous-class resolver dummies (không external mock) |
| 6 | Docs | `ShippingCore/README.md`, `ShippingCore/CHANGELOG.md` | contracts surface + DEC/TASK reference |
| 7 | Governance | `.ai/records/tasks/TASK-AQT7V3.md`, `.ai/specs/SPEC-TASK-AQT7V3-…md`, `.ai/plans/TASK-AQT7V3-…md`, FEAT-YA2C0W ticket_ref += TASK-AQT7V3, working memory sync | spec-first gate trước code |
| 8 | Validation | `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml` · `.ai/bin/project-ai-validate --check-records --check-specs --check-identity` · `bin/magento setup:di:compile` | AC-5/AC-6 |

## Test plan

- Unit (AAA): `ResolvedShippingAddressTest` — 4 status happy path + invariant violations (status lạ,
  EXACT thiếu unitCode, AMBIGUOUS rỗng candidates, UNMAPPED có unitCode, scheme rỗng) + isResolved
  matrix + candidate order preservation. `ExternalAddressResolverPoolTest` — empty pool valid;
  thứ tự giữ nguyên. `ShippingAddressResolutionContextTest` — scalar round-trip.
- Regression: compile pass + Secomm suite pass là đủ (0 runtime behavior change; carriers untouched).

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Preference trỏ class chưa tồn tại gãy compile | KHÔNG khai báo preference cho manager interface |
| Status literal trôi khỏi VietNamAddress | VO validate qua constant reference, không string literal |
| DI array argument sai type ở provider tương lai | docblock contract + instanceof không cần ở E-A (fail fast ở call-site E-B) |

Rollback: revert commits — không DB, không config, không data.
