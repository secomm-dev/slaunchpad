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

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\Request\Item;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;

/**
 * Class Items
 * @package Mageplaza\RMA\Ui\Component\Listing\Columns
 */
class Items extends Column
{
    /**
     * @var RequestFactory
     */
    protected $_requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * Items constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        array $components = [],
        array $data = []
    ) {
        $this->_requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;

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
            foreach ($dataSource['data']['items'] as &$item) {
                $item['items'] = $this->_getItemsHtml($item);
            }
        }

        return $dataSource;
    }

    /**
     * @param array $item
     *
     * @return string
     */
    protected function _getItemsHtml($item)
    {
        /** @var Request $request */
        $request = $this->_requestFactory->create();
        $this->_requestResource->load($request, $item['request_id']);
        $itemCollection = $request->getItemsCollection();
        $html = '';
        foreach ($itemCollection as $requestItem) {
            /** @var Item $requestItem */
            $html .= $requestItem->getName() . '<br>';
        }

        return $html;
    }
}
