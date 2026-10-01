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

namespace Mageplaza\RMA\Controller\Adminhtml\Request;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use RuntimeException;

/**
 * Class InlineEdit
 * @package Mageplaza\RMA\Controller\Adminhtml\Request
 */
class InlineEdit extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var JsonFactory
     */
    public $jsonFactory;

    /**
     * @var RequestFactory
     */
    public $requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * InlineEdit constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        RequestFactory $requestFactory,
        RequestResource $requestResource
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];
        $requestItems = $this->getRequest()->getParam('items', []);
        if (!(!empty($requestItems) && $this->getRequest()->getParam('isAjax'))) {
            return $resultJson->setData([
                'messages' => [__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        $key = array_keys($requestItems);
        $requestId = !empty($key) ? (int)$key[0] : '';
        /** @var Request $request */
        $request = $this->requestFactory->create();
        $this->_requestResource->load($request, $requestId);
        try {
            $requestData = $requestItems[$requestId];
            $request->addData($requestData);
            $this->_requestResource->save($request);
        } catch (RuntimeException $e) {
            $messages[] = $this->getErrorWithRequestId($request, $e->getMessage());
            $error = true;
        } catch (Exception $e) {
            $messages[] = $this->getErrorWithRequestId(
                $request,
                __('Something went wrong while saving the Request.')
            );
            $error = true;
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    /**
     * Add Request id to error message
     *
     * @param Request $request
     * @param string $errorText
     *
     * @return string
     */
    public function getErrorWithRequestId($request, $errorText)
    {
        return '[Request ID: ' . $request->getId() . '] ' . $errorText;
    }
}
