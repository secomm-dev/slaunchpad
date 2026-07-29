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
interface CheckResultInterface
{
    const TABLE_NAME = 'mst_seo_audit_check_result';

    const ID         = 'check_id';
    const URL_ID     = 'url_id';
    const URL_TYPE   = 'url_type';
    const JOB_ID     = 'job_id';
    const IDENTIFIER = 'identifier';
    const IMPORTANCE = 'importance';
    const RESULT     = 'result';
    const VALUE      = 'value';
    const MESSAGE    = 'message';
    const CREATED_AT = 'created_at';

    /**
     * @return int
     */
    public function getId(): int;

    /**
     * @return int
     */
    public function getUrlId(): int;

    /**
     * @param int $urlId
     * @return $this
     */
    public function setUrlId(int $urlId): self;

    /**
     * @return string
     */
    public function getUrlType(): string;

    /**
     * @param string $type
     * @return $this
     */
    public function setUrlType(string $type): self;

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
    public function getImportance(): int;

    /**
     * @param int $importance
     * @return $this
     */
    public function setImportance(int $importance): self;

    /**
     * @return string
     */
    public function getValue(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setValue(string $value): self;

    /**
     * @return int
     */
    public function getResult(): int;

    /**
     * @param int $result
     * @return $this
     */
    public function setResult(int $result): self;

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
     * @return string
     */
    public function getCreatedAt(): string;
}
