<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event\Pool;

/**
 * Generate metadata for specified event type
 */
interface MetadataInterface
{
    /**
     * Retrieve TikTok metadata for event request
     *
     * @param array|null $context
     *
     * @return array
     */
    public function getMetadata(?array $context): array;
}
