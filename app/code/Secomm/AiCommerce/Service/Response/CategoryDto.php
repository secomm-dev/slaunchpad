<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Response;

/**
 * Category node DTO — fixed field allowlist (id, name, url, children).
 */
class CategoryDto
{
    /**
     * Assemble the category node DTO.
     *
     * @param int $id category entity id
     * @param string $name store-scoped category name
     * @param array $urls resolved urls
     * @param array $children child node DTOs
     * @return mixed[] DTO array
     */
    public function toArray(int $id, string $name, array $urls, array $children): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'public_url' => $urls['public_url'],
            'canonical_url' => $urls['canonical_url'],
            'children' => $children,
        ];
    }
}
