<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleTagManagerPlus\Plugin\Magefan\GoogleTagManager\Model\DataLayer\Product;

use Magefan\GoogleTagManager\Api\DataLayer\Product\ItemInterface as Subject;
use Magento\Catalog\Model\Product;
use Magefan\GoogleTagManagerPlus\Model\Config;
use Magento\Customer\Model\Session as CustomerSession;

class ItemPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     */
    public function __construct(
        Config $config,
        CustomerSession $customerSession
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
    }

    /**
     * Extend product item data layer with additional attributes.
     *
     * @param Subject $subject
     * @param array $result
     * @param Product $product
     * @return array
     */
    public function afterGet(
        Subject $subject,
        array $result,
        Product $product
    ): array {
        $allowedGroups = $this->config->getDisplayProductPriceForGroups();

        if (empty($allowedGroups)) {
            return $result;
        }

        $customerGroupId = (string)$this->customerSession->getCustomerGroupId();

        if (!array_intersect([$customerGroupId, 'all'], $allowedGroups)) {
            unset($result['price']);
        }

        return $result;
    }
}