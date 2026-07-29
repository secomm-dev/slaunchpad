<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoAudit\Controller\Adminhtml\Job;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;
use Mirasvit\SeoAudit\Api\Data\CheckResultAggregatedInterface;
use Mirasvit\SeoAudit\Api\Data\JobInterface;
use Mirasvit\SeoAudit\Controller\Adminhtml\Job;
use Mirasvit\SeoAudit\Model\ConfigProvider;
use Mirasvit\SeoAudit\Model\ResourceModel\CheckResultAggregated\Collection;
use Mirasvit\SeoAudit\Model\ResourceModel\CheckResultAggregated\CollectionFactory;
use Mirasvit\SeoAudit\Repository\JobRepository;
use Mirasvit\SeoAudit\Service\CheckResultAggregatedService;

class Details extends Job
{
    private $checkResultAggregatedCollectionFactory;

    private $checkResultAggregatedService;

    public function __construct(
        CollectionFactory $checkResultAggregatedCollectionFactory,
        CheckResultAggregatedService $checkResultAggregatedService,
        ConfigProvider $config,
        JobRepository $jobRepository,
        Registry $registry,
        Context $context
    ) {
        $this->checkResultAggregatedCollectionFactory = $checkResultAggregatedCollectionFactory;
        $this->checkResultAggregatedService = $checkResultAggregatedService;
        parent::__construct($config, $jobRepository, $registry, $context);
    }

    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $id    = $this->getRequest()->getParam(JobInterface::ID);
        $model = $this->initModel();

        if ($id && !$model) {
            $this->messageManager->addErrorMessage((string)__('This job no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $this->addDisabledWarningMessage();

        $dateStr = '';
        if ($model) {
            $timestamp = strtotime((string)$model->getStartedAt());
            if ($timestamp !== false) {
                $dateStr = date('M d, Y', $timestamp);
            }
        }
        $resultPage->getConfig()->getTitle()->prepend((string)__('Job #%1 - %2', $id, $dateStr));
        $this->_initAction();

        if ($id) {
            $this->aggregateCheckResult((int)$id);
        }

        return $resultPage;
    }

    private function aggregateCheckResult(int $jobId): void
    {
        /** @var Collection $checkResultAggregatedCollection */
        $checkResultAggregatedCollection = $this->checkResultAggregatedCollectionFactory->create();
        $checkResultAggregatedCollection->addFieldToFilter(CheckResultAggregatedInterface::JOB_ID, ['eq' => $jobId]);
        $checkResultAggregatedCollection->addFieldToSelect([CheckResultAggregatedInterface::JOB_ID]);

        $items = $checkResultAggregatedCollection->getItems();

        if (empty($items)) {
            $this->checkResultAggregatedService->aggregate($jobId);
        }
    }
}
