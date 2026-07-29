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
 * One golden-output invariant over a generated sitemap. The shipped `mirasvit:seositemap:validate`
 * command and the headless golden test run the same implementations, so the CI gate and the
 * customer diagnostic never drift. Module-local by design — no cross-module (core) coupling.
 */
interface ValidatorInterface
{
    /**
     * Stable snake_case machine code (e.g. "sitemap_loc"). Used as the JSON `check` field and the
     * DegradationReporter event key, so it must stay constant — telemetry and log filters key on it.
     */
    public function getName(): string;

    /**
     * Human-readable title for CLI output (e.g. "Valid sitemap URLs"). Free to reword; unlike
     * getName() it carries no machine contract.
     */
    public function getTitle(): string;

    public function validate(): ValidationResult;
}
