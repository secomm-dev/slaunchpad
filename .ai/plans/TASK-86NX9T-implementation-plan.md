# TASK-86NX9T Implementation Plan — GHTK carrier tracking (webhook primary + API fallback + shared pipeline)

| Field | Value |
|---|---|
| Specification | specs/SPEC-TASK-86NX9T-ghtk-carrier-tracking.md |

> **Mode A** · Tier 2 · Status: **Approved 2026-08-17 (user acting as SA/TL; DEC-TASK86NX9T-001 accepted) — Phase C cleared**
> Audit 2026-08-17 (Magento 2.4.8-p5 vendor + working tree). Build trên TASK-NDASAD/TASK-KM6YAT.

---

## PART 1 — ANALYSIS (Phase A)

### 1.1 Current state (audit)

| Câu hỏi | Hiện trạng |
|---|---|
| Tracking number / label_id lưu ở đâu? | Native sau label submit (TASK-KM6YAT): `sales_shipment_track` (carrier_code `ghtk`, number = tracking_code → fallback label) + `shipping_label` PDF + comment snapshot (partner/label/pick_money/weight) |
| Magento shipment track tạo thế nào? | Native `LabelGenerator::addTrackingNumbersToShipment` → `$shipment->addTrack()`; Track có column `description` (đang trống — dùng làm surface hiển thị status) |
| Webhook endpoint tồn tại? | KHÔNG (Ghtk). Precedent project: `Secomm_Ahamove\Controller\Webhooks\Index` (POST + CsrfAwareActionInterface) |
| Cron/polling tracking? | KHÔNG (Ghtk không có crontab.xml) |
| API client có Tracking Status API? | KHÔNG — chỉ `getFee` + `submitOrder` |
| Order/shipment status mapping từ carrier? | KHÔNG có — đúng theo DEC-TASKKM6YAT-001 (không mutation) |
| Observer/plugin update order từ carrier status? | KHÔNG |
| DB chỗ lưu carrier tracking state? | KHÔNG — chỉ `secomm_ghtk_address_map`. Magento native: KHÔNG có shipment delivery-state; Track không có status column |

### 1.2 Gaps

Không có: nguồn update (webhook/API), status model dùng chung, processor pipeline, persistence queryable, idempotency/ordering guard, event emission, native display update. `isTrackingAvailable()` = false (lookup chưa có — scope này thêm trạng thái, không bắt buộc live popup).

### 1.3 Proposed components

**ShippingCore (shared — không GHTK codes):**
- `Api\Tracking\NormalizedTrackingStatus` — 11 const + label + `isTerminal()`.
- `Api\Tracking\TrackingUpdateInterface` / `CarrierTrackingProcessorInterface` / `CarrierStatusMapperInterface`.
- `Model\Tracking\TrackingUpdate` (immutable DTO).
- `Model\Tracking\ShipmentTrackingProcessor` — pipeline duy nhất: find Track (collection filter carrier_code + track_number; TASK-KM6YAT track number = identifier) → load/upsert state → idempotency (same normalized+code+occurredAt no-op) → sticky-terminal guard → persist → `Track::setDescription("GHTK: {NORMALIZED} — {message} ({d/m H:i})")` + save → shipment comment ở transitions quan trọng (delivered/failed/returned; silent reload tránh spam) → dispatch events (`secomm_shipping_tracking_updated` generic + 3 specific). KHÔNG đụng order state.
- Persistence: `Model\CarrierTrackingState` + ResourceModel + Collection + `etc/db_schema.xml` (`secomm_carrier_tracking_state`) — justification xem DEC-TASK86NX9T-001 §2.
- di preferences.

**Ghtk (carrier-specific):**
- `Model\Tracking\GhtkStatusMapper` — numeric GHTK → normalized (Q-EXT defensive, UNKNOWN default; một chỗ duy nhất).
- `Controller\Webhook\Index` — POST, Csrf-exempt (precedent), luôn 200 JSON; validate: JSON parse → required fields (label_id/tracking + status) → optional `webhook_secret` query so config → build TrackingUpdate (source `webhook`, occurredAt nếu payload có) → processor. Edge: invalid/unknown tracking/no shipment/duplicate/out-of-order → 200 + log, không throw.
- `GhtkApiClient::getOrderStatus(string $labelId, ?int $storeId): array` — GET `/services/shipment/v2/{label_id}` (Q-EXT), same transport rules.
- `Model\Tracking\TrackingRefreshService` — select stale non-terminal states (join shipment track carrier ghtk) → API → TrackingUpdate (source `api`) → cùng processor.
- `Cron\RefreshTracking` + `etc/crontab.xml` (mỗi 30 phút; guard config `tracking_refresh_enabled` default 0 + threshold giờ).
- Config + system.xml + i18n: `webhook_secret`, `tracking_refresh_enabled`, `tracking_refresh_threshold_hours`.

### 1.4 Files/classes đổi — tóm tắt

**ADD ShippingCore (0.3.0):** 4 Api interfaces/class + TrackingUpdate + Processor + state model/resource/collection + db_schema(+whitelist) + tests.
**ADD Ghtk (1.3.0):** GhtkStatusMapper + Webhook controller + TrackingRefreshService + Cron + crontab.xml + frontend/routes.xml + config fields + i18n + tests.
**Sửa:** `GhtkApiClient` (+getOrderStatus). Không sửa carrier/label flow (TASK-KM6YAT giữ nguyên).

### 1.5 DB/schema impact

Bảng mới `secomm_carrier_tracking_state` (ShippingCore db_schema + whitelist — additive; setup:upgrade). Không đụng table có sẵn. Schema = Tier 2 → đã nằm trong DEC approval.

### 1.6 Backward-compat risks

- Schema additive — an toàn; không đổi public API có sẵn; label flow không đổi.
- Webhook endpoint mới public — chỉ nhận 200, secret optional; security review note (secret-in-URL do GHTK không có HMAC documented).
- Cron default OFF → không side effect khi deploy.

### 1.7 Test plan (§17 mapping)

```
Tracking created      — (TASK-KM6YAT đã persist; assert processor tìm thấy track theo carrier+number)
Webhook valid         — controller/service → processor → state row + Track.description + event
Duplicate webhook     — second identical → no-op (không comment/event lặp)
Out-of-order          — DELIVERED rồi IN_TRANSIT cũ → vẫn DELIVERED (sticky terminal + timestamp)
Unknown status        — code lạ → UNKNOWN + raw code/message giữ + warning log, không fail
API fallback same path — RefreshService → cùng processor (assert source=api, state update)
Returned/Cancelled    — state + comment + event; order status KHÔNG đổi (assert)
Mapper                — bảng map chính + UNKNOWN default
Processor ordering    — timestamp priority case; DELIVERY_FAILED → IN_TRANSIT được phép
Webhook edge          — invalid JSON / thiếu field / unknown tracking / sai secret → 200 graceful
```

---

## PART 2 — TASKS (Phase B)

### Task 1 — ShippingCore tracking contracts + DTO
- **Goal:** Api\Tracking 4 thành phần + TrackingUpdate immutable. **Files:** `Api/Tracking/*`, `Model/Tracking/TrackingUpdate.php`, tests.
- **AC:** const đủ 11 status; isTerminal đúng 3 terminal; DTO getter đầy đủ. **Risk:** thấp.

### Task 2 — CarrierTrackingState persistence + db_schema
- **Goal:** bảng + model/resource/collection. **Files:** db_schema.xml + whitelist, `Model/CarrierTrackingState.php`, ResourceModel ×2, tests (skip DB — integration-deferred; unit test model setters).
- **AC:** `setup:db:status`/upgrade tạo bảng (local verify); UNIQUE(carrier_code, tracking_number). **Risk:** medium (schema — Tier 2 đã approve trong DEC).

### Task 3 — ShipmentTrackingProcessor (pipeline duy nhất)
- **Goal:** find → idempotency → sticky-terminal/timestamp → persist → Track.description + comment → events. **Files:** `Model/Tracking/ShipmentTrackingProcessor.php`, di, tests (mock Track collection/resource — processor dùng TrackRepository/search hoặc collection factory).
- **AC:** test plan rows (duplicate/out-of-order/unknown/no-order-mutation). **Risk:** medium — ordering logic là core.

### Task 4 — GhtkStatusMapper + getOrderStatus client
- **Goal:** map numeric → normalized (một chỗ) + API transport. **Files:** `Ghtk/Model/Tracking/GhtkStatusMapper.php`; `GhtkApiClient` +method; tests.
- **AC:** mapper table test + UNKNOWN default; client POST/GET reuse pattern submitOrder (timeout/headers/masked). **Risk:** Q-EXT — defensive.

### Task 5 — Webhook controller + routes + secret
- **Goal:** endpoint mỏng luôn-200. **Files:** `Controller/Webhook/Index.php`, `etc/frontend/routes.xml`, config `webhook_secret` (+system.xml + i18n), tests.
- **AC:** edge cases §4 (invalid/unknown/dup/secret sai) → 200; processor được gọi đúng với update parsed. **Risk:** security note.

### Task 6 — TrackingRefreshService + cron
- **Goal:** fallback/reconciliation same-pipeline + cron opt-in. **Files:** `Ghtk/Model/Tracking/TrackingRefreshService.php`, `Cron/RefreshTracking.php`, `etc/crontab.xml`, config refresh fields, tests.
- **AC:** stale non-terminal selection đúng; default OFF; API failure không crash cron (log + continue). **Risk:** thấp.

### Task 7 — Tests đầy đủ + DI compile + docs
- **Goal:** §17 map đầy đủ green; records closure-ready. **Files:** tests tổng + CHANGELOG/README ×2 + evidence + ticket AC check + TASK-KM6YAT limitation note cập nhật (isTrackingAvailable).
- **AC:** phpunit green (110+ mới); DI compile OK; validator VALID. **Risk:** thấp.

### Sequence & gating

```
[SA/TL approve DEC-TASK86NX9T-001]
→ Task 1 → 2 → 3 (core pipeline + tests) → 4 → 5 → 6 → 7
→ QC sandbox: GHTK dashboard webhook setup + secret; gửi webhook test (valid/dup/out-of-order);
  API refresh manual run; shipment view hiển thị Track.description + comments
→ AI pre-review → TL review (Tier 2)
```
