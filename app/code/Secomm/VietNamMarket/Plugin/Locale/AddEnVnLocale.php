<?php
/**
 * Secomm_VietNamMarket — inject `en_VN` (English - Vietnam) into the locale dropdown.
 */

declare(strict_types=1);

namespace Secomm\VietNamMarket\Plugin\Locale;

use Magento\Framework\Locale\TranslatedLists;

/**
 * Adds the `en_VN` locale to the admin locale dropdown options.
 *
 * Magento's `Magento\Framework\Locale\TranslatedLists::_getOptionLocales()` iterates
 * `\ResourceBundle::getLocales('')` (the ICU locale list). ICU (e.g. 70.1) does NOT ship
 * `en_VN`, so the locale never appears in Stores → Configuration → Locale Options even
 * though it is registered as an allowed locale. This plugin appends `en_VN` to the option
 * lists so admins can select it per store-view (then `Secomm_VietNamAddress/i18n/en_VN.csv`
 * provides the VN-English address labels for that store-view).
 */
class AddEnVnLocale
{
    private const EN_VN = 'en_VN';
    private const LABEL = 'English (Vietnam)';

    /**
     * Append `en_VN` to the non-translated locale option list.
     *
     * @param TranslatedLists $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetOptionLocales(TranslatedLists $subject, array $result): array
    {
        return $this->addEnVn($result);
    }

    /**
     * Append `en_VN` to the translated locale option list.
     *
     * @param TranslatedLists $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetTranslatedOptionLocales(TranslatedLists $subject, array $result): array
    {
        return $this->addEnVn($result);
    }

    /**
     * Append `en_VN` if it is not already present (idempotent).
     *
     * @param array $result
     * @return array
     */
    private function addEnVn(array $result): array
    {
        foreach ($result as $option) {
            if (($option['value'] ?? null) === self::EN_VN) {
                return $result;
            }
        }
        $result[] = ['value' => self::EN_VN, 'label' => self::LABEL];
        return $result;
    }
}
