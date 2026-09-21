<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Plugin\Mirasvit\SeoAutolink;

use Mirasvit\SeoAutolink\Service\TextProcessorService;
use Psr\Log\LoggerInterface;

/**
 * Prevents a failed SEO Autolink pass from erasing rendered Secomm UI content.
 */
class PreserveSecommUiContent
{
    private const COMPONENT_MARKER = 'data-secomm-ui-component=';

    /**
     * @param LoggerInterface $logger Application logger.
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Restore the source only when Autolink unexpectedly erases Secomm UI HTML.
     *
     * @param TextProcessorService $subject Autolink text processor.
     * @param string|null $result Processed content.
     * @param string|null $text Original content.
     */
    public function afterAddLinks(
        TextProcessorService $subject,
        ?string $result,
        ?string $text = null
    ): ?string {
        if (!$this->shouldRestore($text, $result)) {
            return $result;
        }

        $this->logger->warning(
            'Mirasvit SEO Autolink returned empty Secomm UI content; original HTML was restored.',
            ['source_length' => strlen((string)$text)]
        );

        return $text;
    }

    /**
     * Check the narrow compatibility fallback contract.
     *
     * @param string|null $text Original content.
     * @param string|null $result Processed content.
     */
    private function shouldRestore(?string $text, ?string $result): bool
    {
        return $text !== null
            && $text !== ''
            && str_contains($text, self::COMPONENT_MARKER)
            && ($result === null || trim($result) === '');
    }
}
