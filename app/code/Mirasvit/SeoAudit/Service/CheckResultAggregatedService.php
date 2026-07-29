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

namespace Mirasvit\SeoAudit\Service;

use Magento\Framework\App\ResourceConnection;
use Mirasvit\SeoAudit\Api\Data\CheckResultAggregatedInterface;
use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Zend_Db_Expr;

class CheckResultAggregatedService
{
    private $adapter;

    public function __construct(
        ResourceConnection $adapter
    ) {
        $this->adapter = $adapter;
    }

    public function aggregate(?int $jobId = null): void
    {
        $adapter = $this->adapter->getConnection();

        $t2 = '(SELECT count(url_id) AS url_count, job_id, identifier FROM '
            . $this->adapter->getTableName(CheckResultInterface::TABLE_NAME)
            . ' WHERE result < 0 GROUP BY job_id, identifier)';

        $t3 = '(SELECT count(url_id) AS url_count, job_id, identifier FROM '
            . $this->adapter->getTableName(CheckResultInterface::TABLE_NAME)
            . ' WHERE result >= 0 AND result < 5 GROUP BY job_id, identifier)';

        $t4 = '(SELECT count(url_id) AS url_count, job_id, identifier FROM '
            . $this->adapter->getTableName(CheckResultInterface::TABLE_NAME)
            . ' WHERE result >= 5 AND result < 9 GROUP BY job_id, identifier)';

        $select = $adapter->select()
            ->from(
                ['main' => $this->adapter->getTableName(CheckResultInterface::TABLE_NAME)],
                null
            )->columns([
                'job_id'     => 'main.job_id',
                'identifier' => 'main.identifier',
                'total'      => 'COUNT(main.url_id)',
                'error'      => 'IFNULL(t2.url_count, 0)',
                'warning'    => 'IFNULL(t3.url_count, 0)',
                'notice'     => 'IFNULL(t4.url_count, 0)'
            ])->joinLeft(
                ['t2' => new Zend_Db_Expr($t2)],
                'main.identifier = t2.identifier AND main.job_id = t2.job_id',
                null
            )->joinLeft(
                ['t3' => new Zend_Db_Expr($t3)],
                'main.identifier = t3.identifier AND main.job_id = t3.job_id',
                null
            )->joinLeft(
                ['t4' => new Zend_Db_Expr($t4)],
                'main.identifier = t4.identifier AND main.job_id = t4.job_id',
                null
            )
            ->group(['main.job_id', 'main.identifier']);

        if ($jobId) {
            $select->where('main.job_id = ' . $jobId);
        } else {
            $select->where(
                'main.job_id > (SELECT MAX(main.job_id) - 3 FROM '
                . $this->adapter->getTableName(CheckResultInterface::TABLE_NAME)
                . ')');
        }

        $sql = $adapter->insertFromSelect(
            $select,
            $this->adapter->getTableName(CheckResultAggregatedInterface::TABLE_NAME),
            [
                CheckResultAggregatedInterface::JOB_ID,
                CheckResultAggregatedInterface::IDENTIFIER,
                CheckResultAggregatedInterface::TOTAL,
                CheckResultAggregatedInterface::ERROR,
                CheckResultAggregatedInterface::WARNING,
                CheckResultAggregatedInterface::NOTICE
            ]
        );

        $adapter->query($sql);
    }
}
