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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Link\Request;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\RMA\Helper\Data;
use Mageplaza\RMA\Model\Config\Source\System\Request\Location;


/**
 * Class Header
 * @package Mageplaza\RMA\Block\Link\Request
 */
class Header extends Top
{
    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * Header constructor.
     *
     * @param Context $context
     * @param Data $helperData
     * @param HttpContext $httpContext
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helperData,
        HttpContext $httpContext,
        array $data = []
    ) {
        $this->httpContext = $httpContext;
        parent::__construct($context, $helperData, $data);
    }


    /**
     * @return string
     */
    protected function _toHtml()
    {
        $locations = $this->_helperData->getConfigGeneral('location');
        $locations = array_map('intval', explode(',', $locations));

        if (!in_array(Location::TOP_LINK, $locations, true)
            || ($this->_helperData->isLoggedIn()
                && $this->_helperData->getConfigGeneral('enabled_guest'))) {
            return '';
        }

        return parent::_toHtml();
    }
}
