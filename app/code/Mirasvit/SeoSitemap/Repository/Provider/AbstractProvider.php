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



namespace Mirasvit\SeoSitemap\Repository\Provider;

use Mirasvit\SeoSitemap\Api\Repository\ProviderInterface;

abstract class AbstractProvider implements ProviderInterface
{
    const KEY         = '';
    const MODULE_NAME = '';
    const TITLE       = '';

    public function getModuleName(): string
    {
        return static::MODULE_NAME;
    }

    public function getTitle(): string
    {
        return (string)__(static::TITLE);
    }

    public function getKey(): string
    {
        return static::KEY;
    }

    public function isApplicable(): bool
    {
        return true;
    }
}
