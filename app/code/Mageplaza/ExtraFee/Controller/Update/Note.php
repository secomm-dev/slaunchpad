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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Controller\Update;

use Magento\Checkout\Model\SessionFactory as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class Note
 * @package Mageplaza\ExtraFee\Controller\Update
 */
class Note extends Action
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * Note constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param Data $helper
     * @param Context $context
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        Data $helper,

        Context $context
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helper          = $helper;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $key             = $this->_request->getParam('key');
        $note            = $this->_request->getParam('note');
        $addressId       = $this->_request->getParam('address_id');
        $checkoutSession = $this->checkoutSession->create();
        $extraFeeNote    = $checkoutSession->getExtraFeeMultiNote() ?: [];

        if (!empty($note)) {
            $extraFeeNote[$addressId][$key] = $note;
        }

        $checkoutSession->setExtraFeeMultiNote($extraFeeNote);
    }
}
