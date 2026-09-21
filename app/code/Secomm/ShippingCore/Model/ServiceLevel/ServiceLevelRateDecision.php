<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ServiceLevel;

use Secomm\ShippingCore\Api\Fallback\FallbackRateInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;
use Secomm\ShippingCore\Api\ServiceLevelRateDecisionInterface;

/**
 * TASK-M3ME32 — immutable, self-guarding service-level rate decision VO;
 * @see ServiceLevelRateDecisionInterface.
 *
 * Impossible combinations are unconstructible: a REALTIME decision must carry rates and never a
 * fallback rate, a FALLBACK decision must carry exactly the fallback rate and no realtime rates,
 * and UNAVAILABLE carries neither. Named factories are the ergonomic construction path.
 */
final class ServiceLevelRateDecision implements ServiceLevelRateDecisionInterface
{
    private readonly string $source;

    private readonly ?FallbackRateInterface $fallbackRate;

    /**
     * @param string $serviceLevelCode registered machine code
     * @param string $source SOURCE_*
     * @param array<string, CarrierRateInterface> $realtimeRates carrier-code keyed (REALTIME only)
     * @param FallbackRateInterface|null $fallbackRate (FALLBACK only)
     * @throws \LogicException on any impossible combination or an unknown source
     */
    public function __construct(
        private readonly string $serviceLevelCode,
        string $source,
        private readonly array $realtimeRates = [],
        ?FallbackRateInterface $fallbackRate = null
    ) {
        switch ($source) {
            case self::SOURCE_REALTIME:
                if ($realtimeRates === []) {
                    throw new \LogicException('A REALTIME decision must carry at least one realtime rate.');
                }
                if ($fallbackRate !== null) {
                    throw new \LogicException('A REALTIME decision must not carry a fallback rate.');
                }
                break;
            case self::SOURCE_FALLBACK:
                if ($realtimeRates !== []) {
                    throw new \LogicException('A FALLBACK decision must not carry realtime rates.');
                }
                if ($fallbackRate === null) {
                    throw new \LogicException('A FALLBACK decision must carry a fallback rate.');
                }
                break;
            case self::SOURCE_UNAVAILABLE:
                if ($realtimeRates !== [] || $fallbackRate !== null) {
                    throw new \LogicException('An UNAVAILABLE decision must not carry any rate.');
                }
                break;
            default:
                throw new \LogicException(sprintf('Unknown service-level rate decision source "%s".', $source));
        }

        $this->source = $source;
        $this->fallbackRate = $fallbackRate;
    }

    public static function realtime(string $serviceLevelCode, array $realtimeRates): self
    {
        return new self($serviceLevelCode, self::SOURCE_REALTIME, $realtimeRates);
    }

    public static function fallback(string $serviceLevelCode, FallbackRateInterface $fallbackRate): self
    {
        return new self($serviceLevelCode, self::SOURCE_FALLBACK, [], $fallbackRate);
    }

    public static function unavailable(string $serviceLevelCode): self
    {
        return new self($serviceLevelCode, self::SOURCE_UNAVAILABLE);
    }

    public function getServiceLevelCode(): string
    {
        return $this->serviceLevelCode;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getRealtimeRates(): array
    {
        return $this->realtimeRates;
    }

    public function getFallbackRate(): ?FallbackRateInterface
    {
        return $this->fallbackRate;
    }

    public function isAvailable(): bool
    {
        return $this->source !== self::SOURCE_UNAVAILABLE;
    }
}
