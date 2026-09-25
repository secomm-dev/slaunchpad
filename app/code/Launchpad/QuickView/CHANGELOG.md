# Changelog — Launchpad_QuickView

All notable changes to this module are documented in this file.

## 1.0.1 (2026-09-25)

- Theme modal (`Magento_Theme::html/quickview/modal.phtml`): nút icon **Add to Wish List** + **Add to Compare** cạnh Add to Cart (ẩn theo `Wishlist::isEnabled()` / `ProductCompare::showInProductList()` như product card). Wishlist: cùng endpoint/payload Hyvä `initWishlist` (form data gồm option đã chọn + qty); khách → trang login. Compare: `redirect: 'manual'` để không reload trang. Kết quả hiện trong modal (`<dialog>` top layer che global messages).
- Qty: form `novalidate` — không còn bubble HTML5 native (ngôn ngữ theo browser); validate trong `addToCart()` với message theme (`This is a required field.` / `Please enter a valid number.` / `Value must be greater than or equal to %1.` / `Please enter a valid value.`) hiện dưới ô qty (`aria-invalid` + `aria-describedby`).
- i18n theme vi_VN/en_US: +`You added product %1 to the comparison list.`, +`Could not add item to compare.`

## 1.0.0 (2026-09-15)

- Initial release: `hyva_theme_quickview/general/enabled` system config (Hyvä Themes tab) (default Yes, per store view) + `ViewModel\Config::isEnabled()` for the theme Quick View trigger and modal `ifconfig` wiring (TASK-Z3DAH5, SLP-157).
