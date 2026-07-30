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

namespace Mirasvit\SeoAudit\Service;

use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Api\UrlEntityResolverInterface;

class UrlEntityResolverPool
{
    /** @var UrlEntityResolverInterface[] */
    private $resolvers;

    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers;
    }

    /**
     * @return array{type: string, id: int}|null
     */
    public function resolve(UrlInterface $url): ?array
    {
        foreach ($this->resolvers as $resolver) {
            $entity = $resolver->resolve($url);

            if ($entity) {
                return $entity;
            }
        }

        return null;
    }
}
