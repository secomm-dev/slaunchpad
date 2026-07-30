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

namespace Mirasvit\SeoSitemap\Validate;

/**
 * What one {@see ValidatorInterface} produces: the violations found and how many artifact
 * elements were inspected. The `checkedCount` makes a vacuous pass visible — a validator that
 * ran against a sitemap with none of its target shape reports `checked 0`.
 */
class ValidationResult
{
    /** @var int */
    private $checkedCount;

    /** @var Violation[] */
    private $violations;

    /**
     * @param Violation[] $violations
     */
    public function __construct(int $checkedCount, array $violations = [])
    {
        $this->checkedCount = $checkedCount;
        $this->violations   = array_values($violations);
    }

    public function getCheckedCount(): int
    {
        return $this->checkedCount;
    }

    /**
     * @return Violation[]
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }
}
