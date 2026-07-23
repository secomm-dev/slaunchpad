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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Ui\Component\Listing\Column;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\Store;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class Price
 * @package Mageplaza\AbandonedCart\Ui\Component\Listing\Column
 */
class Price extends Column
{
    /**
     * @var CurrencyFactory
     */
    private $currencyFactory;

    /**
     * @var Store
     */
    private $storeManager;

    /**
     * Price constructor.
     *
     * @param ContextInterface   $context
     * @param UiComponentFactory $uiComponentFactory
     * @param CurrencyFactory    $currencyFactory
     * @param Store              $store
     * @param array              $components
     * @param array              $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        CurrencyFactory $currencyFactory,
        Store $store,
        array $components = [],
        array $data = []
    ) {
        $this->currencyFactory = $currencyFactory;
        $this->storeManager = $store;
        $this->uiComponentFactory = $uiComponentFactory;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     * @throws LocalizedException
     */
    public function prepareDataSource(array $dataSource)
    {
        $storeId = $this->storeManager->getStoreId();
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $currencyCode = isset($item['base_currency_code'])
                    ? $item['base_currency_code']
                    : $this->getBaseCurrencyCode($storeId);

                $basePurchaseCurrency = $this->currencyFactory->create()->load($currencyCode);
                $item[$this->getData('name')] = $basePurchaseCurrency
                    ->format($item[$this->getData('name')], [], false);
            }
        }

        return $dataSource;
    }

    /**
     * @param $storeId
     * @return string|null
     * @throws LocalizedException
     */
    protected function getBaseCurrencyCode($storeId)
    {
        return $this->storeManager->load($storeId)->getBaseCurrencyCode();
    }
}
