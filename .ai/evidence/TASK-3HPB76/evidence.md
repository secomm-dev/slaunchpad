# Evidence — TASK-3HPB76: GHTK Pickup/TestConnection operational tooling

Date: 2026-09-15 · Basis: SPIKE-A1DGPY §13 (official list_pick_add, fetched 2026-09-14) · 0 ShippingCore/VietNamAddress change

## A. Result: FULL OPERATIONAL TOOLING ALIGNMENT (live staging E2E = BLOCKED_BY_CREDENTIAL — §31/§I)

## §41 Delta matrix

| Case | Expected | Result (test-proven) |
|---|---|---|
| valid API, no pickup ID configured | CONNECTED | ✓ `testValidApiWithNoConfiguredPickupIdIsConnected` |
| valid API, valid pickup ID | CONNECTED + PICKUP_VALID | ✓ (connected + configured name diagnostic) |
| valid API, invalid pickup ID | PICKUP_ID_INVALID | ✓ — KHÔNG first-pickup fallback (`testInvalidConfiguredIdNeverFallsBackToFirstPickup`) |
| empty pickup list | CONNECTED (validation tùy config) | ✓ |
| 403 | AUTH_FAILED | ✓ (CLIENT_ERROR category → "Check the API token and X-Client-Source") |
| timeout/network | TECHNICAL_FAILURE | ✓ |
| 5xx | TECHNICAL_FAILURE | ✓ |
| malformed response (success=false / data non-array / rows thiếu id) | TECHNICAL_FAILURE / rows skipped | ✓ (malformed rows skip + count; toàn response unusable → technical) |
| legacy text-only config, không ID | connection pass | ✓ (`testLegacyTextOnlyConfigWithNoIdStillPassesConnection`) |
| exact-ID only | no fuzzy | ✓ (`testConfiguredIdMatchesOnlyExactString` — prefix '8825' ≠ '88256' → INVALID) |

## B. Files

- NEW `Model/Pickup/GhtkPickupAddress.php` (lean VO: id/name/tel/address).
- NEW `Model/Pickup/GhtkPickupList.php` (parser: success/data envelope; malformed rows skip + count;
  exact-ID lookup; KHÔNG phải admin master data).
- NEW `Model/Pickup/TestConnectionResult.php` (4 statuses + message/configuredPickupId/
  configuredPickupName/pickupCount + isConnectionOk()).
- NEW `Model/Pickup/TestConnectionService.php` — §3 flow; CLIENT_ERROR → AUTH_FAILED
  ("Check the API token and X-Client-Source" — 403 §9); NETWORK/SERVER/TIMEOUT/INVALID_RESPONSE →
  TECHNICAL; success≠true/data unusable → TECHNICAL (unusable technical data); no-id → CONNECTED
  với note; id invalid → PICKUP_ID_INVALID, no first-fallback.
- MOD `GhtkApiClient::getPickupAddresses(?int $storeId)` — GET list_pick_add qua shared transport,
  `RetryPolicy::safeRead` (read-only §37 — NETWORK/SERVER_ERROR/TIMEOUT retry; 403/400/429 no).
- MOD `GhtkApiProfile::getPickupListPath()`.
- MOD `Controller/Adminhtml/Ghtk/TestConnection.php` — REFACTOR: fee-probe cũ (đòi pickup config
  valid trước khi test được connectivity) THAY bằng service; messageManager map 4 statuses
  (success/warning/error); ACL `Secomm_Ghtk::config` GIỮ NGUYÊN (§21); backend GET + form key
  convention (§22); không frontend exposure.

## C. Runtime isolation (§17/§39 grep)

```
grep getPickupAddresses/list_pick_add/getPickupListPath ngoài
  GhtkApiClient/GhtkApiProfile/Model/Pickup/Controller Adminhtml TestConnection → CLEAN
grep trong Model/Carrier/Ghtk.php + Model/OrderSubmit/* + Model/Rate/* → CLEAN (0 hit)
pickup persistence/sync/cron/repository → CLEAN (0 file/table/cron)
```

Logging (§38): service trả typed result; controller không log raw payload — diagnostics = count +
configured id + name. Client log giữ pattern chung (không token).

## Tests (§33–§36)

- `TestConnectionServiceTest` (15 — MỚI): §41 matrix đầy đủ + malformed-rows skip + exact-string
  match + storeId passthrough. (Ghi note kỹ thuật: PHPUnit first-stub-wins — mỗi test stub
  `getPickAddressId` tường minh qua helper.)
- `GhtkApiClientTest` +2: pickup path/retry — safeRead retry SERVER_ERROR 2 calls; CLIENT_ERROR
  no-retry.
- Scoped (Ghtk|ShippingCore|VietNamAddress): **645 tests / 1717 assertions — 0 failure**.
- Full suite: 1555 tests — 10 failing TẤT CẢ pre-existing ngoài scope (Tracking 7 + FulfillmentCore 3).
  New failures: 0. Validator: exit 0.

## NEEDS_RUNTIME_VERIFICATION / live

Live TestConnection E2E trên staging: **BLOCKED_BY_CREDENTIAL** (TASK-44F7V7). Khi có token:
TestConnection chính là bước đầu của probe (verify token → discover pick_address_id thật) —
hỗ trợ unblock một phần probe nhưng KHÔNG tự mark TASK-44F7V7 complete.
