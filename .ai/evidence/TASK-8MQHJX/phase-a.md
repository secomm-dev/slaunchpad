# Evidence — TASK-8MQHJX Phase A: DestinationScope + CanonicalZone runtime

Ngày: 2026-09-16 · TASK-8MQHJX · architecture v10 · Phase A only (B/C/D chờ).

## A. Classes/contracts added/reused

| Contract/Class | Trạng thái | Ghi chú |
|---|---|---|
| `Api\Address\DestinationScope` | REUSED (đã tạo đầu task) | ALL \| SELECTED_ZONES constants + assertKnown |
| `Api\Address\CanonicalZoneInterface` | **MỚI** | code/label/enabled/include provinces/wards/exclude wards |
| `Model\Address\CanonicalZone` | **MỚI** | immutable VO, empty code/label fail-fast |
| `Api\Address\CanonicalZoneRegistryInterface` | **MỚI** | getByCode/getAll/getEnabled |
| `Model\Address\CanonicalZoneRegistry` | **MỚI** | DI array; zero-zone valid; duplicate/empty code fail-fast; getEnabled filter |

## B. Matching semantics

```text
1. !enabled → không match
2. provinceCodes non-empty + province KHÔNG match → không match
3. includeWardCodes non-empty + ward KHÔNG trong → không match
4. includeWardCodes rỗng → không có positive ward restriction
5. excludeWardCodes non-empty + ward CÓ trong → KHÔNG match (exclude wins)
```

## C. Tests

```text
CanonicalZoneRegistryTest (5): zero valid · getByCode hit/miss · getEnabled filter · duplicate code fail-fast
CanonicalZoneTest (đã từ Y3X6H5): VO invariants
```

## D. Scoped regression

```text
Tests: 498, Assertions: 1284, Errors: 0, Failures: 0 (deprecations 6 pre-existing)
```
