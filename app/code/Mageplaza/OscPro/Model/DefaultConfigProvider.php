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
 * @package     Mageplaza_OscPro
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscPro\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mageplaza\OscPro\Helper\Data as OscHelper;

/**
 * Class DefaultConfigProvider
 * @package Mageplaza\OscPro\Model
 */
class DefaultConfigProvider implements ConfigProviderInterface
{

    /**
     * @var OscHelper
     */
    protected $oscHelper;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * DefaultConfigProvider constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param OscHelper $oscHelper
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        OscHelper $oscHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->oscHelper = $oscHelper;
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        if (!$this->oscHelper->isOscPage()) {
            return [];
        }
        $quote       = $this->checkoutSession->getQuote();
        $storeId     = $quote->getStoreId();
        $output = [
            'oscProConfig' => $this->oscHelper->getLoadingSpeedConfig('',$storeId )
        ];

        return $output;
    }

}
