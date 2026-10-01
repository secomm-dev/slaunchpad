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

namespace Mageplaza\RMA\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;

/**
 * Class Request
 * @package Mageplaza\RMA\Controller\Adminhtml
 */
abstract class Request extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * Request model factory
     *
     * @var RequestFactory
     */
    public $requestFactory;

    /**
     * @var Registry
     */
    public $coreRegistry;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * Request constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        RequestFactory $requestFactory,
        RequestResource $requestResource
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;

        parent::__construct($context);
    }

    /**
     * @param bool $register
     *
     * @return bool|\Mageplaza\RMA\Model\Request
     */
    protected function initRequest($register = false)
    {
        $requestId = (int)$this->getRequest()->getParam('id');

        /** @var \Mageplaza\RMA\Model\Request $request */
        $request = $this->requestFactory->create();

        if ($requestId) {
            $this->_requestResource->load($request, $requestId);
            if (!$request->getId()) {
                $this->messageManager->addErrorMessage(__('This request no longer exists.'));

                return false;
            }
        }
        if ($register) {
            $this->coreRegistry->register('mageplaza_rma_request', $request);
        }

        return $request;
    }
}
