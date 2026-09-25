# QC GATE — GHN CREATE với `cod_amount > 0` (sandbox)

Status: **BLOCKED_BY_CREDENTIAL** (2026-09-23 — `core_config_data` KHÔNG có row
`carriers/secomm_ghn/api_token` / `carriers/secomm_ghn/shop_id` ở bất kỳ scope nào; base URL
sandbox vẫn resolve từ config default `<environment>sandbox</environment>`).
**KHÔNG được ghi "GHN COD E2E pass" khi chưa có response thành công thật từ sandbox.**

## Điều kiện mở gate

Owner nhập lại credentials vào staging/local qua Admin (encrypted backend):
`carriers/secomm_ghn/api_token` + `carriers/secomm_ghn/shop_id` (sandbox shop).
Credentials KHÔNG bao giờ paste vào chat/log/commit — dùng env-var convention
(theo `.ai/evidence/TASK-44F7V7/runbook.md`).

## Probe request (reuse payload đã sandbox-verified ở TASK-FMBBSD, chỉ thêm cod_amount)

```
POST https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create
Headers: Token: <GHN_SANDBOX_TOKEN>   ShopId: <GHN_SANDBOX_SHOP_ID>   Content-Type: application/json

{
  "client_order_code": "GHNSCOD1",
  "to_name": "Nguyen Van B",
  "to_phone": "0901234567",
  "to_address": "12 Nguyễn Huệ",
  "to_province_name": "Lạng Sơn",
  "to_ward_name": "Xã Tân Thanh",
  "to_district_name": "",
  "is_new_to_address": true,
  "service_type_id": 2,
  "payment_type_id": 1,
  "required_note": "CHOXEMHANGKHONGTHU",
  "cod_amount": 1250000,
  "weight": 1500,
  "length": 30, "width": 20, "height": 10,
  "content": "Waffle Blanket x1"
}
```

(Alternative: thêm `"items"` thay root dims cho type-5 — không bắt buộc cho gate này.)

## Expected success response

```json
{ "code": 200, "message": "Success", "data": { "order_code": "<new L8T*>", "total_fee": <int VND>,
  "expected_delivery_time": "<iso>", ... } }
```

Evidence cần thu thập:
1. Full request/response (token/shop_id MASKED) — lưu vào thư mục evidence này.
2. Confirm `cod_amount` echo/khả kiến ở response hoặc GetOrder info (nếu API trả về).
3. Idempotency: POST lại CÙNG `client_order_code` → CÙNG `order_code` (không tạo đơn thứ hai).
4. Cleanup: cancel probe order qua `POST .../v2/shipping-order/cancel` body
   `{"order_codes": ["<order_code>"], "reason_code": "GHN-CO003"}` (reason đã verified
   TASK-FMBBSD row 7) — xác nhận result true.

## Failure modes cần phân biệt khi probe

- `cod_amount` rejected/vượt cap 50,000,000 → chốt lại cap guard (đã fail-closed ở
  `GhnCreateRequestBuilder::MAX_COD_AMOUNT` + service).
- OTP requirement khi SỬA cod_amount (updateCOD — KHÔNG dùng ở flow này; create là boundary).
- Bất kỳ mismatch giữa docs (contract matrix §5 L102) và sandbox → update
  `address-shipping.md` §32 fact row trước khi đóng gate.
