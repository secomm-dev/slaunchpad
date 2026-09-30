# Runtime Verification Runbook — Address/Shipping audit (TASK-SEC)

Chỉ chạy trên môi trường CÓ Magento DB + browser + credentials. Không đưa credential thật vào
file này — dùng biến môi trường/vault của team. Mỗi bước ghi command + expected + evidence cần
capture. KHÔNG chạy trên dữ liệu production.

## A. DB upgrade (B1 exact migration)

Prerequisites: clone DB có `patch_list` chứa `ImportVnAdminPre2025To2025MappingPatch` (bản
baseline 10,064) — nếu clone từ DB đã có `RefreshVnAdminPre2025Snapshot2024` thì DB đã 10,418
(chọn 1 clone ĐỨNG ở baseline để test đúng kịch bản "old DB").

1. `mysqldump ... > backup.sql` — capture before:
   `SELECT COUNT(*) FROM secomm_vietnam_address_mapping;` (expect 10,064 cho old-baseline clone)
2. Deploy code, `bin/magento setup:upgrade` — expect exit 0; patch_list thêm
   `ResyncVnAdminPre2025SnapshotMappingPatch`.
3. After: mapping count == 10,418; chạy `.ai/evidence/TASK-SEC-AUDIT/reconcile_mapping_tool.php`
   — expect extra=0, missing=0, changed=0, orphans=0, multi-primary=0.
4. Verify 41 stale keys removed (manifest `removed_keys`); merchant/custom rows added trước đó
   vẫn còn (không có key nào thuộc manifest).
5. Chạy `setup:upgrade` LẦN HAI — expect no-op (count == 10,418), zero changes.
6. Checksum drift test: sửa tạm 1 byte trong snapshot CSV → `setup:upgrade` (env mới) → expect
   loud failure "checksum mismatch" — HOÀN TÀI file sau test.
7. Rollback: `mysql backup.sql` + revert code (không có schema change nào từ patch này).

## B. Browser (Batch A + A5)

Data: 1 role restricted (chỉ `Secomm_AddressDropdown::listing`), 1 role management, 1 full admin.

1. POST delete city/region qua form button (admin) → thành công; request log: method=POST,
   có `form_key`.
2. GET `/admin/addressdropdown/city/delete/city_id/N/` → mutation = 0 (record vẫn tồn tại),
   error message "Invalid request method".
3. Mass delete POST → OK; GET mass delete → rejected.
4. Restricted role listing-only: thấy grid, không delete được; role không quyền → access denied.
5. Master switch `address/general/enable=0` (store view EN) vs =1 (store view VI):
   - EN: không dropdown component, không request GraphQL `GetListCity`, không `city-data`
     section, Magento native fields hoạt động; `checkoutConfig.secommAddressDropdownEnabled`
     = false.
   - VI: dropdown hoạt động như cũ.
   - `city_data_cache_key_<store>` khác nhau giữa 2 stores (kiểm cache entry).
6. OSC checkout: same disabled/enabled behavior, không JS error console.
7. Hyvä customer address form: schema renderer theo profile hiện hành.

## C. REST/GraphQL customer group (Batch C3)

Prerequisites: 1 method TableRate giới hạn customer group = General (hoặc custom group),
`show_to_customer=0` + `use_as_fallback=1` cho fallback-only check; 1 customer token.

1. REST guest cart: `POST /V1/guest-carts/:id/estimate-shipping-methods` — method bị group-reject
   KHÔNG xuất hiện (guest ≠ General).
2. REST customer token: `POST /V1/carts/mine/estimate-shipping-methods` — method General PHẢI
   xuất hiện (group từ quote của request, không phải session guest) — đây là verify chính của
   C3 fix.
3. GraphQL guest: `cart` query shipping methods — đúng guest eligibility.
4. GraphQL customer token: group đúng (core bridge + quote-based resolution đồng thuận).
5. Admin order creation: backend quote session group.
6. Fallback: với GHN technical failure (kích hoạt bằng sandbox lỗi), Launchpad fallback append
   đúng group restriction; không duplicate khi mptablerate active=1.
7. Capture: request/response JSON + method list mỗi channel.

## Cleanup

Xóa carts/customers test; restore config flags; giữ evidence JSON/screenshots vào
`.ai/evidence/TASK-SEC-AUDIT/runtime/`.
