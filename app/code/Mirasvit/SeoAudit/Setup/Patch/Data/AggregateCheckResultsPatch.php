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

namespace Mirasvit\SeoAudit\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Mirasvit\SeoAudit\Service\CheckResultAggregatedService;

class AggregateCheckResultsPatch implements DataPatchInterface
{
    private $checkResultAggregatedService;

    public function __construct(
        CheckResultAggregatedService $checkResultAggregatedService
    ) {
        $this->checkResultAggregatedService = $checkResultAggregatedService;
    }

    public function apply()
    {
        $this->checkResultAggregatedService->aggregate();

        return $this;
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
