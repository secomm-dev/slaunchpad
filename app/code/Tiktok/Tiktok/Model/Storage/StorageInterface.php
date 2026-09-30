<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Storage;

/**
 * Cache storage for individual pairs
 */
interface StorageInterface
{
    /**
     * Get storage data by ID
     *
     * @param int|string $id
     * @return mixed|null
     */
    public function get(int|string $id): mixed;

    /**
     * Saves ID/Data pair into cache
     *
     * @param int|string $id
     * @param mixed $data
     * @return mixed
     */
    public function set(int|string $id, mixed $data): mixed;

    /**
     * Clean storage
     *
     * @return void
     */
    public function clean(): void;
}
