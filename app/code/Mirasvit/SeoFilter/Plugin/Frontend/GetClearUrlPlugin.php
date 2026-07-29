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

namespace Mirasvit\SeoFilter\Plugin\Frontend;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\LayeredNavigation\Block\Navigation\State;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Model\Context;
use Mirasvit\SeoFilter\Service\FriendlyUrlService;

/**
 * @see \Magento\LayeredNavigation\Block\Navigation\State::getClearUrl()
 */
class GetClearUrlPlugin
{
    private $config;

    private $friendlyUrlService;

    public function __construct(
        FriendlyUrlService $friendlyUrlService,
        CategoryRepositoryInterface $categoryRepository,
        ConfigProvider $config,
        Context $context
    ) {
        $this->friendlyUrlService = $friendlyUrlService;
        $this->config             = $config;
    }

    /**
     * @param State  $subject
     * @param string $result
     *
     * @return string
     */
    public function afterGetClearUrl($subject, $result)
    {
        if (!$this->config->isApplicable()) {
            return $result;
        }

        return $this->friendlyUrlService->getClearUrl();
    }
}
