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



namespace Mirasvit\Seo\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Store\Model\StoreManagerInterface;

class CrossDomain implements ArrayInterface
{
    protected $storeManager;

    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '0', 'label' => 'Default store URL']];

        foreach ($this->storeManager->getStores() as $store) {
            $secure = '';
            $secureBaseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB,true);
            if (strpos($secureBaseUrl, 'https://') !== false) {
                $secure = ' [https]';
            }

            $options[] = [
                'value' => $store->getId(),
                'label' => $store->getName() . ' — ' . $store->getBaseUrl() . $secure,
            ];
        }

        return $options;
    }
}
