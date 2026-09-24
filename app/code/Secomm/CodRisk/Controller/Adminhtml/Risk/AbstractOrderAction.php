<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Controller\Adminhtml\Risk;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\CodRisk\Controller\Adminhtml\AbstractAction;

/**
 * Shared plumbing for Order View risk actions: POST-only, load order, always
 * return to the order view.
 */
abstract class AbstractOrderAction extends AbstractAction
{
    public function __construct(
        Context $context,
        protected readonly OrderRepositoryInterface $orderRepository,
    ) {
        parent::__construct($context);
    }

    protected function getOrderRedirect(): Redirect
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        $redirect = $this->resultRedirectFactory->create();

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }

    protected function isPost(): bool
    {
        return $this->getRequest()->isPost();
    }
}
