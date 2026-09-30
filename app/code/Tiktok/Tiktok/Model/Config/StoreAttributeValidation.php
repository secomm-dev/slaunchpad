<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config;

use Tiktok\Tiktok\Logger\TiktokLogger;
use Exception;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Attribute Validation Class
 */
class StoreAttributeValidation
{
    /**
     * Locale mapping array
     *
     * @var array|string[]
     */
    private array $localeMapping = [
        'ar_DZ' => 'ar',
        'ar_EG' => 'ar',
        'ar_KW' => 'ar',
        'ar_MA' => 'ar',
        'ar_SA' => 'ar',
        'de_AT' => 'de',
        'de_CH' => 'de',
        'de_DE' => 'de-DE',
        'de_LU' => 'de',
        'en_AU' => 'en',
        'en_CA' => 'en',
        'en_GB' => 'en',
        'en_NZ' => 'en',
        'en_US' => 'en',
        'es_AR' => 'es',
        'es_CO' => 'es',
        'es_PA' => 'es',
        'es_ES' => 'es-ES',
        'es_MX' => 'es',
        'fr_BE' => 'fr',
        'fr_CA' => 'fr',
        'fr_CH' => 'fr',
        'fr_FR' => 'fr-FR',
        'fr_LU' => 'fr',
        'id_ID' => 'id',
        'it_IT' => 'it',
        'it_CH' => 'it',
        'ja_JP' => 'ja-JP',
        'ko_KR' => 'ko-KR',
        'ms_MY' => 'ms-MY',
        'pt_BR' => 'pt-BR',
        'ru_RU' => 'ru-RU',
        'th_TH' => 'th-TH',
        'tr_TR' => 'tr-TR',
        'vi_VN' => 'vi-VN',
        'zh_Hans_CN' => 'zh'
    ];

    /**
     * Approved Currency
     *
     * @var array|string[]
     */
    private array $tiktokApprovedCurrency = [
        'USD',
        'EUR',
        'GBP',
        'JPY',
        'CAD',
        'AUD',
        'CNY',
        'CHF',
        'SEK',
        'NZD'
    ];

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private Resolver $localResolver;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    private TiktokLogger $logger;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\Locale\Resolver $localResolver
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     */
    public function __construct(Resolver $localResolver, StoreManagerInterface $storeManager, TiktokLogger $logger)
    {
        $this->localResolver = $localResolver;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Get store locale and map it to the corresponding locale supported by TikTok.
     *
     * @return string
     */
    public function getStoreLocale(): string
    {
        $magentoLocale = $this->localResolver->getLocale() ?? 'en_US';
        if (!isset($this->localeMapping[$magentoLocale])) {
            $this->logger->info($magentoLocale . ' is not a supported TikTok locale');
        }

        return $this->localeMapping[$magentoLocale] ?? $magentoLocale;
    }

    /**
     * Resolve the currency and throw an exception if it is not in the HTML table.
     *
     * @return string
     * @throws Exception
     */
    public function getCurrency(): string
    {
        $currency = $this->storeManager->getStore()->getBaseCurrencyCode();
        if (!in_array($currency, $this->tiktokApprovedCurrency, true)) {
            $this->logger->info($currency . ' is not currently supported by Tiktok');
        }
        return $currency;
    }
}
