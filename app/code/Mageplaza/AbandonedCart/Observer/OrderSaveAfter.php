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

namespace Mageplaza\AbandonedCart\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Sales\Model\Order;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class OrderSaveAfter
 * @package Mageplaza\AbandonedCart\Observer
 */
class OrderSaveAfter implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $_helper;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * @param Data $helper
     * @param SessionManager $sessionManager
     */
    public function __construct(
        Data $helper,
        SessionManager $sessionManager
    ) {
        $this->_helper        = $helper;
        $this->sessionManager = $sessionManager;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if ($this->_helper->isEnabled()) {
            $recoveryToken = $this->sessionManager->getRecoveryToken();
            $recoveryExpiration = $this->sessionManager->getRecoveryExpiration();

            if ($order->getQuoteId() == $recoveryToken && time() < $recoveryExpiration) {
                $this->sessionManager->unsRecoveryToken();
                $this->sessionManager->unsRecoveryExpiration();

                $order->setData('mp_abandoned_return_cart', 1);
                $order->save();
            }
        }

        return $this;
    }
}
