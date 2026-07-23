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

namespace Mageplaza\AbandonedCart\Block\Adminhtml\Grid\Column\Renderer;

use Magento\Framework\DataObject;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Mageplaza\AbandonedCart\Helper\Data;
/**
 * Class Action
 * @package Mageplaza\AbandonedCart\Block\Adminhtml\Grid\Column\Renderer
 */
class Action extends \Magento\Backend\Block\Widget\Grid\Column\Renderer\Action
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Action constructor.
     *
     * @param \Magento\Backend\Block\Context $context
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param Data $helperData
     * @param array $data
     * @param SecureHtmlRenderer|null $secureHtmlRenderer
     * @param Random|null $random
     */
    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        Data $helperData,
        array $data = [],
        ?SecureHtmlRenderer $secureHtmlRenderer = null,
        ?Random $random = null
    ){
        $this->helperData = $helperData;
        parent::__construct($context, $jsonEncoder,$data,$secureHtmlRenderer,$random);
    }

    /**
     * @param DataObject $row
     *
     * @return string
     */
    public function render(DataObject $row)
    {
        $isResendEmail = $this->helperData->getSendEmailRecoverConfig();
        $actions = [
            [
                'url' => $this->getUrl('abandonedcart/*/preview', ['id' => $row->getId()]),
                'popup' => true,
                'caption' => __('Preview')
            ],
            [
                'url' => $this->getUrl('abandonedcart/*/delete', ['id' => $row->getId()]),
                'caption' => __('Delete')
            ]
        ];
        if(!$isResendEmail)
        {
            if($row->getStatus() !== '2')
            {
                $actions[] = [
                    'url' => $this->getUrl('abandonedcart/*/sentagain', ['id' => $row->getId()]),
                    'caption' => __('Send Again')
                ];
            }
        }else {
            $actions[] = [
                'url' => $this->getUrl('abandonedcart/*/sentagain', ['id' => $row->getId()]),
                'caption' => __('Send Again')
            ];
        }

        $this->getColumn()->setActions($actions);

        return parent::render($row);
    }
}
