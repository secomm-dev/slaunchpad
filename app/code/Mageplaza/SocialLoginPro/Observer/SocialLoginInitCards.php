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
 * @category  Mageplaza
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mageplaza\SocialLoginPro\Block\Adminhtml\Report\OtherConnections;
use Mageplaza\SocialLoginPro\Block\Adminhtml\Report\SocialLoginChart;
use Mageplaza\SocialLoginPro\Block\Adminhtml\Report\TopSocialLogin;

/**
 * Class SocialLoginInitCards
 *
 * @package Mageplaza\SocialLoginPro\Observer
 */
class SocialLoginInitCards implements ObserverInterface
{
    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $cards = $observer->getCards();
        $cards->addData(
            [
                'socialLoginTopSocialLogin' => TopSocialLogin::class
            ]
        );
        $cards->addData(
            [
                'socialLoginChart' => SocialLoginChart::class
            ]
        );
    }
}
