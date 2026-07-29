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

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;

class JsonLdExtractor
{
    private $serializer;

    /** @var array{top: array<array>, types: array<string>} */
    private $cache = ['top' => [], 'types' => []];

    /** @var int|null */
    private $cacheKey;

    public function __construct(Json $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Top-level JSON-LD objects declared on the page (one entry per script tag,
     * or per item if a script tag holds an array / "@graph").
     *
     * @return array[]
     */
    public function extract(UrlInterface $url): array
    {
        return $this->getParsed($url)['top'];
    }

    /**
     * Every "@type" value found anywhere on the page, including nested objects
     * (e.g. a merchant-added type nested inside an Extender snippet).
     *
     * @return string[]
     */
    public function extractAllTypes(UrlInterface $url): array
    {
        return $this->getParsed($url)['types'];
    }

    /**
     * @return array{top: array<array>, types: array<string>}
     */
    private function getParsed(UrlInterface $url): array
    {
        $key = spl_object_id($url);

        if ($this->cacheKey !== $key) {
            $this->cache    = $this->parse($url->getContent());
            $this->cacheKey = $key;
        }

        return $this->cache;
    }

    /**
     * @return array{top: array<array>, types: array<string>}
     */
    private function parse(?string $content): array
    {
        $result = ['top' => [], 'types' => []];

        if (!$content) {
            return $result;
        }

        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//script[@type="application/ld+json"]');

        if (!$nodes) {
            return $result;
        }

        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            try {
                $decoded = $this->serializer->unserialize($node->textContent);
            } catch (Exception $e) {
                continue;
            }

            foreach ($this->flattenTopLevel($decoded) as $object) {
                $result['top'][] = $object;
            }

            $this->collectTypes($decoded, $result['types']);
        }

        return $result;
    }

    /**
     * A script tag can hold a single object, an array of objects, or a
     * "@graph"-wrapped array — normalize all three into a flat list of objects.
     */
    private function flattenTopLevel($decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return array_values(array_filter($decoded['@graph'], 'is_array'));
        }

        if (isset($decoded['@type'])) {
            return [$decoded];
        }

        // plain array of objects, no wrapper
        return array_values(array_filter($decoded, 'is_array'));
    }

    private function collectTypes($node, array &$types): void
    {
        if (!is_array($node)) {
            return;
        }

        if (isset($node['@type'])) {
            foreach ((array)$node['@type'] as $type) {
                if (is_string($type)) {
                    $types[] = $type;
                }
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectTypes($value, $types);
            }
        }
    }
}
