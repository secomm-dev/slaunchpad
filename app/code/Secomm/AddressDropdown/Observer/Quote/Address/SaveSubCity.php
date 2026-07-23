<?php
/**
 * Copyright © Secomm DevTeam All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Observer\Quote\Address;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

class SaveSubCity implements \Magento\Framework\Event\ObserverInterface
{
    public function __construct(
        protected State $state
    )
    {
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    )
    {

        if ($this->state->getAreaCode() == 'frontend' || $this->state->getAreaCode() == 'webapi_rest') {
            $address = $observer->getQuoteAddress();
            $extAttributes = $address->getExtensionAttributes();

            if ($extAttributes->getSubCity()) {
                $address->setData('sub_city', $extAttributes->getSubCity());
            }
        }
    }
}
