<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscUltimate\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\OscUltimate\Helper\Data as OscHelper;
use Mageplaza\Osc\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class DefaultConfigProvider
 * @package Mageplaza\OscUltimate\Model
 */
class DefaultConfigProvider implements ConfigProviderInterface
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @var OscHelper
     */
    protected $oscHelper;

    /**
     * DefaultConfigProvider constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param ModuleManager $moduleManager
     * @param OscHelper $oscHelper
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ModuleManager $moduleManager,
        OscHelper $oscHelper,
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->moduleManager   = $moduleManager;
        $this->oscHelper      = $oscHelper;

    }

    /**
     * @return array|array[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        if (!$this->oscHelper->isOscPage()) {
            return [];
        }
        $data        = [];
        $quote       = $this->checkoutSession->getQuote();
        $storeId     = $quote->getStoreId();
        $pageLayout  = $this->oscHelper->getConfigValue(Data::CONFIG_DISPLAY_PAGE_LAYOUT, $storeId);
        $manageBlock = OscHelper::jsonDecode((string) $this->oscHelper->getConfigValue(Data::SORTED_BLOCK_POSITION,
            $storeId, ScopeInterface::SCOPE_STORE));
        if ($manageBlock && $pageLayout !== $manageBlock['layout']) {
            $manageBlock = null;
            $websiteId   = $quote->getStore()->getWebsiteId();
            $manageBlock = OscHelper::jsonDecode((string) $this->oscHelper->getConfigValue(Data::SORTED_BLOCK_POSITION,
                $websiteId, ScopeInterface::SCOPE_WEBSITE));
            if ($manageBlock && $pageLayout !== $manageBlock['layout']) {
                $manageBlock = null;
                $manageBlock = OscHelper::jsonDecode((string) $this->oscHelper->getConfigValue(Data::SORTED_BLOCK_POSITION));
            }
            $data = $this->getDefaultValue($pageLayout);
        }elseif ($pageLayout && $manageBlock) {
            if ($manageBlock['layout'] !== $pageLayout) {
                foreach ($manageBlock['data'] as $value) {
                    $data[$value[0]] = $value[1];
                }
            } elseif ($manageBlock['layout'] === '2columns' || $manageBlock['layout'] === '3columns') {
                foreach ($manageBlock['data'] as $value) {
                    $data[$value[0]] = $value[1];
                }
            } else {
                $data = $manageBlock['data'];
            }
        }else{
            $data = $this->getDefaultValue($pageLayout);
        }

        $output = [
            'oscUltimateConfig' => $data
        ];

        return $output;
    }

    /**
     * @param $pageLayout
     *
     * @return array|array[]|string[]
     */
    public function getDefaultValue($pageLayout)
    {
        $defaultValue = [];
        $data         = [];
        switch ($pageLayout) {
            case '1column':
            case '3columns-colspan':
                $defaultValue = $this->oscHelper->sortedFields;
                break;
            case '2columns':
                $defaultValue = $this->oscHelper->sortedFields_2columns;
                break;
            case '2columns-floating':
                $defaultValue = $this->oscHelper->sortedFields_2columns_floating;
                break;
            case '3columns':
                $defaultValue = $this->oscHelper->sortedFields_3columns;
                break;
        }
        if ($pageLayout === '2columns' || $pageLayout === '3columns') {
            foreach ($defaultValue as $value) {
                $data[$value[0]] = $value[1];
            }
        } else {
            $data = $defaultValue;
        }

        return $data;

    }

}
