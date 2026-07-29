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
interface JobInterface
{
    const TABLE_NAME  = 'mst_seo_audit_job';

    const ID                = 'job_id';
    const STATUS            = 'status';
    const MESSAGE           = 'message';
    const CREATED_AT        = 'created_at';
    const STARTED_AT        = 'started_at';
    const FINISHED_AT       = 'finished_at';
    const RESULT_SERIALIZED = 'result_serialized';

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FINISHED   = 'finished';
    const STATUS_ERROR      = 'error';

    /**
     * @return int
     */
    public function getId(): int;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * @return string|null
     */
    public function getStartedAt(): ?string;

    /**
     * @param string|null $startedAt
     * @return $this
     */
    public function setStartedAt(?string $startedAt): self;

    /**
     * @return string|null
     */
    public function getFinishedAt(): ?string;

    /**
     * @param string|null $finishedAt
     * @return $this
     */
    public function setFinishedAt(?string $finishedAt): self;

    /**
     * @return string
     */
    public function getMessage(): string;

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self;

    /**
     * @return string[]|null
     */
    public function getResult(): ?array;

    /**
     * @param string[] $result
     * @return $this
     */
    public function setResult(array $result): self;
}
