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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Ui\Component\Listing\Columns;

use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class CustomerGroup
 * @package Mageplaza\RMA\Ui\Component\Listing\Columns
 */
class CustomerGroup extends Column
{
    /**
     * @var GroupCollection
     */
    protected $customerGroup;

    /**
     * CustomerGroup constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param GroupCollection $customerGroup
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        GroupCollection $customerGroup,
        array $components = [],
        array $data = []
    ) {
        $this->customerGroup = $customerGroup;
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
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
            /** @var array[][] $dataSource */
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getData('name')] = $this->_prepareItem($item);
            }
        }

        return $dataSource;
    }

    /**
     * @param array $item
     *
     * @return string
     */
    protected function _prepareItem(array $item)
    {
        $content = [];
        $origGroup = explode(',', $item['customer_group']);
        $customer = $this->customerGroup->toOptionArray();

        foreach ($origGroup as $key) {
            if (!isset($customer[$key])) {
                continue;
            }

            $content[] = $customer[$key]['label'];
        }

        return implode(', ', $content);
    }
}
