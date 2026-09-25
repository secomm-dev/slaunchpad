<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event;

use Tiktok\Tiktok\Model\Event\Pool\MetadataInterface;
use Magento\Framework\Exception\InputException;

class Pool
{
    /**
     * Pool constructor
     *
     * @param array $pool
     *
     * @throws InputException
     */
    public function __construct(private readonly array $pool = [])
    {
        foreach ($pool as $processor) {
            if (!$processor instanceof MetadataInterface) {
                throw new InputException(
                    __('Processor %1 doesn\'t implement MetadataInterface', get_class($processor))
                );
            }
        }
    }

    /**
     * Execute
     *
     * @param string $type
     * @param array|null $context
     *
     * @return array|null
     */
    public function execute(string $type, ?array $context = null): ?array
    {
        return isset($this->pool[$type]) ? $this->pool[$type]->getMetadata($context) : null;
    }
}
