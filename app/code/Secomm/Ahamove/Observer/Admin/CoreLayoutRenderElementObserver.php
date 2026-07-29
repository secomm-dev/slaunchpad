<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Observer\Admin;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Secomm\Ahamove\Helper\Data;

/**
 * Observes the `core_layout_render_element` event.
 */
class CoreLayoutRenderElementObserver implements ObserverInterface
{
    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var Data
     */
    private $configHelper;

    /**
     * @param LayoutInterface $layout
     * @param Data $configHelper
     */
    public function __construct(
        LayoutInterface $layout,
        Data $configHelper,
    ) {
        $this->layout = $layout;
        $this->configHelper = $configHelper;
    }

    /**
     * Execute observer
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        if ($observer->getElementName() == 'order_shipping_view') {

        }
    }
}
