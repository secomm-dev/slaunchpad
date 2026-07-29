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
use Mirasvit\SeoAudit\Model\Config\SchemaRequirements;
use Mirasvit\SeoAudit\Repository\UrlRepository;
use Mirasvit\SeoAudit\Service\JsonLdExtractor;
use Mirasvit\SeoAudit\Service\UrlService;

class SchemaQuality extends AbstractCheck
{
    private $jsonLdExtractor;

    private $schemaRequirements;

    public function __construct(
        UrlRepository       $urlRepository,
        UrlService          $urlService,
        Json                $serializer,
        JsonLdExtractor     $jsonLdExtractor,
        SchemaRequirements  $schemaRequirements
    ) {
        $this->jsonLdExtractor    = $jsonLdExtractor;
        $this->schemaRequirements = $schemaRequirements;

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
        return 'markup_schema_quality';
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
        return (string)__('Schema (JSON-LD) is missing properties Google requires');
    }

    public function getGridColumnLabel(): string
    {
        return (string)__('Schema quality');
    }

    public function getValueGridOutput(string $value): string
    {
        return $value;
    }

    public function getCheckResult(UrlInterface $url): array
    {
        $checked = 0;
        $issues  = [];

        foreach ($this->jsonLdExtractor->extract($url) as $object) {
            foreach ((array)($object['@type'] ?? []) as $type) {
                if (!is_string($type)) {
                    continue;
                }

                $rules = $this->schemaRequirements->get($type);
                if (!$rules) {
                    continue;
                }

                $checked++;

                $missing = $this->findMissing($object, $rules);
                if ($missing) {
                    $issues[$type] = $missing;
                }
            }
        }

        if (!$checked) {
            return [
                CheckResultInterface::RESULT  => self::MAX_SCORE,
                CheckResultInterface::VALUE   => (string)__('No recognized schema on this page'),
                CheckResultInterface::MESSAGE => '',
            ];
        }

        if (!$issues) {
            return [
                CheckResultInterface::RESULT  => self::MAX_SCORE,
                CheckResultInterface::VALUE   => (string)__('All required properties present'),
                CheckResultInterface::MESSAGE => '',
            ];
        }

        $summary = [];
        foreach ($issues as $type => $missing) {
            $summary[] = $type . ': ' . implode(', ', $missing);
        }

        $missingRatio = count($issues) / $checked;
        $score        = (int)max(0, min(5, round(5 - $missingRatio * 5)));

        return [
            CheckResultInterface::RESULT  => $score,
            CheckResultInterface::VALUE   => implode('; ', $summary),
            CheckResultInterface::MESSAGE => (string)__(
                'Some schema.org properties Google requires are missing or empty'
            ),
        ];
    }

    /**
     * @return string[]
     */
    private function findMissing(array $object, array $rules): array
    {
        $missing = [];

        foreach ($rules['required'] ?? [] as $property) {
            if (!$this->isFilled($object[$property] ?? null)) {
                $missing[] = $property;
            }
        }

        foreach ($rules['anyOf'] ?? [] as $group) {
            $found = false;
            foreach ($group as $property) {
                if ($this->isFilled($object[$property] ?? null)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = implode('/', $group);
            }
        }

        return $missing;
    }

    /**
     * @param mixed $value
     */
    private function isFilled($value): bool
    {
        if (is_array($value)) {
            return !empty($value);
        }

        return $value !== null && $value !== '';
    }
}
