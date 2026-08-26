<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface PaymentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return PaymentInterface[]
     */
    public function getItems(): array;

    /**
     * @param PaymentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
