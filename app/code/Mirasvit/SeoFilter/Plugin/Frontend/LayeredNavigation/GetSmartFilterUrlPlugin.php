<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Plugin\Frontend\LayeredNavigation;

use Mirasvit\SmartFilter\Api\Data\SmartFilterInterface;
use Mirasvit\SmartFilter\Service\UrlService;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Service\FriendlyUrlService;

/**
 * @see UrlService::getUrl()
 */
class GetSmartFilterUrlPlugin
{
    /** @var ConfigProvider */
    private $config;

    /** @var FriendlyUrlService */
    private $friendlyUrlService;

    public function __construct(
        ConfigProvider     $config,
        FriendlyUrlService $friendlyUrlService
    ) {
        $this->config             = $config;
        $this->friendlyUrlService = $friendlyUrlService;
    }

    public function afterGetUrl(UrlService $subject, string $result, SmartFilterInterface $smartFilter, ?int $storeId): string
    {
        if (!$this->config->canProceed()) {
            return $result;
        }

        $baseUrl      = strtok($result, '?');
        $filterParams = [];

        foreach ($smartFilter->getFiltersData() as $filter) {
            $code      = (string)($filter['attribute_code'] ?? '');
            $optionIds = (array)($filter['option_ids'] ?? []);

            if ($code === '' || empty($optionIds)) {
                continue;
            }

            $filterParams[$code] = implode(',', $optionIds);
        }

        $extraParams = [];
        parse_str((string)parse_url($result, PHP_URL_QUERY), $queryParams);
        foreach (['product_list_order', 'product_list_limit', 'product_list_mode'] as $param) {
            if (isset($queryParams[$param])) {
                $extraParams[$param] = $queryParams[$param];
            }
        }

        if (empty($filterParams)) {
            return $baseUrl . ($extraParams ? '?' . http_build_query($extraParams) : '');
        }

        $seoUrl = $this->friendlyUrlService->getUrlWithFilters((string)$baseUrl, $filterParams);

        return $seoUrl . ($extraParams ? (strpos($seoUrl, '?') !== false ? '&' : '?') . http_build_query($extraParams) : '');
    }
}
