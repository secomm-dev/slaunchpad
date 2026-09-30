# Changelog — Secomm_Base

## 1.1.0 — 2026-09-23 (TASK-RT50KH — product shipping-dimension contract P1)

### Added
- `Api\Data\ShippingDimensions` (immutable VO, int cm) + `Api\ShippingDimensionsReaderInterface`
  + `Model\Shipping\ProductShippingDimensionsReader` — authoritative read: complete-only
  (cả 3 present + numeric + > 0 → ceil int cm; else null = missing, không rejection);
  composite resolution (configurable → selected child; bundle ship-together → parent).
- First Test/Unit suite (reader boundary grid — 11 tests).
- Migration patch `UpgradeDimensionAttributesToShippingContract`: length/width/height
  varchar/STORE (display) → **decimal/GLOBAL**, labels "Shipping … (cm)",
  `validate-number validate-zero-or-greater`, merchandising flags off. Zero data loss
  (audit 2026-09-23: 0 value rows). Consumer tương thích: Secomm_Base shipping plugin,
  Secomm_Ahamove volumetric.

### Changed
- `etc/module.xml` sequence += Magento_Quote (API typed on Quote\Item); di.xml preference
  cho reader interface.



## 1.0.0
- Initial: "Secomm" admin menu ("CORE" section) + "Secomm Extensions" system configuration tab + shared helpers/plugins.
