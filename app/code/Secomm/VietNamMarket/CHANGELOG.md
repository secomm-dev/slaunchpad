# Secomm_VietNamMarket — Changelog

## 1.0.0 (2026-07-17)
- Initial release.
- Register `en_VN` (English - Vietnam) locale:
  - `allowedLocales` config (`Magento\Framework\Locale\Config`).
  - Dropdown injection plugin (`AddEnVnLocale` on `TranslatedLists`) — ICU does not ship `en_VN`.
  - Static-resource validator plugin (`ValidateEnVnLocale` on `Magento\Framework\Validator\Locale::isValid`) — fixes MIME `text/plain` block for `en_VN` assets.
- Enables VN-English store-views (Province/City, Ward/Commune address labels via `Secomm_VietNamAddress/i18n/en_VN.csv`).
