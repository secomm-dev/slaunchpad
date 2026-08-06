<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Block\Adminhtml\Carrier\Standard\Form\Field;

class Export extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    protected $backendUrl;

    /**
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Backend\Model\UrlInterface $backendUrl
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->backendUrl = $backendUrl;
    }

    /**
     * @return string
     */
    public function getElementHtml()
    {
        /** @var \Magento\Backend\Block\Widget\Button $buttonBlock  */
        $buttonBlock = $this->getForm()->getParent()->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        );

        $params = ['website' => $buttonBlock->getRequest()->getParam('website')];

        $url = $this->backendUrl->getUrl("ahamove/system_config/exportStandard", $params);
        $data = [
            'label' => __('Export CSV'),
            'onclick' => "setLocation('" .
            $url .
            "conditionName/' + $('carriers_ahamove_ahamove_shipping_method_ahamove_standard_condition_name').value + '/ahamove_standard.csv' )",
            'class' => '',
        ];

        $html = $buttonBlock->setData($data)->toHtml();
        return $html;
    }
}
