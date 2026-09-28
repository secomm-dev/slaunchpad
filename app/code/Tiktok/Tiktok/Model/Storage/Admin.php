<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Storage;

use Tiktok\Tiktok\Model\Storage\StorageInterface;

/**
 * @inheritdoc
 */
class Admin implements StorageInterface
{
    /**
     * @var array
     */
    private array $storage = [];

    /**
     * @inheritdoc
     */
    public function get(int|string $id): mixed
    {
        return $this->storage[$id] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function set(int|string $id, mixed $data): mixed
    {
        return $this->storage[$id] ??= $data;
    }

    /**
     * @inheritdoc
     */
    public function clean(): void
    {
        $this->storage = [];
    }

    /**
     * Destruct storage items
     */
    public function __destruct()
    {
        $this->clean();
    }
}
