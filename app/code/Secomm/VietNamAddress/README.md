# Secomm_VietNamAddress

Vietnam address data + i18n for `Secomm_AddressDropdown`. Provides the 2-level VN address dataset (provinces + communes/wards) and the locale-specific address labels.

## What it does
- Installs the **2-level VN address data** (`Files/VN_Address_2Level.csv` via `Setup/Patch/Data/InstallVietNamAddressPatch.php`): region (tỉnh/thành phố) → city (phường/xã / commune). Matches Vietnam's 3-tier admin structure (country → province → commune); the district (quận/huyện) level was removed in the 2025 reform.
- Provides **locale i18n** for address labels:
  - `vi_VN.csv` — Vietnamese (Tỉnh/Thành phố, Phường/Xã).
  - `en_VN.csv` — English-Vietnam (Province/City, Ward/Commune).
  - `en_US.csv` — clean default English (City, State/Province).
  - The store-view locale selects the label set.

## Why separate from Secomm_AddressDropdown
`Secomm_AddressDropdown` is the **generic** address-dropdown module (any country, 3+ admin levels, theme-agnostic Luma + Hyva). This module provides the **Vietnam-specific** data + labels. Clean separation of reusable-generic vs country-specific (DEC-8 boundary).

## Data structure
3-tier VN (2025+ reform): country → region (tỉnh/thành phố) → city (phường/xã). The 4-level file `VN_Address.csv` (with district) is retained in `Files/` but unused — the default import is `VN_Address_2Level.csv` (3-tier).

## Requirements
- `Secomm_AddressDropdown` (consumes the data + emits the `__()` keys this module translates).
- `Secomm_VietNamMarket` (registers the `en_VN` locale used by `en_VN.csv`).

## Related
- `Secomm_AddressDropdown` — generic address-dropdown module.
- `Secomm_VietNamMarket` — registers the `en_VN` locale.
