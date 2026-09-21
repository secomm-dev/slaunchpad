# Evidence — TASK-GKHXY1 GHN-E1: Tracking + Webhook + Status Normalization (sanitized)

> Date: 2026-09-15 · Environment: local dev WSL, Host `fashion-launchpad.localhost`, GHN sandbox shop 200537 (masked)

## 1. Deliverables

| File | Content |
|---|---|
| `Model/Tracking/GhnStatusMapper.php` | 23 GHN statuses → 11 normalized explicit table; unknown → UNKNOWN |
| `Model/Tracking/WebhookPayloadParser.php` | PascalCase → TrackingUpdate; Type-aware (chỉ switch_status); raw sanitized |
| `Model/Tracking/GhnTrackingFetcher.php` | Order Info API → TrackingUpdate(api) — CÙNG mapper |
| `Model/Tracking/TrackingRefreshService.php` + `Cron/RefreshTracking.php` + `etc/crontab.xml` | opt-in reconciliation cron 30' |
| `Controller/Webhook/Tracking.php` + `etc/frontend/routes.xml` | POST `/secomm_ghn/webhook/tracking` — secret header fail-closed |
| `Model/Config.php` + config/system | `webhook_secret` (obscure) + `tracking_refresh_enabled` + `tracking_refresh_threshold_hours` |
| `Model/Shipment/GhnShipmentRepository.php` | += `findByGhnOrderCode` |
| `etc/di.xml` | virtualType `GhnTrackingReconciliation` + RefreshService wiring |
| Tests | mapper (26) + parser (7) + fetcher (3) + controller (7) — Ghn 248 tests |

## 2. Status matrix (docs + legacy reference + matrix terminal list = 23 values)

| GHN status | Normalized | Terminal | Nhóm |
|---|---|---|---|
| ready_to_pick | CREATED | no | creation |
| picking / money_collect_picking | PICKING | no | pickup |
| picked | PICKED_UP | no | pickup |
| storing / transporting / sorting | IN_TRANSIT | no | transport |
| delivering / money_collect_delivering | OUT_FOR_DELIVERY | no | delivery |
| delivered | DELIVERED | **yes** | delivery |
| delivery_fail | DELIVERY_FAILED | no (re-attempt) | failure |
| waiting_to_return / return / return_transporting / return_sorting / returning / return_fail | RETURNING | no | return |
| returned | RETURNED | **yes** | return |
| cancel | CANCELLED | **yes** | cancel |
| damage / lost / scrap | DELIVERY_FAILED | no | failure-side exceptional |
| exception | UNKNOWN | no | failure-side ambiguous |
| (unknown) | UNKNOWN | no | safe fallback |

## 3. Runtime endpoint matrix (REAL shipment 10 / track L8TKYG, order 15)

| # | Case | Request | Response |
|---|---|---|---|
| 1 | đúng secret + switch_status delivering | header X-Secomm-Ghn-Secret ✓ | 200 `{ok:true,matched:true}` → state row `OUT_FOR_DELIVERY/delivering/webhook` |
| 2 | duplicate (cùng payload) | same | 200 `{ok:true,matched:true}` — idempotent (processor absorbs) |
| 3 | sai secret | header wrong | **401** `{ok:false,error:invalid_secret}` — 0 processing |
| 4 | OrderCode lạ | UNKNOWN123 | 200 `{ok:true,matched:false}` — safe reject, không mutation |
| 5 | Type=create (không lifecycle) | Status rỗng | 200 `{ok:true,matched:false,error:invalid_payload}` — ack-drop |
| 6 | switch_status delivered | — | 200 matched:true → state DELIVERED (terminal sticky) |

State row sau run: `{carrier_code:secomm_ghn, tracking_number:L8TKYG, normalized_status:DELIVERED, carrier_status_code:delivered, source:webhook, occurred 2026-09-15 13:00}` — dedupe key docs (OrderCode+Type+Time) hấp thụ bởi processor shouldApply (same status + same raw code → skip).

## 4. Security model (r2 wording — TL corrected)

- **GHN does NOT provide a provider-signed webhook/HMAC signature.**
- **GHN Developer Portal supports merchant-configured custom headers** for webhook callbacks.
- Secomm uses header **`X-Secomm-Ghn-Secret`** as a merchant-configured shared secret; Magento validates it with a **constant-time comparison** (`hash_equals`) and **fails closed** (401 on missing/invalid secret, 0 processing).
- Đây là **shared-secret authentication — NOT cryptographic provider signature verification** (wording đã sửa tại controller docblock + system.xml comment + evidence này).
- Secret không bao giờ log (GhnLogger scrub + không đưa vào context).

## 5. Tests + gates

- Ghn scoped: **248 tests / 0F / 0E** (+38 tracking: mapper 26+2, parser 7, fetcher 3, controller 7)
- Cross-module (Ghn+ShippingCore+VietNamAddress+Ghtk): **927 / 0F / 0E**
- `setup:di:compile` OK · validator specs/records sạch
- Grep: 0 order mutation (setState/setStatus trên Order) trong GHN lifecycle code · 0 payment inspection · 0 cross-carrier code refs (chỉ docblock prose)
- Log audit: 0 token/phone/PII

---

# r2 — TL review corrections (2026-09-16, runtime + sandbox-proven)

## 1. Security wording (chính xác hóa)

GHN **KHÔNG cung cấp provider-signed webhook/HMAC signature**. GHN Developer Portal hỗ trợ
merchant-configured custom headers cho webhook callbacks. Secomm dùng header
`X-Secomm-Ghn-Secret` làm shared secret; Magento validate bằng constant-time comparison
(`hash_equals`). Đây là **shared-secret authentication — KHÔNG phải provider signature
verification**. Đã sửa wording trong: controller docblock, system.xml comment, evidence này.

## 2. Dedupe identity = OrderCode + Type + Time (provider contract)

Runtime PROVEN trên track L8TKYG (shipment 10, dev store):

| # | Event | Kết quả |
|---|---|---|
| 1 | delivering @10:00 | 200 matched:true → state OUT_FOR_DELIVERY |
| 2 | exact duplicate (cùng payload) | 200 matched:true — **no-op** (processor absorbs; row unchanged) |
| 3 | delivery_fail @16:00 | 200 matched:true → DELIVERY_FAILED applied |
| 4 | delivery_fail @17:00 (same status, LATER Time) | 200 matched:true → **distinct occurrence — re-applied** (row ts 17:00, event re-emitted) |
| 5 | lost @18:00 | 200 matched:true → **LOST (terminal)** |
| 6 | transporting @19:00 (stale, sau LOST) | 200 matched:true — **skip** (terminal sticky) |
| 7 | damage @20:00 (terminal sticky) | 200 — skip (LOST giữ nguyên) |
| 8 | Type=update_cod | 200 `{ok:true,matched:false,error:unsupported_event_type}` — ack-drop |
| 9 | OrderCode UNKNOWN999 | 200 `{ok:true,matched:false}` — safe reject |
| 10 | sai secret | **401** invalid_secret |

ShippingCore `shouldApply()` delta (smallest correction — REPORT TL): duplicate guard hiện
chấp nhận distinct occurrence khi `occurredAt` khác stored (cùng status + cùng raw code +
occurredAt mới → re-apply: refresh timestamp/message + event re-emission). Null-occurredAt
nguồn (Ghtk fetcher) giữ nguyên behavior. KHÔNG event-sourcing subsystem.

## 3. Taxonomy r2: LOST + DAMAGED (ShippingCore delta)

`NormalizedTrackingStatus` += `LOST`, `DAMAGED` — **terminal** (sticky) + commentable.
Mapping GHN: `lost`→LOST, `damage`→DAMAGED, `scrap`→DAMAGED (compat — docs terminal list);
`delivery_fail` giữ DELIVERY_FAILED riêng (non-terminal re-attempt). `exception` → UNKNOWN.
Fetch test: webhook lost→LOST và tracking-api lost→LOST CÙNG mapper. Events: generic
`secomm_shipping_tracking_updated` mang new_status (KHÔNG thêm status-specific events — restraint).

## 4. scrap provenance

`scrap` xuất hiện trong contract-matrix terminal list (docs-fetched 2026-09-11 — 7 terminal gồm
scrap) nhưng KHÔNG có trong legacy 22-value list. → giữ mapping `scrap → DAMAGED` như
provider-documented compatibility, provenance = official docs (matrix), flagged: re-verify khi
GHN docs SPA truy cập được.

## 5. Final state row (sau runtime matrix)

`{normalized_status: LOST, carrier_status_code: lost, source: webhook, occurred: 2026-09-15 18:00}`
— stale transporting @19:00 và damage @20:00 KHÔNG hồi quy terminal LOST ✓.

## 6. r2 final gates (2026-09-16)

- Ghn scoped: **273 tests / 0F / 0E** (248 + E2 +21 + r2-taxonomy +4)
- Cross-module (Ghn+ShippingCore+VietNamAddress+Ghtk): **959 / 0F / 0E**
- 6 PHPUnit deprecations = pre-existing module khác (PromotionMaxDiscount / Tracking /
  ExtraFeeFix — non-static data providers), không thuộc stream này
- `setup:di:compile`: **0 lỗi stream này** — 2 lỗi external pre-existing tại LEGACY
  `Secomm_GiaoHangNhanh` (working-tree diff chưa commit của stream khác: parent
  `AbstractDataBuilder::__construct` +`LoggerInterface`, 2 child builders chưa truyền qua —
  N-Defect ghi nhận, KHÔNG vá chéo theo kỷ luật scope)
- Validator `--check-specs`: **0 fail stream này** — 32 FAIL pre-existing thuộc records stream
  khác (TASK-0F96X5/ZR2ZNS/AEZTTB/78PVR0/BS91A3, BUG-*, SPEC naming, plans cũ)
- Mutation-taxonomy r2 áp nhất quán cho E2 services (5xx → UNKNOWN; 429 → TECHNICAL qua
  `ProviderRateLimitException`) — chi tiết tại evidence TASK-4ATBC4
