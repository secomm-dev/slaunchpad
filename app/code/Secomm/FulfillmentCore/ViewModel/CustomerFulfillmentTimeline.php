<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\FulfillmentCore\Model\FulfillmentState;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\CollectionFactory as StateCollectionFactory;

/**
 * Customer order-view fulfillment timeline data (Hyvä / phtml).
 */
class CustomerFulfillmentTimeline implements ArgumentInterface
{
    private ?FulfillmentState $state = null;
    private bool $stateLoaded = false;
    private ?int $orderId = null;

    public function __construct(
        private readonly StateCollectionFactory $stateCollectionFactory
    ) {
    }

    /**
     * Bind Magento order entity id for subsequent getters.
     *
     * @param int $orderId sales_order.entity_id
     */
    public function setOrderId(int $orderId): self
    {
        if ($this->orderId !== $orderId) {
            $this->orderId = $orderId;
            $this->state = null;
            $this->stateLoaded = false;
        }

        return $this;
    }

    /**
     * Whether a fulfillment state row exists for the order.
     */
    public function hasState(): bool
    {
        return $this->getState() !== null;
    }

    /**
     * @return string[] Customer-visible milestone codes in display order
     */
    public function getSteps(): array
    {
        return NormalizedFulfillmentStatus::customerVisible();
    }

    public function getCurrentStatus(): ?string
    {
        $state = $this->getState();
        return $state?->getNormalizedStatus();
    }

    /**
     * Whether the given step is reached (rank <= current).
     *
     * @param string $step NormalizedFulfillmentStatus constant
     */
    public function isStepReached(string $step): bool
    {
        $current = $this->getCurrentStatus();
        if ($current === null) {
            return false;
        }

        return NormalizedFulfillmentStatus::rank($step)
            <= NormalizedFulfillmentStatus::rank($current);
    }

    /**
     * Whether the given step is the current active milestone.
     *
     * @param string $step NormalizedFulfillmentStatus constant
     */
    public function isCurrentStep(string $step): bool
    {
        return $this->getCurrentStatus() === $step;
    }

    public function getCarrierName(): ?string
    {
        return $this->getState()?->getCarrierName();
    }

    public function getTrackingNumber(): ?string
    {
        return $this->getState()?->getTrackingNumber();
    }

    public function getTrackingUrl(): ?string
    {
        return $this->getState()?->getTrackingUrl();
    }

    private function getState(): ?FulfillmentState
    {
        if ($this->stateLoaded) {
            return $this->state;
        }

        $this->stateLoaded = true;
        if ($this->orderId === null || $this->orderId <= 0) {
            return null;
        }

        $collection = $this->stateCollectionFactory->create();
        $collection->addFieldToFilter('magento_order_id', $this->orderId);
        $collection->setPageSize(1);

        /** @var FulfillmentState $item */
        $item = $collection->getFirstItem();
        $this->state = $item->getEntityId() ? $item : null;

        return $this->state;
    }
}
