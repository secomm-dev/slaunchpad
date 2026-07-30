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

namespace Mirasvit\SeoAudit\Model\ResourceModel\CheckResultAggregated;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Mirasvit\SeoAudit\Api\Data\CheckResultAggregatedInterface;
use Mirasvit\SeoAudit\Api\Data\JobInterface;
use Mirasvit\SeoAudit\Model\ResourceModel\CheckResultAggregated;
use Psr\Log\LoggerInterface as Logger;

class Grid extends SearchResult
{
    private $context;

    public function __construct(
        ContextInterface $context,
        EntityFactory    $entityFactory,
        Logger           $logger,
        FetchStrategy    $fetchStrategy,
        EventManager     $eventManager
    ) {
        $this->context = $context;

        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            CheckResultAggregatedInterface::TABLE_NAME,
            CheckResultAggregated::class
        );
    }

    protected function _initSelect()
    {
        $jobId = $this->context->getRequestParam(JobInterface::ID);

        $this->_select->from(
            $this->_resource->getTable(CheckResultAggregatedInterface::TABLE_NAME),
            [
                CheckResultAggregatedInterface::JOB_ID,
                CheckResultAggregatedInterface::IDENTIFIER,
                CheckResultAggregatedInterface::TOTAL,
                CheckResultAggregatedInterface::ERROR,
                CheckResultAggregatedInterface::WARNING,
                CheckResultAggregatedInterface::NOTICE
            ]
        );

        if ($jobId) {
            $this->_select->where('job_id = ' . $jobId);
        }
    }
}
