<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Response;

/**
 * Search result envelope DTO — paging metadata plus product summaries.
 */
class SearchResultDto
{
    /**
     * Assemble the search result envelope.
     *
     * @param array $items product summary DTOs
     * @param int $totalCount total matching count
     * @param int $page current page (1-based)
     * @param int $pageSize effective page size
     * @return mixed[] DTO array
     */
    public function toArray(array $items, int $totalCount, int $page, int $pageSize): array
    {
        return [
            'total_count' => $totalCount,
            'page' => $page,
            'page_size' => $pageSize,
            'items' => array_values($items),
        ];
    }
}
