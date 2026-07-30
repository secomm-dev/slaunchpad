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



namespace Mirasvit\Seo\Model\Config\Source\Alternate;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Option\ArrayInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Config\AlternateConfigInterface as AlternateConfig;

class XDefault implements ArrayInterface
{
    protected $storeManager;

    protected $request;

    public function __construct(
        StoreManagerInterface $storeManager,
        Http                  $request
    ) {
        $this->storeManager = $storeManager;
        $this->request      = $request;
    }

    public function toOptionArray(): array
    {
        $options = [
            ['value' => 0, 'label' => __('Disabled')],
            ['value' => AlternateConfig::X_DEFAULT_AUTOMATICALLY, 'label' => __('Add automatically')],
        ];

        $storeIds = $this->storeManager->getWebsite($this->request->getParam('website'))->getStoreIds();

        foreach ($storeIds as $storeId) {
            $store = $this->storeManager->getStore($storeId);
            if ($store->isActive()) {
                $options[] = [
                    'value' => $store->getId(),
                    'label' => $store->getName() . ' — ' . $store->getBaseUrl()
                ];
            }
        }

        return $options;
    }
}
