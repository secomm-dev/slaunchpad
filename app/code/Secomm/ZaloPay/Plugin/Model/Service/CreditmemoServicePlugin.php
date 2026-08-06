<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Model\Service;

use Exception;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\CreditmemoCommentRepositoryInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoNotifier;
use Magento\Sales\Model\Order\RefundAdapterInterface;
use Magento\Sales\Model\Service\CreditmemoService;

/**
 * Plugin to handle refund processing for ZaloPay
 */
class CreditmemoServicePlugin
{
    /**
     * Constructor
     *
     * @param ResourceConnection $resource
     * @param OrderRepositoryInterface $orderRepository
     * @param RefundAdapterInterface $refundAdapter
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RefundAdapterInterface $refundAdapter,
        private readonly InvoiceRepositoryInterface $invoiceRepository
    ) {
    }

    /**
     * Around plugin to process refund
     *
     * @param CreditmemoService $subject
     * @param callable $proceed
     * @param CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return CreditmemoInterface
     * @throws LocalizedException
     */
    public function aroundRefund(
        CreditmemoService $subject,
        callable $proceed,
        CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ): CreditmemoInterface {
        // Call original refund method
        $result = $proceed($creditmemo, $offlineRequested);

        // Additional processing if needed for ZaloPay
        // (Currently original logic is sufficient)

        return $result;
    }
}
