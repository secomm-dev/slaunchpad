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

namespace Mirasvit\SeoContent\Api\Data;

/**
 * @api
 */
interface ContentInterface
{
    const DESCRIPTION_POSITION_DISABLED                = 0;
    const DESCRIPTION_POSITION_BOTTOM_PAGE             = 1;
    const DESCRIPTION_POSITION_UNDER_SHORT_DESCRIPTION = 2;
    const DESCRIPTION_POSITION_UNDER_FULL_DESCRIPTION  = 3;
    const DESCRIPTION_POSITION_UNDER_PRODUCT_LIST      = 4;
    const DESCRIPTION_POSITION_CUSTOM_TEMPLATE         = 5;

    const TITLE                = 'title';
    const META_TITLE           = 'meta_title';
    const META_KEYWORDS        = 'meta_keywords';
    const META_DESCRIPTION     = 'meta_description';
    const META_ROBOTS          = 'meta_robots';
    const DESCRIPTION          = 'description';
    const DESCRIPTION_POSITION = 'description_position';
    const DESCRIPTION_TEMPLATE = 'description_template';
    const SHORT_DESCRIPTION    = 'short_description';
    const FULL_DESCRIPTION     = 'full_description';
    const CATEGORY_DESCRIPTION = 'category_description';
    const CATEGORY_IMAGE       = 'category_image';
    const APPLIED_TEMPLATE_ID  = 'applied_template_id';
    const APPLIED_REWRITE_ID   = 'applied_rewrite_id';
    const BRAND_DESCRIPTION    = 'brand_description';

    /**
     * @param string $value
     * @return $this
     */
    public function setTitle(string $value): self;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setMetaTitle(string $value): self;

    /**
     * @return string
     */
    public function getMetaTitle(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setMetaKeywords(string $value): self;

    /**
     * @return string
     */
    public function getMetaKeywords(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setMetaDescription(string $value): self;

    /**
     * @return string
     */
    public function getMetaDescription(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setDescription(string $value): self;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param int $value
     * @return $this
     */
    public function setDescriptionPosition(int $value): self;

    /**
     * @return int
     */
    public function getDescriptionPosition(): int;

    /**
     * @param string $value
     * @return $this
     */
    public function setDescriptionTemplate(string $value): self;

    /**
     * @return string
     */
    public function getDescriptionTemplate(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setShortDescription(string $value): self;

    /**
     * @return string
     */
    public function getShortDescription(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setFullDescription(string $value): self;

    /**
     * @return string
     */
    public function getFullDescription(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setCategoryDescription(string $value): self;

    /**
     * @return string
     */
    public function getCategoryDescription(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setCategoryImage(string $value): self;

    /**
     * @return string
     */
    public function getCategoryImage(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setMetaRobots(string $value): self;

    /**
     * @return string
     */
    public function getMetaRobots(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setBrandDescription(string $value): self;

    /**
     * @return string
     */
    public function getBrandDescription(): string;
}
