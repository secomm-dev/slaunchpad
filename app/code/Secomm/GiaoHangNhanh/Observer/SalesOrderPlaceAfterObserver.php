<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Observer;

use Secomm\GiaoHangNhanh\Model\Config;
use Secomm\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Psr\Log\LoggerInterface;
use Secomm\GiaoHangNhanh\Helper\Data as GHNHelperData;

/**
 * Class SalesOrderPlaceAfterObserver
 *
 * @package Secomm\GiaoHangNhanh\Observer
 */
class SalesOrderPlaceAfterObserver implements ObserverInterface
{
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;
    protected $ghnHelperData;

    /**
     * SalesOrderAfterSaveObserver constructor.
     * @param QuoteRepository $quoteRepository
     * @param LoggerInterface $logger
     * @param CommandPoolInterface $commandPool
     */
    public function __construct(
        QuoteRepository $quoteRepository,
        LoggerInterface $logger,
        CommandPoolInterface $commandPool,
        GHNHelperData $ghnHelperData
    ) {
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->commandPool = $commandPool;
        $this->ghnHelperData = $ghnHelperData;
    }

    /**
     * @param Observer $observer
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (false !== strpos($order->getShippingMethod(), Config::GHN_CODE)) {
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $shippingAddress = $quote->getShippingAddress();
            try {
                $this->commandPool->get('synchronize_order')->execute([
                    'order' => $order,
                    'district' => $shippingAddress->getDistrict(),
                    'shipping_service_id' => $shippingAddress->getShippingServiceId(),
                    'shipping_service_type_id' => $shippingAddress->getShippingServiceTypeId(),
                    'is_order_payment_cod' => $this->ghnHelperData->isOrderPaymentCod($order)
                ]);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
                throw new Exception('This shipping method isn\'t valid now. Please select another shipping method.');
            }
        }
    }
}
