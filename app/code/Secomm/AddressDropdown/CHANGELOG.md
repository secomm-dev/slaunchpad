# Secomm AddressDropdown

## [Unreleased] — SL-001 dual-theme + i18n + en_VN (2026-07-17)
- **Dual-theme support (DEC-9)**: module renders both Luma (jQuery/requirejs) + Hyva (Alpine + GraphQL) via `hyva_` layout handle (`hyva_customer_address_form.xml` → `templates/hyva/address/edit.phtml`). No PHP theme detection.
- **Hyva customer form**: `templates/hyva/address/edit.phtml` (Alpine `initCustomerAddressEdit()` + `fetch('/graphql')` cascade, Tailwind v4).
- **Luma customer form**: restored (`templates/address/edit.phtml` + `web/js/address-dropdown.js`, requirejs `directoryAddressDropdownUpdater`).
- **Cart estimation**: Luma Knockout mixin restored (Hyva = native `php-cart`, region-based).
- **i18n (DEC-8 boundary)**: generic module emits `__()` default keys (`City`, `State/Province`, `Sub-City`, placeholders); VN labels via `Secomm_VietNamAddress` (`en_VN`/`vi_VN`). Generic `i18n/en_US.csv` no longer defines storefront VN labels.
- **Ahamave cleanup (DEC-8)**: removed dead `Secomm_Ahamove` requirejs reference (project-leak) + orphan `default-mixin.js`.
- **VN 3-tier**: city = phường/xã (commune); sub_city auto-hidden (matches 3-tier VN data).

## 1.0.0 (first released)
- Apply new address structure for:
  + Customer address form.
  + Order address information in Frontend and Backend.
  + Checkout shipping form, billing form.
  + Checkout render address saved.
  + Render address in email
- View the address list in Backend
