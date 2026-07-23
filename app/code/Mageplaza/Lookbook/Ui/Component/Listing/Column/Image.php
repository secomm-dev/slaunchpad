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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Ui\Component\Listing\Column;

use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Mageplaza\Lookbook\Helper\Data;

/**
 * Class Image
 * @package Mageplaza\Lookbook\Ui\Component\Listing\Column
 */
class Image extends Column
{
    /**
     * @var Data
     */
    protected $helperData;
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * Image constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Data $helperData
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Data $helperData,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->helperData = $helperData;
        $this->urlBuilder = $urlBuilder;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            $path = $this->helperData->getBaseImageUrl();
            foreach ($dataSource['data']['items'] as & $item) {
                $lookbook = new DataObject($item);
                if (isset($item['image']) && $item['image']) {
                    $item[$fieldName . '_src'] = $path . $item['image'];
                }

                $item[$fieldName . '_link'] = $this->urlBuilder->getUrl(
                    'mplookbook/lookbook/edit',
                    ['lookbook_id' => $lookbook->getLookbookId(), 'store' => $this->context->getRequestParam('store')]
                );
            }
        }

        return $dataSource;
    }
}
