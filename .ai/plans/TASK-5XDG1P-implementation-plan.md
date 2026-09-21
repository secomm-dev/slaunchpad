# Implementation Plan: TASK-5XDG1P — Phase E-B local canonical shipping-address orchestration

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-5XDG1P (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract — generic risk category) |
| Specification | [specs/SPEC-TASK-5XDG1P-shippingcore-local-address-orchestration.md](../specs/SPEC-TASK-5XDG1P-shippingcore-local-address-orchestration.md) — FULL, VALID (approved Phase E-B directive; TL review chạy cùng pre-review) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md) — accepted 2026-09-03 (D1 dependency, D2 ownership, D9 ambiguity, D10 no-abstraction); E-B không có architecture decision mới |
| Architecture basis | [SPIKE-W273TB](../research/SPIKE-W273TB-shippingcore-address-orchestration.md) §4 flow + §7 cache — audit + proposed orchestration 2026-09-04 |
| Contract basis | TASK-AQT7V3 / SPEC-TASK-AQT7V3 — E-A contracts dùng nguyên xi, 0 expansion |
| Risk | Medium — additive manager + 1 exception class + 1 DI preference; 0 carrier code, 0 schema; manager CHƯA có caller runtime (carriers chưa consume) |

## Approach

1 concrete manager (`Model\Address\ShippingAddressResolutionManager`, final) orchestrate-only:
validate (non-VN guard) → cache lookup (key `sourceScheme|sourceUnitCode|targetScheme`) →
`VnAdminAddressResolverInterface` khi miss → convert 1-1 status (`ResolvedShippingAddress` VO
đã enforce invariant) → cache → return. Resolution/cardinality/same-scheme semantics là CỦA
`Secomm_VietNamAddress` — ShippingCore không duplicate (D2). Non-VN bypass = domain exception
`UnsupportedDestinationException` (mirror precedent `GhnLocationMappingException`; smallest
adjustment vì return contract 4-status không represent được not-applicable — spec §4.2, REPORT
cho TL). Missing canonical identity → UNMAPPED không gọi resolver (explicit unresolved theo
contract — directive §13). Unknown scheme → `LocalizedException` propagate nguyên văn. Cache =
in-memory array trên shared DI instance (request scope, SPIKE §7 recommendation); cache cả
AMBIGUOUS/UNMAPPED/missing-identity. Không logger (expected states không log — directive §17);
impossible states là `LogicException` tự mang ngữ cảnh. Docblock-only update trên manager
interface (bypass channel + sửa forward-looking E-A text).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | `.ai/specs/SPEC-TASK-5XDG1P-…md`, `.ai/plans/TASK-5XDG1P-…md`, `.ai/records/tasks/TASK-5XDG1P.md`, FEAT-YA2C0W `ticket_ref` += TASK-5XDG1P | spec-first gate TRƯỚC code; ID mint qua `.ai/bin/project-ai-idgen` |
| 2 | Exception | `ShippingCore/Model/Address/Exception/UnsupportedDestinationException.php` | extends `LocalizedException`; mirror `GhnLocationMappingException` precedent |
| 3 | Manager | `ShippingCore/Model/Address/ShippingAddressResolutionManager.php` | final; DI 1 dependency (`VnAdminAddressResolverInterface`); flow spec §3.1; conversion §3.2; cache §3.3 |
| 4 | Contract docblock | `ShippingCore/Api/Address/ShippingAddressResolutionManagerInterface.php` | docblock-only: bypass channel + E-B scope thực tế (external + textual fallback là phase sau); 0 signature change |
| 5 | DI | `ShippingCore/etc/di.xml` | preference manager interface → concrete; thay comment E-A; pool giữ nguyên |
| 6 | Tests | `ShippingCore/Test/Unit/Model/Address/{ShippingAddressResolutionManagerTest,ShippingAddressResolutionManagerIntegrationTest}.php` | §9 spec test plan; AAA; mock resolver cho unit + resolver THẬT cho integration-level |
| 7 | Docs | `ShippingCore/README.md`, `ShippingCore/CHANGELOG.md` | manager surface + non-VN bypass + cache scope |
| 8 | Validation | `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml` · `.ai/bin/project-ai-validate --check-specs --check-records --check-identity` · `bin/magento setup:di:compile` | AC-6 |
| 9 | Working memory | `.ai/project-context/memory/{CURRENT_STATE,NEXT_TASK}.md` | sync sau validation |

## Test plan

- Unit (`ShippingAddressResolutionManagerTest`): 4 status happy path · AMBIGUOUS không auto-select
  (unitCode null ≠ candidates[0]) · cache hit (resolver 1 lần + cùng instance) · cache separation
  (đổi unit / đổi targetScheme) · cache unresolved (AMBIGUOUS/UNMAPPED lặp không re-call) · non-VN
  → exception + resolver 0 lần · countryId null → không bypass · missing sourceScheme/unit →
  UNMAPPED + resolver 0 lần · unknown scheme → `LocalizedException` propagate.
- Integration-level (`ShippingAddressResolutionManagerIntegrationTest`): manager + `VnAdminAddressResolver`
  THẬT (mock `VnAddressUnitProviderInterface` + `MappingCandidateFinder::find`) — chứng minh manager
  contract-fit với resolver implementation thật (EXACT same-scheme, MAPPED, AMBIGUOUS sorted,
  UNMAPPED reasons) không cần DB.
- Regression: compile pass + Secomm suite pass là đủ (manager chưa có caller runtime).

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Abuse UNMAPPED cho non-VN | Cấm — bypass qua dedicated exception (spec §4.2, TL review) |
| Cache stale nếu dataset đổi giữa request | Không xảy ra trong shipping flow (import chạy CLI ngoài request); documented §3.3 |
| Manager vô tình consume address text ngoài canonical identity | Test assert resolver chỉ nhận (scheme, unit, target); contract không mang recipient PII (r1 cleanup); grep review |
| Textual fallback chạy nhầm | `supportsTextualFallback()` không được gọi bất kỳ đâu — test capability chỉ đọc getRequiredScheme |
| Non-shared manager instance (unshared DI) mất cache | Preference dùng shared instance mặc định; cache là optimization, miss chỉ tốn thêm 1 resolver call (đúng kết quả) |

Rollback: revert — không DB, không config data, không carrier code.
