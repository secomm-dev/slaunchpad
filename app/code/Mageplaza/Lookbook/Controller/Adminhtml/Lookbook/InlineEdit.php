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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Controller\Adminhtml\Lookbook;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Lookbook\Controller\Adminhtml\Lookbook;
use Mageplaza\Lookbook\Model\LookbookFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook as ResourceModel;

/**
 * Class InlineEdit
 * @package Mageplaza\Lookbook\Controller\Adminhtml\Lookbook
 */
class InlineEdit extends Lookbook
{
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * InlineEdit constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param LookbookFactory $lookbookFactory
     * @param ResourceModel $resourceModel
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        LookbookFactory $lookbookFactory,
        ResourceModel $resourceModel,
        JsonFactory $jsonFactory
    ) {
        $this->jsonFactory = $jsonFactory;

        parent::__construct(
            $context,
            $coreRegistry,
            $resultPageFactory,
            $resultForwardFactory,
            $lookbookFactory,
            $resourceModel
        );
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
        $postItems = $this->getRequest()->getParam('items', []);

        if (empty($postItems) || !$this->getRequest()->getParam('isAjax')) {
            return $resultJson->setData(
                [
                    'messages' => [__('Please correct the data sent.')],
                    'error' => true,
                ]
            );
        }

        foreach (array_keys($postItems) as $id) {
            $model = $this->lookbookFactory->create();
            try {
                $this->resourceModel->load($model, $id);
                if (!$model->getId()) {
                    return $resultJson->setData(
                        [
                            'messages' => [__('The wrong lookbook is specified.')],
                            'error' => true,
                        ]
                    );
                }
                $model->setData($this->mergeData($model, $postItems[$id]));
                $this->resourceModel->save($model);
            } catch (Exception $e) {
                $messages[] = $this->getErrorWithId(
                    $model,
                    __($e->getMessage())
                );
                $error = true;
            }
        }

        return $resultJson->setData(
            [
                'messages' => $messages,
                'error' => $error
            ]
        );
    }

    /**
     * @param \Mageplaza\Lookbook\Model\Lookbook $model
     * @param array $newData
     *
     * @return array
     */
    protected function mergeData(\Mageplaza\Lookbook\Model\Lookbook $model, array $newData)
    {
        return array_merge($model->getData(), $newData);
    }

    /**
     * Add banner id to error message
     *
     * @param \Mageplaza\Lookbook\Model\Lookbook $model
     * @param string $errorText
     *
     * @return string
     */
    protected function getErrorWithId(\Mageplaza\Lookbook\Model\Lookbook $model, $errorText)
    {
        return '[Lookbook ID: ' . $model->getId() . '] ' . $errorText;
    }
}
