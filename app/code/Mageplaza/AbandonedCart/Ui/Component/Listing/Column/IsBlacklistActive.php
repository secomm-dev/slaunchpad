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

use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class IsBlacklistActive
 * @package Mageplaza\AbandonedCart\Ui\Component\Listing\Column
 */
class IsBlacklistActive extends Column implements OptionSourceInterface
{
    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * IsBlacklistActive constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param CustomerFactory $customerFactory
     * @param Data $helperData
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        CustomerFactory $customerFactory,
        Data $helperData,
        array $components = [],
        array $data = []
    ) {
        $this->customerFactory = $customerFactory;
        $this->helperData      = $helperData;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item['mp_ace_blacklist'] = isset($item['mp_ace_blacklist']) ?
                    ($item['mp_ace_blacklist'] ? __('Active') : __('Inactive')) : '';
            }
        }

        return $dataSource;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['label' => __('Active'), 'value' => 1],
            ['label' => __('Inactive'), 'value' => 0]
        ];
    }
}
