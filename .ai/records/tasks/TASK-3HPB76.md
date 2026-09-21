---
id: TASK-3HPB76
type: task
title: 'GHTK Pickup/TestConnection operational tooling — list_pick_add connectivity + configured pick_address_id exact validation (admin; không checkout path)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed task 2026-09-15; list_pick_add contract theo SPIKE-A1DGPY §13 (official docs fetched 2026-09-14)
specification_ref: Embedded Mini-Spec
risk: low                     # admin-only read-only tooling; 0 checkout/runtime path change; ShippingCore/VN untouched
status: dev-complete          # implemented 2026-09-15 (scoped 645 tests — 0 failure trong scope; live E2E BLOCKED_BY_CREDENTIAL theo TASK-44F7V7; evidence .ai/evidence/TASK-3HPB76/); chờ Tier-2 review
priority: medium
decision_assessment: none-material # operational tooling theo official docs; không architecture change; không DEC mới
decisions: [DEC-TASK7AJ3K8-002]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
changes_project_state: true
created: 2026-09-15
updated: 2026-09-15
owner: [dev]
related_tickets: [SPIKE-A1DGPY, TASK-44F7V7, TASK-FNVHK5]
---

# [SLP][FEAT-YA2C0W][TASK-3HPB76] GHTK Pickup/TestConnection operational tooling

## Embedded Mini-Spec

### Goal

Operational tooling cho setup/vận hành merchant: `GhtkApiClient::getPickupAddresses()` (GET
`/services/shipment/list_pick_add` — official docs SPIKE-A1DGPY §13) + typed TestConnection result
(CONNECTED / AUTH_FAILED / TECHNICAL_FAILURE / PICKUP_ID_INVALID) + exact validation của configured
`pick_address_id` (nếu có). Admin-only — KHÔNG nằm trong collectRates/CREATE/checkout path; KHÔNG
pickup sync/persistence; KHÔNG admin selector/grid; KHÔNG MSI mapping. ShippingCore/VietNamAddress
0 diff; address capability KHÔNG freeze (§29).

### Expected Behavior

1. Client `getPickupAddresses(?int $storeId)` — GET qua shared transport + auth headers +
   `RetryPolicy::safeRead` (read-only — NETWORK/SERVER_ERROR/TIMEOUT retry; 403/400/429 no retry).
2. `GhtkPickupList::fromResponse()` — parse `data[]` → `GhtkPickupAddress` (id/name/tel/address;
   id normalize scalar); malformed individual rows SKIP (policy: thiếu pick_address_id);
   success=false hoặc data non-array → signal technical-unusable cho service.
3. `TestConnectionService::test(?int $storeId): TestConnectionResult`:
   - transport CLIENT_ERROR → AUTH_FAILED ("Check API token / X-Client-Source" — 403 §9);
   - NETWORK/SERVER_ERROR/TIMEOUT/INVALID_RESPONSE → TECHNICAL_FAILURE;
   - success=false / data unusable → TECHNICAL_FAILURE (unusable technical data, §12-apply);
   - success + list: configured `pick_address_id` rỗng → CONNECTED ("No pickup address ID
     configured" — không phải failure, §11); exact-match (string compare) → CONNECTED +
     configured pickup name diagnostic (§23); không match → PICKUP_ID_INVALID ("not found in the
     merchant pickup list") — KHÔNG fallback first pickup (§14).
4. `Controller/Adminhtml/Ghtk/TestConnection` refactor: inject service, giữ ACL
   `Secomm_Ghtk::config` + redirect convention; admin message phân biệt 6 trạng thái (§19), không
   expose token/raw payload; legacy text-only pickup config không bị fail chỉ vì thiếu ID (§15).
5. `GhtkOriginProvider` + rate/create path KHÔNG đổi (§16/§17).

### Constraints / Rules

- KHÔNG: pickup sync/persistence (table/cron/repository §24); selector/grid/CRUD (§25); MSI (§26);
  ShippingCore/VN diff (§27/§28); capability change (§29); RATE/CREATE/CANCEL behavior change (§30).
- Logging masked: pickup count/configured id — không token/telephone/full address (§38).
- Không có staging token → live E2E = BLOCKED_BY_CREDENTIAL (§31) — không fake success.

### Out of Scope

Pickup selector UI/persistence · MSI · Magento cancel-flow wiring · address freeze · runtime probe
(TASK-44F7V7).

### Acceptance Criteria

AC-1: valid API + no configured id → CONNECTED (test). AC-2: configured id exact-match →
CONNECTED + name diagnostic (test). AC-3: configured id không trong list → PICKUP_ID_INVALID,
KHÔNG first-fallback (test). AC-4: empty list + no id → CONNECTED (test). AC-5: CLIENT_ERROR →
AUTH_FAILED (test). AC-6: NETWORK/SERVER_ERROR/TIMEOUT/INVALID_RESPONSE → TECHNICAL_FAILURE
(tests). AC-7: malformed rows skipped, thiếu id skip (test). AC-8: legacy text-only config →
connection pass (test). AC-9: client pickup method — path đúng + safeRead retry NETWORK/SERVER
no-retry CLIENT_ERROR (tests). AC-10: controller mỏng, ACL giữ, 0 call trong collectRates/CREATE
(grep). AC-11: scoped suite pass; ShippingCore/VN 0 diff mới; validator pass.
