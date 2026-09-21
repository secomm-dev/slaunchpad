# Evidence — TASK-4ATBC4 GHN-E2: Cancel + Return APIs (sanitized)

> Date: 2026-09-16 · Environment: local dev WSL + GHN sandbox shop 200537 (masked)

## 1. Deliverables

| File | Content |
|---|---|
| `Model/Shipment/GhnActionOutcome.php` | VO — SUCCESS/BUSINESS_REJECTED/TECHNICAL_FAILURE/UNKNOWN_RESULT |
| `Model/Shipment/GhnCancelService.php` | POST `v2/switch-status/cancel` — reason enum fail-closed, per-order result, mutation-uncertainty split |
| `Model/Shipment/GhnReturnService.php` | POST `v2/switch-status/return` (force R2S) — per-order best-effort |
| `Console/Command/CancelShipmentCommand.php` + `ReturnShipmentCommand.php` + di.xml | CLI `secomm:ghn:shipment:cancel|return` |
| `Model/Client/GhnEndpoints.php` | += RETURN_ORDER |
| Tests | cancel 9 + return 4 + outcome 4 |

## 2. Contract (verified: matrix §9/§10 + runtime)

### Cancel — POST v2/switch-status/cancel
Request: `{order_codes:[string], reason_code: GHN-CO001|GHN-CO002|GHN-CO003|GHN-CANCEL-OTHER, reason?: string}`.
Batch all-or-nothing (E2 luôn gửi 1 code). Response: HTTP 200 + `data[].result` per-order.
**HTTP 200 + result:false ⇒ BUSINESS_REJECTED — KHÔNG bao giờ success.** Repeat: provider
idempotent-POSITIVE (cả 2 lần đều result:true — không duplicate harm, không inconsistent state).

### Return — POST v2/switch-status/return
Request: `{order_codes:[string]}` (không reason). Per-order best-effort. Eligible states:
delivery_fail / storing / waiting_to_return / return. Status khác ⇒ HTTP 200 result:false + message.

## 3. Runtime sandbox (dev store, order clone 19 → shipment 15 → GHN order L8TAKR)

| Case | Hành động | Kết quả |
|---|---|---|
| r3-create | qc_r2 probe POST rows [4.5kg 40/30/20] | SUCCESS L8TAKR 122,100 VND (r3 payload: items[] + root Σ, không dims ✓) |
| Cancel A | CLI cancel GHN-CO003 | **SUCCESS** — "GHN order L8TAKR cancelled" |
| Cancel B repeat | CLI cancel lần 2 | **SUCCESS (provider idempotent-positive)** — không duplicate harm |
| Return C cancelled-order | CLI return | **BUSINESS_REJECTED** — "Trạng thái đơn hàng không hợp lệ" (§25 negative ✓) |
| E1 reconcile | fetcher(detail L8TAKR) → mapper → processor | tracking state sync qua pipeline E1 (không E2 logic) |

GHN call log: cancel_order HTTP 200 ×2 (idempotent), return_order HTTP 200 ×1. 0 token/PII.

## 4. Error matrix (provider → normalized) — r2 taxonomy

| Provider | Normalized |
|---|---|
| HTTP 200 result:true | SUCCESS |
| HTTP 200 result:false (invalid state / already cancelled / not eligible) | BUSINESS_REJECTED |
| HTTP 401-403 / 400 / 404 (typed translator) | BUSINESS_REJECTED (PROVIDER_REJECTED) |
| HTTP 200 unusable envelope (data[] missing/malformed) | UNKNOWN_RESULT |
| timeout / connection / **HTTP 5xx** | UNKNOWN_RESULT (r2: applied-vs-not không kết luận được — reconcile qua Order Info; client không chứng minh before-send → conservative, không fabricate certainty) |
| **HTTP 429** (`ProviderRateLimitException`, r2 split) | TECHNICAL_FAILURE (rejected TRƯỚC xử lý — definitively not-applied; KHÔNG auto-retry) |

## 5. Gates — r2 final

0 order mutation (setState/setStatus/cancel trên Order) · 0 tracking-state direct write ·
0 payment inspection · 0 cross-carrier refs · suites: Ghn **273/0F/0E** · cross-module
**959/0F/0E** · compile: 0 lỗi stream này (2 lỗi external pre-existing tại legacy
`Secomm_GiaoHangNhanh` — working-tree stream khác, ghi nhận tại TASK-GKHXY1 r2 final, KHÔNG vá
chéo) · validator: 0 fail stream này (32 FAIL pre-existing thuộc records stream khác).
