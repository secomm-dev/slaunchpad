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

use Mirasvit\LayeredNavigation\Block\Renderer\SliderRenderer;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\LayeredNavigation\Block\Renderer\AbstractRenderer;
use Mirasvit\SeoFilter\Service\FriendlyUrlService;

/**
 * @see AbstractRenderer::getSeparator()
 */
class GetAttributeClearUrlPlugin
{
    private $config;

    private $friendlyUrlService;

    public function __construct(
        ConfigProvider $config,
        FriendlyUrlService $friendlyUrlService
    ) {
        $this->config             = $config;
        $this->friendlyUrlService = $friendlyUrlService;
    }

    public function afterGetAttributeClearUrl(AbstractRenderer $subject, string $result, string $attributeCode)
    {
        if ($this->config->isApplicable()) {
            return $this->friendlyUrlService->getUrl($attributeCode, 'all', true);
        }

        return $result;

    }
}
