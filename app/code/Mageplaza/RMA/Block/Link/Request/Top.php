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

use Magento\Framework\View\Element\Html\Link;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\RMA\Helper\Data;
use Mageplaza\RMA\Model\Config\Source\System\Request\Location;

/**
 * Class Top
 * @package Mageplaza\RMA\Block\Link
 */
class Top extends Link
{
    /**
     * @var Data
     */
    protected $_helperData;

    /**
     * Top constructor.
     *
     * @param Context $context
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helperData,
        array $data = []
    ) {
        $this->_helperData = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $locations = $this->_helperData->getConfigGeneral('location');
        $locations = array_map('intval', explode(',', $locations));

        if (!in_array(Location::TOP_LINK, $locations, true)
            || (!$this->_helperData->isLoggedIn()
                && !$this->_helperData->getConfigGeneral('enabled_guest'))) {
            return '';
        }

        if ($this->_helperData->checkHyvaTheme()) {
            return $this->getPolicyLinkHtml();
        }

        return parent::_toHtml();
    }

    /**
     * @return string
     */
    protected function getPolicyLinkHtml()
    {
        return '<a class="block px-4 py-2 lg:px-5 lg:py-2 hover:bg-gray-100" ' . $this->getLinkAttributes() . ' >' . $this->escapeHtml($this->getLabel()) . '</a>';
    }
}
