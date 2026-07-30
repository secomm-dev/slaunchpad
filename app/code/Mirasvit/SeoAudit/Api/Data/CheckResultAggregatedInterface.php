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

namespace Mirasvit\SeoAudit\Api\Data;

/**
 * @api
 */
interface CheckResultAggregatedInterface
{
    const TABLE_NAME = 'mst_seo_audit_check_result_aggregated';
    const ID = 'aggregated_id';
    const JOB_ID = 'job_id';
    const IDENTIFIER = 'identifier';
    const TOTAL = 'total';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';

    /**
     * @return int
     */
    public function getId(): int;

    /**
     * @return int
     */
    public function getJobId(): int;

    /**
     * @param int $jobId
     * @return $this
     */
    public function setJobId(int $jobId): self;

    /**
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier(string $identifier): self;

    /**
     * @return int
     */
    public function getTotal(): int;

    /**
     * @param int $total
     * @return $this
     */
    public function setTotal(int $total): self;

    /**
     * @return int
     */
    public function getError(): int;

    /**
     * @param int $error
     * @return $this
     */
    public function setError(int $error): self;

    /**
     * @return int
     */
    public function getWarning(): int;

    /**
     * @param int $warning
     * @return $this
     */
    public function setWarning(int $warning): self;

    /**
     * @return int
     */
    public function getNotice(): int;

    /**
     * @param int $notice
     * @return $this
     */
    public function setNotice(int $notice): self;
}
