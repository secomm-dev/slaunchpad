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

namespace Mirasvit\SeoAudit\Check\Meta;

use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Check\AbstractCheck;

class CanonicalMissing extends AbstractCheck
{
    public function getAllowedTypes(): array
    {
        return [UrlInterface::TYPE_PAGE];
    }

    public function isAllowedForExternal(): bool
    {
        return false;
    }

    public function getIdentifier(): string
    {
        return 'meta_canonical_missing';
    }

    public function getImportance(): int
    {
        return 1;
    }

    public function getCheckResult(UrlInterface $url): array
    {
        $canonical = $url->getCanonical();

        return [
            CheckResultInterface::RESULT  => $canonical ? self::MAX_SCORE : self::MIN_SCORE,
            CheckResultInterface::VALUE   => $canonical ?: (string)__('Canonical tag is missing'),
            CheckResultInterface::MESSAGE => '',
        ];
    }

    public function getValueType(): string
    {
        return self::VALUE_TYPE_STRING;
    }

    public function getLabel(): string
    {
        return (string)__('Canonical tag is missing');
    }

    public function getGridColumnLabel(): string
    {
        return (string)__('Canonical');
    }

    public function getValueGridOutput(string $value): string
    {
        return $value;
    }
}
