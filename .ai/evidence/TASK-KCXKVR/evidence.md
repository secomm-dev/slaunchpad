# Evidence — TASK-KCXKVR: GHTK P0 API correctness (tracking/webhook + CREATE)

Date: 2026-09-14 · Basis: SPIKE-A1DGPY evidence (official docs fetched 2026-09-14) · 0 ShippingCore change

## Gate 1 — Scoped unit suite (Ghtk|ShippingCore|VietNamAddress)

```
Tests: 571, Assertions: 1547 — 1 failure = Secomm_Ghn\...\GhnAddressCapabilityTest
(matched filter QUA METHOD NAME chứa "ShippingCore"; module GHN là WIP stream song song,
pre-existing, ngoài scope — verify: task này 0 file GHN/ShippingCore được sửa)
```
Baseline trước task (cùng filter): 548 pass. Sau: +23 tests GHTK mới/cập nhật, 0 regression
trong 3 module scope.

## Gate 2 — Full suite (§31 — tách pre-existing)

```
Tests: 1454, Assertions: 214839
27 failing — TẤT CẢ ngoài scope, pre-existing WIP streams song song:
  - Secomm_Ghn (17)         — GHN-B.2/RATE slice WIP (chờ TL review theo memory state)
  - Secomm_Tracking (7)     — pre-existing, verified fail cả trên cây HEAD (r0)
  - Secomm_FulfillmentCore (3) — WIP stream khác
New failures do task này: 0.
```

## Gate 3 — §32 grep/static gates

```
1. Single status mapping source: grep raw status comparisons ngoài GhtkStatusMapper → CLEAN
   (webhook + Tracking API fetcher cùng dùng GhtkStatusMapper; parser/fetcher 0 mapping logic)
2. Old flattened CREATE payload: grep "partner_order_id|'weight_option'" production code →
   CLEAN (docblock OrderRequestMapper còn 1 mention = historical note có chủ ý;
   log context key đổi thành order_id — giá trị vẫn là Secomm partner id an toàn)
3. kg leak: buildProducts giữ gram nội bộ; conversion gram→kg CHỈ ở OrderRequestMapper
   (productsPayload + total_weight) → CLEAN
4. ShippingCore production changes trong task này: 0 (diff ShippingCore = r0 work pre-existing
   uncommitted, không file mới trong session này)
```

## Gate 4 — Validator

```
$ php .ai/bin/project-ai-validate --check-specs --check-records → exit 0 (VALID)
```

## Mapping table (Workstream A — final)

| Official GHTK | Meaning (VI) | Normalized | Terminal |
|---|---|---|---|
| -1 | Hủy đơn hàng | CANCELLED | ✓ |
| 1 | Chưa tiếp nhận | CREATED | |
| 2 | Đã tiếp nhận | PICKING | |
| 3 | Đã lấy hàng/Đã nhập kho | PICKED_UP | |
| 4 | Đã điều phối giao hàng/Đang giao hàng | OUT_FOR_DELIVERY (P1-fix: was IN_TRANSIT) | |
| 5 | Đã giao hàng/Chưa đối soát | DELIVERED | ✓ |
| 6 | Đã đối soát | DELIVERED (P0-fix: was DELIVERY_FAILED) — preserved terminal | ✓ |
| 7 | Không lấy được hàng | DELIVERY_FAILED (P0-fix: was PICKING; non-terminal, raw reason preserved) | |
| 8 | Hoãn lấy hàng | PICKING (comment corrected) | |
| 9 | Không giao được hàng | DELIVERY_FAILED (P0-fix: was PICKING) | |
| 10 | Delay giao hàng | OUT_FOR_DELIVERY | |
| 11 | Đã đối soát công nợ trả hàng | RETURNED (P0-fix: was DELIVERY_FAILED) | ✓ |
| 12 | Đã điều phối lấy hàng/Đang lấy hàng | PICKING (P0-fix: was RETURNING) | |
| 13 | Đơn hàng bồi hoàn | RETURNED (semantic-corrected; best existing terminal) | ✓ |
| 20 | Đang trả hàng | RETURNING | |
| 21 | Đã trả hàng | RETURNED (P0-fix: was MISSING → UNKNOWN) | ✓ |
| 123/127/128/45/49/410 | Shipper info — "không phải trạng thái đơn hàng" | UNKNOWN (deliberately unmapped) | |

## CREATE payload (Workstream B — before/after)

```jsonc
// BEFORE (r1 — sai docs): flat + wrong keys
{ "pick_name": "…", "pick_money": 0, "weight_option": "gram", "partner_order_id": "ghtk-…",
  "weight": 900, "order": [ /* products array dưới key "order"! */ ] }

// AFTER (official docs): {order:{…}, products:[…]}
{
  "order": {
    "id": "ghtk-100000001-1",            // partner key — official duplicate-detection field
    "pick_name": "…", "pick_tel": "…", "pick_address": "…",
    "is_freeship": 1, "pick_money": 500000, "value": 1100000,
    "transport": "road", "name": "…", "tel": "…", "address": "…",
    "province": "Hà Nội", "ward": "Phường Hàng Trống", "hamlet": "Khác",
    "district": "Hoàn Kiếm",             // chỉ khi adapter cung cấp (nullable — staging gate)
    "total_weight": 0.9,                 // Double, KG — boundary conversion từ 900 g
    "pick_address_id": "paid-7"          // hoặc pick_province/pick_ward(/pick_district)
  },
  "products": [ { "name": "Ao thun", "weight": 0.2, "quantity": 2, "price": 250000.0 } ]
}
```

**Weight unit note (directive vs official docs):** directive §16 ghi official = "GRAM"; official
docs fetch 2× (2026-09-14) khẳng định ngược lại verbatim — "Product weight in kilograms",
"The unit of weight GHTK uses for each product is kilograms (KG)", `weight_option` default
kilogram. Theo §3 của chính directive (official API evidence > assumptions), products weight
được chuyển sang **KILOGRAM**; `weight_option` OMIT (default khớp — không mixed-unit payload).
Interaction weight_option↔products: NEEDS_RUNTIME_VERIFICATION (staging) — nếu staging chứng
minh ngược, revert là 1 điểm duy nhất (`OrderRequestMapper::gramsToKilograms`).

## Webhook (§9–§11)

- `WebhookPayloadParser::decode()`: JSON first → form-urlencoded fallback (official sample).
- `action_time` ISO 8601 → `occurredAt` (fallback legacy keys giữ; invalid/missing → null,
  update vẫn được xử lý).
- Response semantics: GIỮ nguyên always-200 (valid/duplicate/unknown-status → 200 processed;
  invalid payload → 200 ok:false). Được document: GHTK chỉ retry đúng 1 lần nên non-200 không
  bảo toàn update — cron reconciliation (`TrackingReconciliationService`) là safety net. Policy
  non-200 cho catastrophic internal failure = follow-up (cần quyết lifecycle, không tự ý đổi).
- Identifier candidates += `partner_id` (official payload field — reconciliation fallback).

## Response parsing (§23)

`OrderResponseMapper::trackingNumber` candidates: `tracking_id` (official, ưu tiên) →
`tracking_code` → `tracking` → `label` (historical fallbacks). `labelId` giữ `label`/`label_id`.
`ORDER_ID_EXIST` vẫn chạy nhánh rejection hiện tại (`success:false` → LocalizedException) —
recovery là P1 follow-up (SPIKE backlog #4/TASK 4).

## Logging (§26)

CREATE failure/rejection log: `order_id` (Secomm partner id — safe), exception message class,
GHTK `reason`. Không Token, không full address, không raw payload (MaskingLogger + parser
sanitize giữ nguyên).
