<?php
/**
 * Secomm_VietNamMarket — allow `en_VN` to pass the static-resource locale validator.
 */

declare(strict_types=1);

namespace Secomm\VietNamMarket\Plugin\Locale;

use Magento\Framework\Validator\Locale as LocaleValidator;

/**
 * `Magento\Framework\App\StaticResource::launch()` validates the locale of every
 * static-asset request via `Magento\Framework\Validator\Locale::isValid()`, which
 * checks `Magento\Framework\Setup\Lists::getLocaleList()` = ICU locales ∩ allowed.
 * `en_VN` is not an ICU locale, so `isValid('en_VN')` returns false → static requests
 * for `en_VN` are rejected ("Requested path ... is wrong") and served as text/plain
 * (browser blocks them: MIME text/plain + X-Content-Type-Options nosniff).
 *
 * This after-plugin forces `en_VN` to validate as a valid locale for static serving,
 * so on-demand materialization proceeds and assets are served with correct MIME types.
 */
class ValidateEnVnLocale
{
    private const EN_VN = 'en_VN';

    /**
     * Force `en_VN` to pass locale validation for static resource serving.
     *
     * @param LocaleValidator $subject
     * @param bool $result
     * @param mixed $localeCode
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsValid(LocaleValidator $subject, $result, $localeCode)
    {
        return $localeCode === self::EN_VN ? true : $result;
    }
}
