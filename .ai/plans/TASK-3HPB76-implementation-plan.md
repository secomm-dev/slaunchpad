# Implementation Plan: TASK-3HPB76 — GHTK Pickup/TestConnection operational tooling

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-3HPB76 (parent FEAT-YA2C0W) |
| Mode | B (admin-only operational tooling — read-only, không runtime path) |
| Specification | Embedded Mini-Spec trong [records/tasks/TASK-3HPB76.md](../records/tasks/TASK-3HPB76.md) — MINI, VALID (SPIKE-A1DGPY §13 list_pick_add) |
| Risk | Low — admin-only read-only; existing controller refactor giữ ACL/redirect |

## Approach

Client method (+1, safeRead pattern như getFee) → `GhtkPickupList` parser VO (skip malformed
rows) → `TestConnectionService` (auth/technical classification theo transport category; configured
id exact-match) → typed `TestConnectionResult` → controller mỏng map messageManager (6 trạng thái).
Existing fee-probe flow của TestConnection THAY bằng list_pick_add flow (đúng directive §2/§3 —
fee-probe cũ đòi pickup config valid mới test được; list_pick_add test được connectivity + id
validation độc lập).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | record + plan | spec-first |
| 2 | Client | `GhtkApiClient.php` += getPickupAddresses; `GhtkApiProfile.php` += getPickupListPath | safeRead retry |
| 3 | VOs + parser | NEW `Model/Pickup/{GhtkPickupAddress, GhtkPickupList, TestConnectionResult, TestConnectionService}.php` | §3 flow |
| 4 | Controller | `Controller/Adminhtml/Ghtk/TestConnection.php` | thin; ACL/redirect giữ |
| 5 | Tests | NEW `Test/Unit/Model/Pickup/TestConnectionServiceTest.php`; `GhtkApiClientTest` += pickup cases | §33–§35 |
| 6 | Docs | README + CHANGELOG | caller expectation |
| 7 | Gates | grep §39; scoped + full suite; validator | |

## Test plan

Service: no-id connected; id exact-match connected+name; id missing → PICKUP_ID_INVALID (no
fallback — assert không pick first); empty list; CLIENT_ERROR → AUTH_FAILED; NETWORK/SERVER/
TIMEOUT/INVALID_RESPONSE → TECHNICAL; success=false/data-non-array → TECHNICAL; malformed rows
skipped; legacy text-only (không id) → connected. Client: pickup URI; retry NETWORK, no-retry
CLIENT_ERROR.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| TestConnection cũ phụ thuộc (origin/pickup/fee) bị phụ thuộc admin khác tham chiếu | grep consumers trước khi bỏ ctor deps |
| list shape drift | parser skip-malformed + technical signal; NEEDS_RUNTIME_VERIFICATION documented |

## Validation gates

Scoped suite · full suite (pre-existing tách riêng) · grep §39 · ShippingCore/VN diff 0 · validator.
