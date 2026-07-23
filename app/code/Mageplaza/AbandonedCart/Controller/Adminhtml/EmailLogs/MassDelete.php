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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Controller\Adminhtml\EmailLogs;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Mageplaza\AbandonedCart\Model\ResourceModel\Logs\CollectionFactory;
use Mageplaza\AbandonedCart\Model\LogsFactory;
use Mageplaza\AbandonedCart\Model\AbandonedCart as AbandonedCartModel;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class MassDelete
 * @package Mageplaza\AbandonedCart\Controller\Adminhtml\EmailLogs
 */
class MassDelete extends Action
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $emailLog;

    /**
     * @var LogsFactory
     */
    protected $logsFactory;

    /**
     * @var AbandonedCartModel
     */
    protected $abandonedCartModel;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * MassResend constructor.
     *
     * @param Filter $filter
     * @param Action\Context $context
     * @param CollectionFactory $emailLog
     */
    public function __construct(
        Filter $filter,
        Action\Context $context,
        CollectionFactory $emailLog,
        LogsFactory $logsFactory,
        AbandonedCartModel $abandonedCartModel,
        LoggerInterface $logger
    ) {
        $this->filter             = $filter;
        $this->emailLog           = $emailLog;
        $this->logsFactory        = $logsFactory;
        $this->abandonedCartModel = $abandonedCartModel;
        $this->logger             = $logger;

        parent::__construct($context);
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->emailLog->create());
        $delete     = 0;

        /** @var \Mageplaza\AbandonedCart\Model\Logs $item */
        foreach ($collection->getItems() as $item) {
            $log = $this->logsFactory->create()->load($item->getId());
            if ($log->getId()) {
                try {
                    $log->setDisplay(false)->save();
                    $delete++;
                } catch (Exception $e) {
                    $this->logger->critical($e);
                    $this->messageManager->addErrorMessage(
                        __('We can\'t process your request for email log #%1', $item->getId())
                    );
                }
            }
        }

        if ($delete) {
            $this->messageManager->addSuccessMessage(
                __('Total %1 item(s) have been deleted.', $delete)
            );
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/index/report');
    }
}
