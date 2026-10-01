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

namespace Mageplaza\RMA\Controller\Adminhtml\Status;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;
use Mageplaza\RMA\Model\Status;
use Mageplaza\RMA\Model\StatusFactory;
use RuntimeException;

/**
 * Class InlineEdit
 * @package Mageplaza\RMA\Controller\Adminhtml\Status
 */
class InlineEdit extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::status';

    /**
     * @var JsonFactory
     */
    public $jsonFactory;

    /**
     * @var StatusFactory
     */
    public $statusFactory;

    /**
     * @var StatusResource
     */
    protected $_statusResource;

    /**
     * InlineEdit constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param StatusFactory $statusFactory
     * @param StatusResource $statusResource
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        StatusFactory $statusFactory,
        StatusResource $statusResource
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->statusFactory = $statusFactory;
        $this->_statusResource = $statusResource;

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
        $statusItems = $this->getRequest()->getParam('items', []);
        if (!(!empty($statusItems) && $this->getRequest()->getParam('isAjax'))) {
            return $resultJson->setData([
                'messages' => [__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        $key = array_keys($statusItems);
        $statusId = !empty($key) ? (int)$key[0] : '';
        /** @var Status $status */
        $status = $this->statusFactory->create();
        $this->_statusResource->load($status, $statusId);
        try {
            $statusData = $statusItems[$statusId];
            $status->addData($statusData);
            $this->_statusResource->save($status);
        } catch (RuntimeException $e) {
            $messages[] = $this->getErrorWithStatusId($status, $e->getMessage());
            $error = true;
        } catch (Exception $e) {
            $messages[] = $this->getErrorWithStatusId(
                $status,
                __('Something went wrong while saving the Status.')
            );
            $error = true;
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    /**
     * Add Status id to error message
     *
     * @param Status $status
     * @param string $errorText
     *
     * @return string
     */
    public function getErrorWithStatusId($status, $errorText)
    {
        return '[Status ID: ' . $status->getId() . '] ' . $errorText;
    }
}
