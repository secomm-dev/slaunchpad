<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Adapter;

use InvalidArgumentException;

/**
 * FEAT-CSWYEJ — method_code → adapter registry (DEC D1).
 *
 * The map is a DI argument: provider modules append entries in their OWN di.xml.
 * An empty map is a valid state (no provider integrated yet).
 */
class AdapterPool
{
    /** @var PaymentProviderAdapterInterface[] */
    private array $adapters = [];

    /**
     * @param PaymentProviderAdapterInterface[] $adapters
     */
    public function __construct(array $adapters = [])
    {
        foreach ($adapters as $adapter) {
            if (!$adapter instanceof PaymentProviderAdapterInterface) {
                throw new InvalidArgumentException(
                    'PaymentCore adapter pool entries must implement PaymentProviderAdapterInterface'
                );
            }
            $this->adapters[$adapter->getMethodCode()] = $adapter;
        }
    }

    public function hasAdapter(string $methodCode): bool
    {
        return isset($this->adapters[$methodCode]);
    }

    /**
     * @throws InvalidArgumentException when no adapter is registered for the method
     */
    public function getAdapter(string $methodCode): PaymentProviderAdapterInterface
    {
        if (!isset($this->adapters[$methodCode])) {
            throw new InvalidArgumentException(
                sprintf('No PaymentCore adapter registered for method "%s"', $methodCode)
            );
        }
        return $this->adapters[$methodCode];
    }
}
