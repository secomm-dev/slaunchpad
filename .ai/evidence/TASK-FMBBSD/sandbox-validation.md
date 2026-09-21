# GHN Sandbox Validation — TASK-FMBBSD §42 (2026-09-11)

**Environment:** `carriers/secomm_ghn/environment = sandbox` → `https://dev-online-gateway.ghn.vn/shiip/public-api` · Shop 200537 (credentials do owner cấu hình trong admin; **token không bao giờ xuất hiện trong evidence này**). Toàn bộ request đi qua `GhnApiClient` tập trung. Địa chỉ test là placeholder, không PII.

**Dataset-reviewed destinations dùng thử (không hand-typed):**
- RATE/legacy: Đông Thành → Nhân Thành (`VNAP25-6A84B015D0` → district 1846, ward 291124, Huyện Yên Thành, Nghệ An).
- CREATE/current: Kỳ Lừa → Tân Thanh (`VNA25-4EA3ADA7E1` → `to_ward_name="Xã Tân Thanh"`, `to_province_name="Lạng Sơn"`).

## Kết quả theo critical assertion (§42)

| # | Assertion | Kết quả | Evidence |
|---|---|---|---|
| 1 | **is_new_to_address=true + to_district_name="" + tên 2 cấp** (create) | ✅ **VERIFIED** — tạo đơn thành công `order_code=L8TL6B`, `total_fee=68200`, `expected_delivery_time=2026-09-12T16:59:59Z` | payload: `to_province_name="Lạng Sơn"`, `to_ward_name="Xã Tân Thanh"`, `to_district_name=""`, `is_new_to_address=true`, weight 1500, dims 20/15/10, type 2, `payment_type_id=1`, `required_note=CHOXEMHANGKHONGTHU`, `cod_amount=0`, content + client_order_code |
| 2 | **Idempotency client_order_code** | ✅ **VERIFIED** — retry cùng code → **cùng `order_code=L8TL6B`** (không tạo đơn thứ hai) | §24 |
| 3 | **Fee service_type_id-only (KHÔNG service_id)** | ✅ **VERIFIED** — `{service_type_id:2, weight:1500, to_district_id:1846, to_ward_code:"291124"}` → `total=68200` (không cần from_*; service_id vắng mặt hoàn toàn) | docs contract khớp live |
| 4 | **Leadtime service_type_id + weight** | ✅ **VERIFIED** → `leadtime=1789232399`, `leadtime_order.from/to_estimate_date` (2026-09-12 → 2026-09-13) — **service_id không cần** | docs contract khớp live |
| 5 | **payment_type_id=1 (shop trả)** | ✅ **VERIFIED** — accepted; order info echo `payment_type_id=1`, `cod_amount=0`, `service_type_id=2`, `status=ready_to_pick` | §13 |
| 6 | **Fee == Create charge** | ✅ fee `total` 68200 == create `total_fee` 68200 | consistency |
| 7 | **Cancel per-order result** | ✅ `{order_codes:[L8TL6B], reason_code:"GHN-CO003"}` → `[{order_code, result:true, message:"OK"}]` — sandbox shop sạch lại | §9 matrix |
| 8 | **Type-5 (heavy) fee KHÔNG items** | ❌ REJECTED — HTTP 400 `code_message=USER_ERR_COMMON`, message "Cân nặng không hợp lệ" (21000g + type 5, no items) → **items[] là de-facto bắt buộc cho type 5** (docs hint "used for heavy goods"Confirmed) | §16 — DEFERRED sang CREATE slice |
| 9 | **Available Services body contract** | ⚠️ **DOCS vs SANDBOX CONFLICT** — xem bên dưới | D-new |

## ⚠️ Finding mới: Available-Services docs ≠ sandbox gateway

Docs hiện tại (đã quote verbatim example) và kết quả live:

```json
// docs example (verbatim):        // live sandbox (dev-online-gateway):
{"shop_id":196560,                 {"shop_id":200537,
 "from_district":1452,              "from_district":1846,      → 400 FromDistrictID required
 "to_district":1444}                "to_district":1846}
```

Đã thử TOÀN BỘ biến thể: `from_district` · `from_district_id` · `FromDistrictID` · `fromDistrictId` · `from_district_Id` (và cặp to_*) — **validator Go từ chối tất cả** (`Key: 'myRequest.FromDistrictID' ... 'required'`), trong khi `shop_id` body luôn được binding nhận (chỉ xuất hiện trong lỗi khi bỏ đi) — xác nhận docs về **body shop_id bắt buộc & khác ShopId header** ✓. Endpoint fee/leadtime cùng gateway nhận snake_case bình thường ⇒ chỉ available-services là lệch binding.

**Kết luận:** current-docs AS contract KHÔNG tái sản xuất được trên sandbox gateway. Không block slice (RATE không cần AS — fee trả `ROUTE_NOT_FOUND_SERVICE` khi hết route). AS ⇒ `EXTERNAL/OPERATIONAL` — hỏi GHN support hoặc thử production gateway trước khi dùng; **không code AS vào runtime** trên contract chưa verify.

## Ghi nhận thêm từ live

- Create response trả `sort_code`, `trans_type`, `expected_delivery_time`, fee breakdown đầy đủ — nhiều hơn bảng docs (docs nói sort_code không có → **docs stale, live có**).
- Order info KHÔNG echo `to_ward_name`/`to_province_name` ở new-address mode (trường không xuất hiện trong phần mình đã in) — chi tiết nhỏ, capture đầy đủ khi GHN-D.
- `GhnApiClient` end-to-end trên API thật: envelope parse + error classification (400→InvalidAddress/InvalidRequest, empty-body guard) hoạt động đúng.
