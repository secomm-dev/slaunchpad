# Secomm_VietNamAddress — Changelog

## 1.1.0 (2026-07-17)
- i18n rework (locale-based, supersede global overrides):
  - Added `en_VN.csv` — VN-English address labels (Province/City, Ward/Commune, placeholders, Select-a variants).
  - Reverted `en_US.csv` — removed global VN overrides → clean default (City, State/Province).
  - Cleaned `vi_VN.csv` storefront rows — fixed `Select a city` (district → Phường/Xã), normalized casing (Tỉnh/Thành phố).

## 1.0.0
- Initial: 2-level VN address data install (`Files/VN_Address_2Level.csv` via `InstallVietNamAddressPatch`) + `vi_VN.csv` / `en_US.csv` i18n.
