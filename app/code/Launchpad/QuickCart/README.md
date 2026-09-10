# Launchpad_QuickCart

## Purpose

Enhances the Hyvä native cart drawer (mini-cart) with coupon support, as part of the Quick Cart enhancement (TASK-QX93G3 / SLP-158). The module does NOT replace or re-implement the drawer — the drawer UI lives in the `Secomm/launchpad` theme (`Magento_Theme/templates/html/cart/cart-drawer.phtml` override).

## Features

- **AJAX coupon endpoint** `POST quickcart/coupon/post` — validates the form key, delegates apply/remove to `Magento\Quote\Api\CouponManagementInterface` on the session quote (server-side revalidation through native sales rule validation + totals collection), responds `{success, error_message}` matching the drawer JS contract.
- **Cart section coupon code** — plugin `afterGetSectionData` on `Magento\Checkout\CustomerData\Cart` adds `coupon_code` so the drawer can render the applied-coupon state across page loads.

## How it works

1. The coupon form template (`Magento_Theme::html/cart/coupon-form.phtml` in the theme) POSTs `coupon_code` / `remove` + `form_key` to `quickcart/coupon/post`.
2. The controller resolves the session quote (`Magento\Checkout\Model\Session`), calls `CouponManagement::set()` / `remove()`, and returns JSON. All native phrases (invalid coupon, cart empty…) are translated through the loaded dictionaries (`Launchpad_MageplazaTranslate`).
3. On success the form dispatches `reload-customer-section-data`; the drawer re-reads the cart section (subtotal, totals, `coupon_code`) without a page reload.

## Notes

- Mageplaza_QuickCart stays disabled — this module is the project's own implementation.
- No database schema; rollback = `module:disable Launchpad_QuickCart` + theme revert.
