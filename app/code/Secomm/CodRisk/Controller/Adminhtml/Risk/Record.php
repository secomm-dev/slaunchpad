<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Controller\Adminhtml\Risk;

use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\CodRisk\Api\RiskEventRecorderInterface;
use Secomm\CodRisk\Model\Data\RiskEvent;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Manual risk event entry from Order View (mockup Flow A / D-07). The event only
 * adds to the historical count — it never sets a decision directly (D-08).
 */
class Record extends AbstractOrderAction implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        OrderRepositoryInterface $orderRepository,
        private readonly RiskEventRecorderInterface $recorder,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {
        parent::__construct($context, $orderRepository);
    }

    public function execute()
    {
        $redirect = $this->getOrderRedirect();

        if (!$this->isPost()) {
            return $redirect;
        }

        $order = $this->orderRepository->get((int)$this->getRequest()->getParam('order_id'));
        $reason = (string)$this->getRequest()->getParam('reason');
        $source = strtoupper((string)$this->getRequest()->getParam('source', 'ADMIN'));
        $note = (string)$this->getRequest()->getParam('note');

        if (!in_array($source, [RiskEvent::SOURCE_ADMIN, RiskEvent::SOURCE_CUSTOMER], true)) {
            $this->messageManager->addErrorMessage(__('Event source must be ADMIN or CUSTOMER.'));

            return $redirect;
        }

        $phone = (string)$order->getShippingAddress()?->getTelephone();
        $normalized = $this->phoneNormalizer->normalize($phone);
        if ($normalized === null) {
            $this->messageManager->addErrorMessage(__('Order shipping phone is not a valid Vietnamese number.'));

            return $redirect;
        }

        $this->recorder->record(new RiskEvent(
            $normalized,
            $reason,
            $source,
            (int)$order->getStore()->getWebsiteId(),
            (int)$order->getEntityId(),
            $order->getQuoteId() !== null ? (int)$order->getQuoteId() : null,
            $order->getCustomerId() !== null ? (int)$order->getCustomerId() : null,
            $note
        ));

        $this->messageManager->addSuccessMessage(
            __('Risk event recorded. It adds to the historical count — decisions re-derive from thresholds automatically.')
        );

        return $redirect;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Secomm_CodRisk::manage');
    }
}
