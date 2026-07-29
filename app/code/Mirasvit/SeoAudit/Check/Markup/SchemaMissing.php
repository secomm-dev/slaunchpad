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

namespace Mirasvit\SeoAudit\Check\Markup;

use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Check\AbstractCheck;
use Mirasvit\SeoAudit\Repository\UrlRepository;
use Mirasvit\SeoAudit\Service\JsonLdExtractor;
use Mirasvit\SeoAudit\Service\UrlEntityResolverPool;
use Mirasvit\SeoAudit\Service\UrlService;

class SchemaMissing extends AbstractCheck
{
    private $jsonLdExtractor;

    private $resolverPool;

    public function __construct(
        UrlRepository         $urlRepository,
        UrlService            $urlService,
        Json                  $serializer,
        JsonLdExtractor       $jsonLdExtractor,
        UrlEntityResolverPool $resolverPool
    ) {
        $this->jsonLdExtractor = $jsonLdExtractor;
        $this->resolverPool    = $resolverPool;

        parent::__construct($urlRepository, $urlService, $serializer);
    }

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
        return 'markup_schema_missing';
    }

    public function getImportance(): int
    {
        return 1;
    }

    public function getValueType(): string
    {
        return self::VALUE_TYPE_STRING;
    }

    public function getLabel(): string
    {
        return (string)__('Product page is missing Product schema (JSON-LD)');
    }

    public function getGridColumnLabel(): string
    {
        return (string)__('Product schema');
    }

    public function getValueGridOutput(string $value): string
    {
        return $value;
    }

    public function getCheckResult(UrlInterface $url): array
    {
        $entity = $this->resolverPool->resolve($url);

        if (!$entity || $entity['type'] !== 'product') {
            return [
                CheckResultInterface::RESULT  => self::MAX_SCORE,
                CheckResultInterface::VALUE   => (string)__('Not applicable'),
                CheckResultInterface::MESSAGE => '',
            ];
        }

        $types = $this->getTypes($url);

        if (in_array('Product', $types, true) || in_array('ProductGroup', $types, true)) {
            return [
                CheckResultInterface::RESULT  => self::MAX_SCORE,
                CheckResultInterface::VALUE   => (string)__('Product schema found'),
                CheckResultInterface::MESSAGE => '',
            ];
        }

        return [
            CheckResultInterface::RESULT  => self::MIN_SCORE,
            CheckResultInterface::VALUE   => (string)__('Product schema not found'),
            CheckResultInterface::MESSAGE => (string)__('This product page has no Product (or ProductGroup) JSON-LD — it will not be eligible for Google rich results'),
        ];
    }

    private function getTypes(UrlInterface $url): array
    {
        $types = [];

        foreach ($this->jsonLdExtractor->extract($url) as $object) {
            foreach ((array)($object['@type'] ?? []) as $type) {
                if (is_string($type)) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }
}
