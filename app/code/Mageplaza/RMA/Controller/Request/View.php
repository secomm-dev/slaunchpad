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

namespace Mageplaza\RMA\Controller\Request;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Helper\Data;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;

/**
 * Class View
 * @package Mageplaza\RMA\Controller\Request
 */
class View extends Action
{
    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var ForwardFactory
     */
    protected $_resultForwardFactory;

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var Escaper
     */
    protected $_escaper;

    /**
     * @var RequestFactory
     */
    protected $_requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * @var Data
     */
    protected $_helperData;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param Registry $coreRegistry
     * @param EncryptorInterface $encryptor
     * @param Escaper $escaper
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        Registry $coreRegistry,
        EncryptorInterface $encryptor,
        Escaper $escaper,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        Data $helperData
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultForwardFactory = $resultForwardFactory;
        $this->_coreRegistry = $coreRegistry;
        $this->_encryptor = $encryptor;
        $this->_escaper = $escaper;
        $this->_requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->_helperData->isEnabled()) {
            return $this->_redirect('noroute');
        }
        if ($requestId = $this->_request->getParam('request_id')) {
            /** @var Request $request */
            $request = $this->_requestFactory->create();
            try {
                $this->_requestResource->load($request, $requestId);
                if (!$this->isAllowViewRequest($request)) {
                    $this->messageManager->addErrorMessage(__('This request not found'));

                    return $this->resultRedirectFactory->create()->setPath('mprma/request/form');
                }
                $this->_coreRegistry->register('mageplaza_current_rma_request', $request);
                /** @var Page $resultPage */
                $resultPage = $this->_resultPageFactory->create();

                return $resultPage;
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $this->resultRedirectFactory->create()->setPath('mprma/request/form');
            }
        }

        return $this->_resultForwardFactory->create()->forward('noroute');
    }

    /**
     * @param Request $request
     *
     * @return bool
     * @throws Exception
     */
    public function isAllowViewRequest($request)
    {
        $isAllow = false;
        if ($request->getId()) {
            if ($this->_helperData->isLoggedIn()
                && $request->getOrder()->getCustomerId() === $this->_helperData->getCustomerId()) {
                $isAllow = true;
            }
            $protectKey = $this->_request->getParam('guest_key', '');
            $key = $request->getId() . '_' . strtotime($request->getUpdatedAt());
            if (!$this->_helperData->isLoggedIn() && $this->_encryptor->validateHash($key, $protectKey)) {
                $isAllow = true;
            }
        }

        return $isAllow;
    }
}
