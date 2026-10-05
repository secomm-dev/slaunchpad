# Changelog — Secomm_CurrencyPrecision

All notable changes follow Keep a Changelog; versioning: unreleased until TL review.

## [Unreleased] — 1.0.0 (2026-10-05)

### Added (TL-approved solution, est 5h)

- Module scaffold: `registration.php`, `etc/module.xml` (sequence `Secomm_Base`, `Hyva_Theme`), `di.xml` (2 plugins), README.
- Admin config **Stores → Configuration → General → Currency Setup → Display Precision (Secomm)**, scope Store View:
  `default_precision` (Auto/0–4, source model `PrecisionOptions`) + `pairs` (`CODE=PRECISION`, e.g. `VND=0, USD=2`,
  validated at save by backend model `CurrencyPairs`). Empty/auto = keep native locale behavior.
- `Model\PrecisionResolver` — source of truth: per-currency lookup with `default_precision` fallback,
  `null` = Auto; memoized per store for the request lifetime (hot path: ~thousands of `formatTxt` calls on PLP);
  injected as DI proxy in consumers.
- `Plugin\CurrencyFormatting` — before-plugin on `Magento\Directory\Model\Currency::formatTxt`
  (single choke point `format → formatPrecision → formatTxt`; covers Intl NumberFormatter + legacy fallback paths).
  Overrides caller-supplied precision (D-3 = Có). Skips `adminhtml` area (D-4 default = không áp backend; email/PDF
  vẫn áp vì render trong frontend emulation). Display-only — không đụng numeric value/tax/discount/`convertAndRound`/payment payload.
- `Plugin\JsPriceFormat` — after-plugin on `Magento\Framework\Locale\Format::getPriceFormat`:
  set `precision` + `requiredPrecision` cùng lúc → `checkoutConfig.priceFormat`/`priceUtils` (Luma fallback + Mageplaza OSC) đồng bộ với server.
- Hyvä wrapper: `Block\CurrencyPrecision` + template load **sau** `head.hyva-scripts` (vendor `hyva.phtml`),
  wrap `hyva.formatPrice` với `minimumFractionDigits = maximumFractionDigits = N`; Auto → không render script gì cả.
- Unit test `Test/Unit/Model/PrecisionResolverTest.php` (parse/match/fallback/memoization), chạy với `dev/tests/unit/phpunit-secomm.xml`.
- i18n `vi_VN.csv` + `en_US.csv` cho label admin config.

### Fixed (trong lúc dev — FQCN/property theo vendor, không theo trí nhớ)

- Property promoted `$resolver` trùng tên property non-readonly của parent `Magento\Framework\View\Element\Template` → fatal "Cannot redeclare … as readonly" — đổi thành `$precisionResolver`.
- Import sai `Magento\Framework\Exception\State\StateException` (namespace `State\` không tồn tại) **và** sai loại exception: `AppState::getAreaCode()` thật ra throw `Magento\Framework\Exception\LocalizedException` (vendor `App/State.php:149`) — nếu area chưa set (CLI/setup), exception cũ bay qua catch và vỡ request. Fix cả 2 plugin catch `LocalizedException`. Quét lại toàn bộ FQCN còn lại trong module so vendor: `Area::AREA_ADMINHTML` ✓, `Format::getPriceFormat($locale, $currency)` ✓, `AbstractModel::beforeSave` ✓, `Store::getCurrentCurrencyCode` ✓.
