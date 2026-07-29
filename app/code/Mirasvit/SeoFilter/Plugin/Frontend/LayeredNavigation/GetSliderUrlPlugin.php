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

use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Service\FriendlyUrlService;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Mirasvit\LayeredNavigation\Service\SliderService;

/**
 * @see \Mirasvit\LayeredNavigation\Service\SliderService::getSliderUrl()
 */
class GetSliderUrlPlugin
{
    private $config;

    private $friendlyUrlService;

    public function __construct(
        FriendlyUrlService $friendlyUrlService,
        ConfigProvider $config
    ) {
        $this->friendlyUrlService = $friendlyUrlService;
        $this->config             = $config;
    }

    /**
     * @param SliderService  $subject
     * @param string $result
     *
     * @return string
     */
    public function afterGetSliderUrl($subject, $result, FilterInterface $filter, string $template)
    {
        if (!$this->config->isApplicable()) {
            return $result;
        }

        return $this->friendlyUrlService->getSliderUrl($filter, $template);
    }
}
