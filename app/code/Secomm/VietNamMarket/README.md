# Secomm_VietNamMarket

Vietnam market setup module for Magento 2. Registers the **`en_VN` (English - Vietnam)** locale so store-views can use Vietnam-specific English labels (e.g. address "City" → "Ward/Commune", "State/Province" → "Province/City") without polluting the default `en_US` locale.

## What it does
- Registers `en_VN` as an allowed Magento locale (selectable per store-view + passes locale validation).
- Injects `en_VN` into the admin **locale dropdown** (ICU does not ship `en_VN`; without the plugin it never appears).
- Allows `en_VN` to pass the **static-resource locale validator** so CSS/JS/images serve with correct MIME types for the `en_VN` store-view.

## Why a separate module
Locale registration is a **market-level** concern (broader than address). Address-specific translations (`en_VN.csv` labels) live in `Secomm_VietNamAddress`. This module only **enables** the locale; the address module **provides** the translations. (See DEC-9.)

## How it works (3 mechanisms, all in `etc/di.xml`)
1. `Magento\Framework\Locale\Config` `allowedLocales` += `en_VN` — store-view config validation.
2. `Plugin\Locale\AddEnVnLocale` — injects `en_VN` into `Magento\Framework\Locale\TranslatedLists` (dropdown source, ICU gap).
3. `Plugin\Locale\ValidateEnVnLocale` — forces `en_VN` valid in `Magento\Framework\Validator\Locale::isValid` (static-resource serving).

## Requirements
- Magento 2.4.x + PHP `intl` extension.
- After enabling: `bin/magento setup:di:compile && bin/magento cache:flush`. Then set the store-view locale to `en_VN` (Stores → Configuration → General → Locale Options). Deploy static content for production: `bin/magento setup:static-content:deploy en_US vi_VN en_VN`.

## Related
- `Secomm_VietNamAddress` — provides `en_VN.csv` address labels + the 2-level VN address data.
- DEC-9 (dual-theme / locale architecture).
