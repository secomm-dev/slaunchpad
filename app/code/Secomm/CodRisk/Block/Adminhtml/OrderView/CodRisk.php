<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Block\Adminhtml\OrderView;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\Config;
use Secomm\CodRisk\Model\Source\ReasonCodes;
use Secomm\CodRisk\Model\Service\OrderRiskView;

/**
 * Order View COD Risk section (always rendered for COD orders — D-06 A, D-07).
 *
 * @method array|null getSummaryData()
 */
class CodRisk extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly OrderRiskView $orderRiskView,
        private readonly ReasonCodes $reasonCodes,
        private readonly Config $config,
        private readonly TimezoneInterface $localeDate,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Admin-timezone datetime in the yy-MM-dd HH:mm pattern (Bug 2).
     * Template::formatDate() does not accept a pattern — the stdlib does.
     */
    public function formatRiskDateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->localeDate->formatDateTime(
            $value,
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::SHORT,
            null,
            null,
            'yy-MM-dd HH:mm'
        );
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('sales_order')
            ?? $this->registry->registry('current_order');

        return $order instanceof OrderInterface ? $order : null;
    }

    public function isCodOrder(): bool
    {
        $order = $this->getOrder();

        return $order !== null
            && $order->getPayment() !== null
            && $order->getPayment()->getMethod() === 'cashondelivery';
    }

    public function getSummaryData(): ?array
    {
        $order = $this->getOrder();

        return $order !== null ? $this->orderRiskView->getSummary($order) : null;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getReasonOptions(): array
    {
        return $this->reasonCodes->toOptionArray();
    }

    public function getWarningThreshold(): int
    {
        $order = $this->getOrder();

        return $this->config->getWarningThreshold($order !== null ? (int)$order->getStore()->getWebsiteId() : null);
    }

    public function getBlockThreshold(): int
    {
        $order = $this->getOrder();

        return $this->config->getBlockThreshold($order !== null ? (int)$order->getStore()->getWebsiteId() : null);
    }

    public function isBlockDecision(?CodRiskDecisionInterface $decision): bool
    {
        return $decision !== null && $decision->getDecision() === CodRiskDecisionInterface::BLOCK;
    }

    public function getRecordUrl(int $orderId): string
    {
        return $this->getUrl('codrisk/risk/record', ['order_id' => $orderId]);
    }

    public function getAddToListUrl(int $orderId): string
    {
        return $this->getUrl('codrisk/risk/addlist', ['order_id' => $orderId]);
    }

    public function getOverrideUrl(int $orderId): string
    {
        return $this->getUrl('codrisk/risk/override', ['order_id' => $orderId]);
    }

    public function getDeactivateListUrl(int $listId): string
    {
        return $this->getUrl('codrisk/lists/deactivate', ['id' => $listId, 'active' => 0]);
    }
}
