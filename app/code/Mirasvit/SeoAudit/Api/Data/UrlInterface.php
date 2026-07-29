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
interface UrlInterface
{
    const TABLE_NAME = 'mst_seo_audit_url';

    const PARENT_TABLE_NAME      = 'mst_seo_audit_url_parent';
    const PARENT_REL_URL_ID      = 'url_id';
    const PARENT_REL_PARENT_ID   = 'parent_url_id';

    const ID                = 'url_id';
    const PARENT_IDS        = 'parent_ids';
    const JOB_ID            = 'job_id';
    const URL               = 'url';
    const URL_HASH          = 'url_hash';
    const STATUS_CODE       = 'status_code';
    const TYPE              = 'type';
    const META_TITLE        = 'meta_title';
    const META_DESCRIPTION  = 'meta_description';
    const CANONICAL         = 'canonical';
    const ROBOTS            = 'robots';
    const CONTENT           = 'content';
    const STATUS            = 'status';
    const META_FIX_ATTEMPTS = 'meta_fix_attempts';

    const STATUS_PENDING    = 'pending';
    const STATUS_CRAWLED    = 'crawled';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FINISHED   = 'finished';
    const STARUS_ERROR      = 'error';

    const TYPE_PAGE     = 'page';
    const TYPE_IMAGE    = 'image';
    const TYPE_VIDEO    = 'video';
    const TYPE_AUDIO    = 'audio';
    const TYPE_JS       = 'js';
    const TYPE_CSS      = 'css';
    const TYPE_FONT     = 'font';
    const TYPE_SITEMAP  = 'sitemap';
    const TYPE_ROBOTS   = 'robots';
    const TYPE_REDIRECT = 'redirect';
    const TYPE_OTHER    = 'other';

    /**
     * @return int
     */
    public function getId(): int;

    /**
     * @return int[]
     */
    public function getParentIds(): array;

    /**
     * @param int[] $parentId
     * @return $this
     */
    public function setParentIds(array $parentId): self;

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
    public function getUrl(): string;

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url): self;

    /**
     * @return string
     */
    public function getUrlHash(): string;

    /**
     * @param string $urlHash
     * @return $this
     */
    public function setUrlHash(string $urlHash): self;

    /**
     * @return int
     */
    public function getStatusCode(): int;

    /**
     * @param int $code
     * @return $this
     */
    public function setStatusCode(int $code): self;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content = null): self;

    /**
     * @return string
     */
    public function getMetaTitle(): string;

    /**
     * @param string $metaTitle
     * @return $this
     */
    public function setMetaTitle(string $metaTitle): self;

    /**
     * @return string
     */
    public function getMetaDescription(): string;

    /**
     * @param string $metaDescription
     * @return $this
     */
    public function setMetaDescription(string $metaDescription): self;

    /**
     * @return string|null
     */
    public function getRobots(): ?string;

    /**
     * @param string $robots
     * @return $this
     */
    public function setRobots(string $robots): self;

    /**
     * @return string|null
     */
    public function getCanonical(): ?string;

    /**
     * @param string $canonical
     * @return $this
     */
    public function setCanonical(string $canonical): self;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $checkStatus
     * @return $this
     */
    public function setStatus(string $checkStatus): self;

    /**
     * Number of times the AI meta-fix job has tried and failed to fix this URL.
     * Used to exclude permanently-failing URLs so the queue drains.
     *
     * @return int
     */
    public function getMetaFixAttempts(): int;

    /**
     * @param int $attempts
     * @return $this
     */
    public function setMetaFixAttempts(int $attempts): self;
}
