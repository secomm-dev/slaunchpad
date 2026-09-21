<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ServiceLevel;

use Magento\Framework\Exception\LocalizedException;
use Secomm\ShippingCore\Api\ShippingServiceLevelInterface;

/**
 * TASK-XXBN5X r1 — runtime lookup over the DYNAMICALLY registered service-level definitions.
 *
 * ShippingCore knows NO specific codes: project/composition modules (e.g. the Launchpad
 * composition with its EXPRESS/SAME_DAY/STANDARD business defaults) register definitions via
 * the `serviceLevels` DI array argument, and validation is therefore dynamic — an unknown code
 * is an explicit configuration error, never silently tolerated into ambiguous orchestration.
 *
 * Zero registered levels is a valid state (ShippingCore compiles and runs outside any project
 * taxonomy). Duplicate codes or empty codes in the registration are misconfigurations and fail
 * fast at construction.
 *
 * Storage is DI registration (Option A): definitions are few, version-controlled, and need no
 * admin CRUD today; a system-config or DB-backed source would arrive as a different registration
 * mechanism, not by hardcoding values back into ShippingCore.
 */
final class ShippingServiceLevelRegistry
{
    /** @var array<string, ShippingServiceLevelInterface> keyed by machine code */
    private array $byCode = [];

    /** @var ShippingServiceLevelInterface[] registration order preserved */
    private array $all = [];

    /**
     * @param ShippingServiceLevelInterface[] $serviceLevels contributed via DI
     * @throws \LogicException on a duplicate or empty machine code
     */
    public function __construct(array $serviceLevels = [])
    {
        foreach ($serviceLevels as $serviceLevel) {
            $code = $serviceLevel->getCode();
            if (trim($code) === '') {
                throw new \LogicException('Registered shipping service levels must have a non-empty machine code.');
            }
            if (isset($this->byCode[$code])) {
                throw new \LogicException(
                    sprintf('Duplicate shipping service level code "%s" in registration.', $code)
                );
            }
            $this->byCode[$code] = $serviceLevel;
            $this->all[] = $serviceLevel;
        }
    }

    /**
     * All registered definitions in registration order (zero entries = valid empty state).
     *
     * @return ShippingServiceLevelInterface[]
     */
    public function getAll(): array
    {
        return $this->all;
    }

    /**
     * Enabled definitions sorted by sort order (stable — equal order keeps registration order).
     *
     * @return ShippingServiceLevelInterface[]
     */
    public function getEnabled(): array
    {
        $enabled = array_values(array_filter($this->all, static fn (ShippingServiceLevelInterface $level): bool => $level->isEnabled()));
        usort($enabled, static fn (ShippingServiceLevelInterface $a, ShippingServiceLevelInterface $b): int => $a->getSortOrder() <=> $b->getSortOrder());

        return $enabled;
    }

    public function has(string $code): bool
    {
        return isset($this->byCode[$code]);
    }

    /**
     * Definition for a machine code, or null when the code is not registered — callers treat
     * null as an explicit configuration error, not as an empty match.
     */
    public function getByCode(string $code): ?ShippingServiceLevelInterface
    {
        return $this->byCode[$code] ?? null;
    }

    /**
     * @throws LocalizedException unknown service-level code (explicit configuration error)
     */
    public function assertKnown(string $code): void
    {
        if (!$this->has($code)) {
            throw new LocalizedException(
                __('Unknown shipping service level code "%1" — it is not registered by any composition module.', $code)
            );
        }
    }
}
