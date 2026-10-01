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

namespace Mageplaza\RMA\Block\Link\Policy;

use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\System\Policy\Location;

/**
 * Class Footer
 * @package Mageplaza\Faqs\Block\Html
 */
class Footer extends Current
{
    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Footer constructor.
     *
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        HelperData $helperData,
        array $data = []
    ) {
        $this->_helperData = $helperData;

        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->_helperData->getPolicyLink(Location::FOOTER_LINK)) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * @return string
     */
    public function getHref()
    {
        return $this->_helperData->getPolicyLink(Location::FOOTER_LINK);
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->_helperData->getConfigGeneral('policy');
    }
}
