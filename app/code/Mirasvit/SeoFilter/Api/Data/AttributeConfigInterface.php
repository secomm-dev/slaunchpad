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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Api\Data;

interface AttributeConfigInterface
{
    const TABLE_NAME = 'mst_seo_filter_attribute_config';

    const ID               = 'config_id';
    const ATTRIBUTE_CODE   = 'attribute_code';
    const ATTRIBUTE_STATUS = 'attribute_status';

    const SEO_STATUS_DEFAULT  = 1;
    const SEO_STATUS_ENABLED  = 2;
    const SEO_STATUS_DISABLED = 3;

    const ENABLE_SEO_URL = 'enable_seo_url';

    public function getId(): ?int;

    public function getAttributeCode(): string;

    public function setAttributeCode(string $value): self;

    public function getAttributeStatus(): int;

    public function setAttributeStatus(int $value): self;
}
