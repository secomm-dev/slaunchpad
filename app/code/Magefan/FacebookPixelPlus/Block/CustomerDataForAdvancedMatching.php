<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Block;

use Magefan\FacebookPixelPlus\Model\GetDataForAdvancedMatching;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;

class CustomerDataForAdvancedMatching extends \Magento\Framework\View\Element\Template
{

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var GetDataForAdvancedMatching
     */
    private $getDataForAdvancedMatching;

    /**
     * @param Template\Context $context
     * @param Session $customerSession
     * @param GetDataForAdvancedMatching $getDataForAdvancedMatching
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Session $customerSession,
        GetDataForAdvancedMatching $getDataForAdvancedMatching,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->getDataForAdvancedMatching = $getDataForAdvancedMatching;
        parent::__construct($context, $data);
    }

    /**
     * Convert block to HTML string
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ($this->customerSession->isLoggedIn()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Get customer data for advanced matching
     *
     * @return array
     */
    public function getCustomerData()
    {
        return $this->getDataForAdvancedMatching->execute();
    }
}
