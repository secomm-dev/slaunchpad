# TASK-Y23EMS (SLP-264) — PDP Add to Cart AJAX qua Monsoon_HyvaAjaxAddToCart

Verify: 2026-09-22, local env `http://slaunchpad.localhost/` (curl `--resolve` do DNS hiccup), Playwright probe `/tmp/pw-cal` (bản copy trong thư mục này), chạy as `secomm`. Store vi_VN (store 1).

## Files changed

1. `app/design/frontend/Secomm/launchpad/Monsoon_HyvaAjaxAddToCart/templates/hyva/script/addtocart.phtml` — MỚI (theme override module template; tách `window.ajaxSubmitCart(form, recursive)`; branch FormData cho file option).
2. `app/design/frontend/Secomm/launchpad/Magento_Catalog/templates/product/view/product-form.phtml` — override `onSubmit` của component `hyva.formValidation` (valid → `ajaxSubmitCart`, helper vắng → fallback native `form.submit()`); `php -l` PASS cả 2 file.

## Kết quả Playwright (probe-pdp-atc.js — run sau khi restore config, lần 3)

| Test | Kịch bản | Kết quả | AC |
|---|---|---|---|
| T1 | PDP simple (`joust-duffle-bag`) click ATC | `noNav=true, posts=1, cart 0→1, drawerOpen=true` | AC-001 PASS |
| T2 | PDP configurable (`chaz-kangeroo-hoodie`) thiếu option | `posts=0, fieldError=true, msg="Vui lòng chọn một trong các tùy chọn."` (inline VI, 0 POST) | AC-002 PASS |
| T3 | PDP configurable đủ 2 nhóm option (radio swatch 154+93) | `noNav=true, posts=1, cart +1, drawerOpen=true` | AC-003 PASS |
| T4 | PLP `/gear/bags.html` form card (12 form `.product_addtocart_form`) click `button[data-addto="cart"]` | `noNav=true, posts=1` (Monsoon bind path intact) | AC-005 (card) PASS |
| T5 | Flag `enable_ajax_add_to_cart=0` (DB toggle + `cache:flush`) | `ajaxSubmitCartPresent=undefined, postsToCartAdd=1 (native), backOnPdp=true` → fallback native hoạt động | AC-006 PASS |

Flag đã restore `=1` + `cache:flush` + re-run T1–T4 PASS xác nhận trạng thái as-found:
`enable_agreements=1, enable_ajax_add_to_cart=1, ajax_cart_open_after_add_to_cart=1, ajax_add_to_cart_selectors=.product_addtocart_form` (nguyên bộ `checkout/options/*` không đổi so với đầu session).

## Ghi chú phạm vi verify

- **AC-004 (file option)**: KHÔNG có sản phẩm custom-option type `file` trong fixtures local (`catalog_product_option` rỗng nhánh file). Đã verify code-path: `hasFileInput` → `new FormData(form)` không set `Content-Type` (browser tự set multipart boundary). Cần QC trên data thật khi có sản phẩm file-option.
- **AC-007 (en_US)**: local `?___store=` không switch store (LL-0011, qiurk env). VI verified live (T2). EN: message key `There was a problem adding your item to the cart.` có sẵn tại `i18n/en_US.csv:873` (đã có từ trước — không thêm key mới); validation message EN thuộc mechanism BUG-KFJ49A (đã verify trong ticket đó).
- **Quick View regression (phần AC-005)**: modal Quick View không match selectors Monsoon + không đụng form PDP (TASK-Z3DAH5 T2 baseline); 2 file sửa không nằm trong path Quick View → review-code verified, không probe browser riêng.

## Traps đã gặp khi verify

1. DNS `slaunchpad.localhost` hiccup → curl false "không render" — fix `curl --resolve slaunchpad.localhost:80:127.0.0.1`.
2. `cache:flush config` KHÔNG xóa layout cache → ifconfig block vẫn render sau khi toggle flag; phải `cache:flush` full.
3. Native fallback ATC redirect về chính PDP → assert "URL đổi" sai; assert bằng POST count.
4. Form card PLP có 3 button; ATC = `button[data-addto="cart"]`, không phải button đầu tiên.
5. Configurable PDP có 2 nhóm radio super_attribute — phải chọn đủ cả 2 nhóm trước ATC.
