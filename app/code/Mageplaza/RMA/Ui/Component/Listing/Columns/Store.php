<?php /** @noinspection ALL */

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

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Ui\Component\Listing\Columns\Column;
use Mageplaza\RMA\Helper\Data as HelperData;

/**
 * Class Store
 * @package Mageplaza\RMA\Ui\Component\Listing\Columns
 */
class Store extends Column
{
    /**
     * @var StoreManager
     */
    protected $_storeManager;

    /**
     * @var string
     */
    protected $_storeKey;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param StoreManager $storeManager
     * @param HelperData $helperData
     * @param array $components
     * @param array $data
     * @param string $storeKey
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManager $storeManager,
        HelperData $helperData,
        array $components = [],
        array $data = [],
        $storeKey = 'store_id'
    ) {
        $this->_storeKey = $storeKey;
        $this->_storeManager = $storeManager;
        $this->_helperData = $helperData;

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
            foreach ($dataSource['data']['items'] as & $item) {
                $itemName = explode(',', $item[$this->getData('name')]);
                $item[$this->getData('name')] = $itemName;
                $item[$this->getData('name')] = $this->prepareItem($item);
            }
        }

        return $dataSource;
    }

    /**
     * Get data
     *
     * @param array $item
     *
     * @return string
     */
    protected function prepareItem($item)
    {
        $origStores = $item[$this->_storeKey];
        if (!is_array($origStores)) {
            $origStores = [$origStores];
        }
        if (in_array('0', $origStores, true)) {
            return __('All Store Views');
        }

        return $this->_helperData->getStoresStructureHtml($origStores);
    }

    /**
     * Prepare component configuration
     *
     * @return void
     */
    public function prepare()
    {
        parent::prepare();
        if ($this->_storeManager->isSingleStoreMode()) {
            $this->_data['config']['componentDisabled'] = true;
        }
    }
}
