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

namespace Mageplaza\RMA\Controller\Adminhtml\Request\File;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Mageplaza\RMA\Helper\Image as HelperImage;

/**
 * Class Download
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\File
 */
class Download extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var HelperImage
     */
    protected $_helperImage;

    /**
     * Download constructor.
     *
     * @param Context $context
     * @param HelperImage $helperImage
     */
    public function __construct(
        Context $context,
        HelperImage $helperImage
    ) {
        $this->_helperImage = $helperImage;

        parent::__construct($context);
    }

    /**
     * execute the action
     *
     * @throws Exception
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();

        return $this->_helperImage->downloadFile($params);
    }
}
