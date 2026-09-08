# BUG-5NR0PD (SLP-150) — Rework 2026-09-07: coupon messages qua Secomm_MageplazaTranslate

## Trigger

User QC report 09-07: message apply/remove coupon ở checkout vẫn EN. Xác nhận **side-finding của BUG-GJT6C1** (LL-0011): fix 09-04 đặt value VI vào dict của theme `Secomm/launchpad` (174 keys, đủ key) — nhưng checkout chạy **Magento/luma scope**, dict thật = `pub/static/frontend/Magento/luma/vi_VN/js-translation.json` (45 keys, coupon MISSING).

Hiện trạng tree trước rework (đối chiếu record §Fix cũ):
- `app/code/Mageplaza/OscPro/i18n/` — **không tồn tại** (khác mô tả CURRENT_STATE 09-04; file đã bị bỏ khi pivot theme-layer).
- `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/` — **không tồn tại**; 2 theme JS override "dead code" trong record cũng vậy.
- Còn nguyên: theme CSV (2 key từ SLP-133), theme override `Mageplaza_TableRateShipping` (2 template + Secomm comment + `$t()` wrap), `TableRateShipping/i18n/{en_US,vi_VN}.csv`.
- (b) shipping errmsg: key VI **đã có trong luma dict** (verify lại 09-07 — chỉ (a) coupon là thiếu).

## Rework (directive user 09-07: "tạo translate trong secomm mageplaza translate")

= module **`Secomm_MageplazaTranslate`** (home i18n Secomm cho Mageplaza strings, đã tạo 09-07 trong BUG-GJT6C1 Phase B sau khi user chối CSV vào vendor):

- `app/code/Secomm/MageplazaTranslate/i18n/vi_VN.csv` — append 2 key success coupon (LF, quote style khớp file), value mirror theme CSV (SLP-133).
- Không đụng vendor `Mageplaza_OscPro` (candidate literal `$t()` sẵn trong vendor JS, scan được ở mọi theme gồm luma) — module CSV là value source cho mọi scope.

Đổi script verify: `verify-checkout-dictionary.php` (trước gọi nhầm `verify-pack-checkout.php`).

## Detour đã gỡ — language pack `app/i18n/Secomm/vi_VN` (finding giữ lại cho TL)

Đọc nhầm directive đầu giờ → đã dựng thử language pack rồi **gỡ sạch** (`app/i18n` không còn). Giữ 2 dữ kiện verified trong detour (giá trị cho quyết định error REST sau này):

1. **Pack hoạt động đúng cơ chế** (đã verify trước khi gỡ: luma vi dict 49 keys, 2 coupon VI; verify script 3/3 + 2/2 per store) — pack là phương án khi cần dịch chuỗi **webapi/REST** (error `CouponManagement` "The coupon code isn't valid…") vì module CSV không áp cho area webapi. Quyết kiến trúc vẫn chờ TL/SA như record ghi.
2. **Gotcha registration**: `ComponentRegistrar::LANGUAGE` key phải **underscore** `'secomm_vi_vn'` (chuẩn pack thật `magento_zh_hans_cn`). Key slash → `Dictionary::readPackCsv` `getPath()` null → **silent skip** (deploy ra dict thiếu key, không error nào). Pack discovery qua composer autoload files (`app/etc/NonComposerComponentRegistration.php`) — không cần `setup:upgrade`.

## Verify (09-07, sau rework cuối — module CSV, không pack)

- **luma vi dict** (`json_decode`): **49 keys**; `"Your coupon was successfully applied." => "Mã giảm giá đã được áp dụng thành công."` + `"…removed." => "Mã giảm giá đã được xóa thành công."` ✅; errmsg + `Delivery Date` giữ nguyên ✅.
- **luma en dict**: 0 keys, 0 dòng chứa ký tự VI ✅ (BR-001 identity).
- **Dictionary PHP-side, checkout context** (`.ai/evidence/BUG-5NR0PD/verify-checkout-dictionary.php`, theme **Magento/luma**, 1 process/store, renderer wired — LL-0007):
  - store `default` (vi): **3/3 PASS** (2 coupon + errmsg VI).
  - store `launchpad_en`: **2/2 PASS** (identity EN).
- Deploy: `rm` 2 luma locale dict → `setup:static-content:deploy -f --theme="Magento/luma" vi_VN en_US` as `secomm` → `cache:flush` (as secomm).

## QC pending (browser, storefront thật)

1. vi store: checkout OSC → apply coupon hợp lệ → "Mã giảm giá đã được áp dụng thành công."; remove → "Mã giảm giá đã được xóa thành công." (AC-001/AC-002).
2. en store (`launchpad_en`): 2 message EN nguyên bản (AC-004).
3. errmsg TableRate (AC-003): bật `carriers/mptablerate/active=1` + giỏ không khớp rate — render qua template `-osc`.
4. Discount form (batch 2): placeholder "Nhập mã giảm giá", nút "Áp dụng" / "Hủy mã giảm giá" trên checkout OSC vi; apply coupon SAI → error message VI (REST, đã verify API-level); en store English nguyên bản.

## Batch 2 (09-07, cùng session) — discount labels + error REST

User yêu cầu thêm: error coupon SAI + "Enter discount code" + "Apply discount".

- **3 label** (`$t()` literals trong `Mageplaza_Osc/view/frontend/web/template/container/{payment,review}/discount.html:35,42,47` — candidate hợp lệ): append vào module CSV, value mirror theme CSV → luma vi dict **52 keys**: "Nhập mã giảm giá" / "Áp dụng" / "Hủy mã giảm giá" ✅; luma en dict 0, VI-leak 0 ✅.
- **Error REST** (`The coupon code isn't valid. Verify the code and try again.` — `vendor/magento/module-quote/Model/CouponManagement.php:72`, render **server-side area webapi**, KHÔNG BAO GIỜ vào js-translation.json vì literal nằm trong PHP — LL-0008(1)): append vào module CSV + mirror theme CSV pair (wording mới "Mã giảm giá không hợp lệ. Vui lòng kiểm tra lại mã và thử lại." — **chờ TL/QC duyệt wording**).
- **Verify end-to-end bằng REST thật** (guest cart có item, `PUT /V1/guest-carts/<id>/coupons/<code-sai>`):
  - store `default` (vi): `{"message":"Mã giảm giá không hợp lệ. Vui lòng kiểm tra lại mã và thử lại."}` ✅
  - store `launchpad_en`: `{"message":"The coupon code isn't valid. Verify the code and try again."}` ✅ (identity)
- **⟹ LL-0012(4) VERIFIED: module CSV `Secomm_MageplazaTranslate` feed cả area webapi** — error REST KHÔNG cần language pack; quyết định "chờ TL/SA về pack" thu hẹp còn error concat không dịch được (CouldNotSaveException) nếu có.
- Ghi chú: route guest coupon = **PUT** (POST → "Request does not match any route"); cart trống chặn trước bằng "The "%1" Cart doesn't contain products." (chưa dịch — ngoài yêu cầu; thêm key vào CSV nếu QC muốn); DELETE guest-cart route 404 trên setup này → các cart test còn lại trong DB (guest carts 137–142, vô hại local).

## Ghi chú

- Delta 45→47 keys ở deploy trung gian: 2 key VI label phụ scan-set (không liên quan coupon, en dict vẫn 0) — không phải regression.
- Error REST `CouponManagement` + 5 phrase `$t()` Osc/OscPro còn lại (Login/PayPal/Address validation/Gift ×2): error REST cần pack (xem detour finding); 5 phrase JS còn lại chỉ cần append vào CSV module này.
