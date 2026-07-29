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

namespace Mirasvit\SeoAudit\Check\Content;

use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Check\AbstractCheck;
use Mirasvit\SeoAudit\Repository\UrlRepository;
use Mirasvit\SeoAudit\Service\UrlEntityResolverPool;
use Mirasvit\SeoAudit\Service\UrlService;
use Mirasvit\SeoAudit\Service\VisibleTextExtractor;

class ThinContent extends AbstractCheck
{
    private const WORDS_ERROR_MAX        = 49;
    private const WORDS_WARNING_LOW_MAX  = 99;
    private const WORDS_WARNING_HIGH_MAX = 149;

    private const APPLICABLE_TYPES = ['product', 'category'];

    private $textExtractor;

    private $resolverPool;

    public function __construct(
        UrlRepository         $urlRepository,
        UrlService            $urlService,
        Json                  $serializer,
        VisibleTextExtractor  $textExtractor,
        UrlEntityResolverPool $resolverPool
    ) {
        $this->textExtractor = $textExtractor;
        $this->resolverPool  = $resolverPool;

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
        return 'content_thin_content';
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
        return (string)__('Page has thin content');
    }

    public function getGridColumnLabel(): string
    {
        return (string)__('Word count');
    }

    public function getValueGridOutput(string $value): string
    {
        return $value;
    }

    public function getCheckResult(UrlInterface $url): array
    {
        $entity = $this->resolverPool->resolve($url);

        if (!$entity || !in_array($entity['type'], self::APPLICABLE_TYPES, true)) {
            return [
                CheckResultInterface::RESULT  => self::MAX_SCORE,
                CheckResultInterface::VALUE   => (string)__('Not applicable'),
                CheckResultInterface::MESSAGE => '',
            ];
        }

        $wordCount = $this->textExtractor->extractWordCount($url);

        return [
            CheckResultInterface::RESULT  => $this->scoreFor($wordCount),
            CheckResultInterface::VALUE   => (string)__('%1 words', $wordCount),
            CheckResultInterface::MESSAGE => $this->messageFor($wordCount),
        ];
    }

    private function scoreFor(int $wordCount): int
    {
        if ($wordCount <= self::WORDS_ERROR_MAX) {
            return self::MIN_SCORE;
        }

        if ($wordCount <= self::WORDS_WARNING_LOW_MAX) {
            return 3;
        }

        if ($wordCount <= self::WORDS_WARNING_HIGH_MAX) {
            return 6;
        }

        return self::MAX_SCORE;
    }

    private function messageFor(int $wordCount): string
    {
        if ($wordCount > self::WORDS_WARNING_HIGH_MAX) {
            return '';
        }

        return (string)__('Visible page content looks thin — consider adding more unique, substantive copy');
    }
}
