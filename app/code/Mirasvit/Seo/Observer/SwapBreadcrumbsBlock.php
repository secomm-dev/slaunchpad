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
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\Seo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\Seo\Block\Catalog\Product\Breadcrumbs;

class SwapBreadcrumbsBlock implements ObserverInterface
{
    private $stateService;

    public function __construct(
        StateServiceInterface $stateService
    ) {
        $this->stateService = $stateService;
    }

    public function execute(Observer $observer)
    {
        $layout = $observer->getLayout();
        if ($this->stateService->isProductPage()) {
            $block = $layout->getBlock('breadcrumbs');
            $blockParentName = $layout->getParentName('breadcrumbs');
            if ($block && $blockParentName) {
                $layout->unsetElement('breadcrumbs');
                $layout->addBlock(Breadcrumbs::class, 'breadcrumbs', $blockParentName);
                $block = $layout->getBlock('breadcrumbs');
                $block->addBreadcrumbs();
            }
        }
    }
}
