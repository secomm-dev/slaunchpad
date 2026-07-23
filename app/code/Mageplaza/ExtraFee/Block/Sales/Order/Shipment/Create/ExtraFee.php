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

namespace Mageplaza\ExtraFee\Block\Sales\Order\Shipment\Create;

use Mageplaza\ExtraFee\Block\Sales\Order\AbstractExtraFee;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Block\Sales\Order\Shipment\Create
 */
class ExtraFee extends AbstractExtraFee
{
    /**
     * @return mixed
     */
    protected function getOrder()
    {
        if (!$this->order) {
            $this->order = $this->registry->registry('current_shipment')->getOrder();
        }

        return $this->order;
    }
}
