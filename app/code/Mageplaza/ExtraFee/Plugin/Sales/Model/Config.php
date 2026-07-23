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

namespace Mageplaza\ExtraFee\Plugin\Sales\Model;

use Closure;
use Mageplaza\ExtraFee\Block\Adminhtml\Totals\ExtraFee;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class Config
 * @package Mageplaza\ExtraFee\Plugin\Sales\Model
 */
class Config
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * Config constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Sales\Model\Config $subject
     * @param Closure $proceed
     * @param $section
     * @param $group
     * @param $code
     *
     * @return mixed|string
     */
    public function aroundGetTotalsRenderer(
        \Magento\Sales\Model\Config $subject,
        Closure $proceed,
        $section,
        $group,
        $code
    ) {
        if ($this->helper->isEnabled() && strpos($code, 'mp_extra_fee') !== false) {
            $result = ExtraFee::class;
        } else {
            $result = $proceed($section, $group, $code);
        }

        return $result;
    }
}
