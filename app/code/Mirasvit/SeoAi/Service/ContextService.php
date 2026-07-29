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


namespace Mirasvit\SeoAi\Service;


use Mirasvit\SeoAi\Service\Context\AbstractContext;

class ContextService
{
    /** @var AbstractContext[] */
    private $pool;

    public function __construct(array $pool)
    {
        $this->pool = $pool;
    }

    public function getContextByType(string $type, ?int $entityId = null, ?array $params = null): array
    {
        return isset($this->pool[$type])
            ? $this->pool[$type]->getContext($entityId, $params)
            : $this->pool['default']->getContext($entityId, $params);
    }
}
