# Implementation Plan: TASK-KCXKVR — GHTK P0 API correctness

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-KCXKVR (parent FEAT-YA2C0W) |
| Mode | B (bug-fix theo official docs — architecture unchanged) |
| Specification | Embedded Mini-Spec trong [records/tasks/TASK-KCXKVR.md](../records/tasks/TASK-KCXKVR.md) — MINI, VALID (SPIKE-A1DGPY evidence basis) |
| Evidence basis | [SPIKE-A1DGPY evidence](../evidence/SPIKE-A1DGPY/evidence.md) §2/§5/§6 + official docs api.ghtk.vn (fetched 2026-09-14) |
| Decision | Không cần DEC mới — thực thi SPIKE findings; DEC-TASK7AJ3K8-002 giữ nguyên (TEXT_NATIVE không đụng) |
| Risk | Medium — carrier runtime fix; unit-testable; native label flow regression được bảo vệ |

## Approach

Ba lớp fix carrier-owned, không đụng shared layer: (A) status mapping table viết lại theo bảng
chính thức + parser webhook đa định dạng; (B) CREATE mapper serialize đúng docs shape
(`{order, products}`, `order.id`, weights kg tại boundary). Conversion unit (project gram →
GHTK kg) nằm ở mapper (§17 provider-owned); `buildProducts` giữ gram nội bộ.

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | TASK record (mini-spec) + plan | spec-first |
| 2 | Status mapper | `Model/Tracking/GhtkStatusMapper.php` | bảng §6 SPIKE; comment = official meanings |
| 3 | Webhook parser | `Model/Tracking/WebhookPayloadParser.php` | `decode()` JSON→form; `parsePayload()`; `action_time` |
| 4 | Webhook controller | `Controller/Webhook/Index.php` | dùng decode/parsePayload; luôn-200 giữ + docblock lý do |
| 5 | CREATE mapper | `Model/OrderSubmit/OrderRequestMapper.php` | `{order, products}`; `id`; kg boundary conversion; bỏ `weight_option`/`partner_order_id` |
| 6 | CREATE service | `Model/OrderSubmit/OrderSubmitService.php` | buildProducts giữ gram; log ids an toàn |
| 7 | Response mapper | `Model/OrderSubmit/OrderResponseMapper.php` | candidates += `tracking_id` |
| 8 | Tests | mapper/parser/controller/request/response/service tests | §12/§27 matrix |
| 9 | Gates | scoped + full suite; grep gates §32 | tách new vs pre-existing failures |
| 10 | Evidence + report | `.ai/evidence/TASK-KCXKVR/` | Tier-2 review pack |

## Test plan

Mapper: từng code theo bảng (5,6,7,9,11,12,13,21,4,8,10,-1,1,2,3,20; shipper-info; unknown;
string|int input). Parser: form-urlencoded valid; JSON valid; invalid cả hai → null;
action_time ISO 8601 → occurredAt; missing/invalid → null; identifier candidates; sanitize.
Controller: invalid payload → 200 ok:false; unknown status → 200 processed; duplicate → 200.
Request mapper: top-level exactly `order`+`products`; `order.id`; kg conversion (1kg product,
min 1g floor, multiple products, quantity, price); pickup nested (pick_address_id path +
names path); destination nested (district nullable; hamlet Khác); pick_money/is_freeship
location; total_weight kg. Response: label + tracking_id preferred; malformed → nulls.
Regression: OrderSubmitServiceTest + GhtkTest cập nhật shape.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| products weight kg vs directive text "GRAM" | official docs verbatim kilogram (fetch 2×, 2026-09-14); documented trong record + report; NEEDS_RUNTIME_VERIFICATION note cho staging |
| weight_option bỏ — GHTK default kilogram | docs-documented default; staging probe xác nhận |
| Mapper đổi làm tracking hiện hữu sai thêm | mapping table + tests là nguồn duy nhất; webhook + API cùng mapper |
| Always-200 che lỗi internal | documented: GHTK retry chỉ 1 lần → cron reconciliation là safety net; follow-up nếu cần policy khác |

## Validation gates

Scoped suite (Ghtk|ShippingCore|VietNamAddress) → full suite (tách pre-existing Secomm_Tracking
errors) · grep: 1 status-mapping source · 0 `partner_order_id`/`weight_option` trong payload ·
0 ShippingCore diff · validator.
